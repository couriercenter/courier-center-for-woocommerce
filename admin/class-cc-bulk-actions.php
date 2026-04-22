<?php
/**
 * Bulk Actions & Custom Columns for WooCommerce Orders List
 *
 * - Στήλη "CC Voucher" με AWB
 * - Στήλη "CC Status" με τρέχον status
 * - Bulk action: Δημιουργία Vouchers (Επόμενη Μέρα)
 * - Bulk action: Ενημέρωση Status
 * - Bulk action: Μαζική Εκτύπωση Vouchers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Bulk_Actions {

    public function __construct() {
        // === BULK ACTIONS ===
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk_actions' ) );
        add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk_actions' ) );

        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_action' ), 10, 3 );
        add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_action' ), 10, 3 );

        add_action( 'admin_notices', array( $this, 'show_bulk_result_notice' ) );

        // === MASS PRINT HANDLER ===
        add_action( 'admin_post_cc_mass_print_vouchers',        array( $this, 'handle_mass_print' ) );
        add_action( 'admin_post_cc_mass_print_boxnow_vouchers', array( $this, 'handle_mass_print' ) );

        // === CUSTOM COLUMNS (HPOS) ===
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_custom_columns' ) );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );

        // === CUSTOM COLUMNS (Legacy) ===
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_custom_columns' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_custom_columns_legacy' ), 10, 2 );

        // === COLUMN STYLES ===
        add_action( 'admin_head', array( $this, 'column_styles' ) );
    }

    // =========================================================================
    // CUSTOM COLUMNS
    // =========================================================================

    public function add_custom_columns( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            if ( $key === 'order_status' ) {
                $new_columns['cc_voucher']  = 'CC Voucher';
                $new_columns['cc_type']     = 'Τύπος';
                $new_columns['cc_status']   = 'CC Status';
            }
        }
        if ( ! isset( $new_columns['cc_voucher'] ) ) {
            $new_columns['cc_voucher'] = 'CC Voucher';
            $new_columns['cc_type']    = 'Τύπος';
            $new_columns['cc_status']  = 'CC Status';
        }
        return $new_columns;
    }

    public function render_custom_columns( $column_name, $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) { echo '—'; return; }
        $this->render_column_content( $column_name, $order );
    }

    public function render_custom_columns_legacy( $column_name, $post_id ) {
        $order = wc_get_order( $post_id );
        if ( ! $order ) { echo '—'; return; }
        $this->render_column_content( $column_name, $order );
    }

    private function render_column_content( $column_name, $order ) {
        if ( $column_name === 'cc_voucher' ) {
            $voucher   = $order->get_meta( '_cc_voucher_number' );
            $is_voided = $order->get_meta( '_cc_voided' ) === '1';

            if ( empty( $voucher ) ) {
                echo '<span style="color: #999;">—</span>';
            } elseif ( $is_voided ) {
                echo '<span style="color: #b32d2e; text-decoration: line-through;" title="Ακυρωμένη">' . esc_html( $voucher ) . '</span>';
            } else {
                echo '<span style="color: #2271b1; font-weight: 500;">' . esc_html( $voucher ) . '</span>';
            }
        }

        if ( $column_name === 'cc_type' ) {
            $voucher   = $order->get_meta( '_cc_voucher_number' );
            $is_voided = $order->get_meta( '_cc_voided' ) === '1';
            $is_boxnow = $order->get_meta( '_cc_boxnow' ) === '1';

            if ( empty( $voucher ) || $is_voided ) {
                echo '<span style="color:#999;">—</span>';
            } elseif ( $is_boxnow ) {
                echo '<span class="cc-col-badge cc-col-boxnow">📦 BOX NOW</span>';
            } else {
                echo '<span class="cc-col-badge cc-col-normal">🚚 Κανονική</span>';
            }
        }

        if ( $column_name === 'cc_status' ) {
            $voucher     = $order->get_meta( '_cc_voucher_number' );
            $is_voided   = $order->get_meta( '_cc_voided' ) === '1';
            $status_desc = $order->get_meta( '_cc_shipment_status_desc' );
            $status_code = $order->get_meta( '_cc_shipment_status' );

            if ( empty( $voucher ) ) {
                echo '<span style="color: #999;">—</span>';
            } elseif ( $is_voided ) {
                echo '<span class="cc-col-badge cc-col-cancelled">Ακυρωμένη</span>';
            } elseif ( ! empty( $status_desc ) && $status_desc !== 'None' ) {
                $badge_class = 'cc-col-active';
                if ( in_array( $status_code, array( '29', '87' ), true ) ) {
                    $badge_class = 'cc-col-delivered';
                } elseif ( in_array( $status_code, array( '25', '99', '14', '95' ), true ) ) {
                    $badge_class = 'cc-col-cancelled';
                } elseif ( in_array( $status_code, array( '28', '30' ), true ) ) {
                    $badge_class = 'cc-col-failed';
                }
                echo '<span class="cc-col-badge ' . esc_attr( $badge_class ) . '" title="Code: ' . esc_attr( $status_code ) . '">';
                echo esc_html( $this->shorten_status( $status_desc ) );
                echo '</span>';
            } else {
                echo '<span class="cc-col-badge cc-col-new">Νέα</span>';
            }
        }
    }

    private function shorten_status( $desc ) {
        $map = array(
            'ShipmentCreated'               => 'Δημιουργήθηκε',
            'ShipmentPickedUp'              => 'Παραλήφθηκε',
            'ShipmentInTransit'             => 'Σε μεταφορά',
            'ShipmentArriveAtFinalStation'   => 'Στον σταθμό',
            'ShipmentOutForDelivery'        => 'Σε διανομή',
            'ShipmentDelivered'             => 'Παραδόθηκε',
            'ShipmentDeliveryFailed'        => 'Αποτυχία',
            'ShipmentReturnedToSender'      => 'Επιστράφηκε',
            'ShipmentCancelled'             => 'Ακυρωμένη',
        );
        return $map[ $desc ] ?? $desc;
    }

    public function column_styles() {
        $screen = get_current_screen();
        if ( ! $screen ) { return; }
        if ( strpos( $screen->id, 'wc-orders' ) === false && $screen->post_type !== 'shop_order' ) { return; }

        echo '<style>
            .column-cc_voucher { width: 130px; }
            .column-cc_type   { width: 110px; }
            .column-cc_status { width: 120px; }
            .cc-col-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
                line-height: 1.4;
                white-space: nowrap;
            }
            .cc-col-new { background: #f0f6fc; color: #2271b1; }
            .cc-col-active { background: #e7f0ff; color: #1d4ed8; }
            .cc-col-delivered { background: #e7f5e9; color: #1e6823; }
            .cc-col-failed { background: #fff3cd; color: #856404; }
            .cc-col-cancelled { background: #fce8e6; color: #8b0000; }
            .cc-col-boxnow    { background: #fff3e0; color: #e65100; }
            .cc-col-normal    { background: #f1f1f1; color: #555; }
        </style>';
    }

    // =========================================================================
    // BULK ACTIONS
    // =========================================================================

    public function register_bulk_actions( $actions ) {
        $actions['cc_create_vouchers']  = '🚚 CC — Δημιουργία Vouchers (Επόμενη Μέρα)';
        $actions['cc_update_statuses']  = '🔄 CC — Ενημέρωση Status';
        $actions['cc_mass_print']       = '🖨️ CC — Μαζική Εκτύπωση Vouchers';
        $actions['cc_mass_print_boxnow'] = '🟡 CC — Μαζική Εκτύπωση BOX NOW';
        return $actions;
    }

    public function handle_bulk_action( $redirect_to, $action, $order_ids ) {
        if ( $action === 'cc_create_vouchers' ) {
            return $this->bulk_create_vouchers( $redirect_to, $order_ids );
        }
        if ( $action === 'cc_update_statuses' ) {
            return $this->bulk_update_statuses( $redirect_to, $order_ids );
        }
        if ( $action === 'cc_mass_print' ) {
            return $this->bulk_mass_print( $redirect_to, $order_ids );
        }
        if ( $action === 'cc_mass_print_boxnow' ) {
            return $this->bulk_mass_print_boxnow( $redirect_to, $order_ids );
        }
        return $redirect_to;
    }

    // =========================================================================
    // MASS PRINT
    // =========================================================================

    /**
     * Bulk mass print - store AWBs in transient and redirect to print handler
     */
    private function bulk_mass_print( $redirect_to, $order_ids ) {
        if ( empty( $order_ids ) ) {
            return $redirect_to;
        }

        $awbs        = array();
        $boxnow_awbs = array();
        $skipped     = 0;

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) { continue; }

            $awb       = $order->get_meta( '_cc_voucher_number' );
            $is_voided = $order->get_meta( '_cc_voided' ) === '1';
            $is_boxnow = $order->get_meta( '_cc_boxnow' ) === '1';

            if ( empty( $awb ) || $is_voided ) {
                $skipped++;
                continue;
            }

            if ( $is_boxnow ) {
                $boxnow_awbs[] = $awb;
            } else {
                $awbs[] = $awb;
            }
        }

        if ( empty( $awbs ) && empty( $boxnow_awbs ) ) {
            set_transient( 'cc_bulk_result_' . get_current_user_id(), array(
                'type' => 'print', 'success' => 0, 'failed' => 0,
                'skipped' => $skipped, 'errors' => array( 'Δεν βρέθηκαν ενεργά vouchers' ),
                'total' => count( $order_ids ),
            ), 60 );
            return add_query_arg( 'cc_bulk_done', '1', $redirect_to );
        }

        // Store AWBs in transient for the print handler
        $print_key = 'cc_mass_print_' . get_current_user_id() . '_' . time();
        set_transient( $print_key, array(
            'awbs'        => $awbs,
            'boxnow_awbs' => $boxnow_awbs,
        ), 300 ); // 5 minutes expiry

        // Redirect to print handler in new tab via JS
        $print_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=cc_mass_print_vouchers&print_key=' . urlencode( $print_key ) ),
            'cc_mass_print'
        );

        // Store the print URL so we can show it in the notice
        set_transient( 'cc_bulk_result_' . get_current_user_id(), array(
            'type'      => 'print',
            'success'   => count( $awbs ) + count( $boxnow_awbs ),
            'failed'    => 0,
            'skipped'   => $skipped,
            'errors'    => array(),
            'total'     => count( $order_ids ),
            'print_url' => $print_url,
        ), 60 );

        return add_query_arg( 'cc_bulk_done', '1', $redirect_to );
    }

    /**
     * BOX NOW mass print - εκτυπώνει μόνο BOX NOW vouchers με template singlepdf_100x150
     */
    private function bulk_mass_print_boxnow( $redirect_to, $order_ids ) {
        if ( empty( $order_ids ) ) {
            return $redirect_to;
        }

        $awbs    = array();
        $skipped = 0;

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) { continue; }

            $awb        = $order->get_meta( '_cc_voucher_number' );
            $is_voided  = $order->get_meta( '_cc_voided' ) === '1';
            $is_boxnow  = $order->get_meta( '_cc_boxnow' ) === '1';

            if ( empty( $awb ) || $is_voided || ! $is_boxnow ) {
                $skipped++;
                continue;
            }

            $awbs[] = $awb;
        }

        if ( empty( $awbs ) ) {
            set_transient( 'cc_bulk_result_' . get_current_user_id(), array(
                'type'    => 'print',
                'success' => 0,
                'failed'  => 0,
                'skipped' => $skipped,
                'errors'  => array( 'Καμία από τις επιλεγμένες παραγγελίες δεν είναι BOX NOW' ),
                'total'   => count( $order_ids ),
            ), 60 );
            return add_query_arg( 'cc_bulk_done', '1', $redirect_to );
        }

        $print_key = 'cc_mass_print_boxnow_' . get_current_user_id() . '_' . time();
        set_transient( $print_key, array(
            'awbs'        => $awbs,
            'boxnow_awbs' => array(),
            'template'    => get_option( 'cc_wc_print_template_boxnow', 'singlepdf_100x150_4up' ),
        ), 300 );

        $print_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=cc_mass_print_boxnow_vouchers&print_key=' . urlencode( $print_key ) ),
            'cc_mass_print'
        );

        set_transient( 'cc_bulk_result_' . get_current_user_id(), array(
            'type'      => 'print',
            'success'   => count( $awbs ),
            'failed'    => 0,
            'skipped'   => $skipped,
            'errors'    => array(),
            'total'     => count( $order_ids ),
            'print_url' => $print_url,
        ), 60 );

        return add_query_arg( 'cc_bulk_done', '1', $redirect_to );
    }

    /**
     * Handle mass print - fetch combined PDF from API, scale it down, serve directly
     */
    public function handle_mass_print() {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cc_mass_print' ) ) {
            wp_die( 'Invalid request' );
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( 'Unauthorized' );
        }

        $print_key = isset( $_GET['print_key'] ) ? sanitize_text_field( $_GET['print_key'] ) : '';
        $data = get_transient( $print_key );

        if ( ! $data ) {
            wp_die( 'Τα δεδομένα εκτύπωσης έληξαν. Δοκιμάστε ξανά.' );
        }

        delete_transient( $print_key );

        $awbs        = $data['awbs'] ?? array();
        $boxnow_awbs = $data['boxnow_awbs'] ?? array();

        // Determine which AWBs to print and which template
        $print_awbs = ! empty( $awbs ) ? $awbs : $boxnow_awbs;
        $is_boxnow_batch = empty( $awbs ) && ! empty( $boxnow_awbs );
        $template = $data['template'] ?? (
            $is_boxnow_batch
                ? get_option( 'cc_wc_print_template_boxnow', 'singlepdf_100x150_4up' )
                : get_option( 'cc_wc_print_template', 'pdf' )
        );

        if ( empty( $print_awbs ) ) {
            wp_die( 'Δεν βρέθηκαν vouchers' );
        }

        // singlepdf_100x150_4up είναι custom — στέλνουμε singlepdf_100x150 στο API
        // αλλά μετά κάνουμε arrange_4up() με FPDI
        $raw_template = $template;
        $use_4up      = ( $raw_template === 'singlepdf_100x150_4up' );
        $api_template = $use_4up ? 'singlepdf_100x150' : $raw_template;

        $api = new CC_API();
        $pdf = $api->get_voucher_pdf( $print_awbs, $api_template );

        if ( is_wp_error( $pdf ) ) {
            wp_die( 'Σφάλμα λήψης vouchers: ' . esc_html( $pdf->get_error_message() ) );
        }

        // Αν είναι 4up layout, κάνε arrange με FPDI
        if ( $use_4up ) {
            $arranged = CC_PDF_Scaler::arrange_4up( $pdf );
            if ( ! is_wp_error( $arranged ) ) {
                $pdf = $arranged;
            }
        }

        // Scale για κανονικά (pdf / clean)
        if ( in_array( $api_template, array( 'pdf', 'clean' ), true ) ) {
            $scaled = CC_PDF_Scaler::scale_pdf( $pdf, 0.95 );
            if ( ! is_wp_error( $scaled ) ) {
                $pdf = $scaled;
            }
        }

        $scaled_pdf = $pdf;

        // Σερβίρισε το scaled PDF απευθείας στον browser
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="vouchers-' . date( 'Y-m-d-His' ) . '.pdf"' );
        header( 'Content-Length: ' . strlen( $scaled_pdf ) );
        echo $scaled_pdf;
        exit;
    }
    // =========================================================================
    // BULK CREATE VOUCHERS
    // =========================================================================

    private function bulk_create_vouchers( $redirect_to, $order_ids ) {
        if ( empty( $order_ids ) ) { return $redirect_to; }

        $success = 0; $failed = 0; $skipped = 0; $errors = array();
        $api = new CC_API();

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) { $failed++; $errors[] = "#$order_id: Δεν βρέθηκε"; continue; }

            $existing  = $order->get_meta( '_cc_voucher_number' );
            $is_voided = $order->get_meta( '_cc_voided' ) === '1';
            if ( $existing && ! $is_voided ) { $skipped++; continue; }

            $builder = new CC_Shipment_Builder( $order );
            $check = $builder->validate_settings();
            if ( is_wp_error( $check ) ) { $failed++; $errors[] = "#$order_id: " . $check->get_error_message(); continue; }
            $check = $builder->validate_order();
            if ( is_wp_error( $check ) ) { $failed++; $errors[] = "#$order_id: " . $check->get_error_message(); continue; }

            $payload = $builder->build_payload( 'next_day', false );
            $result  = $api->create_shipment( $payload );

            if ( is_wp_error( $result ) ) {
                $failed++; $errors[] = "#$order_id: " . $result->get_error_message();
                $order->add_order_note( '❌ Bulk: ' . $result->get_error_message() );
                continue;
            }

            $voucher_number  = $result['ShipmentNumber'] ?? '';
            $tracking_number = isset( $result['TrackingNumbers'][0] ) ? $result['TrackingNumbers'][0] : $voucher_number;
            if ( empty( $voucher_number ) ) { $failed++; $errors[] = "#$order_id: Δεν επιστράφηκε AWB"; continue; }

            if ( $is_voided ) {
                $order->delete_meta_data( '_cc_voided' );
                $order->delete_meta_data( '_cc_voided_at' );
                $order->delete_meta_data( '_cc_shipment_status' );
                $order->delete_meta_data( '_cc_shipment_status_desc' );
                $order->delete_meta_data( '_cc_shipment_action_code' );
                $order->delete_meta_data( '_cc_status_updated_at' );
            }

            $order->update_meta_data( '_cc_voucher_number', $voucher_number );
            $order->update_meta_data( '_cc_tracking_number', $tracking_number );
            $order->update_meta_data( '_cc_service_type', 'next_day' );
            $order->update_meta_data( '_cc_boxnow', '0' );
            $order->update_meta_data( '_cc_created_at', current_time( 'mysql' ) );
            $order->save();

            $order->add_order_note( '✅ CC voucher (Bulk): ' . $voucher_number . ' | Επόμενη Μέρα' );
            $success++;
            usleep( 300000 );
        }

        set_transient( 'cc_bulk_result_' . get_current_user_id(), array(
            'type' => 'create', 'success' => $success, 'failed' => $failed,
            'skipped' => $skipped, 'errors' => $errors, 'total' => count( $order_ids ),
        ), 60 );
        return add_query_arg( 'cc_bulk_done', '1', $redirect_to );
    }

    // =========================================================================
    // BULK UPDATE STATUSES
    // =========================================================================

    private function bulk_update_statuses( $redirect_to, $order_ids ) {
        if ( empty( $order_ids ) ) { return $redirect_to; }

        $updated = 0; $skipped = 0; $failed = 0; $errors = array();
        $api = new CC_API();

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) { $failed++; continue; }

            $awb       = $order->get_meta( '_cc_voucher_number' );
            $is_voided = $order->get_meta( '_cc_voided' ) === '1';
            if ( empty( $awb ) || $is_voided ) { $skipped++; continue; }

            // Έλεγχος rate limit
            $rate_check = CC_Status_Tracker::check_rate_limit( $order );
            if ( ! $rate_check['allowed'] ) {
                $skipped++;
                $errors[] = sprintf(
                    '#%d (%s): Ενημερώθηκε %s, διαθέσιμο σε %d λεπτά',
                    $order_id,
                    $awb,
                    $rate_check['last_updated_human'],
                    $rate_check['minutes_remaining']
                );
                continue;
            }

            $result = $api->get_shipment_details( $awb );
            if ( is_wp_error( $result ) ) { $failed++; $errors[] = "#$order_id ($awb): " . $result->get_error_message(); continue; }

            $status_code = ''; $status_desc = ''; $action_code = '';
            if ( isset( $result['ShipmentDetails'][0]['ShipmentInfo'] ) ) {
                $info        = $result['ShipmentDetails'][0]['ShipmentInfo'];
                $status_code = (string) ( $info['ShipmentStatus'] ?? '' );
                $status_desc = $info['ShipmentStatusDesc'] ?? '';
                $action_code = $info['CollectionStatus'] ?? '';
            }
            if ( empty( $status_code ) && empty( $status_desc ) ) { $failed++; $errors[] = "#$order_id ($awb): Δεν βρέθηκε status"; continue; }

            $old_status = $order->get_meta( '_cc_shipment_status' );
            $order->update_meta_data( '_cc_shipment_status', $status_code );
            $order->update_meta_data( '_cc_shipment_status_desc', $status_desc );
            $order->update_meta_data( '_cc_shipment_action_code', $action_code );
            $order->update_meta_data( '_cc_status_updated_at', current_time( 'Y-m-d H:i' ) );

            $final = array( '29', '25', '99', '14', '87', '95' );
            if ( in_array( $status_code, $final, true ) ) {
                $order->update_meta_data( '_cc_shipment_final', '1' );
            }
            $order->save();

            CC_Status_Tracker::mark_check_now( $order );

            if ( $old_status !== $status_code ) {
                $order->add_order_note( sprintf( '📍 [Bulk] Status: %s - %s', $status_code, $status_desc ) );
                if ( in_array( $status_code, array( '29', '87' ), true ) && $order->get_status() !== 'completed' ) {
                    $order->update_status( 'completed', '🚚 Αυτόματη ολοκλήρωση - Παραδόθηκε.' );
                }
            }

            $updated++;
            usleep( 500000 );
        }

        set_transient( 'cc_bulk_result_' . get_current_user_id(), array(
            'type' => 'status', 'success' => $updated, 'failed' => $failed,
            'skipped' => $skipped, 'errors' => $errors, 'total' => count( $order_ids ),
        ), 60 );
        return add_query_arg( 'cc_bulk_done', '1', $redirect_to );
    }

    // =========================================================================
    // RESULTS NOTICE
    // =========================================================================

    public function show_bulk_result_notice() {
        if ( ! isset( $_GET['cc_bulk_done'] ) ) { return; }

        $result = get_transient( 'cc_bulk_result_' . get_current_user_id() );
        if ( ! $result ) { return; }
        delete_transient( 'cc_bulk_result_' . get_current_user_id() );

        $type    = $result['type'] ?? 'create';
        $success = $result['success'];
        $failed  = $result['failed'];
        $skipped = $result['skipped'];
        $errors  = $result['errors'];
        $total   = $result['total'];

        // Special handling for print action - show link to open PDF
        if ( $type === 'print' && ! empty( $result['print_url'] ) ) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>🖨️ Courier Center — Μαζική Εκτύπωση</strong></p>';
            echo '<p>' . esc_html( $success ) . ' vouchers έτοιμα για εκτύπωση.';
            if ( $skipped > 0 ) {
                echo ' (' . esc_html( $skipped ) . ' παραλείφθηκαν — χωρίς ενεργό voucher)';
            }
            echo '</p>';
            echo '<p><a href="' . esc_url( $result['print_url'] ) . '" target="_blank" class="button button-primary">📄 Άνοιγμα PDF για εκτύπωση</a></p>';
            echo '</div>';

            // Auto-open in new tab via JS
            echo '<script>window.open("' . esc_url( $result['print_url'] ) . '", "_blank");</script>';
            return;
        }

        $notice_type = 'success';
        if ( $failed > 0 && $success === 0 ) { $notice_type = 'error'; }
        elseif ( $failed > 0 ) { $notice_type = 'warning'; }

        $titles = array(
            'create' => '🚚 Courier Center — Bulk Voucher Creation',
            'status' => '🔄 Courier Center — Bulk Status Update',
            'print'  => '🖨️ Courier Center — Μαζική Εκτύπωση',
        );
        $title = $titles[ $type ] ?? $titles['create'];

        $success_labels = array( 'create' => 'Δημιουργήθηκαν', 'status' => 'Ενημερώθηκαν', 'print' => 'Εκτυπώθηκαν' );
        $skip_labels    = array( 'create' => 'Ήδη έχουν voucher', 'status' => 'Χωρίς voucher', 'print' => 'Χωρίς voucher' );

        echo '<div class="notice notice-' . esc_attr( $notice_type ) . ' is-dismissible">';
        echo '<p><strong>' . esc_html( $title ) . '</strong></p>';
        echo '<p>Σύνολο: ' . esc_html( $total ) . ' | ✅ ' . esc_html( $success_labels[ $type ] ?? 'OK' ) . ': ' . esc_html( $success );
        if ( $skipped > 0 ) { echo ' | ⏭️ ' . esc_html( $skip_labels[ $type ] ?? 'Παράλειψη' ) . ': ' . esc_html( $skipped ); }
        if ( $failed > 0 ) { echo ' | ❌ Απέτυχαν: ' . esc_html( $failed ); }
        echo '</p>';

        if ( ! empty( $errors ) ) {
            echo '<details style="margin-top: 5px;"><summary style="cursor: pointer; color: #b32d2e;">Λεπτομέρειες σφαλμάτων</summary><ul style="margin-top: 5px;">';
            foreach ( $errors as $error ) { echo '<li>' . esc_html( $error ) . '</li>'; }
            echo '</ul></details>';
        }
        echo '</div>';
    }
}