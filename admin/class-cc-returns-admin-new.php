<?php
/**
 * Επιστροφές: «Νέα επιστροφή» από το κατάστημα (Courier Center → Επιστροφές, tab=new&order_id=N).
 *
 * - Φόρμα για μία παραγγελία με AWB: προϊόντα/ποσότητες, μέθοδος (και οι τρεις, ανεξάρτητα από τις
 *   ρυθμίσεις του πελάτη), μέγεθος BOX NOW, στοιχεία Pickup, όνομα/τηλέφωνα, αιτιολογία, «Αποστολή email».
 * - Υποβολή σε admin-post.php?action=cc_return_new (nonce ανά παραγγελία, edit_shop_orders) →
 *   CC_Returns_Service::shop_create() → redirect στην παραγγελία με admin notice.
 * - Σφάλμα επικύρωσης → πίσω στη φόρμα με τις τιμές (transient ανά χρήστη).
 * - Ίδιο cc-returns.js με τη φόρμα του πελάτη (id="cc-return-form").
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Admin_New {

    const ACTION     = 'cc_return_new';
    const POSTED_TTL = 300;

    /** @var CC_Returns_Service */
    private $service;

    public function __construct( CC_Returns_Service $service ) {
        $this->service = $service;
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public static function url( $order_id ) {
        return CC_Returns_Admin::page_url( array( 'tab' => 'new', 'order_id' => (int) $order_id ) );
    }

    private static function posted_key( $user_id ) {
        return 'cc_returns_new_post_' . (int) $user_id;
    }

    // ── Φόρμα ────────────────────────────────────────────────────────────────

    /** @param WC_Order|false|null $order */
    public function render( $order ) {
        if ( ! $order instanceof WC_Order ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html( 'Η παραγγελία δεν βρέθηκε.' ) . '</p></div>';
            return;
        }
        $order_id = $order->get_id();
        echo '<h2>' . esc_html( sprintf( 'Νέα επιστροφή για την παραγγελία #%s', $order->get_order_number() ) ) . '</h2>';
        echo '<p><a href="' . esc_url( $order->get_edit_order_url() ) . '">← Πίσω στην παραγγελία</a></p>';

        $awb = trim( (string) $order->get_meta( '_cc_voucher_number' ) );
        if ( ! CC_Returns_Service::has_active_awb( $order ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html( 'Η παραγγελία δεν έχει ενεργή αποστολή Courier Center (AWB)· η επιστροφή δημιουργείται μόνο για παραγγελίες που στάλθηκαν με Courier Center και δεν ακυρώθηκαν.' ) . '</p></div>';
            return;
        }

        $user_id = get_current_user_id();
        $posted  = get_transient( self::posted_key( $user_id ) );
        if ( is_array( $posted ) ) {
            delete_transient( self::posted_key( $user_id ) );
            if ( (int) ( isset( $posted['order_id'] ) ? $posted['order_id'] : 0 ) !== $order_id ) {
                $posted = array();
            }
        } else {
            $posted = array();
        }
        $val = function ( $key, $default = '' ) use ( $posted ) {
            return array_key_exists( $key, $posted ) ? $posted[ $key ] : $default;
        };

        $consignee = ( new CC_Shipment_Builder( $order ) )->get_consignee_fields();
        $phone     = CC_Returns_Rules::normalize_phone( $consignee['phone'] );
        $is_land   = ! CC_Returns_Rules::is_gr_mobile( $phone ) && CC_Returns_Rules::is_gr_landline( $phone );
        $method    = (string) $val( 'method', CC_Returns_Rules::METHOD_DROPOFF );
        $size      = (string) $val( 'locker_size', '' );
        $qtys      = (array) $val( 'qty', array() );
        $uid       = (string) $val( 'request_uid', '' );
        if ( 1 !== preg_match( '/^[0-9a-f]{32}$/', $uid ) ) {
            $uid = bin2hex( random_bytes( 16 ) );
        }
        $notify    = array_key_exists( 'notify', $posted ) ? ! empty( $posted['notify'] ) : true;
        $available = $this->service->available_items( $order );

        echo '<p>Αρχική αποστολή: <strong>' . esc_html( $awb ) . '</strong>. Η αποστολή επιστροφής δημιουργείται αμέσως, χωρίς έγκριση και χωρίς προθεσμία.</p>';

        echo '<form id="cc-return-form" class="cc-return-form cc-return-admin-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
        wp_nonce_field( 'cc_return_new_' . $order_id );
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr( $order_id ) . '">';
        echo '<input type="hidden" name="request_uid" value="' . esc_attr( $uid ) . '">';

        echo '<table class="widefat striped cc-return-items" style="max-width:720px"><thead><tr><th>Προϊόν</th><th>Παραγγέλθηκαν</th><th>Διαθέσιμα</th><th>Τεμάχια προς επιστροφή</th></tr></thead><tbody>';
        foreach ( $available as $item_id => $line ) {
            $qty = isset( $qtys[ $item_id ] ) ? max( 0, (int) $qtys[ $item_id ] ) : 0;
            echo '<tr><td>' . esc_html( $line['name'] ) . '</td><td>' . esc_html( $line['ordered'] ) . '</td><td>' . esc_html( $line['available'] ) . '</td><td>';
            echo '<input type="number" name="qty[' . esc_attr( $item_id ) . ']" min="0" max="' . esc_attr( $line['available'] ) . '" step="1" value="' . esc_attr( $qty ) . '"' . ( $line['available'] > 0 ? '' : ' disabled' ) . '>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<fieldset class="cc-return-methods"><legend><strong>Τρόπος επιστροφής</strong></legend>';
        foreach ( CC_Returns_Rules::METHODS_ORDER as $m ) {
            echo '<p><label><input type="radio" name="method" value="' . esc_attr( $m ) . '"' . ( $m === $method ? ' checked' : '' ) . '> ' . esc_html( CC_Returns_Service::method_label( $m ) ) . '</label></p>';
        }
        echo '</fieldset>';

        $boxnow_on = CC_Returns_Rules::METHOD_BOXNOW === $method;
        echo '<fieldset class="cc-return-boxnow"' . ( $boxnow_on ? '' : ' style="display:none"' ) . '><legend><strong>Μέγεθος BOX NOW locker</strong></legend>';
        foreach ( CC_Returns_Rules::LOCKER_SIZES as $key => $info ) {
            echo '<p><label><input type="radio" name="locker_size" value="' . esc_attr( $key ) . '"' . ( $key === $size ? ' checked' : '' ) . '> '
                . esc_html( $info['label'] ) . ' (' . esc_html( $info['dims'] ) . ')</label></p>';
        }
        echo '</fieldset>';

        CC_Returns_Account::render_pickup_fieldset( array(
            'address'  => $val( 'pickup_address', $consignee['address'] ),
            'city'     => $val( 'pickup_city', $consignee['city'] ),
            'postcode' => $val( 'pickup_postcode', $consignee['postcode'] ),
            'date'     => $val( 'pickup_date', '' ),
            'from'     => $val( 'pickup_time_from', '' ),
            'to'       => $val( 'pickup_time_to', '' ),
            'notes'    => $val( 'courier_notes', '' ),
        ), CC_Returns_Rules::METHOD_PICKUP === $method );

        echo '<p class="form-row"><label for="cc-return-name">Ονοματεπώνυμο αποστολέα (πελάτη)</label><br>';
        echo '<input type="text" id="cc-return-name" name="name" class="regular-text" value="' . esc_attr( $val( 'name', $consignee['name'] ) ) . '" maxlength="100"></p>';
        echo '<p class="form-row"><label for="cc-return-mobile"><span class="cc-return-mobile-label" data-base="Κινητό" data-boxnow="Κινητό (υποχρεωτικό για BOX NOW)">'
            . ( $boxnow_on ? 'Κινητό (υποχρεωτικό για BOX NOW)' : 'Κινητό' ) . '</span></label><br>';
        echo '<input type="tel" id="cc-return-mobile" name="mobile" value="' . esc_attr( $val( 'mobile', $is_land ? '' : $phone ) ) . '"></p>';
        echo '<p class="form-row"><label for="cc-return-landline">Σταθερό</label><br>';
        echo '<input type="tel" id="cc-return-landline" name="landline" value="' . esc_attr( $val( 'landline', $is_land ? $phone : '' ) ) . '"></p>';
        echo '<p class="form-row"><label for="cc-return-reason">Αιτιολογία (προαιρετικό, δεν εμφανίζεται στον πελάτη)</label><br>';
        echo '<textarea id="cc-return-reason" name="reason" rows="3" cols="60" maxlength="' . esc_attr( CC_Returns_Service::REASON_MAX ) . '">' . esc_textarea( $val( 'reason', '' ) ) . '</textarea></p>';
        echo '<p><label><input type="checkbox" name="notify" value="1"' . ( $notify ? ' checked' : '' ) . '> Αποστολή email στον πελάτη (' . esc_html( $order->get_billing_email() ) . ')</label></p>';

        echo '<p class="cc-return-error notice notice-error inline" role="alert" style="display:none"></p>';
        echo '<p><button type="submit" class="button button-primary cc-return-submit">Δημιουργία επιστροφής</button></p>';
        echo '</form>';
    }

    // ── Υποβολή ──────────────────────────────────────────────────────────────

    public function handle() {
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        check_admin_referer( 'cc_return_new_' . $order_id );
        if ( ! current_user_can( CC_Returns_Admin::CAPABILITY ) ) {
            wp_die( 'Δεν έχετε δικαίωμα να εκτελέσετε αυτή την ενέργεια.', 'Μη εξουσιοδοτημένη πρόσβαση', array( 'response' => 403 ) );
        }
        $user_id = get_current_user_id();
        $result  = $this->process( wp_unslash( $_POST ), $user_id );
        set_transient( 'cc_returns_notice_' . $user_id, array( 'type' => $result['type'], 'message' => $result['message'] ), CC_Returns_Admin::NOTICE_TTL );
        if ( wp_safe_redirect( $result['redirect'] ) ) {
            exit;
        }
    }

    /**
     * @param array $post Ήδη unslashed.
     * @return array type (success|warning|error), message, redirect
     */
    public function process( array $post, $user_id ) {
        $order_id = isset( $post['order_id'] ) ? absint( $post['order_id'] ) : 0;
        $order    = $order_id ? wc_get_order( $order_id ) : false;
        if ( ! $order instanceof WC_Order ) {
            return array( 'type' => 'error', 'message' => 'Η παραγγελία δεν βρέθηκε.', 'redirect' => CC_Returns_Admin::page_url() );
        }

        $qty = array();
        if ( isset( $post['qty'] ) && is_array( $post['qty'] ) ) {
            foreach ( $post['qty'] as $item_id => $q ) {
                if ( is_scalar( $q ) ) {
                    $qty[ (int) $item_id ] = max( 0, (int) $q );
                }
            }
        }
        $text = function ( $key, $multiline = false ) use ( $post ) {
            if ( ! isset( $post[ $key ] ) || ! is_scalar( $post[ $key ] ) ) {
                return '';
            }
            return $multiline ? sanitize_textarea_field( (string) $post[ $key ] ) : sanitize_text_field( (string) $post[ $key ] );
        };
        $data = array(
            'request_uid'      => $text( 'request_uid' ),
            'method'           => $text( 'method' ),
            'items'            => array_filter( $qty ),
            'reason'           => $text( 'reason', true ),
            'name'             => $text( 'name' ),
            'mobile'           => $text( 'mobile' ),
            'landline'         => $text( 'landline' ),
            'locker_size'      => $text( 'locker_size' ),
            'pickup_address'   => $text( 'pickup_address' ),
            'pickup_city'      => $text( 'pickup_city' ),
            'pickup_postcode'  => $text( 'pickup_postcode' ),
            'pickup_date'      => $text( 'pickup_date' ),
            'pickup_time_from' => $text( 'pickup_time_from' ),
            'pickup_time_to'   => $text( 'pickup_time_to' ),
            'courier_notes'    => $text( 'courier_notes', true ),
            'notify'           => $text( 'notify' ),
        );

        $id = $this->service->shop_create( $order, (int) $user_id, $data );
        if ( is_wp_error( $id ) ) {
            if ( 'cc_return_forbidden' === $id->get_error_code() ) {
                unset( $data['request_uid'] ); // ανήκει σε άλλη παραγγελία/πελάτη → νέο uid στη φόρμα
            }
            set_transient( self::posted_key( $user_id ), array_merge( $data, array( 'qty' => $qty, 'order_id' => $order_id ) ), self::POSTED_TTL );
            return array( 'type' => 'error', 'message' => $id->get_error_message(), 'redirect' => self::url( $order_id ) );
        }

        $row = CC_Returns_Repo::get( $id );
        if ( $row && CC_Returns_Rules::ST_CREATED === $row['status'] ) {
            $msg = sprintf( 'Η επιστροφή #%d δημιουργήθηκε. Αριθμός αποστολής επιστροφής: %s.', $id, $row['return_awb'] );
            if ( ! empty( $row['notify_customer'] ) ) {
                $msg .= ' Ο πελάτης ενημερώνεται με email.';
            }
            return array( 'type' => 'success', 'message' => $msg, 'redirect' => $order->get_edit_order_url() );
        }
        $error = $row && '' !== trim( (string) $row['last_error'] ) ? $row['last_error'] : CC_Returns_Service::status_label( $row ? $row['status'] : '' );
        return array(
            'type'     => 'warning',
            'message'  => sprintf( 'Η επιστροφή #%d καταχωρήθηκε, αλλά η δημιουργία της αποστολής δεν ολοκληρώθηκε: %s', $id, $error ),
            'redirect' => $order->get_edit_order_url(),
        );
    }

    // ── Assets ───────────────────────────────────────────────────────────────

    public function enqueue_assets() {
        // phpcs:disable WordPress.Security.NonceVerification
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        // phpcs:enable
        if ( CC_Returns_Admin::PAGE !== $page || 'new' !== $tab ) {
            return;
        }
        wp_enqueue_style( 'cc-returns', CC_WC_PLUGIN_URL . 'assets/css/cc-returns.css', array(), CC_WC_VERSION );
        wp_enqueue_script( 'cc-returns', CC_WC_PLUGIN_URL . 'assets/js/cc-returns.js', array( 'jquery' ), CC_WC_VERSION, true );
        wp_localize_script( 'cc-returns', 'ccReturns', CC_Returns_Account::script_config() );
    }
}
