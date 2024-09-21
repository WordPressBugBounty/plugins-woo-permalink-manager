<?php namespace Premmerce\UrlManager\Admin;

use Premmerce\SDK\V2\FileManager\FileManager;

class Settings
{
    const OPTION_DISABLED = 'premmerce_url_manager_disabled';

    const OPTION_FLUSH = 'premmerce_url_manager_flush_rules';

    const OPTIONS = 'premmerce_permalink_manager';

    const SETTINGS_PAGE = 'premmerce_permalink_manager_page';

    const PERMALINK_STRUCTURE = '/%postname%/';

    const PERMALINK_WC_PRODUCT_CAT = '/product/%product_cat%/';

    const PERMALINK_WC_PRODUCT = 'product';

    /**
     * FileManager
     *
     * @var FileManager
     */
    private $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public function register()
    {
        register_setting(self::OPTIONS, self::OPTIONS, array(
            'sanitize_callback' => array( $this, 'updateSettings' ),
        ));

        add_settings_section('category_link', __('Categories', 'premmerce-url-manager'), array(
            $this,
            'categorySection',
        ), self::SETTINGS_PAGE);

        add_settings_section('product_link', __('Products', 'premmerce-url-manager'), array(
            $this,
            'productSection',
        ), self::SETTINGS_PAGE);

        add_settings_section('sku_link', __('SKU', 'premmerce-url-manager'), array(
          $this,
      'skuSection',
    ), self::SETTINGS_PAGE);

        add_settings_section('additional', __('Additional', 'premmerce-url-manager'), array(
            $this,
            'canonicalSection',
        ), self::SETTINGS_PAGE);

        add_settings_section('suffix', __('URL\'s suffix', 'premmerce-url-manager'), array(
            $this,
            'suffixSection',
        ), self::SETTINGS_PAGE);
    }

    /**
     * Get Main Settings Text
     *
     * @return void
     */
    public static function getMainSettingsText()
    {
        return __('WooCommerce Permalink Manager offers you the ability to create a custom URL structure for your permalinks. Custom URL structures can improve the aesthetics, usability, and forward-compatibility of your links. A number of settings are available, and here are some examples to get you started.', 'premmerce-url-manager');
    }

    public function show()
    {
        print('<form action="' . esc_url(admin_url('options.php')) . '" method="post">');

        settings_errors();

        settings_fields(self::OPTIONS);

        do_settings_sections(self::SETTINGS_PAGE);

        submit_button();
        print('</form>');
    }

    /**
     * Get Product Path For Sku
     *
     * @return void
     */
    public function getProductPathForSku()
    {
        $product = $this->getOption('product');

        switch ($product) {
            case 'category_slug':
                return '/category';
            case 'hierarchical':
                return 'parent-category/category';
            default:
                return '';
        }
    }

    public function categorySection()
    {
        $this->fileManager->includeTemplate('admin/section/category.php', array(
            'category' => $this->getOption('category'),
        ));
    }

    public function productSection()
    {
        $this->fileManager->includeTemplate('admin/section/product.php', array(
            'product' => $this->getOption('product'),
        ));
    }

    public function skuSection()
    {
        $this->fileManager->includeTemplate('admin/section/sku.php', array(
            'sku'         => $this->getOption('sku'),
            'productPath' => $this->getProductPathForSku(),
        ));
    }

    public function canonicalSection()
    {
        $this->fileManager->includeTemplate('admin/section/additional.php', array(
            'tag'                  => $this->getOption('tag'),
            'canonical'            => $this->getOption('canonical'),
            'redirect'             => $this->getOption('redirect'),
            'use_primary_category' => $this->getOption('use_primary_category'),
            'breadcrumbs'          => $this->getOption('breadcrumbs'),
            'br_remove_shop'       => $this->getOption('br_remove_shop'),
        ));
    }

    public function suffixSection()
    {
        $this->fileManager->includeTemplate('admin/section/suffix.php', array(
            'suffix'                   => $this->getOption('suffix', ''),
            'enable_suffix_products'   => $this->getOption('enable_suffix_products', 'no'),
            'enable_suffix_categories' => $this->getOption('enable_suffix_categories', 'no'),
            'product'                  => $this->getOption('product'),
            'category'                 => $this->getOption('category')
        ));
    }

    /**
     * Update Settings
     *
     * @param  mixed $settings
     * @return void
     */
    public function updateSettings($settings)
    {
        $this->fixWPWCSettings($settings);

        if (! empty($settings['suffix'])) {
            esc_url_raw($settings['suffix']);
        }

        return $settings;
    }

    /**
     * Fix WP WC Settings
     *
     * @param  mixed $options
     * @return void
     */
    private function fixWPWCSettings($options)
    {
        if (isset($options['product']) && !empty($options['product']) || isset($options['category']) && !empty($options['category'])) {
            if (! get_option('permalink_structure')) {
                update_option('permalink_structure', self::PERMALINK_STRUCTURE);
            };
        }

        if (isset($options['product']) && !empty($options['product'])) {
            if ('slug' === $options['product']) {
                $wc['product_base'] = self::PERMALINK_WC_PRODUCT;
            }
            if (in_array($options['product'], array( 'category_slug', 'hierarchical' ))) {
                $wc['product_base'] = self::PERMALINK_WC_PRODUCT_CAT;
            }

            update_option('woocommerce_permalinks', $wc);
        }
    }

    /**
     * Get Option
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getOption($key, $default = null)
    {
        if (! isset($this->options)) {
            $this->options = get_option(self::OPTIONS);
        }

        return isset($this->options[ $key ]) ? $this->options[ $key ] : $default;
    }
}
