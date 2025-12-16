<?php
/**
 * Plugin Name: WooCommerce Etsy Importer
 * Plugin URI: https://dcarock.com/wordpress/
 * Description: Import Etsy listings as WooCommerce products with images, variations, and pricing
 * Version: 1.3.1
 * Author: Chris Arock
 * Author URI: https://dcarock.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-etsy-importer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WC_ETSY_IMPORTER_VERSION', '1.3.1');
define('WC_ETSY_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_ETSY_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_ETSY_IMPORTER_PLUGIN_FILE', __FILE__);

/**
 * Declare WooCommerce HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Main plugin class
 */
class WC_Etsy_Importer {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load admin page (always needed for menu)
        $this->load_admin_page();

        // Check if WooCommerce is active and load dependencies
        add_action('plugins_loaded', array($this, 'check_dependencies'));

        // Initialize plugin
        add_action('init', array($this, 'init'));

        // Load admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Register AJAX handlers
        add_action('wp_ajax_wcei_import_etsy_listing', array($this, 'ajax_import_listing'));
        add_action('wp_ajax_wcei_validate_etsy_url', array($this, 'ajax_validate_url'));
    }

    /**
     * Load admin page class (always needed for menu)
     */
    private function load_admin_page() {
        if (is_admin()) {
            require_once WC_ETSY_IMPORTER_PLUGIN_DIR . 'includes/class-admin-page.php';
        }
    }

    /**
     * Check for WooCommerce dependency
     */
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load plugin files
        $this->load_dependencies();
    }

    /**
     * Display WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('WooCommerce Etsy Importer requires WooCommerce to be installed and active.', 'wc-etsy-importer'); ?></p>
        </div>
        <?php
    }

    /**
     * Load required files (scraper and product creator)
     */
    private function load_dependencies() {
        require_once WC_ETSY_IMPORTER_PLUGIN_DIR . 'includes/class-etsy-scraper.php';
        require_once WC_ETSY_IMPORTER_PLUGIN_DIR . 'includes/class-wc-product-creator.php';
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize admin page if we're in admin and class exists
        // (Admin page will show even if WooCommerce is not active)
        if (is_admin() && class_exists('WC_Etsy_Importer_Admin_Page')) {
            WC_Etsy_Importer_Admin_Page::get_instance();
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if ('toplevel_page_wc-etsy-importer' !== $hook) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'wc-etsy-importer-admin',
            WC_ETSY_IMPORTER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_ETSY_IMPORTER_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'wc-etsy-importer-admin',
            WC_ETSY_IMPORTER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WC_ETSY_IMPORTER_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('wc-etsy-importer-admin', 'wcEtsyImporter', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_etsy_importer_nonce'),
            'strings' => array(
                'importing' => __('Importing...', 'wc-etsy-importer'),
                'success' => __('Import completed successfully!', 'wc-etsy-importer'),
                'error' => __('Import failed. Please check the error message.', 'wc-etsy-importer'),
                'validating' => __('Validating URL...', 'wc-etsy-importer'),
                'invalidUrl' => __('Invalid Etsy listing URL', 'wc-etsy-importer'),
            )
        ));
    }

    /**
     * AJAX handler for importing Etsy listing
     */
    public function ajax_import_listing() {
        check_ajax_referer('wc_etsy_importer_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wc-etsy-importer')));
        }

        // Ensure WooCommerce is active
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(array('message' => __('WooCommerce is not active. Please activate WooCommerce first.', 'wc-etsy-importer')));
        }

        // Load dependencies if not already loaded
        if (!class_exists('WC_Etsy_Scraper')) {
            require_once WC_ETSY_IMPORTER_PLUGIN_DIR . 'includes/class-etsy-scraper.php';
        }
        if (!class_exists('WC_Product_Creator')) {
            require_once WC_ETSY_IMPORTER_PLUGIN_DIR . 'includes/class-wc-product-creator.php';
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (empty($url)) {
            wp_send_json_error(array('message' => __('No URL provided', 'wc-etsy-importer')));
        }

        try {
            // Scrape Etsy listing
            $scraper = new WC_Etsy_Scraper();
            $listing_data = $scraper->scrape_listing($url);

            if (!$listing_data) {
                wp_send_json_error(array('message' => __('Failed to scrape Etsy listing', 'wc-etsy-importer')));
            }

            // Create WooCommerce product
            $creator = new WC_Product_Creator();
            $product_id = $creator->create_product($listing_data);

            if (is_wp_error($product_id)) {
                wp_send_json_error(array('message' => $product_id->get_error_message()));
            }

            wp_send_json_success(array(
                'message' => __('Product imported successfully!', 'wc-etsy-importer'),
                'product_id' => $product_id,
                'edit_url' => admin_url('post.php?post=' . $product_id . '&action=edit'),
                'listing_data' => $listing_data
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for validating Etsy URL
     */
    public function ajax_validate_url() {
        check_ajax_referer('wc_etsy_importer_nonce', 'nonce');

        // Ensure WooCommerce is active
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(array('message' => __('WooCommerce is not active. Please activate WooCommerce first.', 'wc-etsy-importer')));
        }

        // Load dependencies if not already loaded
        if (!class_exists('WC_Etsy_Scraper')) {
            require_once WC_ETSY_IMPORTER_PLUGIN_DIR . 'includes/class-etsy-scraper.php';
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (empty($url)) {
            wp_send_json_error(array('message' => __('No URL provided', 'wc-etsy-importer')));
        }

        $scraper = new WC_Etsy_Scraper();
        $is_valid = $scraper->validate_url($url);

        if ($is_valid) {
            wp_send_json_success(array('message' => __('Valid Etsy listing URL', 'wc-etsy-importer')));
        } else {
            wp_send_json_error(array('message' => __('Invalid Etsy listing URL', 'wc-etsy-importer')));
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('WC_Etsy_Importer', 'get_instance'));
