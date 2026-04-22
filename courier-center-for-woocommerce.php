<?php
/**
 * Plugin Name: Courier Center for WooCommerce
 * Plugin URI: https://courier.gr
 * Description: Ενσωμάτωση Courier Center με WooCommerce - Αυτόματη δημιουργία vouchers, tracking, και διαχείριση αποστολών
 * Version: 1.1.2
 * Author: Courier Center
 * Author URI: https://courier.gr
 * Text Domain: courier-center-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Έλεγχος αν το WooCommerce είναι ενεργό
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

// Δήλωση συμβατότητας με WooCommerce HPOS
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

/**
 * Κύρια κλάση plugin
 */
class Courier_Center_WooCommerce {

    private static $instance = null;
    const VERSION = '1.1.2';
    private $settings;
    private $order_meta_box;
    private $status_tracker;
    private $bulk_actions;
    private $manifest_page;
    private $email_integration;
    private $bug_report;


    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants() {
        define( 'CC_WC_VERSION', self::VERSION );
        define( 'CC_WC_PLUGIN_FILE', __FILE__ );
        define( 'CC_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'CC_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    private function includes() {
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-api.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-city-scope.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-shipment-builder.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-status-tracker.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-pdf-scaler.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-email-integration.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-settings.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-order-meta-box.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-bulk-actions.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-manifest-page.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-bug-report.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-updater.php';
    }

    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

        add_filter( 'cron_schedules', function( $schedules ) {
            if ( ! isset( $schedules['cc_every_two_hours'] ) ) {
                $schedules['cc_every_two_hours'] = array(
                    'interval' => 7200,
                    'display'  => 'Κάθε 2 ώρες (Courier Center)',
                );
            }
            return $schedules;
        } );

        $this->settings       = new CC_Settings();
        $this->order_meta_box = new CC_Order_Meta_Box();
        $this->bulk_actions = new CC_Bulk_Actions();
        $this->manifest_page = new CC_Manifest_Page();
        $this->email_integration = new CC_Email_Integration();
        $this->bug_report = new CC_Bug_Report();
        new CC_Updater();
    }

    public function admin_scripts( $hook ) {
        if ( strpos( $hook, 'courier-center' ) === false ) {
            return;
        }
        wp_enqueue_style(
            'cc-wc-admin',
            CC_WC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CC_WC_VERSION
        );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Courier Center',
            'Courier Center',
            'manage_woocommerce',
            'courier-center',
            array( $this->settings, 'render_page' ),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'courier-center',
            'Ρυθμίσεις',
            'Ρυθμίσεις',
            'manage_woocommerce',
            'courier-center',
            array( $this->settings, 'render_page' )
        );
        $this->manifest_page->add_submenu();
        $this->bug_report->add_submenu();
    }
}

// Initialize plugin
Courier_Center_WooCommerce::get_instance();
