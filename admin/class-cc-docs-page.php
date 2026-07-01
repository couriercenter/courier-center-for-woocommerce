<?php
/**
 * Documentation Page - Οδηγίες Χρήσης (in-admin user guide)
 *
 * Το περιεχόμενο βρίσκεται στο admin/views/documentation.php.
 * ΕΝΗΜΕΡΩΣΗ: σε κάθε νέα έκδοση/αλλαγή ενημερώνουμε το view + το changelog εκεί.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Docs_Page {

    public function add_submenu() {
        add_submenu_page(
            'courier-center',
            __( 'Οδηγίες', 'courier-center-woocommerce' ),
            '📖 ' . __( 'Οδηγίες', 'courier-center-woocommerce' ),
            'manage_woocommerce',
            'courier-center-docs',
            array( $this, 'render_page' )
        );
    }

    public function render_page() {
        $version    = CC_WC_VERSION;
        $report_url = admin_url( 'admin.php?page=courier-center-bug-report' );
        include CC_WC_PLUGIN_DIR . 'admin/views/documentation.php';
    }
}
