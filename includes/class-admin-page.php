<?php
/**
 * Admin Page Class
 *
 * Handles the admin interface for the Etsy importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Etsy_Importer_Admin_Page {

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Etsy Importer', 'wc-etsy-importer'),
            __('Etsy Importer', 'wc-etsy-importer'),
            'manage_woocommerce',
            'wc-etsy-importer',
            array($this, 'render_admin_page'),
            'dashicons-download',
            56
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap wc-etsy-importer-wrap">
            <h1><?php _e('WooCommerce Etsy Importer', 'wc-etsy-importer'); ?></h1>

            <div class="wc-etsy-importer-container">
                <div class="wc-etsy-importer-form-section">
                    <h2><?php _e('Import Etsy Listings', 'wc-etsy-importer'); ?></h2>
                    <p class="description">
                        <?php _e('Enter one or more Etsy listing URLs (one per line) to import them as WooCommerce products.', 'wc-etsy-importer'); ?>
                    </p>

                    <form id="wc-etsy-importer-form" method="post">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="etsy-urls"><?php _e('Etsy Listing URLs', 'wc-etsy-importer'); ?></label>
                                </th>
                                <td>
                                    <textarea
                                        id="etsy-urls"
                                        name="etsy_urls"
                                        rows="10"
                                        cols="80"
                                        class="large-text"
                                        placeholder="https://www.etsy.com/listing/123456789/product-name&#10;https://www.etsy.com/listing/987654321/another-product"
                                    ></textarea>
                                    <p class="description">
                                        <?php _e('Example: https://greenwoodcreation.etsy.com/listing/1896333507', 'wc-etsy-importer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary button-hero" id="start-import">
                                <?php _e('Start Import', 'wc-etsy-importer'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Progress Section -->
                <div id="import-progress-section" class="wc-etsy-importer-progress-section" style="display: none;">
                    <h2><?php _e('Import Progress', 'wc-etsy-importer'); ?></h2>

                    <!-- Overall Progress -->
                    <div class="progress-container">
                        <h3><?php _e('Overall Progress', 'wc-etsy-importer'); ?></h3>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar">
                                <div class="progress-bar-fill" id="overall-progress-bar"></div>
                            </div>
                            <span class="progress-text" id="overall-progress-text">0%</span>
                        </div>
                        <p class="progress-info" id="overall-progress-info">
                            <?php _e('0 of 0 listings imported', 'wc-etsy-importer'); ?>
                        </p>
                    </div>

                    <!-- Current Item Progress -->
                    <div class="progress-container">
                        <h3><?php _e('Current Item', 'wc-etsy-importer'); ?></h3>
                        <div class="current-item-url" id="current-item-url"></div>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar">
                                <div class="progress-bar-fill" id="current-progress-bar"></div>
                            </div>
                            <span class="progress-text" id="current-progress-text">0%</span>
                        </div>
                        <p class="progress-status" id="current-progress-status">
                            <?php _e('Waiting to start...', 'wc-etsy-importer'); ?>
                        </p>
                    </div>

                    <!-- Import Log -->
                    <div class="import-log-container">
                        <h3><?php _e('Import Log', 'wc-etsy-importer'); ?></h3>
                        <div id="import-log" class="import-log"></div>
                    </div>

                    <!-- Imported Products -->
                    <div class="imported-products-container" id="imported-products-container" style="display: none;">
                        <h3><?php _e('Imported Products', 'wc-etsy-importer'); ?></h3>
                        <ul id="imported-products-list" class="imported-products-list"></ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
