<?php
/**
 * Επιστροφές: οθόνη πελάτη στο My Account.
 *
 * - Κουμπί «Αίτημα Επιστροφής» στη λίστα παραγγελιών (μόνο για επιλέξιμες παραγγελίες).
 * - Ενότητα «Επιστροφές» στη σελίδα παραγγελίας: αιτήματα, οδηγίες, κωδικός BOX NOW και φόρμα.
 * - Υποβολή φόρμας στο template_redirect (nonce, ιδιοκτησία, CC_Returns_Service::submit) με PRG.
 *
 * Ο κωδικός BOX NOW εμφανίζεται ΜΟΝΟ στον ιδιοκτήτη της παραγγελίας (εδώ και στο email).
 * Δεν εμφανίζονται ποτέ σφάλματα του API στον πελάτη.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Account {

    const MSG_FORBIDDEN = 'Δεν έχετε δικαίωμα πρόσβασης σε αυτή την παραγγελία.';
    const MSG_EXPIRED   = 'Η φόρμα έληξε. Ανανεώστε τη σελίδα και δοκιμάστε ξανά.';
    const MSG_RECEIVED  = 'Το αίτημα επιστροφής σας καταχωρήθηκε και θα εξεταστεί από το κατάστημα.';
    const MSG_PENDING   = 'Το αίτημα επιστροφής σας καταχωρήθηκε. Θα ενημερωθείτε μόλις δημιουργηθεί η αποστολή επιστροφής.';
    const MSG_NO_ITEMS  = 'Επιλέξτε τουλάχιστον ένα προϊόν για επιστροφή.';
    const MSG_NO_LEFT   = 'Όλα τα προϊόντα της παραγγελίας περιλαμβάνονται ήδη σε αιτήματα επιστροφής.';

    /** @var CC_Returns_Service|null */
    private $service;

    /** @var array|null Οι τιμές της τελευταίας υποβολής (για επανασυμπλήρωση της φόρμας μετά από σφάλμα). */
    private $posted = null;

    public function __construct( ?CC_Returns_Service $service = null ) {
        $this->service = $service;

        add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_orders_actions' ), 10, 2 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_order_section' ), 10, 1 );
        add_action( 'template_redirect', array( $this, 'maybe_handle_submission' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /** Lazy: το CC_API δεν δημιουργείται σε σελίδες που δεν το χρειάζονται. */
    private function service() {
        if ( null === $this->service ) {
            $this->service = new CC_Returns_Service();
        }
        return $this->service;
    }

    // ── Κοινοί έλεγχοι ───────────────────────────────────────────────────────

    private function is_account_page() {
        return (bool) apply_filters( 'cc_returns_is_account_page', function_exists( 'is_account_page' ) && is_account_page() );
    }

    private function viewer_owns( WC_Order $order ) {
        $uid = get_current_user_id();
        return $uid > 0 && (int) $order->get_customer_id() === $uid;
    }

    private function any_available( WC_Order $order ) {
        foreach ( $this->service()->available_items( $order ) as $line ) {
            if ( $line['available'] > 0 ) {
                return true;
            }
        }
        return false;
    }

    private function can_request( WC_Order $order ) {
        return CC_Returns_Settings::enabled()
            && $this->viewer_owns( $order )
            && true === $this->service()->check_eligibility( $order, get_current_user_id() )
            && $this->any_available( $order );
    }

    private function form_url( WC_Order $order ) {
        return add_query_arg( 'cc-return', '1', $order->get_view_order_url() );
    }

    // ── Λίστα παραγγελιών ────────────────────────────────────────────────────

    public function my_orders_actions( $actions, $order ) {
        if ( ! $order instanceof WC_Order || ! is_array( $actions ) ) {
            return $actions;
        }
        if ( $this->can_request( $order ) ) {
            $actions['cc_return'] = array(
                'url'  => $this->form_url( $order ),
                'name' => 'Αίτημα Επιστροφής',
            );
        }
        return $actions;
    }

    // ── Ενότητα «Επιστροφές» ─────────────────────────────────────────────────

    public function render_order_section( $order ) {
        if ( ! $order instanceof WC_Order || ! CC_Returns_Settings::enabled() ) {
            return;
        }
        if ( ! $this->is_account_page() || ! $this->viewer_owns( $order ) ) {
            return;
        }

        $user_id   = get_current_user_id();
        $requests  = CC_Returns_Repo::for_order( $order->get_id() );
        $eligible  = $this->service()->check_eligibility( $order, $user_id );
        $wants_form = isset( $_GET['cc-return'] ) && '' !== (string) $_GET['cc-return']; // phpcs:ignore WordPress.Security.NonceVerification

        $form_error = '';
        $show_form  = false;
        if ( $wants_form ) {
            if ( is_wp_error( $eligible ) ) {
                $form_error = $eligible->get_error_message();
            } elseif ( ! $this->any_available( $order ) ) {
                $form_error = self::MSG_NO_LEFT;
            } else {
                $show_form = true;
            }
        }

        $can_start = ! $show_form && ! $wants_form && true === $eligible && $this->any_available( $order );

        if ( ! $requests && ! $show_form && '' === $form_error && ! $can_start ) {
            return;
        }

        echo '<section class="cc-returns woocommerce-order-returns">';
        echo '<h2 class="woocommerce-order-details__title">Επιστροφές</h2>';

        if ( '' !== $form_error ) {
            echo '<p class="cc-return-note">' . esc_html( $form_error ) . '</p>';
        }

        foreach ( $requests as $req ) {
            $this->render_request( $order, $req );
        }

        if ( $can_start ) {
            echo '<p><a class="button cc-return-start" href="' . esc_url( $this->form_url( $order ) ) . '">Αίτημα Επιστροφής</a></p>';
        }
        if ( $show_form ) {
            $this->render_form( $order );
        }
        echo '</section>';
    }

    private function creation_notice( array $row ) {
        $lines = array();
        foreach ( CC_Returns_Service::creation_parts( $row ) as $part ) {
            $lines[] = esc_html( $part[0] . $part[1] );
        }
        return implode( '<br>', $lines );
    }

    private function render_request( WC_Order $order, array $req ) {
        $names = array();
        foreach ( $req['items'] as $line ) {
            $iid     = isset( $line['item_id'] ) ? (int) $line['item_id'] : 0;
            $item    = $order->get_item( $iid );
            $names[] = ( $item ? $item->get_name() : 'Προϊόν' ) . ' × ' . ( isset( $line['qty'] ) ? (int) $line['qty'] : 0 );
        }
        $created = ! empty( $req['created_at'] ) ? mysql2date( get_option( 'date_format' ), $req['created_at'] ) : '';

        echo '<div class="cc-return-request">';
        echo '<ul class="cc-return-meta">';
        if ( '' !== $created ) {
            echo '<li>Ημερομηνία: ' . esc_html( $created ) . '</li>';
        }
        echo '<li>Είδη: ' . esc_html( implode( ', ', $names ) ) . '</li>';
        echo '<li>Μέθοδος: ' . esc_html( CC_Returns_Service::method_label( $req['method'] ) ) . '</li>';
        if ( CC_Returns_Rules::METHOD_PICKUP === $req['method'] && '' !== CC_Returns_Service::pickup_label( $req ) && CC_Returns_Rules::ST_CREATED !== $req['status'] ) {
            echo '<li>Παραλαβή: ' . esc_html( CC_Returns_Service::pickup_label( $req ) ) . '</li>';
        }
        echo '<li>Κατάσταση αιτήματος: ' . esc_html( CC_Returns_Service::customer_status_label( $req['status'] ) ) . '</li>';
        echo '</ul>';

        if ( CC_Returns_Rules::ST_CREATED === $req['status'] ) {
            echo '<p class="cc-return-created">';
            $lines = array();
            foreach ( CC_Returns_Service::creation_parts( $req ) as $part ) {
                $html = esc_html( $part[0] );
                if ( $part[2] ) {
                    $html .= '<code class="cc-return-code">' . esc_html( $part[1] ) . '</code> '
                        . '<button type="button" class="button cc-return-copy" data-copy="' . esc_attr( $part[1] ) . '">Αντιγραφή</button>';
                } else {
                    $html .= esc_html( $part[1] );
                }
                $lines[] = $html;
            }
            echo implode( '<br>', $lines ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped παραπάνω
            echo '</p>';
            $instructions = CC_Returns_Settings::instructions_for( $req['method'] );
            echo '<div class="cc-return-instructions">' . wpautop( esc_html( $instructions ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
        } elseif ( '' !== (string) $req['return_awb'] ) {
            echo '<p class="cc-return-awb">Αριθμός αποστολής επιστροφής: ' . esc_html( $req['return_awb'] ) . '</p>';
        }

        if ( self::has_shipment_status( $req ) ) {
            echo '<p class="cc-return-shipment-status">Κατάσταση αποστολής: ' . esc_html( trim( (string) $req['display_status'] ) ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Υπάρχει πραγματικό status αποστολής για τον πελάτη; Μια φρέσκια επιστροφή έχει ShipmentStatus 0 και
     * ShipmentStatusDesc «None» — τότε η γραμμή «Κατάσταση αποστολής» δεν εμφανίζεται.
     */
    private static function has_shipment_status( array $req ) {
        $display = trim( (string) $req['display_status'] );
        if ( '' === $display || 0 === strcasecmp( $display, 'None' ) ) {
            return false;
        }
        return '0' !== trim( (string) $req['shipment_status'] );
    }

    // ── Φόρμα ────────────────────────────────────────────────────────────────

    private function posted( $key, $default = '' ) {
        if ( is_array( $this->posted ) && array_key_exists( $key, $this->posted ) ) {
            return $this->posted[ $key ];
        }
        return $default;
    }

    private function render_form( WC_Order $order ) {
        $order_id  = $order->get_id();
        $available = $this->service()->available_items( $order );
        $enabled   = CC_Returns_Settings::enabled_methods();
        $method    = (string) $this->posted( 'method', CC_Returns_Rules::default_method( $enabled ) );
        if ( ! in_array( $method, $enabled, true ) ) {
            $method = CC_Returns_Rules::default_method( $enabled );
        }

        $consignee = ( new CC_Shipment_Builder( $order ) )->get_consignee_fields();
        $mobile    = '';
        $landline  = '';
        $phone     = CC_Returns_Rules::normalize_phone( $consignee['phone'] );
        if ( ! CC_Returns_Rules::is_gr_mobile( $phone ) && CC_Returns_Rules::is_gr_landline( $phone ) ) {
            $landline = $phone;
        } else {
            $mobile = $phone;
        }
        $name     = (string) $this->posted( 'name', $consignee['name'] );
        $mobile   = (string) $this->posted( 'mobile', $mobile );
        $landline = (string) $this->posted( 'landline', $landline );
        $reason   = (string) $this->posted( 'reason', '' );
        $size     = (string) $this->posted( 'locker_size', '' );
        $qtys     = (array) $this->posted( 'qty', array() );
        $dates   = CC_Returns_Rules::pickup_dates( CC_Returns_Service::now_local() );
        $p_addr  = (string) $this->posted( 'pickup_address', $consignee['address'] );
        $p_city  = (string) $this->posted( 'pickup_city', $consignee['city'] );
        $p_pc    = (string) $this->posted( 'pickup_postcode', $consignee['postcode'] );
        $p_date  = (string) $this->posted( 'pickup_date', '' );
        $p_date  = in_array( $p_date, $dates, true ) ? $p_date : $dates[0];
        $p_from  = (string) $this->posted( 'pickup_time_from', '' );
        $p_to    = (string) $this->posted( 'pickup_time_to', '' );
        $p_notes = (string) $this->posted( 'courier_notes', '' );
        $pickup_on = CC_Returns_Rules::METHOD_PICKUP === $method;

        $uid = (string) $this->posted( 'request_uid', '' );
        if ( 1 !== preg_match( '/^[0-9a-f]{32}$/', $uid ) ) {
            $uid = bin2hex( random_bytes( 16 ) );
        }

        $boxnow_on = CC_Returns_Rules::METHOD_BOXNOW === $method;

        echo '<form id="cc-return-form" class="cc-return-form" method="post" action="' . esc_url( $this->form_url( $order ) ) . '" novalidate>';
        wp_nonce_field( 'cc_return_submit_' . $order_id, '_wpnonce' );
        echo '<input type="hidden" name="cc_return_submit" value="1">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr( $order_id ) . '">';
        echo '<input type="hidden" name="request_uid" value="' . esc_attr( $uid ) . '">';

        echo '<h3>Αίτημα επιστροφής</h3>';
        echo '<table class="shop_table cc-return-items"><thead><tr><th>Προϊόν</th><th>Παραγγέλθηκαν</th><th>Διαθέσιμα</th><th>Τεμάχια προς επιστροφή</th></tr></thead><tbody>';
        foreach ( $available as $item_id => $line ) {
            $qty = isset( $qtys[ $item_id ] ) ? max( 0, (int) $qtys[ $item_id ] ) : 0;
            echo '<tr><td>' . esc_html( $line['name'] ) . '</td><td>' . esc_html( $line['ordered'] ) . '</td><td>' . esc_html( $line['available'] ) . '</td><td>';
            echo '<input type="number" name="qty[' . esc_attr( $item_id ) . ']" min="0" max="' . esc_attr( $line['available'] ) . '" step="1" value="' . esc_attr( $qty ) . '"' . ( $line['available'] > 0 ? '' : ' disabled' ) . '>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<fieldset class="cc-return-methods"><legend>Τρόπος επιστροφής</legend>';
        foreach ( $enabled as $m ) {
            echo '<p><label><input type="radio" name="method" value="' . esc_attr( $m ) . '"' . ( $m === $method ? ' checked' : '' ) . '> ' . esc_html( CC_Returns_Service::method_label( $m ) ) . '</label></p>';
        }
        echo '</fieldset>';

        if ( in_array( CC_Returns_Rules::METHOD_BOXNOW, $enabled, true ) ) {
            echo '<fieldset class="cc-return-boxnow"' . ( $boxnow_on ? '' : ' style="display:none"' ) . '><legend>Μέγεθος BOX NOW locker</legend>';
            foreach ( CC_Returns_Rules::LOCKER_SIZES as $key => $info ) {
                echo '<p><label><input type="radio" name="locker_size" value="' . esc_attr( $key ) . '"' . ( $key === $size ? ' checked' : '' ) . '> '
                    . esc_html( $info['label'] ) . ' (' . esc_html( $info['dims'] ) . ')</label></p>';
            }
            echo '</fieldset>';
        }

        if ( in_array( CC_Returns_Rules::METHOD_PICKUP, $enabled, true ) ) {
            echo '<fieldset class="cc-return-pickup"' . ( $pickup_on ? '' : ' style="display:none"' ) . '><legend>Στοιχεία παραλαβής</legend>';
            echo '<p class="form-row"><label for="cc-return-pickup-address">Διεύθυνση παραλαβής</label>';
            echo '<input type="text" id="cc-return-pickup-address" name="pickup_address" value="' . esc_attr( $p_addr ) . '" maxlength="100"></p>';
            echo '<p class="form-row"><label for="cc-return-pickup-city">Πόλη</label>';
            echo '<input type="text" id="cc-return-pickup-city" name="pickup_city" value="' . esc_attr( $p_city ) . '" maxlength="60"></p>';
            echo '<p class="form-row"><label for="cc-return-pickup-postcode">ΤΚ</label>';
            echo '<input type="text" id="cc-return-pickup-postcode" name="pickup_postcode" value="' . esc_attr( $p_pc ) . '" maxlength="6" inputmode="numeric"></p>';

            echo '<p class="form-row"><label for="cc-return-pickup-date">Ημερομηνία παραλαβής</label><select id="cc-return-pickup-date" name="pickup_date">';
            foreach ( $dates as $d ) {
                echo '<option value="' . esc_attr( $d ) . '"' . ( $d === $p_date ? ' selected' : '' ) . '>' . esc_html( CC_Returns_Rules::pickup_date_label( $d ) ) . '</option>';
            }
            echo '</select></p>';

            echo '<p class="form-row cc-return-window"><span>Χρονικό διάστημα (προαιρετικό, τουλάχιστον 4 ώρες, 09:00–17:00)</span><br>';
            echo '<label for="cc-return-pickup-from">Από</label> <select id="cc-return-pickup-from" name="pickup_time_from"><option value="">—</option>';
            foreach ( CC_Returns_Rules::pickup_hours_from() as $h ) {
                echo '<option value="' . esc_attr( $h ) . '"' . ( $h === $p_from ? ' selected' : '' ) . '>' . esc_html( $h ) . '</option>';
            }
            echo '</select> <label for="cc-return-pickup-to">Έως</label> <select id="cc-return-pickup-to" name="pickup_time_to"><option value="">—</option>';
            foreach ( CC_Returns_Rules::pickup_hours_to() as $h ) {
                echo '<option value="' . esc_attr( $h ) . '"' . ( $h === $p_to ? ' selected' : '' ) . '>' . esc_html( $h ) . '</option>';
            }
            echo '</select></p>';

            echo '<p class="form-row"><label for="cc-return-courier-notes">Οδηγίες για τον courier (προαιρετικό)</label>';
            echo '<textarea id="cc-return-courier-notes" name="courier_notes" rows="2" maxlength="' . esc_attr( CC_Returns_Rules::COURIER_NOTES_MAX ) . '">' . esc_textarea( $p_notes ) . '</textarea></p>';
            echo '</fieldset>';
        }

        echo '<p class="form-row"><label for="cc-return-name">Ονοματεπώνυμο αποστολέα</label>';
        echo '<input type="text" id="cc-return-name" name="name" value="' . esc_attr( $name ) . '" maxlength="100"></p>';

        echo '<p class="form-row"><label for="cc-return-mobile"><span class="cc-return-mobile-label" data-base="Κινητό" data-boxnow="Κινητό (υποχρεωτικό για BOX NOW)">'
            . ( $boxnow_on ? 'Κινητό (υποχρεωτικό για BOX NOW)' : 'Κινητό' ) . '</span></label>';
        echo '<input type="tel" id="cc-return-mobile" name="mobile" value="' . esc_attr( $mobile ) . '" inputmode="tel"></p>';

        echo '<p class="form-row"><label for="cc-return-landline">Σταθερό</label>';
        echo '<input type="tel" id="cc-return-landline" name="landline" value="' . esc_attr( $landline ) . '" inputmode="tel"></p>';

        echo '<p class="form-row"><label for="cc-return-reason">Αιτιολογία (προαιρετικό)</label>';
        echo '<textarea id="cc-return-reason" name="reason" rows="3" maxlength="' . esc_attr( CC_Returns_Service::REASON_MAX ) . '">' . esc_textarea( $reason ) . '</textarea></p>';

        echo '<p class="cc-return-error" role="alert" style="display:none"></p>';
        echo '<p><button type="submit" class="button cc-return-submit">Υποβολή αιτήματος</button> ';
        echo '<a href="' . esc_url( $order->get_view_order_url() ) . '">Ακύρωση</a></p>';
        echo '</form>';
    }

    // ── Υποβολή ──────────────────────────────────────────────────────────────

    /** template_redirect: διαβάζει μόνο το $_POST και μεταφέρει τη δουλειά στο process_submission(). */
    public function maybe_handle_submission() {
        if ( empty( $_POST['cc_return_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }
        $post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
        $result = $this->process_submission( $post, get_current_user_id() );

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( $result['notice'], $result['notice_type'] );
        }
        if ( $result['ok'] && '' !== $result['redirect'] ) {
            wp_safe_redirect( $result['redirect'] );
            exit;
        }
    }

    /**
     * @param array $post Ήδη unslashed.
     * @return array ok, id, notice, notice_type, redirect
     */
    public function process_submission( array $post, $user_id ) {
        $user_id  = (int) $user_id;
        $order_id = isset( $post['order_id'] ) ? absint( $post['order_id'] ) : 0;

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
            'request_uid' => $text( 'request_uid' ),
            'method'      => $text( 'method' ),
            'items'       => array_filter( $qty ),
            'reason'      => $text( 'reason', true ),
            'name'        => $text( 'name' ),
            'mobile'      => $text( 'mobile' ),
            'landline'    => $text( 'landline' ),
            'locker_size' => $text( 'locker_size' ),
            'pickup_address'   => $text( 'pickup_address' ),
            'pickup_city'      => $text( 'pickup_city' ),
            'pickup_postcode'  => $text( 'pickup_postcode' ),
            'pickup_date'      => $text( 'pickup_date' ),
            'pickup_time_from' => $text( 'pickup_time_from' ),
            'pickup_time_to'   => $text( 'pickup_time_to' ),
            'courier_notes'    => $text( 'courier_notes', true ),
        );
        // Για την επανασυμπλήρωση της φόρμας μετά από σφάλμα
        $this->posted = $data;
        $this->posted['qty'] = $qty;

        if ( $user_id <= 0 || ! is_user_logged_in() ) {
            return $this->fail( self::MSG_FORBIDDEN );
        }
        $nonce = isset( $post['_wpnonce'] ) && is_scalar( $post['_wpnonce'] ) ? (string) $post['_wpnonce'] : '';
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'cc_return_submit_' . $order_id ) ) {
            return $this->fail( self::MSG_EXPIRED );
        }
        $order = $order_id ? wc_get_order( $order_id ) : false;
        if ( ! $order instanceof WC_Order || (int) $order->get_customer_id() !== $user_id ) {
            return $this->fail( self::MSG_FORBIDDEN );
        }

        $id = $this->service()->submit( $order, $user_id, $data );
        if ( is_wp_error( $id ) ) {
            return $this->fail( $id->get_error_message() );
        }

        $row = CC_Returns_Repo::get( $id );
        if ( $row && CC_Returns_Rules::ST_CREATED === $row['status'] ) {
            $notice = $this->creation_notice( $row );
        } elseif ( ( $row && CC_Returns_Rules::ST_PENDING === $row['status'] ) || CC_Returns_Settings::require_approval() ) {
            $notice = self::MSG_RECEIVED;
        } else {
            // Η δημιουργία απέτυχε ή είναι σε εξέλιξη: ποτέ τα σφάλματα του API στον πελάτη
            $notice = self::MSG_PENDING;
        }

        $this->posted = null;
        return array(
            'ok'          => true,
            'id'          => (int) $id,
            'notice'      => $notice,
            'notice_type' => 'success',
            'redirect'    => $order->get_view_order_url(),
        );
    }

    private function fail( $message ) {
        return array(
            'ok'          => false,
            'id'          => null,
            'notice'      => (string) $message,
            'notice_type' => 'error',
            'redirect'    => '',
        );
    }

    // ── Assets ───────────────────────────────────────────────────────────────

    public function enqueue_assets() {
        if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'view-order' ) || ! CC_Returns_Settings::enabled() ) {
            return;
        }
        wp_enqueue_style( 'cc-returns', CC_WC_PLUGIN_URL . 'assets/css/cc-returns.css', array(), CC_WC_VERSION );
        wp_enqueue_script( 'cc-returns', CC_WC_PLUGIN_URL . 'assets/js/cc-returns.js', array( 'jquery' ), CC_WC_VERSION, true );
        wp_localize_script( 'cc-returns', 'ccReturns', array(
            'msgPhone'   => CC_Returns_Rules::MSG_PHONE,
            'msgMobile'  => CC_Returns_Rules::MSG_MOBILE,
            'msgItems'   => self::MSG_NO_ITEMS,
            'msgSize'    => 'Επιλέξτε μέγεθος BOX NOW locker (Μικρό, Μεσαίο ή Μεγάλο).',
            'copied'     => 'Αντιγράφηκε',
            'boxnow'     => CC_Returns_Rules::METHOD_BOXNOW,
            'pickup'            => CC_Returns_Rules::METHOD_PICKUP,
            'minHours'          => CC_Returns_Rules::PICKUP_MIN_HOURS,
            'msgPickupAddress'  => 'Συμπληρώστε διεύθυνση και πόλη παραλαβής.',
            'msgPickupPostcode' => 'Ο ΤΚ της διεύθυνσης παραλαβής πρέπει να έχει 5 ψηφία.',
            'msgPickupHalf'     => CC_Returns_Rules::MSG_PICKUP_HALF,
            'msgPickupWindow'   => CC_Returns_Rules::MSG_PICKUP_WINDOW,
        ) );
    }
}
