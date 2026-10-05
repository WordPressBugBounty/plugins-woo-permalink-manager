<?php

namespace Premmerce\UrlManager\Frontend;

use Premmerce\UrlManager\Admin\Settings;
use WP_Post;
use WP_Term;
/**
 * Class Frontend
 *
 * @package Premmerce\UrlManager
 */
class Frontend {
    const WOO_PRODUCT = 'product';

    const WOO_CATEGORY = 'product_cat';

    /**
     * Options
     *
     * @var array
     */
    protected $options = array();

    /**
     * Frontend constructor.
     */
    public function __construct() {
        $options = get_option( Settings::OPTIONS );
        $this->options = $options;
        if ( !empty( $options['product'] ) || !empty( $options['sku'] ) ) {
            add_action( 'request', array($this, 'replaceRequest'), 11 );
        }
        if ( !empty( $options['canonical'] ) ) {
            add_action( 'wp_head', array($this, 'addCanonical') );
        }
        $isGetParamUrlFormat = apply_filters( 'wpml_setting', 0, 'language_negotiation_type' ) == '3';
        if ( class_exists( 'SitePress' ) && $isGetParamUrlFormat ) {
            add_filter( 'icl_ls_languages', array($this, 'modifyWpmlLanguageSwitcher'), 20 );
        }
    }

    /**
     * Modify wpml Language Switcher
     *
     * @param array $languages
     *
     * @return array
     */
    public function modifyWpmlLanguageSwitcher( $languages ) {
        global $sitepress;
        foreach ( $languages as $key => $val ) {
            $switcherLink = $val['url'];
            $parsedLink = parse_url( $switcherLink );
            if ( isset( $parsedLink['query'] ) ) {
                $switcherLink = str_replace( '?' . $parsedLink['query'], '', $switcherLink );
            }
            if ( $key != $sitepress->get_default_language() ) {
                $languages[$key]['url'] = $switcherLink . '?lang=' . $key;
            } else {
                $languages[$key]['url'] = $switcherLink;
            }
        }
        return $languages;
    }

    /**
     * Replace request if product found
     *
     * @param array $request
     *
     * @return array
     */
    public function replaceRequest( $request ) {
        global $wp, $wpdb;
        if ( $this->checkIfWooCategoryExists( $request ) ) {
            return $request;
        }
        $url = $wp->request;
        // Leave REST API requests alone (#46), whatever the REST prefix: WordPress has
        // already routed them, and a route ending in a product slug isn't a product.
        if ( isset( $request['rest_route'] ) ) {
            return $request;
        }
        if ( !empty( $url ) ) {
            $url = explode( '/', $url );
            $slug = array_pop( $url );
            // Keep the query vars that didn't come from the path WordPress matched, such as
            // public ones in the query string (#5: an affiliate plugin's ?ref=). Drop the ones
            // that did (e.g. pagename from the page rule), and WordPress's 404 flag.
            parse_str( (string) $wp->matched_query, $matchedVars );
            $replace = array_diff_key( $request, $matchedVars, array(
                'error' => '',
            ) );
            if ( 'feed' === $slug ) {
                $replace['feed'] = $slug;
                $slug = array_pop( $url );
            }
            if ( 'amp' === $slug ) {
                $replace['amp'] = $slug;
                $slug = array_pop( $url );
            }
            if ( !$slug ) {
                $slug = '';
            }
            $commentsPosition = strpos( $slug, 'comment-page-' );
            if ( 0 === $commentsPosition ) {
                $replace['cpage'] = substr( $slug, strlen( 'comment-page-' ) );
                $slug = array_pop( $url );
            }
            // The path without feed/, amp/ or comment-page-N/, as requested (#69).
            $requestPath = array_merge( $url, array((string) $slug) );
            $suffix = '';
            $productId = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s ORDER BY post_status = 'publish' DESC, ID ASC LIMIT 1", array($slug, self::WOO_PRODUCT) ) );
            if ( $productId && $this->shouldRouteToProduct(
                $productId,
                $requestPath,
                $suffix,
                $request
            ) ) {
                $replace['page'] = '';
                $replace['post_type'] = self::WOO_PRODUCT;
                $replace['product'] = $slug;
                $replace['name'] = $slug;
                return $replace;
            }
        }
        return $request;
    }

    /**
     * Whether a request whose last segment is a product's slug is for that product (#69).
     *
     * The product's own URL is. Any other URL ending in its slug isn't: a page, post, other
     * post type, term or search WordPress already found for it is left alone, and anything
     * else stays a 404. The exception is an old form of the product's URL with "Create
     * redirects" on: it's routed to the product so the redirect can 301 it (#66).
     *
     * @param int      $productId
     * @param string[] $requestPath Requested path segments, ending in the product's slug.
     * @param string   $suffix      URL suffix, e.g. ".html"; '' for none.
     * @param array    $request     Query vars WordPress matched for the request.
     *
     * @return bool
     */
    protected function shouldRouteToProduct(
        $productId,
        array $requestPath,
        $suffix,
        array $request
    ) {
        $requestPath = $this->normalisePath( $requestPath, $suffix );
        $permalinkPath = $this->normalisePath( explode( '/', $this->pathOf( get_permalink( $productId ) ) ), $suffix );
        if ( $requestPath === $permalinkPath ) {
            return true;
        }
        if ( $this->requestFindsSomething( $request ) ) {
            return false;
        }
        return $this->redirectsOldUrls() && $this->isOldProductPath( $requestPath, $permalinkPath );
    }

    /**
     * An old form of a product's URL, for the redirect to send to the new one: the product's
     * path with something in front of it (/product/…, /shop/…, /123/…, #66), or the slug
     * under product categories only, e.g. a wrong or partial category path, or the slug alone,
     * optionally after WooCommerce's product base or the shop page (/product/<slug>/).
     * Not a path under anything else, such as /no-such-category/<slug>/.
     *
     * @param string[] $requestPath
     * @param string[] $permalinkPath
     *
     * @return bool
     */
    protected function isOldProductPath( array $requestPath, array $permalinkPath ) {
        if ( count( $requestPath ) >= count( $permalinkPath ) && array_slice( $requestPath, -count( $permalinkPath ) ) === $permalinkPath ) {
            return true;
        }
        $segments = array_slice( $requestPath, 0, -1 );
        foreach ( $this->productBases() as $base ) {
            if ( array_slice( $segments, 0, count( $base ) ) === $base ) {
                $segments = array_slice( $segments, count( $base ) );
                break;
            }
        }
        foreach ( $segments as $segment ) {
            if ( !get_term_by( 'slug', $segment, self::WOO_CATEGORY ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Path segments that can come before a product's old URL: WooCommerce's product base
     * without %product_cat% (e.g. "product"), WooCommerce's default "product", and the shop
     * page's path. Once the plugin's settings put %product_cat% in the base, WooCommerce has
     * no rule for /product/<slug>/ any more, so the plugin has to recognise it.
     *
     * @return string[][]
     */
    protected function productBases() {
        $bases = array('product');
        $structure = ( function_exists( 'wc_get_permalink_structure' ) ? wc_get_permalink_structure() : array() );
        if ( !empty( $structure['product_rewrite_slug'] ) ) {
            $bases[] = str_replace( '%product_cat%', '', $structure['product_rewrite_slug'] );
        }
        $shopPageId = ( function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0 );
        if ( $shopPageId > 0 ) {
            $bases[] = get_page_uri( $shopPageId );
        }
        $bases = array_map( function ( $base ) {
            return $this->normalisePath( explode( '/', (string) $base ), '' );
        }, $bases );
        return array_filter( $bases );
    }

    /**
     * Whether WordPress found something for the request itself: a post of any type, a term
     * or author archive, or a search. Mirrors what WP::handle_404() treats as not a 404.
     *
     * @param array $request
     *
     * @return bool
     */
    protected function requestFindsSomething( array $request ) {
        if ( isset( $request['error'] ) ) {
            return false;
        }
        if ( isset( $request['s'] ) ) {
            return true;
        }
        // WordPress's attachment rule, [^/]+/([^/]+)/?$, matches any two-segment path, so it
        // would find a product image that shares the product's slug: a match there doesn't
        // mean WordPress found the URL.
        unset($request['attachment'], $request['attachment_id']);
        $query = new \WP_Query();
        $posts = $query->query( array_merge( $request, array(
            'fields'              => 'ids',
            'posts_per_page'      => 1,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ) ) );
        // No query vars that pick anything out: the latest posts, not a match.
        if ( $query->is_home() ) {
            return false;
        }
        if ( $posts ) {
            return true;
        }
        $isArchive = $query->is_tax() || $query->is_category() || $query->is_tag() || $query->is_author() || $query->is_post_type_archive();
        return $isArchive && $query->get_queried_object();
    }

    /**
     * Whether "Create redirects" (premium) will 301 a product's old URLs.
     *
     * @return bool
     */
    protected function redirectsOldUrls() {
        $redirects = false;
        return $redirects;
    }

    /**
     * Path segments compared as WordPress compares slugs: decoded, lower case, and without
     * the suffix, which is optional in a request.
     *
     * @param string[] $segments
     * @param string   $suffix
     *
     * @return string[]
     */
    protected function normalisePath( array $segments, $suffix ) {
        $segments = array_values( array_filter( array_map( function ( $segment ) {
            return mb_strtolower( rawurldecode( (string) $segment ) );
        }, $segments ), 'strlen' ) );
        if ( $suffix && $segments ) {
            $last = count( $segments ) - 1;
            $segments[$last] = $this->removeSuffix( $segments[$last], mb_strtolower( $suffix ) );
        }
        return $segments;
    }

    /**
     * A URL's path relative to the site's home, without slashes at either end.
     *
     * @param string $url
     *
     * @return string
     */
    protected function pathOf( $url ) {
        $path = (string) parse_url( (string) $url, PHP_URL_PATH );
        $homePath = trim( (string) parse_url( home_url(), PHP_URL_PATH ), '/' );
        $path = trim( $path, '/' );
        if ( '' !== $homePath && 0 === strpos( $path . '/', $homePath . '/' ) ) {
            $path = substr( $path, strlen( $homePath ) );
        }
        return trim( $path, '/' );
    }

    protected function removeSuffix( $url, $suffix ) {
        $length = strlen( $suffix );
        if ( 0 === $length ) {
            return $url;
        }
        // Ends with
        if ( substr( $url, -$length ) === $suffix ) {
            $url = substr( $url, 0, -$length );
        }
        return $url;
    }

    public function addCanonical() {
        //avoid canonicals duplication
        if ( !defined( 'WPSEO_VERSION' ) && !get_queried_object() instanceof WP_Post ) {
            $canonical = apply_filters( 'premmerce_permalink_manager_canonical', $this->getCanonical() );
            if ( !empty( $canonical ) ) {
                echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
            }
        }
    }

    private function getCanonical( $useCommentsPagination = false ) {
        global $wp_rewrite;
        $qo = get_queried_object();
        $canonical = null;
        if ( $qo instanceof WP_Term ) {
            $canonical = get_term_link( $qo );
            $paged = get_query_var( 'paged' );
            if ( $paged > 1 ) {
                /**
                 * The pagination base in a term's paged canonical URL, and so in the URL the
                 * redirect compares the request with. PermalinkListener gives Polylang Pro's
                 * translation for the term's language (#100).
                 *
                 * @param string  $base "page", or the site's pagination base.
                 * @param WP_Term $term
                 */
                $base = apply_filters( 'premmerce_permalink_manager_pagination_base', $wp_rewrite->pagination_base, $qo );
                $canonical = trailingslashit( $canonical ) . trailingslashit( $base ) . $paged;
            }
        } elseif ( $qo instanceof WP_Post ) {
            $canonical = get_permalink( $qo );
            if ( $useCommentsPagination ) {
                $page = get_query_var( 'cpage' );
                if ( $page > 1 ) {
                    $canonical = trailingslashit( $canonical ) . $wp_rewrite->comments_pagination_base . '-' . $page;
                }
            }
        }
        if ( $canonical ) {
            return user_trailingslashit( $canonical );
        }
    }

    /**
     * Find current slug by product SKU
     *
     * @param string $sku
     *
     * @return string
     */
    protected function findSlugBySku( $sku ) {
        global $wpdb;
        $skuId = $wpdb->get_row( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value = %s AND meta_key = '_sku'", array($sku) ), ARRAY_A );
        if ( isset( $skuId['post_id'] ) ) {
            $postSlug = get_post_field( 'post_name', $skuId['post_id'] );
            if ( '' !== $postSlug ) {
                return $postSlug;
            }
        }
        return $sku;
    }

    /**
     * Check if woocommerce category exists in request
     *
     * @param array $request
     *
     * @return boolean
     */
    protected function checkIfWooCategoryExists( $request ) {
        if ( !empty( $this->options['category'] ) && in_array( $this->options['product'], array('category_slug', 'hierarchical') ) ) {
            if ( array_key_exists( self::WOO_CATEGORY, $request ) ) {
                return true;
            }
        }
        return false;
    }

}
