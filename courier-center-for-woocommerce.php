<?php
/**
 * Plugin Name: Courier Center for WooCommerce
 * Plugin URI: https://courier.gr
 * Description: Ενσωμάτωση Courier Center με WooCommerce - Αυτόματη δημιουργία vouchers, tracking, και διαχείριση αποστολών
 * Version: 1.6.2
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
    const VERSION = '1.6.2';
    private $settings;
    private $order_meta_box;
    private $status_tracker;
    private $bulk_actions;
    private $manifest_page;
    private $returns_admin;
    private $email_integration;
    private $bug_report;
    private $docs_page;


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
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-pickup-points.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/returns/class-cc-returns-rules.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/returns/class-cc-returns-repo.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/returns/class-cc-returns-settings.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/returns/class-cc-return-payload.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/returns/class-cc-returns-service.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/returns/class-cc-returns-tracker.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/returns/class-cc-returns-account.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-returns-admin.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-returns-admin-new.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/returns/class-cc-returns-emails.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-shipment-builder.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-status-tracker.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-pdf-scaler.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-email-integration.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-boxnow-checkout.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-settings.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-order-meta-box.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-bulk-actions.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-orders-column.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-manifest-page.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-bug-report.php';
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-docs-page.php';
        require_once CC_WC_PLUGIN_DIR . 'includes/class-cc-updater.php';
    }

    private function init_hooks() {
        // Επιστροφές: δημιουργία/αναβάθμιση του πίνακα αιτημάτων
        add_action( 'plugins_loaded', array( 'CC_Returns_Repo', 'maybe_upgrade' ) );
        // Επιστροφές: παρακολούθηση status στο ίδιο cron με τις κανονικές αποστολές (ξεχωριστό callback)
        add_action( 'cc_status_tracking_cron', array( 'CC_Returns_Tracker', 'cron' ), 20, 0 );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

        // Εξασφάλισε ότι το schedule υπάρχει πριν γίνει instantiation
        add_filter( 'cron_schedules', function( $schedules ) {
            if ( ! isset( $schedules['cc_every_two_hours'] ) ) {
                $schedules['cc_every_two_hours'] = array(
                    'interval' => 7200,
                    'display'  => 'Every 2 hours (Courier Center)',
                );
            }
            return $schedules;
        }, 5 );

        $this->status_tracker = new CC_Status_Tracker();
        $this->settings       = new CC_Settings();
        $this->order_meta_box = new CC_Order_Meta_Box();
        $this->bulk_actions = new CC_Bulk_Actions();
        new CC_Orders_Column();
        $this->manifest_page = new CC_Manifest_Page();
        $this->email_integration = new CC_Email_Integration();
        $this->bug_report = new CC_Bug_Report();
        $this->docs_page = new CC_Docs_Page();
        new CC_BoxNow_Checkout();
        new CC_Returns_Settings();
        new CC_Returns_Account(); // My Account: αίτημα επιστροφής (τρέχει και στο frontend)
        $this->returns_admin = new CC_Returns_Admin(); // Επιστροφές: σελίδα αιτημάτων + meta box παραγγελίας
        new CC_Returns_Emails(); // Επιστροφές: emails πελάτη (ελήφθη, δημιουργήθηκε, απορρίφθηκε)
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
            'Courier Center', // ο αριθμός αιτημάτων επιστροφής μπαίνει από το CC_Returns_Admin::badge_top_menu()
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
        $this->returns_admin->add_submenu();
        $this->bug_report->add_submenu();
        $this->docs_page->add_submenu();
    }
}

// Activation hook — fires a ping immediately so installs appear in the dashboard
register_activation_hook( __FILE__, function() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-cc-updater.php';
    CC_Updater::on_activation();
} );

// Initialize plugin
Courier_Center_WooCommerce::get_instance();
