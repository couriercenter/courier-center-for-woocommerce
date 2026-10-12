<?php
/**
 * Επιστροφές: διαχείριση (σελίδα «Επιστροφές» και meta box στην παραγγελία).
 *
 * - Submenu «Επιστροφές» (tabs: Αιτήματα | Ρυθμίσεις) με badge για αιτήματα που περιμένουν ενέργεια.
 * - Meta box «Επιστροφές Courier Center» στην οθόνη της παραγγελίας (legacy + HPOS), όταν υπάρχουν αιτήματα ή AWB,
 *   με σύνδεσμο «Νέα επιστροφή» (CC_Returns_Admin_New) για παραγγελίες με AWB.
 * - Ενέργειες (έγκριση, απόρριψη, δημιουργία, επανάληψη, έλεγχος, tracking, voucher) ως συνδέσμοι προς
 *   admin-post.php?action=cc_return_action με nonce ανά αίτημα. Δεν υπάρχουν <form>/<button> μέσα στο
 *   meta box, γιατί αυτό βρίσκεται μέσα στη φόρμα της παραγγελίας.
 * - Το αποτέλεσμα κάθε ενέργειας αποθηκεύεται σε transient ανά χρήστη και εμφανίζεται ως admin notice.
 *
 * Το _cc_return_awb της παραγγελίας διαβάζεται μόνο για προειδοποίηση· δεν γράφεται ποτέ.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Admin {

    const PAGE       = 'courier-center-returns';
    const CAPABILITY = 'manage_woocommerce';
    const ACTION     = 'cc_return_action';
    const NOTICE_TTL = 60;
    const CONFIRM_REJECT = "return confirm('Απόρριψη του αιτήματος επιστροφής;')";

    /** @var CC_Returns_Service */
    private $service;

    /** @var CC_API */
    private $api;

    /** @var CC_Returns_Admin_New */
    public $new_return;

    public function __construct( ?CC_Returns_Service $service = null, ?CC_API $api = null ) {
        $this->api     = $api ? $api : new CC_API();
        $this->service = $service ? $service : new CC_Returns_Service( $this->api );
        $this->new_return = new CC_Returns_Admin_New( $this->service );

        add_action( 'add_meta_boxes_shop_order', array( $this, 'add_meta_box_legacy' ) );
        add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( $this, 'add_meta_box_hpos' ) );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
        add_action( 'admin_notices', array( $this, 'show_notice' ) );
    }

    // ── Μενού ────────────────────────────────────────────────────────────────

    /**
     * Πλήθος αιτημάτων που περιμένουν ενέργεια: pending_approval + needs_verification + creation_failed
     * + approved (εγκεκριμένο που δεν έφτασε σε δημιουργία, π.χ. διακοπή του request).
     */
    public function pending_count() {
        $counts = CC_Returns_Repo::count_by_status();
        $total  = 0;
        foreach ( array( CC_Returns_Rules::ST_PENDING, CC_Returns_Rules::ST_VERIFY, CC_Returns_Rules::ST_FAILED, CC_Returns_Rules::ST_APPROVED ) as $status ) {
            $total += (int) ( isset( $counts[ $status ] ) ? $counts[ $status ] : 0 );
        }
        return $total;
    }

    public function menu_title() {
        $n = $this->pending_count();
        return 'Επιστροφές' . ( $n > 0 ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '' );
    }

    public function add_submenu() {
        add_submenu_page(
            'courier-center',
            'Επιστροφές',
            $this->menu_title(),
            self::CAPABILITY,
            self::PAGE,
            array( $this, 'render_page' )
        );
    }

    public static function page_url( array $args = array() ) {
        return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
    }

    // ── Σελίδα ───────────────────────────────────────────────────────────────

    /** @return CC_Returns_List_Table */
    public function list_table() {
        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }
        require_once CC_WC_PLUGIN_DIR . 'admin/class-cc-returns-list-table.php';
        return new CC_Returns_List_Table();
    }

    public function render_page() {
        // Ληγμένα «creating» → needs_verification, ώστε να φαίνεται αμέσως η ενέργεια ελέγχου (όχι στο επόμενο cron)
        $this->service->release_stale_locks();
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $tab = in_array( $tab, array( 'settings', 'new' ), true ) ? $tab : 'requests';
        ?>
        <div class="wrap">
            <h1>↩️ Επιστροφές</h1>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( self::page_url( array( 'tab' => 'requests' ) ) ); ?>" class="nav-tab<?php echo 'requests' === $tab ? ' nav-tab-active' : ''; ?>">Αιτήματα</a>
                <a href="<?php echo esc_url( self::page_url( array( 'tab' => 'settings' ) ) ); ?>" class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active' : ''; ?>">Ρυθμίσεις</a>
            </h2>
            <?php
            if ( 'settings' === $tab ) {
                CC_Returns_Settings::render_tab(); // καλεί ήδη settings_errors()
            } elseif ( 'new' === $tab ) {
                $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
                $this->new_return->render( $order_id ? wc_get_order( $order_id ) : false );
            } else {
                $table = $this->list_table();
                $table->prepare_items();
                $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
                ?>
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
                    <input type="hidden" name="tab" value="requests">
                    <?php if ( in_array( $status, CC_Returns_List_Table::statuses(), true ) ) : ?>
                        <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                    <?php endif; ?>
                    <?php
                    $table->views();
                    $table->display();
                    ?>
                </form>
                <?php
            }
            ?>
        </div>
        <?php
    }

    // ── Κοινά helpers (λίστα + meta box) ─────────────────────────────────────

    /** Ενέργειες ανά status: array( array( do, label, confirm, blank ), ... ). */
    public static function actions_for( array $row ) {
        $actions = array();
        switch ( $row['status'] ) {
            case CC_Returns_Rules::ST_PENDING:
                $actions[] = array( 'do' => 'approve', 'label' => 'Έγκριση', 'confirm' => false, 'blank' => false );
                $actions[] = array( 'do' => 'reject', 'label' => 'Απόρριψη', 'confirm' => true, 'blank' => false );
                break;
            case CC_Returns_Rules::ST_APPROVED:
                // Εγκρίθηκε αλλά η δημιουργία δεν ξεκίνησε (π.χ. το request διακόπηκε)
                $actions[] = array( 'do' => 'create', 'label' => 'Δημιουργία αποστολής', 'confirm' => false, 'blank' => false );
                $actions[] = array( 'do' => 'reject', 'label' => 'Απόρριψη', 'confirm' => true, 'blank' => false );
                break;
            case CC_Returns_Rules::ST_FAILED:
                $actions[] = array( 'do' => 'retry', 'label' => 'Επανάληψη δημιουργίας', 'confirm' => false, 'blank' => false );
                $actions[] = array( 'do' => 'reject', 'label' => 'Απόρριψη', 'confirm' => true, 'blank' => false );
                break;
            case CC_Returns_Rules::ST_VERIFY:
                $actions[] = array( 'do' => 'verify', 'label' => 'Έλεγχος ύπαρξης αποστολής', 'confirm' => false, 'blank' => false );
                break;
            case CC_Returns_Rules::ST_CREATED:
                $actions[] = array( 'do' => 'refresh', 'label' => 'Ενημέρωση tracking', 'confirm' => false, 'blank' => false );
                if ( '' !== trim( (string) $row['return_awb'] ) ) {
                    $actions[] = array( 'do' => 'voucher', 'label' => 'Voucher PDF', 'confirm' => false, 'blank' => true );
                }
                break;
        }
        return $actions;
    }

    public static function action_url( $id, $do ) {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=' . self::ACTION . '&id=' . (int) $id . '&do=' . rawurlencode( $do ) ),
            'cc_return_action_' . (int) $id
        );
    }

    /** <a> ενέργειας· $button = true → class "button" (meta box). */
    public static function action_link( array $row, array $action, $button ) {
        return sprintf(
            '<a href="%s" class="%s"%s%s>%s</a>',
            esc_url( self::action_url( $row['id'], $action['do'] ) ),
            $button ? 'button' : 'cc-return-action',
            ! empty( $action['blank'] ) ? ' target="_blank" rel="noopener"' : '',
            ! empty( $action['confirm'] ) ? ' onclick="' . self::CONFIRM_REJECT . '"' : '',
            esc_html( $action['label'] )
        );
    }

    public static function customer_name( array $row ) {
        $name = isset( $row['shipper_details']['name'] ) ? trim( (string) $row['shipper_details']['name'] ) : '';
        if ( '' !== $name ) {
            return $name;
        }
        $order = wc_get_order( (int) $row['order_id'] );
        return $order ? trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) : '';
    }

    public static function format_date( $mysql ) {
        if ( empty( $mysql ) || 0 === strpos( (string) $mysql, '0000' ) ) {
            return '';
        }
        return (string) mysql2date( 'd/m/Y H:i', $mysql );
    }

    // ── Meta box ─────────────────────────────────────────────────────────────

    private static function order_id_of( $post_or_order ) {
        if ( $post_or_order instanceof WC_Order ) {
            return (int) $post_or_order->get_id();
        }
        if ( is_object( $post_or_order ) && isset( $post_or_order->ID ) ) {
            return (int) $post_or_order->ID;
        }
        return 0;
    }

    public function add_meta_box_legacy( $post = null ) {
        $this->add_meta_box( $post, 'shop_order' );
    }

    public function add_meta_box_hpos( $order = null ) {
        $this->add_meta_box( $order, wc_get_page_screen_id( 'shop-order' ) );
    }

    private function add_meta_box( $post_or_order, $screen ) {
        $order_id = self::order_id_of( $post_or_order );
        if ( ! $order_id ) {
            return;
        }
        $order = wc_get_order( $order_id );
        $awb   = $order ? trim( (string) $order->get_meta( '_cc_voucher_number' ) ) : '';
        if ( '' === $awb && ! CC_Returns_Repo::for_order( $order_id ) ) {
            return;
        }
        add_meta_box(
            'cc-returns',
            '↩️ Επιστροφές Courier Center',
            array( $this, 'render_meta_box' ),
            $screen,
            'normal',
            'default'
        );
    }

    public function render_meta_box( $post_or_order ) {
        $this->service->release_stale_locks(); // πριν διαβαστούν οι γραμμές (βλ. render_page)
        $order_id = self::order_id_of( $post_or_order );
        $order    = wc_get_order( $order_id );
        $rows     = $order_id ? CC_Returns_Repo::for_order( $order_id ) : array();
        ?>
        <style>
            .cc-ret-box { border: 1px solid #dcdcde; border-radius: 3px; padding: 10px 12px; margin: 0 0 12px; background: #fff; }
            .cc-ret-box h4 { margin: 0 0 8px; }
            .cc-ret-box table { width: 100%; border-collapse: collapse; }
            .cc-ret-box th { text-align: left; width: 220px; padding: 3px 8px 3px 0; vertical-align: top; font-weight: 600; }
            .cc-ret-box td { padding: 3px 0; vertical-align: top; }
            .cc-ret-box .cc-ret-actions { margin-top: 8px; }
            .cc-ret-box .cc-ret-actions .button { margin-right: 6px; }
            .cc-ret-box .notice { margin: 8px 0 0; }
        </style>
        <?php
        $existing = $order ? trim( (string) $order->get_meta( '_cc_return_awb' ) ) : '';
        if ( '' !== $existing ) {
            echo '<div class="notice notice-warning inline"><p>Η αρχική αποστολή έχει ήδη επιστροφικό AWB ' . esc_html( $existing ) . '</p></div>';
        }
        foreach ( $rows as $row ) {
            $this->render_request( $row );
        }
        if ( $order && CC_Returns_Service::has_active_awb( $order ) ) {
            if ( ! $rows ) {
                echo '<p>' . esc_html( 'Δεν υπάρχουν επιστροφές για αυτή την παραγγελία.' ) . '</p>';
            }
            echo '<p><a class="button" href="' . esc_url( CC_Returns_Admin_New::url( $order_id ) ) . '">' . esc_html( '+ Νέα επιστροφή' ) . '</a></p>';
        }
    }

    private function render_request( array $row ) {
        $lines = array(
            '#Αιτήματος'              => '#' . (int) $row['id'],
            'Από'                     => ( isset( $row['origin'] ) && 'shop' === $row['origin'] ) ? 'Κατάστημα' : 'Πελάτης',
            'Πελάτης'                 => self::customer_name( $row ),
            'Μέθοδος'                 => CC_Returns_Service::method_label( $row['method'] ),
            'Κατάσταση'               => CC_Returns_Service::status_label( $row['status'] ),
            'Αρχικό AWB'              => $row['original_awb'],
            'AWB επιστροφής'          => $row['return_awb'],
            'Αρχικός κωδικός'         => $row['original_shipper_code'],
            'Λογαριασμός επιστροφών'  => $row['return_account_code'],
            'Tracking'                => $row['display_status'],
            'Δημιουργία'              => self::format_date( $row['created_at'] ),
            'Τελ. ενημέρωση tracking' => self::format_date( $row['last_tracking_at'] ),
        );

        if ( CC_Returns_Rules::METHOD_DROPOFF === $row['method'] ) {
            $s = CC_Returns_Rules::DROPOFF_SHIPPER;
            $lines['Αποστολέας Drop Off'] = $s['ZipCode'] . ' / ' . $s['Area'] . ' / ' . $s['City'];
        } elseif ( CC_Returns_Rules::METHOD_BOXNOW === $row['method'] ) {
            $sizes = CC_Returns_Rules::LOCKER_SIZES;
            $lines['Μέγεθος locker']          = isset( $sizes[ $row['locker_size'] ] ) ? $sizes[ $row['locker_size'] ]['label'] : $row['locker_size'];
            $lines['ReturnShipmentNumber']    = $row['return_shipment_number'];
            $lines['InboundShipmentNumber']   = $row['inbound_shipment_number'];
            $lines['ShipmentMasterId']        = $row['shipment_master_id'];
        } elseif ( CC_Returns_Rules::METHOD_PICKUP === $row['method'] ) {
            $sd = is_array( $row['shipper_details'] ) ? $row['shipper_details'] : array();
            $lines['Παραλαβή']             = CC_Returns_Service::pickup_label( $row );
            $lines['Διεύθυνση παραλαβής']  = trim( ( isset( $sd['address'] ) ? $sd['address'] : '' ) . ', ' . ( isset( $sd['city'] ) ? $sd['city'] : '' ) . ' ' . ( isset( $sd['postcode'] ) ? $sd['postcode'] : '' ), ' ,' );
            $lines['Οδηγίες courier']      = isset( $sd['courier_notes'] ) ? (string) $sd['courier_notes'] : '';
        }

        $items = array();
        foreach ( (array) $row['items'] as $item ) {
            $name    = isset( $item['name'] ) && '' !== $item['name'] ? $item['name'] : '#' . ( isset( $item['item_id'] ) ? (int) $item['item_id'] : 0 );
            $items[] = $name . ' × ' . ( isset( $item['qty'] ) ? (int) $item['qty'] : 0 );
        }

        echo '<div class="cc-ret-box"><h4>Αίτημα #' . (int) $row['id'] . '</h4><table>';
        foreach ( $lines as $label => $value ) {
            if ( '' === trim( (string) $value ) ) {
                continue;
            }
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
        }
        if ( $items ) {
            echo '<tr><th>Τεμάχια</th><td>' . implode( '<br>', array_map( 'esc_html', $items ) ) . '</td></tr>';
        }
        if ( '' !== trim( (string) $row['reason'] ) ) {
            echo '<tr><th>Αιτιολογία</th><td>' . nl2br( esc_html( (string) $row['reason'] ) ) . '</td></tr>';
        }
        echo '</table>';

        if ( '' !== trim( (string) $row['last_error'] ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html( (string) $row['last_error'] ) . '</p></div>';
        }

        $actions = self::actions_for( $row );
        if ( $actions ) {
            echo '<p class="cc-ret-actions">';
            foreach ( $actions as $a ) {
                echo self::action_link( $row, $a, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in action_link
            }
            echo '</p>';
        }
        echo '</div>';
    }

    // ── Handler ──────────────────────────────────────────────────────────────

    public function handle_action() {
        $id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
        $do = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : '';

        check_admin_referer( 'cc_return_action_' . $id );
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( 'Δεν έχετε δικαίωμα να εκτελέσετε αυτή την ενέργεια.', 'Μη εξουσιοδοτημένη πρόσβαση', array( 'response' => 403 ) );
        }
        $user_id = get_current_user_id();

        if ( 'voucher' === $do ) {
            $voucher = $this->build_voucher( $id );
            if ( ! is_wp_error( $voucher ) ) {
                $this->stream_pdf( $voucher );
                return;
            }
            $result = array( 'type' => 'error', 'message' => $voucher->get_error_message() );
        } else {
            $result = $this->process_action( $do, $id, $user_id );
        }

        set_transient( 'cc_returns_notice_' . $user_id, $result, self::NOTICE_TTL );
        $back = wp_get_referer();
        if ( wp_safe_redirect( $back ? $back : self::page_url() ) ) {
            exit;
        }
    }

    /**
     * Εκτελεί μια ενέργεια μέσω της υπηρεσίας.
     *
     * @return array( 'type' => 'success'|'error'|'warning', 'message' => string )
     */
    public function process_action( $do, $id, $user_id ) {
        $id  = (int) $id;
        $row = CC_Returns_Repo::get( $id );
        if ( ! $row ) {
            return self::result( 'error', 'Το αίτημα επιστροφής δεν βρέθηκε.' );
        }

        switch ( $do ) {
            case 'approve':
                $res = $this->service->approve( $id, $user_id );
                if ( is_wp_error( $res ) ) {
                    if ( 'cc_return_not_pending' === $res->get_error_code() ) {
                        return self::result( 'error', $res->get_error_message() );
                    }
                    // Η έγκριση έγινε· απέτυχε η δημιουργία της αποστολής
                    return self::result( 'warning', sprintf( 'Το αίτημα #%d εγκρίθηκε, αλλά η δημιουργία της αποστολής δεν ολοκληρώθηκε: %s', $id, $res->get_error_message() ) );
                }
                $msg = sprintf( 'Το αίτημα #%d εγκρίθηκε.', $id ) . self::awb_suffix( $res );
                return self::result( self::has_warning( $res ) ? 'warning' : 'success', $msg . self::warning_suffix( $res ) );

            case 'reject':
                $res = $this->service->reject( $id, $user_id );
                return is_wp_error( $res )
                    ? self::result( 'error', $res->get_error_message() )
                    : self::result( 'success', sprintf( 'Το αίτημα #%d απορρίφθηκε.', $id ) );

            case 'create': // approved που δεν έφτασε σε δημιουργία
            case 'retry':  // creation_failed
                $res = $this->service->create( $id );
                if ( is_wp_error( $res ) ) {
                    return self::result( 'error', $res->get_error_message() );
                }
                return self::result( self::has_warning( $res ) ? 'warning' : 'success', sprintf( 'Η αποστολή επιστροφής του αιτήματος #%d δημιουργήθηκε.', $id ) . self::awb_suffix( $res ) . self::warning_suffix( $res ) );

            case 'verify':
                $res = $this->service->verify( $id );
                if ( is_wp_error( $res ) ) {
                    return self::result( 'error', $res->get_error_message() );
                }
                return self::result( self::has_warning( $res ) ? 'warning' : 'success', sprintf( 'Η αποστολή του αιτήματος #%d βρέθηκε και καταχωρήθηκε.', $id ) . self::awb_suffix( $res ) . self::warning_suffix( $res ) );

            case 'refresh':
                $res = $this->service->refresh_tracking( $id );
                if ( is_wp_error( $res ) ) {
                    return self::result( 'error', $res->get_error_message() );
                }
                $status = '' !== trim( (string) $res['display_status'] ) ? ': ' . $res['display_status'] : '.';
                return self::result( 'success', sprintf( 'Το tracking του αιτήματος #%d ενημερώθηκε%s', $id, $status ) );
        }

        return self::result( 'error', 'Μη υποστηριζόμενη ενέργεια.' );
    }

    private static function result( $type, $message ) {
        return array( 'type' => $type, 'message' => $message );
    }

    private static function awb_suffix( $row ) {
        return ( is_array( $row ) && '' !== trim( (string) $row['return_awb'] ) ) ? ' Αριθμός αποστολής επιστροφής: ' . $row['return_awb'] . '.' : '';
    }

    private static function has_warning( $row ) {
        return is_array( $row ) && '' !== trim( (string) $row['last_error'] );
    }

    private static function warning_suffix( $row ) {
        return self::has_warning( $row ) ? ' Προσοχή: ' . $row['last_error'] : '';
    }

    // ── Voucher ──────────────────────────────────────────────────────────────

    /**
     * Το PDF του voucher επιστροφής (ίδια πρότυπα εκτύπωσης με τα κανονικά voucher).
     *
     * @return array|WP_Error array( 'pdf' => bytes, 'filename' => string )
     */
    public function build_voucher( $id ) {
        $row = CC_Returns_Repo::get( (int) $id );
        if ( ! $row || CC_Returns_Rules::ST_CREATED !== $row['status'] || '' === trim( (string) $row['return_awb'] ) ) {
            return new WP_Error( 'cc_return_no_voucher', 'Το αίτημα δεν έχει αποστολή επιστροφής για εκτύπωση.' );
        }

        if ( CC_Returns_Rules::METHOD_BOXNOW === $row['method'] ) {
            $raw_template = get_option( 'cc_wc_print_template_boxnow', 'singlepdf_100x150_4up' );
        } else {
            $raw_template = get_option( 'cc_wc_print_template', 'pdf' );
        }
        // singlepdf_100x150_4up είναι custom: στέλνουμε singlepdf_100x150 και μετά arrange_4up()
        $use_4up  = ( 'singlepdf_100x150_4up' === $raw_template );
        $template = $use_4up ? 'singlepdf_100x150' : $raw_template;

        $pdf = $this->api->get_voucher_pdf( $row['return_awb'], $template );
        if ( is_wp_error( $pdf ) ) {
            return new WP_Error( 'cc_return_voucher_failed', 'Σφάλμα λήψης voucher: ' . $pdf->get_error_message() );
        }

        if ( $use_4up ) {
            $arranged = CC_PDF_Scaler::arrange_4up( $pdf );
            if ( ! is_wp_error( $arranged ) ) {
                $pdf = $arranged;
            }
        }
        if ( in_array( $template, array( 'pdf', 'clean' ), true ) ) {
            $scaled = CC_PDF_Scaler::scale_pdf( $pdf, 0.95 );
            if ( ! is_wp_error( $scaled ) ) {
                $pdf = $scaled;
            }
        }

        return array(
            'pdf'      => $pdf,
            'filename' => 'return-' . sanitize_file_name( $row['return_awb'] ) . '.pdf',
        );
    }

    private function stream_pdf( array $voucher ) {
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . $voucher['filename'] . '"' );
        header( 'Content-Length: ' . strlen( $voucher['pdf'] ) );
        echo $voucher['pdf']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF bytes
        exit;
    }

    // ── Notices ──────────────────────────────────────────────────────────────

    public function show_notice() {
        $key    = 'cc_returns_notice_' . get_current_user_id();
        $notice = get_transient( $key );
        if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
            return;
        }
        delete_transient( $key );
        $type = in_array( $notice['type'], array( 'success', 'error', 'warning' ), true ) ? $notice['type'] : 'info';
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
    }
}
