<?php

namespace Premmerce\UrlManager;

use Premmerce\UrlManager\Admin\Settings;
use WP_Post;
/**
 * Class PermalinkListener
 *
 * The class is responsible for filtering of
 * product and category links invoked by 'post_type_link' and 'term_link'
 *
 * @package Premmerce\UrlManager
 */
class PermalinkListener {
    const WOO_CAT = 'product_cat';

    const WOO_TAG = 'product_tag';

    const WOO_PRODUCT = 'product';

    private $options = array();

    private $taxonomyOptions = array();

    private $productBase;

    private $polyLang = null;

    public function __construct() {
        $this->loadOptions();
    }

    /**
     * Read the plugin's settings. Called again when they're saved, so the rewrite rules
     * flushed at the end of that request are built from the new settings.
     */
    public function loadOptions() {
        $options = get_option( Settings::OPTIONS );
        $this->options = array(
            'use_primary_category'     => !empty( $options['use_primary_category'] ),
            'product'                  => ( isset( $options['product'] ) ? $options['product'] : '' ),
            'suffix'                   => ( !empty( $options['suffix'] ) ? $options['suffix'] : false ),
            'enable_suffix_categories' => ( isset( $options['enable_suffix_categories'] ) && !empty( $options['enable_suffix_categories'] ) ? true : false ),
            'enable_suffix_products'   => ( isset( $options['enable_suffix_products'] ) && !empty( $options['enable_suffix_products'] ) ? true : false ),
            'sku'                      => ( isset( $options['sku'] ) ? $options['sku'] : '' ),
            'include_shop'             => false,
        );
        $this->taxonomyOptions['product_cat'] = ( isset( $options['category'] ) ? $options['category'] : '' );
    }

    /**
     * Add post_type_link, term_link, rewrite_rules_array filters
     */
    public function registerFilters() {
        add_filter(
            'post_type_link',
            array($this, 'replaceProductLink'),
            1,
            2
        );
        add_filter(
            'term_link',
            array($this, 'replaceTermLink'),
            0,
            3
        );
        add_filter( 'rewrite_rules_array', array($this, 'addRewriteRules'), 99 );
        add_filter( 'user_trailingslashit', array($this, 'removeSlashAfterSuffix') );
        add_filter(
            'premmerce_permalink_manager_pagination_base',
            array($this, 'translatePaginationBase'),
            10,
            2
        );
        add_action( 'add_option_' . Settings::OPTIONS, array($this, 'loadOptions') );
        add_action( 'update_option_' . Settings::OPTIONS, array($this, 'loadOptions') );
        add_action( 'pll_init', function ( $polylang ) {
            $this->polyLang = $polylang;
        } );
    }

    /**
     * No trailing slash after the URL suffix: /beanie.html, not /beanie.html/ (#33). The
     * links are built without one, but WordPress's canonical redirect passes every path
     * through user_trailingslashit(), which adds the slash the permalink structure ends in,
     * so each suffixed URL 301'd to a copy with a slash.
     *
     * @param string $url
     *
     * @return string
     */
    public function removeSlashAfterSuffix( $url ) {
        $suffix = $this->options['suffix'];
        if ( !$suffix || !($this->options['enable_suffix_products'] || $this->options['enable_suffix_categories']) ) {
            return $url;
        }
        $withoutSlash = untrailingslashit( $url );
        if ( substr( $withoutSlash, -strlen( $suffix ) ) === $suffix ) {
            return $withoutSlash;
        }
        return $url;
    }

    /**
     * Replace category permalink according to settings
     *
     * @param string $link
     * @param object $term
     * @param string $taxonomy
     *
     * @return string
     */
    public function replaceTermLink( $link, $term, $taxonomy ) {
        if ( empty( $this->taxonomyOptions[$taxonomy] ) ) {
            return $link;
        }
        $suffix = ( $this->options['enable_suffix_categories'] ? $this->options['suffix'] : false );
        $suffix = ( $suffix ? $suffix : false );
        $isHierarchical = $this->isHierarchical( $this->taxonomyOptions[$taxonomy] );
        $path = $this->buildTermPath( $term, $isHierarchical, $suffix );
        $shopBase = $this->getShopBase();
        if ( $shopBase ) {
            $path = $shopBase . '/' . $path;
        }
        return ( $suffix ? home_url( $path ) : home_url( user_trailingslashit( $path ) ) );
    }

    /**
     * Replace product permalink according to settings
     *
     * @param string $permalink
     * @param WP_Post $post
     *
     * @return string
     */
    public function replaceProductLink( $permalink, $post ) {
        if ( self::WOO_PRODUCT !== $post->post_type ) {
            return $permalink;
        }
        if ( !get_option( 'permalink_structure' ) ) {
            return $permalink;
        }
        // A plain link, ?post_type=product&p=<id>, which WordPress gives drafts, pending and
        // scheduled products: there's no path to change. The suffix made it /.html?post_type=…,
        // so a draft's preview link was a 403 or 404 (#26). The SKU replaced the whole query,
        // and Include Shop put /shop/ in front of it.
        if ( $this->isPlainLink( $permalink ) ) {
            return $permalink;
        }
        if ( empty( $this->options['product'] ) ) {
            return $permalink;
        }
        $product_base = $this->getProductBase();
        if ( strpos( $product_base, '%product_cat%' ) !== false ) {
            $product_base = str_replace( '%product_cat%', '', $product_base );
        }
        $product_base = trim( $product_base, '/' );
        // A base of just /%product_cat%/ leaves nothing to remove. Replacing '//' would
        // turn http:// into http:/ (#91).
        $link = ( '' === $product_base ? $permalink : str_replace( '/' . $product_base . '/', '/', $permalink ) );
        $link = $this->addPostParentLink( $link, $post, $this->isHierarchical( $this->options['product'] ) );
        $shopBase = $this->getShopBase();
        if ( $shopBase ) {
            $link = home_url( '/' . $shopBase ) . str_replace( home_url(), '', $link );
        }
        return $link;
    }

    /**
     * Whether a product link is WordPress's plain form, ?post_type=product&p=<id> or
     * ?product=<slug>, rather than a pretty URL with a path.
     *
     * @param string $permalink
     *
     * @return bool
     */
    private function isPlainLink( $permalink ) {
        parse_str( (string) parse_url( $permalink, PHP_URL_QUERY ), $args );
        return isset( $args['p'] ) || isset( $args[self::WOO_PRODUCT] );
    }

    /**
     * Add rewrite rules for wp
     *
     * @param $rules
     *
     * @return array
     */
    public function addRewriteRules( $rules ) {
        if ( empty( $this->taxonomyOptions ) ) {
            return $rules;
        }
        global $wp_rewrite;
        $feed = '(' . trim( implode( '|', $wp_rewrite->feeds ) ) . ')';
        $paged = $this->getPaginationBasePattern();
        $customRules = array();
        $shopBase = $this->getShopBase();
        /**
         * Remove WPML filters while getting terms, to get all languages
         */
        if ( isset( $GLOBALS['sitepress'] ) ) {
            $sitepress = $GLOBALS['sitepress'];
            $has_get_terms_args_filter = remove_filter( 'get_terms_args', array($sitepress, 'get_terms_args_filter') );
            $has_get_term_filter = remove_filter( 'get_term', array($sitepress, 'get_term_adjust_id'), 1 );
            $has_terms_clauses_filter = remove_filter( 'terms_clauses', array($sitepress, 'terms_clauses') );
        }
        foreach ( $this->taxonomyOptions as $taxonomy => $option ) {
            if ( !empty( $option ) ) {
                $terms = get_categories( array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'lang'       => '',
                ) );
                $hierarchical = $this->isHierarchical( $option );
                $suffix = false;
                foreach ( $terms as $term ) {
                    $slug = $this->buildTermPath( $term, $hierarchical, $suffix );
                    if ( $shopBase ) {
                        $slug = $shopBase . '/' . $slug;
                    }
                    $customRules["{$slug}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug;
                    $customRules["{$slug}/embed/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&embed=true';
                    $customRules["{$slug}/{$wp_rewrite->feed_base}/{$feed}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&feed=$matches[1]';
                    $customRules["{$slug}/{$feed}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&feed=$matches[1]';
                    $customRules["{$slug}/{$paged}/?([0-9]{1,})/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&paged=$matches[1]';
                    // Polylang compatibility
                    $polylangURLslug = $this->getPolylangLangSlug();
                    if ( $polylangURLslug ) {
                        $slug = $polylangURLslug . $slug;
                        $customRules["{$slug}/?\$"] = 'index.php?lang=$matches[1]&' . $taxonomy . '=' . $term->slug;
                        $customRules["{$slug}/embed/?\$"] = 'index.php?lang=$matches[1]&' . $taxonomy . '=' . $term->slug . '&embed=true';
                        $customRules["{$slug}/{$wp_rewrite->feed_base}/{$feed}/?\$"] = 'index.php?lang=$matches[1]&' . $taxonomy . '=' . $term->slug . '&feed=$matches[2]';
                        $customRules["{$slug}/{$feed}/?\$"] = 'index.php?lang=$matches[1]&' . $taxonomy . '=' . $term->slug . '&feed=$matches[2]';
                        $customRules["{$slug}/{$paged}/?([0-9]{1,})/?\$"] = 'index.php?lang=$matches[1]&' . $taxonomy . '=' . $term->slug . '&paged=$matches[2]';
                    }
                }
            }
        }
        /**
         * Register WPML filters back
         */
        if ( isset( $sitepress ) ) {
            if ( !empty( $has_terms_clauses_filter ) ) {
                add_filter(
                    'terms_clauses',
                    array($sitepress, 'terms_clauses'),
                    10,
                    3
                );
            }
            if ( !empty( $has_get_term_filter ) ) {
                add_filter(
                    'get_term',
                    array($sitepress, 'get_term_adjust_id'),
                    1,
                    1
                );
            }
            if ( !empty( $has_get_terms_args_filter ) ) {
                add_filter(
                    'get_terms_args',
                    array($sitepress, 'get_terms_args_filter'),
                    10,
                    2
                );
            }
        }
        return $customRules + $rules;
    }

    private function getPolylangLangSlug() {
        if ( !empty( $this->polyLang ) ) {
            global $wp_rewrite;
            $languages = $this->polyLang->model->get_languages_list( array(
                'fields' => 'slug',
            ) );
            if ( $this->polyLang->options['hide_default'] ) {
                $languages = array_diff( $languages, array($this->polyLang->options['default_lang']) );
            }
            if ( !empty( $languages ) ) {
                return $wp_rewrite->root . (( $this->polyLang->options['rewrite'] ? '' : 'language/' )) . '(' . implode( '|', $languages ) . ')/';
            }
        }
        return false;
    }

    /**
     * The shop page's path, e.g. "shop", when "Include Shop" is on (#44); otherwise ''.
     *
     * @return string
     */
    private function getShopBase() {
        if ( empty( $this->options['include_shop'] ) ) {
            return '';
        }
        $shopPageId = ( function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0 );
        $path = ( $shopPageId > 0 ? get_page_uri( $shopPageId ) : '' );
        return ( $path ? urldecode( $path ) : 'shop' );
    }

    /**
     * The pagination base in the category rules: "page", or with Polylang Pro's "Translate
     * slugs" module translating it, every translation as a non-capturing group, e.g.
     * (?:page|pagina), so /nl/glaswerk/pagina/2/ resolves too. The same pattern Polylang Pro
     * gives its own rules; being non-capturing, the $matches numbers don't move.
     *
     * @return string
     */
    private function getPaginationBasePattern() {
        global $wp_rewrite;
        $bases = array_unique( array_merge( array($wp_rewrite->pagination_base), $this->getPolylangPaginationBases() ) );
        if ( 1 === count( $bases ) ) {
            return $wp_rewrite->pagination_base;
        }
        return '(?:' . implode( '|', array_map( function ( $base ) {
            return preg_quote( $base, '#' );
        }, $bases ) ) . ')';
    }

    /**
     * The pagination base in each language, from Polylang Pro's "Translate slugs" module
     * ($polylang->translate_slugs->slugs_model->translated_slugs['paged']). Empty without
     * Polylang Pro, with the module off, or when no language translates "page".
     *
     * @return string[]
     */
    private function getPolylangPaginationBases() {
        return array_values( $this->getPolylangPaginationTranslations() );
    }

    /**
     * The pagination base in each language that translates it, from Polylang Pro's
     * "Translate slugs" module, keyed by language slug, e.g. [ 'nl' => 'pagina' ].
     *
     * @return string[]
     */
    private function getPolylangPaginationTranslations() {
        if ( empty( $this->polyLang->translate_slugs->slugs_model ) ) {
            return array();
        }
        $model = $this->polyLang->translate_slugs->slugs_model;
        // Polylang Pro fills this on wp_loaded; a flush before then would find it empty.
        if ( !isset( $model->translated_slugs ) && method_exists( $model, 'init_translated_slugs' ) ) {
            $model->init_translated_slugs();
        }
        if ( empty( $model->translated_slugs['paged']['translations'] ) || !is_array( $model->translated_slugs['paged']['translations'] ) ) {
            return array();
        }
        return array_filter( $model->translated_slugs['paged']['translations'], function ( $base ) {
            return is_string( $base ) && '' !== trim( $base, '/' );
        } );
    }

    /**
     * The pagination base in a term's paged canonical URL (#100): the translation for the
     * term's language when Polylang Pro translates "page", e.g. /nl/glaswerk/pagina/2/, the
     * same base Polylang Pro puts in its pagination links. Otherwise the base unchanged.
     *
     * @param string   $base
     * @param \WP_Term $term
     *
     * @return string
     */
    public function translatePaginationBase( $base, $term ) {
        if ( !$term instanceof \WP_Term || empty( $this->polyLang->model->term ) ) {
            return $base;
        }
        $language = $this->polyLang->model->term->get_language( $term->term_id );
        if ( empty( $language->slug ) ) {
            return $base;
        }
        $translations = $this->getPolylangPaginationTranslations();
        return ( isset( $translations[$language->slug] ) ? trim( $translations[$language->slug], '/' ) : $base );
    }

    private function getProductBase() {
        if ( is_null( $this->productBase ) ) {
            $permalinkStructure = wc_get_permalink_structure();
            $this->productBase = $permalinkStructure['product_rewrite_slug'];
        }
        return $this->productBase;
    }

    private function addPostParentLink( $permalink, $post, $hierarchical ) {
        if ( false === strpos( $permalink, '%product_cat%' ) ) {
            return $permalink;
        }
        $term = $this->getProductCategory( $post );
        if ( $term ) {
            $slug = $this->buildTermPath( $term, $hierarchical );
            $permalink = str_replace( '%product_cat%', $slug, $permalink );
        }
        return $permalink;
    }

    private function buildTermPath( $term, $hierarchical, $suffix = false ) {
        //urldecode used here to fix copied url via ctrl+c
        $slug = urldecode( $term->slug );
        if ( $hierarchical && $term->parent ) {
            $ancestors = get_ancestors( $term->term_id, 'product_cat' );
            foreach ( $ancestors as $ancestor ) {
                $ancestor_object = get_term( $ancestor, 'product_cat' );
                $slug = urldecode( $ancestor_object->slug ) . '/' . $slug;
            }
        }
        return ( $suffix ? $slug . $suffix : $slug );
    }

    /**
     * The category a product's URL uses, and so its breadcrumbs too: the Yoast SEO primary
     * category when "use primary category" is on and the product is still in it, otherwise
     * the category with the highest ID, through WooCommerce's
     * wc_product_post_type_link_product_cat filter (which Yoast SEO also uses).
     *
     * @param WP_Post $product
     *
     * @return \WP_Term|null Null when the product has no category.
     */
    public function getProductCategory( $product ) {
        $term = null;
        if ( !empty( $this->options['use_primary_category'] ) ) {
            $term = $this->getSeoPrimaryTerm( $product );
        }
        if ( !$term instanceof \WP_Term ) {
            $term = $this->getWcPrimaryTerm( $product );
        }
        if ( $term instanceof \WP_Term ) {
            return $term;
        }
        return null;
    }

    private function getSeoPrimaryTerm( $product ) {
        if ( $this->checkSeoPlugin() ) {
            $primaryTerm = yoast_get_primary_term_id( self::WOO_CAT, $product->ID );
            return get_term( $primaryTerm );
        }
        return null;
    }

    private function getWcPrimaryTerm( $product ) {
        $terms = get_the_terms( $product->ID, 'product_cat' );
        if ( empty( $terms ) ) {
            return null;
        }
        if ( function_exists( 'wp_list_sort' ) ) {
            $terms = wp_list_sort( $terms, 'term_id', 'DESC' );
        } else {
            usort( $terms, '_usort_terms_by_ID' );
        }
        $category_object = apply_filters(
            'wc_product_post_type_link_product_cat',
            $terms[0],
            $terms,
            $product
        );
        $category_object = get_term( $category_object, 'product_cat' );
        return $category_object;
    }

    private function isHierarchical( $type ) {
        return 'hierarchical' === $type;
    }

    /**
     * Check that seo plugin is enabled and available to use
     *
     * @return bool
     */
    protected function checkSeoPlugin() {
        if ( !function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return function_exists( 'is_plugin_active' ) && defined( 'WPSEO_BASENAME' ) && is_plugin_active( WPSEO_BASENAME ) && function_exists( 'yoast_get_primary_term_id' );
    }

    /**
     * Check that WPML plugin is enabled and available to use
     *
     * @return bool
     */
    protected function checkWpmlPlugin() {
        return class_exists( 'SitePress' );
    }

    /**
     * Replace current post slug with woocommerce SKU
     *
     * @param string $permalink
     * @param integer $postID
     *
     * @return string
     */
    protected function replaceSlugWithSku( $permalink, $postID ) {
        $skuString = get_post_meta( $postID, '_sku', true );
        if ( '' !== $skuString ) {
            if ( 'sku' === $this->options['sku'] ) {
                return str_replace( basename( $permalink ), $skuString, $permalink );
            }
        }
        return $permalink;
    }

    /**
     * Add parameters to permalink
     *
     * @param string $permalink
     *
     * @return string
     */
    protected function addParamsToPermalink( $permalink ) {
        $parsedUrl = parse_url( $permalink, PHP_URL_QUERY );
        parse_str( $parsedUrl, $output );
        if ( isset( $output['lang'] ) ) {
            return $permalink;
        }
        global $sitepress;
        $isGetParamUrlFormat = apply_filters( 'wpml_setting', 0, 'language_negotiation_type' ) == '3';
        if ( $sitepress->get_default_language() != ICL_LANGUAGE_CODE && $isGetParamUrlFormat ) {
            return add_query_arg( array(
                'lang' => ICL_LANGUAGE_CODE,
            ), $permalink );
        }
        return $permalink;
    }

}
