<?php namespace Premmerce\UrlManager\Admin;

use Premmerce\UrlManager\UrlManagerPlugin;
use Premmerce\SDK\V2\FileManager\FileManager;

/**
 * Class Admin
 *
 * @package Premmerce\UrlManager
 */
class Admin
{
    /**
     * Settings Page
     *
     * @var string
     */
    private $settingsPage;

    /**
     * Settings Array
     *
     * @var arrat
     */
    private $settingsArray;

    /**
     * All Settings Field Names
     *
     * @var array
     */
    private $allFieldsName;

    /**
     * Settings Page
     *
     * @var string
     */
    private $parrentPageSlug;

    /**
     * FileManager
     *
     * @var FileManager
     */
    private $fileManager;

    /**
     * Settings
     *
     * @var Settings
     */
    private $settings;

    /**
     * Bundles and Save
     *
     * @var bundlesAndSave
     */
    private $bundlesAndSave;

    const META_IGNORE_BANNER = 'premmerce_url_manager_ignore_banner';

    const WOO_TAB_NAME = 'woo_tab_premmerce_permalink';

    /**
     * Admin constructor.
     *
     * @param FileManager $fileManager
     */
    public function __construct(FileManager $fileManager)
    {
        $this->fileManager  = $fileManager;
        $this->settings     = new Settings($fileManager);
        $this->settingsPage = UrlManagerPlugin::DOMAIN . '-admin';
        
        $this->parrentPageSlug = 'premmerce';
        $this->bundlesAndSave  = new BundleAndSave($fileManager);
        $this->registerActions();
    }

    private function registerActions()
    {
        $flushActions = array(
            'created_product_cat',
            'edited_product_cat',
            'delete_product_cat',

            'created_product_tag',
            'edited_product_tag',
            'delete_product_tag',

            'update_option_' . Settings::OPTIONS,
        );

        foreach ($flushActions as $action) {
            add_action($action, array($this, 'triggerFlush'));
        }

        add_action('shutdown', array($this, 'flush'));

        add_action('admin_init', array($this->settings, 'register'));

        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));

        

        add_action('admin_menu', array($this, 'addMenuPage'), 80);
        add_action('admin_menu', array($this, 'addFullPack'), 100);
        add_action('admin_notices', array($this, 'renderAdminBanner'));
        add_action('wp_ajax_premmerce_url_manager_ignore_banner', array($this, 'ignoreAdminBanner'));
        add_filter('plugin_action_links_' . plugin_basename($this->fileManager->getMainFile()), array($this, 'settingPageLink'), 10, 4);
        add_filter('admin_footer_text', array($this, 'removeFooterAdmin'));
    }

    /**
     * Setting Page Link
     *
     * @param  mixed $actions
     * @return void
     */
    public function settingPageLink($actions)
    {
        $actions[] = '<a href="' . $this->getSettingLink() . '">' . __('Settings', 'premmerce-url-manager') . '</a>';

        return $actions;
    }

    /**
     * Get url to settings page
     *
     * @return string
     */
    public function getSettingLink()
    {
        $param = array('page' => 'premmerce-url-manager-admin' );
        return esc_url(add_query_arg($param, get_admin_url() . 'admin.php'));
    }

    /**
     * Show Banner
     */
    public function renderAdminBanner()
    {
        if ($this->isBannerActive()) {
            $this->fileManager->includeTemplate('admin/banner.php', array('fm' => $this->fileManager));
        }
    }

    /**
     * Hide Banner
     */
    public function ignoreAdminBanner()
    {
        $user    = wp_get_current_user();
        $user_id = $user->ID;

        add_user_meta($user_id, self::META_IGNORE_BANNER, true, true);

        die;
    }

    /**
     * Add submenu to premmerce menu page
     */
    public function addMenuPage()
    {
        global $admin_page_hooks;


        $premmerceMenuExists = isset($admin_page_hooks['premmerce']);

        if (! $premmerceMenuExists) {
            $svg = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="20" height="16" style="fill:#82878c" viewBox="0 0 20 16"><g id="Rectangle_7"> <path d="M17.8,4l-0.5,1C15.8,7.3,14.4,8,14,8c0,0,0,0,0,0H8h0V4.3C8,4.1,8.1,4,8.3,4H17.8 M4,0H1C0.4,0,0,0.4,0,1c0,0.6,0.4,1,1,1 h1.7C2.9,2,3,2.1,3,2.3V12c0,0.6,0.4,1,1,1c0.6,0,1-0.4,1-1V1C5,0.4,4.6,0,4,0L4,0z M18,2H7.3C6.6,2,6,2.6,6,3.3V12 c0,0.6,0.4,1,1,1c0.6,0,1-0.4,1-1v-1.7C8,10.1,8.1,10,8.3,10H14c1.1,0,3.2-1.1,5-4l0.7-1.4C20,4,20,3.2,19.5,2.6 C19.1,2.2,18.6,2,18,2L18,2z M14,11h-4c-0.6,0-1,0.4-1,1c0,0.6,0.4,1,1,1h4c0.6,0,1-0.4,1-1C15,11.4,14.6,11,14,11L14,11z M14,14 c-0.6,0-1,0.4-1,1c0,0.6,0.4,1,1,1c0.6,0,1-0.4,1-1C15,14.4,14.6,14,14,14L14,14z M4,14c-0.6,0-1,0.4-1,1c0,0.6,0.4,1,1,1 c0.6,0,1-0.4,1-1C5,14.4,4.6,14,4,14L4,14z"/></g></svg>';
            $svg = 'data:image/svg+xml;base64,' . base64_encode($svg);
            add_menu_page(
                'Premmerce',
                'Premmerce',
                'manage_options',
                'premmerce',
                '',
                $svg
            );
        }


        add_submenu_page(
            $this->parrentPageSlug,
            __('Permalink Manager', 'premmerce-url-manager'),
            __('Permalink Manager', 'premmerce-url-manager'),
            'manage_options',
            $this->settingsPage,
            array($this, 'options'),
            10
        );

        if (! $premmerceMenuExists) {
            global $submenu;
            unset($submenu['premmerce'][0]);
        }
    }

    public function addFullPack()
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        $premmerceInstalled = array_key_exists('premmerce-premium/premmerce.php', $plugins)
                              || array_key_exists('premmerce/premmerce.php', $plugins);

        if (! $premmerceInstalled) {
            add_submenu_page(
                'premmerce',
                __('Get premmerce full pack', 'premmerce-url-manager'),
                __('Get premmerce full pack', 'premmerce-url-manager'),
                'manage_options',
                admin_url('plugin-install.php?tab=plugin-information&plugin=premmerce')
            );
        }
    }

    /**
     * Options page
     */
    public function options()
    {
        $this->triggerFlush();

        $current = isset($_GET['tab']) ? sanitize_file_name(wp_unslash($_GET['tab'])) : 'settings';

        $tabs['settings'] = __('Settings', 'premmerce-url-manager');
        $tabs['bundle_and_save'] = __('Bundle and Save', 'premmerce-url-manager');

        if (function_exists('premmerce_wpm_fs')) {
            if (premmerce_wpm_fs()->is_registered()) {
                $tabs['account'] = __('Account', 'premmerce-url-manager');
            }
            $tabs['contact'] = __('Contact Us', 'premmerce-url-manager');
        }

        if (! in_array($current, array_keys($tabs))) {
            $current = 'settings';
        }

        $this->fileManager->includeTemplate('admin/main.php', array(
            'settings'  => $this->settings,
            'bundles'   => $this->bundlesAndSave,
            'tabs'      => $tabs,
            'current'   => $current,
        ));
    }

    /**
     * Enqueue Scripts
     *
     * @param string $hook
     */
    public function enqueueScripts($hook)
    {
        //init input-mask
        wp_enqueue_script(
            'input-mask.js',
            $this->fileManager->locateAsset('admin/js/input-mask.min.js'),
            array( 'jquery' ),
            UrlManagerPlugin::getPluginVersion()
        );
        //add styles for Settings
        if (in_array($hook, array('premmerce_page_premmerce-url-manager-admin', 'woocommerce_page_wc-settings'))) {
            wp_enqueue_style(
                'premmerce-permalink-style',
                $this->fileManager->locateAsset('admin/css/style.css'),
                array(),
                UrlManagerPlugin::getPluginVersion()
            );
        }
        //add scripts for settings
        wp_enqueue_script(
            'premmerce-permalink-settings-script',
            $this->fileManager->locateAsset('admin/js/settings.js'),
            array('jquery'),
            UrlManagerPlugin::getPluginVersion()
        );

        if ($this->isBannerActive()) {
            wp_enqueue_script(
                'premmerce-permalink-banner-script',
                $this->fileManager->locateAsset('admin/js/banner.js'),
                array('jquery'),
                UrlManagerPlugin::getPluginVersion()
            );
            wp_enqueue_style(
                'premmerce-permalink-banner-style',
                $this->fileManager->locateAsset('admin/css/banner.css'),
                array(),
                UrlManagerPlugin::getPluginVersion()
            );
        }
    }

    /**
     * Set flush trigger
     */
    public function triggerFlush()
    {
        update_option(Settings::OPTION_FLUSH, true);
    }

    /**
     * Flush rewrite rules
     */
    public function flush()
    {
        if (get_option(Settings::OPTION_FLUSH)) {
            flush_rewrite_rules();
            delete_option(Settings::OPTION_FLUSH);
        }
    }

    /**
     * Admin footer modification
     *
     * @param $text - default WordPress footer thankyou text
     */
    public function removeFooterAdmin($text)
    {
        $screen         = get_current_screen();
        $premmercePages = array(
            'premmerce_page_premmerce-url-manager-admin'
        );

        if (in_array($screen->id, $premmercePages)) {
            $link   = 'https://wordpress.org/support/plugin/woo-permalink-manager/reviews/?filter=5';
            $target = 'target="_blank"';
            $text   = '<span id="footer-thankyou">';
            $text  .= sprintf(
                /* translators: %%1$s: plugin link, %2$s target */
                __('Please rate our Premmerce Permalink Manager for WooCommerce on <a href="%1$s" %2$s>WordPress.org</a><br/>Thank you from the Premmerce team!', 'premmerce-filter'),
                $link,
                $target
            );
            $text .= '</span>';
        } else {
            $text = '<span id="footer-thankyou">' . $text . '</span>';
        }

        return $text;
    }

    public function isBannerActive()
    {
        $user    = wp_get_current_user();
        $user_id = $user->ID;

        return ! get_user_meta($user_id, self::META_IGNORE_BANNER, true);
    }
}
