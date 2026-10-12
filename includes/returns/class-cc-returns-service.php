<?php
/**
 * Επιστροφές: υπηρεσία αιτημάτων επιστροφής.
 *
 * Επιλεξιμότητα παραγγελίας, υποβολή αιτήματος από τον πελάτη, έγκριση/απόρριψη από
 * τον διαχειριστή και δημιουργία της αποστολής επιστροφής στο API με ατομικό κλείδωμα
 * (status «creating») και επαλήθευση με GetShipmentDetails όταν το αποτέλεσμα είναι
 * αβέβαιο (timeout, ShipmentAlreadyExist). Η κανονική αποστολή της παραγγελίας
 * (status, _cc_* meta, _cc_return_awb) δεν αγγίζεται ποτέ.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Service {

    const LOG_SOURCE = 'cc-returns';
    const REASON_MAX = 500;
    const ERROR_MAX  = 300; // χαρακτήρες για last_error / log

    const MSG_FORBIDDEN   = 'Μη εξουσιοδοτημένη πρόσβαση στην παραγγελία.';
    const MSG_NOT_PENDING = 'Το αίτημα έχει ήδη εξεταστεί.';
    const MSG_BUSY        = 'Η δημιουργία είναι ήδη σε εξέλιξη ή έχει ολοκληρωθεί.';
    const MSG_NO_RSN      = 'Το API δεν επέστρεψε κωδικό BOX NOW επιστροφής';
    const MSG_RECHECK     = 'Η αποστολή δεν βρέθηκε ακόμη στο API· θα γίνει νέος έλεγχος (αυτόματα ή με «Έλεγχος ύπαρξης αποστολής») πριν από οποιαδήποτε νέα δημιουργία.';

    /** @var CC_API */
    private $api;

    public function __construct( ?CC_API $api = null ) {
        $this->api = $api ? $api : new CC_API();
    }

    // ── Ετικέτες (κοινές για τις οθόνες διαχείρισης/πελάτη) ─────────────────

    public static function status_label( $status ) {
        $labels = array(
            CC_Returns_Rules::ST_PENDING  => 'Σε αναμονή έγκρισης',
            CC_Returns_Rules::ST_APPROVED => 'Εγκρίθηκε',
            CC_Returns_Rules::ST_CREATING => 'Δημιουργία σε εξέλιξη',
            CC_Returns_Rules::ST_VERIFY   => 'Σε έλεγχο',
            CC_Returns_Rules::ST_CREATED  => 'Δημιουργήθηκε αποστολή',
            CC_Returns_Rules::ST_FAILED   => 'Αποτυχία δημιουργίας',
            CC_Returns_Rules::ST_REJECTED => 'Απορρίφθηκε',
        );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }

    /**
     * Ετικέτα κατάστασης για τον πελάτη (My Account, emails). Οι εσωτερικές καταστάσεις της
     * δημιουργίας (approved, creating, needs_verification, creation_failed) εμφανίζονται ως μία.
     */
    public static function customer_status_label( $status ) {
        $preparing = 'Εγκρίθηκε – η αποστολή επιστροφής ετοιμάζεται';
        $labels    = array(
            CC_Returns_Rules::ST_PENDING  => 'Σε αναμονή έγκρισης',
            CC_Returns_Rules::ST_APPROVED => $preparing,
            CC_Returns_Rules::ST_CREATING => $preparing,
            CC_Returns_Rules::ST_VERIFY   => $preparing,
            CC_Returns_Rules::ST_FAILED   => $preparing,
            CC_Returns_Rules::ST_CREATED  => 'Δημιουργήθηκε αποστολή',
            CC_Returns_Rules::ST_REJECTED => 'Απορρίφθηκε',
        );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : $preparing;
    }

    public static function method_label( $method ) {
        $labels = array(
            CC_Returns_Rules::METHOD_DROPOFF => 'Παράδοση σε κατάστημα Courier Center',
            CC_Returns_Rules::METHOD_PICKUP  => 'Παραλαβή από διεύθυνση (Courier Center)',
            CC_Returns_Rules::METHOD_BOXNOW  => 'BOX NOW locker',
        );
        return isset( $labels[ $method ] ) ? $labels[ $method ] : $method;
    }

    /** «Τρίτη 13/10/2026, 09:00–13:00» για αίτημα Pickup ('' αν δεν υπάρχει ημερομηνία). */
    public static function pickup_label( array $row ) {
        return CC_Returns_Rules::pickup_text(
            isset( $row['pickup_date'] ) ? $row['pickup_date'] : '',
            isset( $row['pickup_time_from'] ) ? $row['pickup_time_from'] : '',
            isset( $row['pickup_time_to'] ) ? $row['pickup_time_to'] : ''
        );
    }

    /** Η παραγγελία έχει AWB Courier Center που δεν έχει ακυρωθεί. */
    public static function has_active_awb( WC_Order $order ) {
        return '' !== trim( (string) $order->get_meta( '_cc_voucher_number' ) ) && '1' !== (string) $order->get_meta( '_cc_voided' );
    }

    /** Τώρα, στη ζώνη ώρας του site (για ημερομηνίες παραλαβής). */
    public static function now_local() {
        return new DateTimeImmutable( 'now', wp_timezone() );
    }

    /**
     * Τα μηνύματα επιτυχίας δημιουργίας (§7.4 / §10.6) ως array( array( κείμενο, τιμή, είναι_κωδικός ), ... ).
     * Κοινό για το My Account και τα emails πελάτη. Αν λείπει ο κωδικός BOX NOW (ή το μέγεθος), η γραμμή παραλείπεται.
     */
    public static function creation_parts( array $row ) {
        $parts = array( array( 'Η επιστροφή σας δημιουργήθηκε επιτυχώς!', '', false ) );
        if ( CC_Returns_Rules::METHOD_BOXNOW === $row['method'] ) {
            $parts[] = array( 'Μπορείτε να παραδώσετε το δέμα σας σε ένα BOX NOW locker που σας εξυπηρετεί.', '', false );
            if ( '' !== (string) $row['return_shipment_number'] ) {
                $parts[] = array( 'Κωδικός BOX NOW επιστροφής: ', (string) $row['return_shipment_number'], true );
            }
            $parts[] = array( 'Αριθμός αποστολής Courier Center: ', (string) $row['return_awb'], false );
            if ( isset( CC_Returns_Rules::LOCKER_SIZES[ $row['locker_size'] ] ) ) {
                $parts[] = array( 'Επιλεγμένο μέγεθος: ', CC_Returns_Rules::LOCKER_SIZES[ $row['locker_size'] ]['label'], false );
            }
        } elseif ( CC_Returns_Rules::METHOD_PICKUP === $row['method'] ) {
            $sd   = is_array( $row['shipper_details'] ) ? $row['shipper_details'] : array();
            $addr = trim( ( isset( $sd['address'] ) ? $sd['address'] : '' ) . ', ' . ( isset( $sd['city'] ) ? $sd['city'] : '' ) . ' ' . ( isset( $sd['postcode'] ) ? $sd['postcode'] : '' ), ' ,' );
            $parts[] = array( 'Ο courier θα παραλάβει το δέμα από τη διεύθυνση: ', $addr, false );
            $label   = self::pickup_label( $row );
            if ( '' !== $label ) {
                $parts[] = array( 'Παραλαβή: ', $label, false );
            }
            $parts[] = array( 'Αριθμός αποστολής επιστροφής: ', (string) $row['return_awb'], false );
        } else {
            $parts[] = array( 'Μπορείτε να παραδώσετε το δέμα σας σε κατάστημα Courier Center.', '', false );
            $parts[] = array( 'Αριθμός αποστολής επιστροφής: ', (string) $row['return_awb'], false );
        }
        return $parts;
    }

    /** Τα ίδια μηνύματα ως απλές γραμμές κειμένου (για emails). */
    public static function creation_message_lines( array $row ) {
        $lines = array();
        foreach ( self::creation_parts( $row ) as $part ) {
            $lines[] = $part[0] . $part[1];
        }
        return $lines;
    }

    // ── Επιλεξιμότητα / ποσότητες ────────────────────────────────────────────

    /**
     * Ιδιοκτήτης, ενεργές επιστροφές, completed, ενεργό AWB, εντός προθεσμίας.
     *
     * @return true|WP_Error
     */
    public function check_eligibility( WC_Order $order, $user_id ) {
        // Πρώτα ο ιδιοκτήτης: σε ξένη παραγγελία δεν αποκαλύπτουμε τίποτα άλλο
        if ( (int) $user_id <= 0 || (int) $order->get_customer_id() !== (int) $user_id ) {
            return new WP_Error( 'cc_return_forbidden', self::MSG_FORBIDDEN );
        }
        if ( ! CC_Returns_Settings::enabled() ) {
            return new WP_Error( 'cc_return_disabled', 'Οι επιστροφές δεν είναι διαθέσιμες.' );
        }
        if ( ! $order->has_status( 'completed' ) ) {
            return new WP_Error( 'cc_return_not_completed', 'Η επιστροφή είναι διαθέσιμη μόνο για ολοκληρωμένες παραγγελίες.' );
        }
        if ( '' === trim( (string) $order->get_meta( '_cc_voucher_number' ) ) || '1' === (string) $order->get_meta( '_cc_voided' ) ) {
            return new WP_Error( 'cc_return_no_shipment', 'Η παραγγελία δεν έχει ενεργή αποστολή Courier Center.' );
        }

        $days = CC_Returns_Settings::window_days();
        if ( $days > 0 ) {
            $date = $order->get_date_completed();
            if ( ! $date ) {
                $date = $order->get_date_modified();
            }
            if ( $date && time() - $date->getTimestamp() > $days * DAY_IN_SECONDS ) {
                return new WP_Error( 'cc_return_expired', 'Η προθεσμία επιστροφής για αυτή την παραγγελία έχει λήξει.' );
            }
        }
        return true;
    }

    /**
     * item_id => array( 'name', 'ordered', 'available' ):
     * ordered − refunded − τεμάχια σε αιτήματα της παραγγελίας που δεν απορρίφθηκαν.
     */
    public function available_items( WC_Order $order ) {
        $reserved = array();
        foreach ( CC_Returns_Repo::for_order( $order->get_id() ) as $req ) {
            if ( CC_Returns_Rules::ST_REJECTED === $req['status'] ) {
                continue;
            }
            foreach ( $req['items'] as $line ) {
                $iid              = isset( $line['item_id'] ) ? (int) $line['item_id'] : 0;
                $reserved[ $iid ] = ( isset( $reserved[ $iid ] ) ? $reserved[ $iid ] : 0 ) + ( isset( $line['qty'] ) ? (int) $line['qty'] : 0 );
            }
        }

        $out = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $ordered  = (int) $item->get_quantity();
            $refunded = abs( (int) $order->get_qty_refunded_for_item( $item_id ) );
            $taken    = isset( $reserved[ $item_id ] ) ? $reserved[ $item_id ] : 0;

            $out[ $item_id ] = array(
                'name'      => $item->get_name(),
                'ordered'   => $ordered,
                'available' => max( 0, $ordered - $refunded - $taken ),
            );
        }
        return $out;
    }

    // ── Υποβολή / απόφαση ────────────────────────────────────────────────────

    /**
     * $data: request_uid, method, items[item_id => qty], reason, name, mobile, landline, locker_size,
     *        pickup_address, pickup_city, pickup_postcode, pickup_date, pickup_time_from, pickup_time_to, courier_notes.
     *
     * @return int|WP_Error id. Ίδιο request_uid → επιστρέφει το υπάρχον id.
     */
    public function submit( WC_Order $order, $user_id, array $data ) {
        $uid = isset( $data['request_uid'] ) ? (string) $data['request_uid'] : '';
        if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{8,32}$/', $uid ) ) {
            return new WP_Error( 'cc_return_invalid_request', 'Μη έγκυρο αίτημα. Ανανεώστε τη σελίδα και δοκιμάστε ξανά.' );
        }

        // Ξανά-υποβολή (διπλό κλικ / refresh): το υπάρχον αίτημα, αν είναι του ίδιου πελάτη
        $existing = $this->existing_for_uid( $uid, $order, $user_id );
        if ( null !== $existing ) {
            return $existing;
        }

        $ok = $this->check_eligibility( $order, $user_id );
        if ( is_wp_error( $ok ) ) {
            return $ok;
        }

        $method = isset( $data['method'] ) ? (string) $data['method'] : '';
        if ( ! in_array( $method, CC_Returns_Settings::enabled_methods(), true ) ) {
            return new WP_Error( 'cc_return_method_unavailable', 'Η επιλεγμένη μέθοδος επιστροφής δεν είναι διαθέσιμη.' );
        }

        $row = $this->build_request( $order, $method, $data );
        if ( is_wp_error( $row ) ) {
            return $row;
        }
        $row = array_merge( array(
            'request_uid'     => $uid,
            'reference_token' => self::new_token(),
            'customer_id'     => (int) $user_id,
            'status'          => CC_Returns_Rules::ST_PENDING,
        ), $row );

        $id = CC_Returns_Repo::insert( $row );
        if ( is_wp_error( $id ) ) {
            // Ταυτόχρονο request με το ίδιο uid κέρδισε τον αγώνα
            $existing = $this->existing_for_uid( $uid, $order, $user_id );
            if ( null !== $existing ) {
                return $existing;
            }
            if ( 'duplicate' === $id->get_error_code() ) {
                // Σύγκρουση token (απίθανη): νέο token, μία φορά
                $row['reference_token'] = self::new_token();
                $id                     = CC_Returns_Repo::insert( $row );
            }
            if ( is_wp_error( $id ) ) {
                $existing = $this->existing_for_uid( $uid, $order, $user_id );
                if ( null !== $existing ) {
                    return $existing;
                }
                $this->log( 'error', sprintf( 'Επιστροφή: αποτυχία καταχώρισης (παραγγελία %d, %s): %s', $order->get_id(), $method, $id->get_error_code() ) );
                return new WP_Error( 'cc_return_db', 'Δεν ήταν δυνατή η καταχώριση του αιτήματος. Δοκιμάστε ξανά.' );
            }
        }

        $this->log( 'info', sprintf( 'Επιστροφή #%d: νέο αίτημα (παραγγελία %d, %s).', $id, $order->get_id(), $method ) );

        if ( CC_Returns_Settings::require_approval() ) {
            do_action( 'cc_return_request_received', $id );
            return $id;
        }

        // Χωρίς έγκριση: πρώτα η δημιουργία. Αν πέτυχε, ο πελάτης παίρνει μόνο το «δημιουργήθηκε»·
        // αλλιώς (αποτυχία / έλεγχος σε εξέλιξη) το «ελήφθη».
        if ( CC_Returns_Repo::transition( $id, array( CC_Returns_Rules::ST_PENDING ), CC_Returns_Rules::ST_APPROVED, array( 'approved_at' => current_time( 'mysql' ) ) ) ) {
            $this->create( $id ); // το αποτέλεσμα φαίνεται στο status του αιτήματος
        }
        $fresh = CC_Returns_Repo::get( $id );
        if ( ! $fresh || CC_Returns_Rules::ST_CREATED !== $fresh['status'] ) {
            do_action( 'cc_return_request_received', $id );
        }
        return $id;
    }

    /**
     * Επιστροφή που δημιουργεί το κατάστημα (admin): χωρίς τους κανόνες επιλεξιμότητας του πελάτη
     * (κατάσταση, προθεσμία, ενεργές μέθοδοι) — αρκεί το AWB της αρχικής αποστολής. Η γραμμή καταχωρείται
     * απευθείας approved (origin shop) και η αποστολή δημιουργείται αμέσως· δεν στέλνεται «ελήφθη».
     *
     * $data: όπως στο submit() + notify ('1' = email «δημιουργήθηκε» στον πελάτη).
     *
     * @return int|WP_Error id (και όταν η δημιουργία απέτυχε — φαίνεται στο status). Ίδιο request_uid → το υπάρχον id.
     */
    public function shop_create( WC_Order $order, $admin_id, array $data ) {
        $uid = isset( $data['request_uid'] ) ? (string) $data['request_uid'] : '';
        if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{8,32}$/', $uid ) ) {
            return new WP_Error( 'cc_return_invalid_request', 'Μη έγκυρο αίτημα. Ανανεώστε τη σελίδα και δοκιμάστε ξανά.' );
        }

        $customer = (int) $order->get_customer_id();
        $existing = $this->existing_for_uid( $uid, $order, $customer );
        if ( null !== $existing ) {
            return $existing;
        }

        if ( ! self::has_active_awb( $order ) ) {
            return new WP_Error( 'cc_return_no_awb', 'Η παραγγελία δεν έχει αποστολή Courier Center (AWB)· η επιστροφή δημιουργείται μόνο για παραγγελίες που στάλθηκαν με Courier Center.' );
        }

        $method = isset( $data['method'] ) ? (string) $data['method'] : '';
        if ( ! in_array( $method, CC_Returns_Rules::METHODS_ORDER, true ) ) {
            return new WP_Error( 'cc_return_method_unavailable', 'Η επιλεγμένη μέθοδος επιστροφής δεν είναι διαθέσιμη.' );
        }

        $row = $this->build_request( $order, $method, $data );
        if ( is_wp_error( $row ) ) {
            return $row;
        }
        $row = array_merge( array(
            'request_uid'     => $uid,
            'reference_token' => self::new_token(),
            'customer_id'     => $customer,
            'status'          => CC_Returns_Rules::ST_APPROVED,
            'origin'          => 'shop',
            'notify_customer' => empty( $data['notify'] ) ? 0 : 1,
            'decided_by'      => (int) $admin_id,
            'approved_at'     => current_time( 'mysql' ),
        ), $row );

        $id = CC_Returns_Repo::insert( $row );
        if ( is_wp_error( $id ) ) {
            $existing = $this->existing_for_uid( $uid, $order, $customer );
            if ( null !== $existing ) {
                return $existing;
            }
            $this->log( 'error', sprintf( 'Επιστροφή: αποτυχία καταχώρισης από το κατάστημα (παραγγελία %d, %s): %s', $order->get_id(), $method, $id->get_error_code() ) );
            return new WP_Error( 'cc_return_db', 'Δεν ήταν δυνατή η καταχώριση της επιστροφής. Δοκιμάστε ξανά.' );
        }

        $this->log( 'info', sprintf( 'Επιστροφή #%d: δημιουργία από το κατάστημα (παραγγελία %d, %s, χρήστης %d).', $id, $order->get_id(), $method, (int) $admin_id ) );
        $this->create( $id ); // το αποτέλεσμα φαίνεται στο status του αιτήματος
        return (int) $id;
    }

    /**
     * Κοινοί έλεγχοι πεδίων (προϊόντα, αιτιολογία, όνομα, τηλέφωνο, BOX NOW, Pickup) για πελάτη και κατάστημα.
     *
     * @return array|WP_Error Τα πεδία της γραμμής χωρίς request_uid/reference_token/customer_id/status.
     */
    private function build_request( WC_Order $order, $method, array $data ) {
        $items = $this->validate_items( $order, isset( $data['items'] ) ? $data['items'] : array() );
        if ( is_wp_error( $items ) ) {
            return $items;
        }

        $reason = sanitize_textarea_field( isset( $data['reason'] ) ? (string) $data['reason'] : '' );
        if ( mb_strlen( $reason, 'UTF-8' ) > self::REASON_MAX ) {
            return new WP_Error( 'cc_return_reason_length', 'Η αιτιολογία μπορεί να έχει έως 500 χαρακτήρες.' );
        }

        $name = trim( sanitize_text_field( isset( $data['name'] ) ? (string) $data['name'] : '' ) );
        if ( '' === $name ) {
            return new WP_Error( 'cc_return_missing_name', 'Συμπληρώστε το ονοματεπώνυμο του αποστολέα.' );
        }

        $phone = CC_Returns_Rules::pick_phone(
            $method,
            isset( $data['mobile'] ) ? (string) $data['mobile'] : '',
            isset( $data['landline'] ) ? (string) $data['landline'] : ''
        );
        if ( is_wp_error( $phone ) ) {
            return $phone;
        }

        $locker_size = '';
        if ( CC_Returns_Rules::METHOD_BOXNOW === $method ) {
            $locker_size = isset( $data['locker_size'] ) ? (string) $data['locker_size'] : '';
            if ( '' === CC_Returns_Rules::locker_reference( $locker_size ) ) {
                return new WP_Error( 'cc_return_locker_size', 'Επιλέξτε μέγεθος BOX NOW locker (Μικρό, Μεσαίο ή Μεγάλο).' );
            }
        }

        $pickup = CC_Returns_Rules::METHOD_PICKUP === $method;
        $pk     = array( 'address' => '', 'city' => '', 'postcode' => '', 'date' => null, 'from' => null, 'to' => null, 'notes' => '' );
        if ( $pickup ) {
            $field = function ( $key ) use ( $data ) {
                return trim( sanitize_text_field( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? (string) $data[ $key ] : '' ) );
            };
            $pk['address']  = $field( 'pickup_address' );
            $pk['city']     = $field( 'pickup_city' );
            $pk['postcode'] = preg_replace( '/\s+/', '', $field( 'pickup_postcode' ) );
            if ( '' === $pk['address'] ) {
                return new WP_Error( 'cc_return_missing_address', 'Συμπληρώστε τη διεύθυνση παραλαβής.' );
            }
            if ( '' === $pk['city'] ) {
                return new WP_Error( 'cc_return_missing_city', 'Συμπληρώστε την πόλη της διεύθυνσης παραλαβής.' );
            }
            if ( 1 !== preg_match( '/^\d{5}$/', $pk['postcode'] ) ) {
                return new WP_Error( 'cc_return_invalid_postcode', 'Ο ΤΚ της διεύθυνσης παραλαβής πρέπει να έχει 5 ψηφία.' );
            }
            $date = $field( 'pickup_date' );
            $ok   = CC_Returns_Rules::validate_pickup_date( $date, self::now_local() );
            if ( is_wp_error( $ok ) ) {
                return $ok;
            }
            $from = $field( 'pickup_time_from' );
            $to   = $field( 'pickup_time_to' );
            $ok   = CC_Returns_Rules::validate_pickup_window( $from, $to );
            if ( is_wp_error( $ok ) ) {
                return $ok;
            }
            $notes = trim( sanitize_textarea_field( isset( $data['courier_notes'] ) && is_scalar( $data['courier_notes'] ) ? (string) $data['courier_notes'] : '' ) );
            if ( mb_strlen( $notes, 'UTF-8' ) > CC_Returns_Rules::COURIER_NOTES_MAX ) {
                return new WP_Error( 'cc_return_notes_length', 'Οι οδηγίες για τον courier μπορούν να έχουν έως 200 χαρακτήρες.' );
            }
            $pk['date']  = $date;
            $pk['from']  = '' !== $from ? $from : null;
            $pk['to']    = '' !== $to ? $to : null;
            $pk['notes'] = $notes;
        }

        $consignee = ( new CC_Shipment_Builder( $order ) )->get_consignee_fields();
        $shipper   = trim( (string) $order->get_meta( '_cc_pickup_point' ) );

        $row = array(
            'order_id'              => $order->get_id(),
            'method'                => $method,
            'items'                 => $items,
            'reason'                => $reason,
            'original_awb'          => trim( (string) $order->get_meta( '_cc_voucher_number' ) ),
            'original_shipper_code' => '' !== $shipper ? $shipper : (string) get_option( 'cc_wc_billing_account', '' ),
            'shipper_details'       => array(
                'name'          => $name,
                'address'       => $pickup ? $pk['address'] : $consignee['address'],
                'city'          => $pickup ? $pk['city'] : $consignee['city'],
                'postcode'      => $pickup ? $pk['postcode'] : $consignee['postcode'],
                'phone'         => $phone['phone'],
                'email'         => $order->get_billing_email(),
                'courier_notes' => $pk['notes'],
            ),
            'pickup_date'           => $pk['date'],
            'pickup_time_from'      => $pk['from'],
            'pickup_time_to'        => $pk['to'],
            'locker_size'           => $locker_size,
            'locker_reference'      => CC_Returns_Rules::locker_reference( $locker_size ),
        );

        return $row;
    }

    /** pending_approval → approved → create(). @return array|WP_Error */
    public function approve( $id, $admin_id ) {
        $ok = CC_Returns_Repo::transition( $id, array( CC_Returns_Rules::ST_PENDING ), CC_Returns_Rules::ST_APPROVED, array(
            'decided_by'  => (int) $admin_id,
            'approved_at' => current_time( 'mysql' ),
        ) );
        if ( ! $ok ) {
            return new WP_Error( 'cc_return_not_pending', self::MSG_NOT_PENDING );
        }
        $this->log( 'info', sprintf( 'Επιστροφή #%d: εγκρίθηκε.', $id ) );
        return $this->create( $id );
    }

    /** pending_approval|approved|creation_failed → rejected. @return true|WP_Error */
    public function reject( $id, $admin_id ) {
        $ok = CC_Returns_Repo::transition(
            $id,
            array( CC_Returns_Rules::ST_PENDING, CC_Returns_Rules::ST_APPROVED, CC_Returns_Rules::ST_FAILED ),
            CC_Returns_Rules::ST_REJECTED,
            array( 'decided_by' => (int) $admin_id )
        );
        if ( ! $ok ) {
            return new WP_Error( 'cc_return_not_pending', self::MSG_NOT_PENDING );
        }
        $this->log( 'info', sprintf( 'Επιστροφή #%d: απορρίφθηκε.', $id ) );
        do_action( 'cc_return_rejected', $id );
        return true;
    }

    // ── Δημιουργία ───────────────────────────────────────────────────────────

    /**
     * approved|creation_failed → creating (ατομικά) → API.
     *
     * - needs_verification: ΜΟΝΟ verify(). «Δεν βρέθηκε» → creation_failed + WP_Error, χωρίς create στην
     *   ίδια κλήση (η επόμενη ρητή create() ξεκινά πάλι με αναζήτηση token). Εξαίρεση: εντός LOCK_TTL από
     *   μια αβέβαιη προσπάθεια (timeout κ.λπ.) το «δεν βρέθηκε» δεν είναι οριστικό → μένει needs_verification.
     * - creation_failed με attempts > 0: πριν από την επανάληψη αναζήτηση με το token, γιατί μια προηγούμενη
     *   προσπάθεια (π.χ. timeout) μπορεί να πέτυχε στο API αργότερα. Βρέθηκε → created χωρίς create·
     *   «No shipments found» → κανονική επανάληψη· άλλο σφάλμα → needs_verification χωρίς create.
     *
     * @return array|WP_Error Η γραμμή όταν η αποστολή υπάρχει, αλλιώς WP_Error (busy / αποτυχία / αβέβαιο).
     */
    public function create( $id ) {
        $this->release_stale_locks();

        $row = CC_Returns_Repo::get( $id );
        if ( ! $row ) {
            return new WP_Error( 'cc_return_not_found', 'Το αίτημα επιστροφής δεν βρέθηκε.' );
        }

        if ( CC_Returns_Rules::ST_VERIFY === $row['status'] ) {
            return $this->verify( $id );
        }

        if ( CC_Returns_Rules::ST_FAILED === $row['status'] && (int) $row['attempts'] > 0 ) {
            $checked = $this->recheck_before_retry( $row );
            if ( null !== $checked ) {
                return $checked;
            }
        }

        $claimed = CC_Returns_Repo::transition(
            $id,
            array( CC_Returns_Rules::ST_APPROVED, CC_Returns_Rules::ST_FAILED ),
            CC_Returns_Rules::ST_CREATING,
            array( 'locked_at' => self::now_gmt(), 'attempts' => '+1' )
        );
        if ( ! $claimed ) {
            return new WP_Error( 'cc_return_busy', self::MSG_BUSY );
        }

        return $this->run_create( CC_Returns_Repo::get( $id ) );
    }

    /**
     * Αναζήτηση της αποστολής με Identifier = reference_token (μόνο σε needs_verification).
     *
     * «Δεν βρέθηκε» μέσα σε LOCK_TTL από την αβέβαιη προσπάθεια δημιουργίας (locked_at, GMT) δεν είναι
     * οριστικό: ένα timeout του gateway μπορεί να επιστρέψει ενώ το API ακόμη δημιουργεί την αποστολή.
     * Μένει needs_verification (κρατώντας το αρχικό μήνυμα σφάλματος)· μόνο μετά το LOCK_TTL → creation_failed.
     *
     * @return array|WP_Error Η γραμμή (shipment_created), ή WP_Error: cc_return_creation_failed
     *                        (δεν βρέθηκε → creation_failed, επιτρέπεται νέα create()) ή
     *                        cc_return_unverified (μένει needs_verification).
     */
    public function verify( $id ) {
        $row = CC_Returns_Repo::get( $id );
        if ( ! $row || CC_Returns_Rules::ST_VERIFY !== $row['status'] ) {
            return new WP_Error( 'cc_return_not_verifiable', 'Το αίτημα δεν βρίσκεται σε κατάσταση ελέγχου.' );
        }

        $look = $this->lookup_token( $row );
        if ( 'found' === $look['state'] ) {
            return $this->mark_created( $row, array( CC_Returns_Rules::ST_VERIFY ), $look['fields'], $look['warnings'] );
        }
        if ( 'none' === $look['state'] ) {
            if ( self::attempt_is_recent( $row ) ) {
                return $this->recheck_later( $row );
            }
            return $this->fail( $row, $look['message'], array( CC_Returns_Rules::ST_VERIFY ) );
        }
        return $this->still_unverified( $row, $look['message'] );
    }

    /** Ανανέωση του status της αποστολής επιστροφής (ο κώδικας βρίσκεται στον CC_Returns_Tracker). */
    public function refresh_tracking( $id ) {
        return ( new CC_Returns_Tracker( $this->api ) )->refresh( $id );
    }

    /**
     * creating με locked_at (GMT) παλαιότερο από LOCK_TTL → needs_verification (το request που
     * κρατούσε το κλείδωμα πιθανότατα πέθανε· η αποστολή μπορεί να έχει δημιουργηθεί).
     *
     * @return int Πόσα αιτήματα απελευθερώθηκαν.
     */
    public function release_stale_locks() {
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - CC_Returns_Repo::LOCK_TTL );
        $count  = 0;
        foreach ( CC_Returns_Repo::query( array( 'status' => CC_Returns_Rules::ST_CREATING, 'limit' => 200 ) ) as $row ) {
            if ( ! empty( $row['locked_at'] ) && $row['locked_at'] >= $cutoff ) {
                continue;
            }
            // locked_at = NULL: η μετάβαση αλλάζει πάντα στήλη, άρα το transition μετράει 1 γραμμή
            if ( CC_Returns_Repo::transition( $row['id'], array( CC_Returns_Rules::ST_CREATING ), CC_Returns_Rules::ST_VERIFY, array( 'locked_at' => null ) ) ) {
                $count++;
                $this->log( 'warning', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): ληγμένο κλείδωμα δημιουργίας → έλεγχος ύπαρξης.', $row['id'], $row['order_id'], $row['method'] ) );
            }
        }
        return $count;
    }

    // ── Εσωτερικά ────────────────────────────────────────────────────────────

    /** Βήματα 3–6 της δημιουργίας, με το αίτημα ήδη σε «creating». */
    private function run_create( array $row ) {
        // 3. Η αρχική αποστολή: ο πραγματικός αποστολέας (σημείο) από το API
        $details = $this->api->get_shipment_details( $row['original_awb'] );
        if ( is_wp_error( $details ) ) {
            return $this->fail( $row, 'Αποτυχία ανάκτησης αρχικής αποστολής: ' . $details->get_error_message() );
        }
        if ( ! is_array( $details ) || ! self::details_ok( $details ) ) {
            return $this->fail( $row, 'Αποτυχία ανάκτησης αρχικής αποστολής: ' . self::api_error_text( is_array( $details ) ? $details : array() ) );
        }

        $info = null;
        foreach ( $details['ShipmentDetails'] as $detail ) {
            $candidate = isset( $detail['ShipmentInfo'] ) && is_array( $detail['ShipmentInfo'] ) ? $detail['ShipmentInfo'] : array();
            if ( isset( $candidate['ShipmentAwb'] ) && trim( (string) $candidate['ShipmentAwb'] ) === $row['original_awb'] ) {
                $info = $candidate;
                break;
            }
        }
        if ( null === $info ) {
            $first = reset( $details['ShipmentDetails'] );
            $info  = isset( $first['ShipmentInfo'] ) && is_array( $first['ShipmentInfo'] ) ? $first['ShipmentInfo'] : array();
        }

        $api_code = trim( (string) ( isset( $info['Shipper']['Code'] ) ? $info['Shipper']['Code'] : '' ) );
        $point    = '';
        foreach ( CC_Pickup_Points::get_points() as $p ) {
            if ( '' !== (string) $p['code'] && CC_Returns_Rules::codes_equal( $p['code'], $api_code ) ) {
                $point = (string) $p['code'];
                break;
            }
        }
        if ( '' === $point ) {
            return $this->fail( $row, sprintf( 'Ο αποστολέας της αρχικής αποστολής (%s) δεν είναι σημείο αποστολής του καταστήματος.', $api_code ) );
        }
        if ( ! CC_Returns_Rules::codes_equal( $point, $row['original_shipper_code'] ) ) {
            $this->log( 'warning', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): ο αποστολέας της αρχικής στο API (%s) διαφέρει από το σημείο της παραγγελίας (%s)· ισχύει του API.', $row['id'], $row['order_id'], $row['method'], $point, $row['original_shipper_code'] ) );
        }

        // 4. Λογαριασμοί και χρέωση· για Pickup, ημερομηνία που πέρασε → επόμενη εργάσιμη (ίδιο διάστημα)
        $main   = (string) get_option( 'cc_wc_billing_account', '' );
        $fields = array(
            'original_shipper_code' => $point,
            'return_account_code'   => CC_Returns_Settings::return_account_for( $point ) ?: $point,
            'requestor_account'     => $main,
        );
        if ( CC_Returns_Rules::METHOD_PICKUP === $row['method'] ) {
            $next = CC_Returns_Rules::next_working_day( self::now_local() );
            if ( (string) $row['pickup_date'] < $next ) {
                $fields['pickup_date'] = $next;
                $this->log( 'info', sprintf( 'Επιστροφή #%d: η ημερομηνία παραλαβής μετακινήθηκε από %s σε %s.', $row['id'], (string) $row['pickup_date'], $next ) );
            }
        }
        CC_Returns_Repo::update( $row['id'], $fields );
        $row            = array_merge( $row, $fields );
        $row['bill_to'] = CC_Returns_Rules::bill_to( CC_Pickup_Points::get_point( $point ), $main );
        $this->log( 'info', sprintf( 'Επιστροφή #%d: χρέωση %s (σημείο %s, λογαριασμός επιστροφών %s).', $row['id'], $row['bill_to'], $point, $row['return_account_code'] ) );

        // 5. Payload
        $ctx = array(
            'UserAlias'       => get_option( 'cc_wc_user_alias', '' ),
            'CredentialValue' => get_option( 'cc_wc_credential_value', '' ),
            'ApiKey'          => get_option( 'cc_wc_api_key', '' ),
        );
        $payload = CC_Return_Payload::build( $row, $ctx, CC_Returns_Settings::weight() );
        if ( is_wp_error( $payload ) ) {
            return $this->fail( $row, $payload->get_error_message() );
        }

        // 6. Create
        $res = $this->api->create_shipment( $payload );
        if ( is_wp_error( $res ) ) {
            if ( 'api_exception' === $res->get_error_code() ) {
                return $this->to_verification( $row, 'Αβέβαιο αποτέλεσμα δημιουργίας: ' . $res->get_error_message() );
            }
            return $this->fail( $row, $res->get_error_message(), array( CC_Returns_Rules::ST_CREATING ), $res->get_error_code() );
        }

        $awb = trim( (string) ( isset( $res['ShipmentNumber'] ) ? $res['ShipmentNumber'] : '' ) );
        if ( ! empty( $res['ShipmentAlreadyExist'] ) ) {
            return $this->to_verification( $row, 'Το API απάντησε ότι η αποστολή υπάρχει ήδη· έλεγχος ύπαρξης.' );
        }
        if ( '' === $awb ) {
            return $this->to_verification( $row, 'Το API δεν επέστρεψε αριθμό αποστολής· έλεγχος ύπαρξης.' );
        }

        return $this->mark_created( $row, array( CC_Returns_Rules::ST_CREATING ), array(
            'return_awb'              => $awb,
            'return_shipment_number'  => (string) ( isset( $res['ReturnShipmentNumber'] ) ? $res['ReturnShipmentNumber'] : '' ),
            'inbound_shipment_number' => (string) ( isset( $res['InboundShipmentNumber'] ) ? $res['InboundShipmentNumber'] : '' ),
            'shipment_master_id'      => (string) ( isset( $res['ShipmentMasterId'] ) ? $res['ShipmentMasterId'] : '' ),
        ) );
    }

    /**
     * GetShipmentDetails( reference_token ).
     *
     * @return array state 'found' (fields, warnings) | 'none' (message) | 'error' (message)
     */
    private function lookup_token( array $row ) {
        $res = $this->api->get_shipment_details( $row['reference_token'] );
        if ( is_wp_error( $res ) || ! is_array( $res ) ) {
            return array( 'state' => 'error', 'message' => is_wp_error( $res ) ? $res->get_error_message() : 'Μη έγκυρη απάντηση του API' );
        }

        if ( ! self::details_ok( $res ) ) {
            // Μόνο το ρητό «No shipments found» (ή Success χωρίς αποτελέσματα) σημαίνει «δεν υπάρχει»
            $msg = self::api_error_text( $res );
            if ( false === stripos( $msg, 'No shipments found' ) ) {
                return array( 'state' => 'error', 'message' => $msg );
            }
            return array( 'state' => 'none', 'message' => 'Δεν βρέθηκε αποστολή για το αίτημα· μπορείτε να δοκιμάσετε ξανά τη δημιουργία.' );
        }

        // Μόνο μη ακυρωμένες αποστολές (CollectionStatus 14 = Cancel)
        $alive = array();
        foreach ( $res['ShipmentDetails'] as $detail ) {
            $info = isset( $detail['ShipmentInfo'] ) && is_array( $detail['ShipmentInfo'] ) ? $detail['ShipmentInfo'] : array();
            if ( '' !== (string) ( isset( $info['ShipmentAwb'] ) ? $info['ShipmentAwb'] : '' )
                && CC_Returns_Rules::COLLECTION_CANCELLED !== (int) ( isset( $info['CollectionStatus'] ) ? $info['CollectionStatus'] : 0 ) ) {
                $alive[] = $info;
            }
        }
        if ( ! $alive ) {
            return array( 'state' => 'none', 'message' => 'Βρέθηκαν μόνο ακυρωμένες αποστολές για το αίτημα· μπορείτε να δοκιμάσετε ξανά τη δημιουργία.' );
        }

        $info     = $alive[0];
        $warnings = array();
        if ( count( $alive ) > 1 ) {
            $warnings[] = sprintf( 'Βρέθηκαν %d αποστολές με το ίδιο token', count( $alive ) );
        }
        return array(
            'state'    => 'found',
            'warnings' => $warnings,
            'fields'   => array(
                'return_awb'              => (string) $info['ShipmentAwb'],
                'return_shipment_number'  => CC_Returns_Rules::METHOD_BOXNOW === $row['method'] ? (string) ( isset( $info['Reference4'] ) ? $info['Reference4'] : '' ) : '',
                'inbound_shipment_number' => (string) ( isset( $info['InboundAwb'] ) ? $info['InboundAwb'] : '' ),
                'shipment_master_id'      => (string) ( isset( $info['ShipmentId'] ) ? $info['ShipmentId'] : '' ),
            ),
        );
    }

    /**
     * Πριν από επανάληψη από creation_failed: υπάρχει ήδη αποστολή με το token;
     *
     * @return array|WP_Error|null null = καμία αποστολή, προχώρα σε create.
     */
    private function recheck_before_retry( array $row ) {
        $look = $this->lookup_token( $row );
        if ( 'found' === $look['state'] ) {
            return $this->mark_created( $row, array( CC_Returns_Rules::ST_FAILED ), $look['fields'], $look['warnings'] );
        }
        if ( 'none' === $look['state'] ) {
            return null;
        }
        $text = self::clip( 'Ο έλεγχος ύπαρξης πριν από την επανάληψη δεν ολοκληρώθηκε: ' . $look['message'] );
        if ( CC_Returns_Repo::transition( $row['id'], array( CC_Returns_Rules::ST_FAILED ), CC_Returns_Rules::ST_VERIFY, array( 'last_error' => $text, 'locked_at' => null ) ) ) {
            $this->log( 'warning', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): %s', $row['id'], $row['order_id'], $row['method'], $text ) );
        }
        return new WP_Error( 'cc_return_unverified', $text );
    }

    /**
     * → shipment_created (ατομικά από $from). Note/hook/log μόνο αν η μετάβαση έγινε εδώ.
     * Αν η αποστολή μόλις δημιουργήθηκε (from creating) αλλά το αίτημα άλλαξε κατάσταση στο μεταξύ,
     * το AWB καταγράφεται σε log επιπέδου error ώστε ένα πιθανό διπλό να εντοπίζεται.
     */
    private function mark_created( array $row, array $from, array $fields, array $warnings = array() ) {
        if ( CC_Returns_Rules::METHOD_BOXNOW === $row['method'] && '' === $fields['return_shipment_number'] ) {
            $warnings[] = self::MSG_NO_RSN;
        }
        $fields['locked_at']           = null;
        $fields['shipment_created_at'] = current_time( 'mysql' );
        $fields['last_error']          = implode( ' · ', $warnings );

        if ( CC_Returns_Repo::transition( $row['id'], $from, CC_Returns_Rules::ST_CREATED, $fields ) ) {
            $this->note( $row, sprintf( '↩️ Επιστροφή #%d: AWB %s (%s)', $row['id'], $fields['return_awb'], self::method_label( $row['method'] ) ) );
            $this->log( 'info', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): δημιουργήθηκε αποστολή.', $row['id'], $row['order_id'], $row['method'] ) );
            foreach ( $warnings as $w ) {
                $this->log( 'warning', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): %s', $row['id'], $row['order_id'], $row['method'], $w ) );
                if ( self::MSG_NO_RSN === $w ) {
                    $this->note( $row, sprintf( '↩️ Επιστροφή #%d: %s', $row['id'], $w ) );
                }
            }
            do_action( 'cc_return_shipment_created', $row['id'] );
            return CC_Returns_Repo::get( $row['id'] );
        }

        $fresh  = CC_Returns_Repo::get( $row['id'] );
        $status = $fresh ? $fresh['status'] : '';
        if ( in_array( CC_Returns_Rules::ST_CREATING, $from, true ) ) {
            $this->log( 'error', sprintf(
                'Επιστροφή #%d (παραγγελία %d, %s): η αποστολή AWB %s%s δημιουργήθηκε στο API αλλά το αίτημα είχε ήδη αλλάξει κατάσταση (%s) και δεν αποθηκεύτηκε — έλεγχος για διπλό AWB.',
                $row['id'],
                $row['order_id'],
                $row['method'],
                $fields['return_awb'],
                '' !== $fields['return_shipment_number'] ? ' / BOX NOW ' . $fields['return_shipment_number'] : '',
                $status
            ) );
        }
        if ( $fresh && CC_Returns_Rules::ST_CREATED === $status ) {
            return $fresh; // ολοκληρώθηκε από άλλο request
        }
        return new WP_Error( 'cc_return_conflict', 'Η κατάσταση του αιτήματος άλλαξε κατά τη δημιουργία· ελέγξτε το αίτημα.' );
    }

    /** → creation_failed με το μήνυμα (order note + log). Επιστρέφει πάντα WP_Error. */
    private function fail( array $row, $message, array $from = array( CC_Returns_Rules::ST_CREATING ), $api_code = '' ) {
        $message = self::clip( $message );
        $moved   = CC_Returns_Repo::transition( $row['id'], $from, CC_Returns_Rules::ST_FAILED, array(
            'last_error' => $message,
            'locked_at'  => null,
        ) );
        if ( $moved ) {
            $this->note( $row, sprintf( '↩️ Επιστροφή #%d: αποτυχία δημιουργίας — %s', $row['id'], $message ) );
            $this->log( 'error', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): αποτυχία δημιουργίας%s — %s', $row['id'], $row['order_id'], $row['method'], '' !== $api_code ? ' [' . $api_code . ']' : '', $message ) );
        }
        return new WP_Error( 'cc_return_creation_failed', $message );
    }

    /**
     * creating → needs_verification και αμέσως verify() (ποτέ δεύτερο create).
     * Το locked_at (χρόνος της προσπάθειας, GMT) ΔΕΝ μηδενίζεται: το verify() το χρειάζεται για να
     * ξεχωρίσει ένα πρόωρο «δεν βρέθηκε» (εντός LOCK_TTL) από ένα οριστικό.
     */
    private function to_verification( array $row, $message ) {
        $message = self::clip( $message );
        CC_Returns_Repo::transition( $row['id'], array( CC_Returns_Rules::ST_CREATING ), CC_Returns_Rules::ST_VERIFY, array(
            'last_error' => $message,
        ) );
        $this->log( 'warning', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): %s', $row['id'], $row['order_id'], $row['method'], $message ) );
        return $this->verify( $row['id'] );
    }

    /** Η αβέβαιη προσπάθεια δημιουργίας (locked_at, GMT) έγινε πριν από λιγότερο από LOCK_TTL. */
    private static function attempt_is_recent( array $row ) {
        if ( empty( $row['locked_at'] ) ) {
            return false; // άγνωστος χρόνος (π.χ. ληγμένο κλείδωμα / έλεγχος πριν από επανάληψη) → όχι πρόσφατη
        }
        return $row['locked_at'] >= gmdate( 'Y-m-d H:i:s', time() - CC_Returns_Repo::LOCK_TTL );
    }

    /**
     * «Δεν βρέθηκε» πολύ νωρίς μετά από αβέβαιη δημιουργία: μένει needs_verification. Το last_error κρατά
     * το αρχικό μήνυμα (π.χ. timeout) και προστίθεται μία φορά ότι θα ξαναελεγχθεί.
     */
    private function recheck_later( array $row ) {
        $prev = trim( (string) $row['last_error'] );
        if ( false !== strpos( $prev, self::MSG_RECHECK ) ) {
            $text = $prev; // ήδη σημειωμένο (επαναλαμβανόμενος έλεγχος)
        } else {
            $prev = self::clip( $prev ); // μόνο το αρχικό μήνυμα κόβεται· η σημείωση μένει ολόκληρη
            $text = ( '' !== $prev ? $prev . ' · ' : '' ) . self::MSG_RECHECK;
        }
        CC_Returns_Repo::transition( $row['id'], array( CC_Returns_Rules::ST_VERIFY ), CC_Returns_Rules::ST_VERIFY, array( 'last_error' => $text ) );
        $this->log( 'warning', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): η αποστολή δεν βρέθηκε ακόμη μετά από αβέβαιη δημιουργία· μένει σε έλεγχο.', $row['id'], $row['order_id'], $row['method'] ) );
        return new WP_Error( 'cc_return_unverified', $text );
    }

    /** Ο έλεγχος δεν κατέληξε: μένει needs_verification (το last_error γράφεται μόνο αν είναι ακόμα εκεί). */
    private function still_unverified( array $row, $message ) {
        $text = self::clip( 'Ο έλεγχος ύπαρξης της αποστολής δεν ολοκληρώθηκε: ' . $message );
        CC_Returns_Repo::transition( $row['id'], array( CC_Returns_Rules::ST_VERIFY ), CC_Returns_Rules::ST_VERIFY, array( 'last_error' => $text ) );
        $this->log( 'warning', sprintf( 'Επιστροφή #%d (παραγγελία %d, %s): %s', $row['id'], $row['order_id'], $row['method'], $text ) );
        return new WP_Error( 'cc_return_unverified', $text );
    }

    /** Το υπάρχον id για το uid (ίδια παραγγελία/πελάτης), WP_Error για ξένο, null αν δεν υπάρχει. */
    private function existing_for_uid( $uid, WC_Order $order, $user_id ) {
        $row = CC_Returns_Repo::get_by_uid( $uid );
        if ( ! $row ) {
            return null;
        }
        if ( (int) $row['order_id'] !== (int) $order->get_id() || (int) $row['customer_id'] !== (int) $user_id ) {
            return new WP_Error( 'cc_return_forbidden', self::MSG_FORBIDDEN );
        }
        return (int) $row['id'];
    }

    /**
     * @return array[]|WP_Error Λίστα {item_id, product_id, name, qty} μόνο για qty > 0.
     */
    private function validate_items( WC_Order $order, $requested ) {
        if ( ! is_array( $requested ) ) {
            return new WP_Error( 'cc_return_no_items', 'Επιλέξτε τουλάχιστον ένα τεμάχιο για επιστροφή.' );
        }
        $available = $this->available_items( $order );
        $order_items = $order->get_items();
        $lines     = array();

        foreach ( $requested as $item_id => $qty ) {
            $item_id = (int) $item_id;
            if ( ! isset( $available[ $item_id ], $order_items[ $item_id ] ) ) {
                return new WP_Error( 'cc_return_invalid_item', 'Μη έγκυρο προϊόν στο αίτημα επιστροφής.' );
            }
            if ( is_int( $qty ) ) {
                $raw = (string) $qty;
            } else {
                $raw = is_string( $qty ) ? trim( $qty ) : '';
            }
            if ( '' === $raw || ! ctype_digit( $raw ) ) {
                return new WP_Error( 'cc_return_invalid_qty', 'Οι ποσότητες πρέπει να είναι ακέραιοι αριθμοί από 0 και πάνω.' );
            }
            $qty = (int) $raw;
            if ( $qty > $available[ $item_id ]['available'] ) {
                return new WP_Error( 'cc_return_qty_exceeds', sprintf( 'Η ποσότητα για «%s» ξεπερνά τη διαθέσιμη (%d).', $available[ $item_id ]['name'], $available[ $item_id ]['available'] ) );
            }
            if ( $qty > 0 ) {
                $lines[] = array(
                    'item_id'    => $item_id,
                    'product_id' => (int) $order_items[ $item_id ]->get_product_id(),
                    'name'       => $available[ $item_id ]['name'],
                    'qty'        => $qty,
                );
            }
        }
        if ( ! $lines ) {
            return new WP_Error( 'cc_return_no_items', 'Επιλέξτε τουλάχιστον ένα τεμάχιο για επιστροφή.' );
        }
        return $lines;
    }

    /** Result Success με μη κενό ShipmentDetails. */
    private static function details_ok( array $res ) {
        return isset( $res['Result'] ) && 'Success' === $res['Result']
            && ! empty( $res['ShipmentDetails'] ) && is_array( $res['ShipmentDetails'] );
    }

    private static function api_error_text( array $res ) {
        if ( ! empty( $res['Errors'][0]['Message'] ) ) {
            return (string) $res['Errors'][0]['Message'];
        }
        if ( isset( $res['Result'] ) && 'Success' === $res['Result'] ) {
            return 'No shipments found';
        }
        return 'Άγνωστο σφάλμα από το API';
    }

    private static function new_token() {
        return 'RR' . strtoupper( bin2hex( random_bytes( 6 ) ) );
    }

    /** Το locked_at γράφεται/συγκρίνεται σε GMT (η τοπική ώρα πηδά με τη θερινή ώρα). */
    private static function now_gmt() {
        return current_time( 'mysql', true );
    }

    /** Τα μηνύματα σφάλματος (π.χ. «HTTP 500: <body>») κόβονται πριν αποθηκευτούν/καταγραφούν. */
    private static function clip( $text ) {
        $text = (string) $text;
        return mb_strlen( $text, 'UTF-8' ) > self::ERROR_MAX ? mb_substr( $text, 0, self::ERROR_MAX, 'UTF-8' ) . '…' : $text;
    }

    private function note( array $row, $text ) {
        $order = wc_get_order( $row['order_id'] );
        if ( $order ) {
            $order->add_order_note( $text );
        }
    }

    /** Μόνο id αιτήματος/παραγγελίας, μέθοδος και κωδικός/μήνυμα σφάλματος — ποτέ στοιχεία πελάτη. */
    private function log( $level, $message ) {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
        }
    }
}
