<?php
/**
 * Status Tracker - Automatic status updates via WP Cron
 *
 * Τρέχει κάθε 2 ώρες και ενημερώνει τα status όλων των ενεργών αποστολών.
 * Σταματάει το polling για αποστολές σε τελικά status (παραδομένη, ακυρωμένη, κλπ).
 * Σέβεται rate limit 60 λεπτών ανά αποστολή — αν μια αποστολή ενημερώθηκε
 * πρόσφατα (από manual, bulk, ή cron), ο cron την παραλείπει.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Status_Tracker {

    /**
     * Rate limit window σε δευτερόλεπτα (60 λεπτά)
     * Δεν επιτρέπεται ενημέρωση status της ίδιας αποστολής συχνότερα από αυτό
     */
    const STATUS_UPDATE_RATE_LIMIT = 3600;

    /** Μόνο vouchers των τελευταίων 7 ημερών παίρνουν το email «στάλθηκε» (σε παλιά, το δέμα ίσως έφτασε προ πολλού). */
    const PICKUP_NOTIFY_MAX_AGE = 604800;

    /** Καταστάσεις που δεν μπορούν να είναι η «σε παραλαβή» (= ανενεργό). */
    const PICKUP_STATUS_EXCLUDED = array( 'pending', 'processing', 'cancelled', 'refunded', 'failed', 'checkout-draft' );

    /**
     * Final statuses - stop polling when reached
     */
    const FINAL_STATUSES = array(
        '29',  // ΠΑΡΑΔΟΘΗΚΕ
        '25',  // ΕΠΙΣΤΡΑΦΗΚΕ ΣΤΟΝ ΑΠΟΣΤΟΛΕΑ
        '99',  // ΑΚΥΡΩΜΕΝΗ ΑΠΟΣΤΟΛΗ
        '14',  // ΑΚΥΡΩΜΕΝΗ ΠΑΡΑΓΓΕΛΙΑ
        '87',  // CLEVER POINT - ΠΑΡΑΛΗΦΘΗΚΕ
        '95',  // ΑΠΟΖΗΜΙΩΘΗΚΕ
    );

    /**
     * Έλεγχος αν μια παραγγελία μπορεί να ενημερωθεί τώρα.
     *
     * @param WC_Order $order
     * @return array {
     *     @type bool   $allowed             True αν επιτρέπεται η ενημέρωση
     *     @type int    $seconds_remaining   Δευτερόλεπτα μέχρι την επόμενη επιτρεπόμενη ενημέρωση
     *     @type int    $minutes_remaining   Λεπτά (ακέραιος, για εμφάνιση)
     *     @type string $last_updated_human  π.χ. "πριν 23 λεπτά"
     * }
     */
    public static function check_rate_limit( $order ) {
        $last_check = $order->get_meta( '_cc_status_last_check_ts' );

        // Αν δεν έχει γίνει ποτέ check, επιτρέπεται
        if ( empty( $last_check ) ) {
            return array(
                'allowed'            => true,
                'seconds_remaining'  => 0,
                'minutes_remaining'  => 0,
                'last_updated_human' => '',
            );
        }

        $elapsed = time() - (int) $last_check;

        if ( $elapsed >= self::STATUS_UPDATE_RATE_LIMIT ) {
            return array(
                'allowed'            => true,
                'seconds_remaining'  => 0,
                'minutes_remaining'  => 0,
                'last_updated_human' => human_time_diff( (int) $last_check, time() ),
            );
        }

        $remaining = self::STATUS_UPDATE_RATE_LIMIT - $elapsed;

        return array(
            'allowed'            => false,
            'seconds_remaining'  => $remaining,
            'minutes_remaining'  => max( 1, (int) ceil( $remaining / 60 ) ),
            'last_updated_human' => human_time_diff( (int) $last_check, time() ),
        );
    }

    /**
     * Σημειώνει ότι έγινε API check τώρα — καλείται κάθε φορά που γίνεται κλήση
     * στο /api/Shipment/GetShipmentDetails για αυτή την αποστολή.
     *
     * @param WC_Order $order
     */
    public static function mark_check_now( $order ) {
        $order->update_meta_data( '_cc_status_last_check_ts', time() );
        $order->save();
    }

    /**
     * Constructor - register hooks
     */
    public function __construct() {
        // Register custom cron schedule
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );

        // Register the cron action
        add_action( 'cc_status_tracking_cron', array( $this, 'run_status_update' ) );

        // Χωρίς email «Completed» για σιωπηλή ολοκλήρωση παραγγελιών που είχαν μείνει πίσω
        add_filter( 'woocommerce_email_enabled_customer_completed_order', array( $this, 'maybe_block_completed_email' ), 10, 2 );

        // Schedule cron - τρέχει ΑΜΕΣΩΣ στον constructor, χωρίς hook
        $next = wp_next_scheduled( 'cc_status_tracking_cron' );
        if ( ! $next ) {
            $result = wp_schedule_event( time(), 'cc_every_two_hours', 'cc_status_tracking_cron' );
            if ( is_wp_error( $result ) ) {
                error_log( 'CC CRON - wp_schedule_event FAILED: ' . $result->get_error_message() );
            } else {
                error_log( 'CC CRON - Scheduled successfully. Next: ' . wp_next_scheduled( 'cc_status_tracking_cron' ) );
            }
        }
    }

    /**
     * Add custom 2-hour cron schedule
     */
    public function add_cron_schedule( $schedules ) {
        $schedules['cc_every_two_hours'] = array(
            'interval' => 7200, // 2 hours in seconds
            'display'  => 'Κάθε 2 ώρες (Courier Center)',
        );
        return $schedules;
    }

    /**
     * Run status update for all active shipments
     */
    public function run_status_update() {
        update_option( 'cc_wc_cron_last_run', current_time( 'Y-m-d H:i:s' ) );

        $orders = $this->get_active_shipment_orders();

        if ( empty( $orders ) ) {
            return;
        }

        $api = new CC_API();

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }

            $awb = $order->get_meta( '_cc_voucher_number' );
            if ( empty( $awb ) ) {
                continue;
            }

            // Skip αν είναι μέσα στο rate limit window (60 λεπτά)
            $rate_check = self::check_rate_limit( $order );
            if ( ! $rate_check['allowed'] ) {
                continue;
            }

            $result = $api->get_shipment_details( $awb );

            if ( is_wp_error( $result ) ) {
                error_log( 'CC Cron - Failed to get status for AWB ' . $awb . ': ' . $result->get_error_message() );
                continue;
            }

            $this->process_status_response( $order, $result );

            // Σημείωσε ότι έγινε check τώρα — ενεργοποιεί το rate limit για 60 λεπτά
            self::mark_check_now( $order );

            // Small delay between API calls to avoid rate limiting
            usleep( 500000 ); // 0.5 seconds
        }
    }

    /**
     * Get all orders with active (non-final) shipments
     */
    private function get_active_shipment_orders() {
        // Use wc_get_orders for HPOS compatibility
        $statuses = array( 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-completed' );
        if ( '' !== self::pickup_status() ) {
            // η «σε παραλαβή» (π.χ. «Εστάλη»): η παρακολούθηση συνεχίζει μέχρι την παράδοση
            $statuses[] = 'wc-' . self::pickup_status();
        }
        $args = array(
            'limit'      => 200,
            'status'     => array_values( array_unique( $statuses ) ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_cc_voucher_number',
                    'compare' => 'EXISTS',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_cc_voided',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_cc_voided',
                        'value'   => '1',
                        'compare' => '!=',
                    ),
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_cc_shipment_final',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_cc_shipment_final',
                        'value'   => '1',
                        'compare' => '!=',
                    ),
                ),
            ),
            'return'     => 'ids',
        );

        return wc_get_orders( $args );
    }

    /**
     * Process status response and update order
     */
    private function process_status_response( $order, $result ) {
        $status_code = '';
        $status_desc = '';
        $action_code = '';

        $info = CC_API::pick_shipment_info( $result, $order->get_meta( '_cc_voucher_number' ) );
        if ( null !== $info ) {
            $status_code = (string) ( $info['ShipmentStatus'] ?? '' );
            $status_desc = $info['ShipmentStatusDesc'] ?? '';
            $action_code = $info['CollectionStatus'] ?? '';
        }

        if ( empty( $status_code ) && empty( $status_desc ) ) {
            return;
        }

        self::apply_status( $order, $status_code, $status_desc, $action_code, 'auto', (array) $info );
    }

    /**
     * Αποθηκεύει το status της αποστολής — κοινό για cron ('auto'), μαζική
     * ενημέρωση ('bulk') και το κουμπί της παραγγελίας ('manual').
     *
     * Η σημείωση μπαίνει μόνο όταν αλλάζει το status. Η ολοκλήρωση της παραγγελίας
     * για παραδομένες αποστολές ελέγχεται πάντα, ώστε να μην «κολλάει» όταν το
     * status είχε ήδη αποθηκευτεί (π.χ. από το χειροκίνητο κουμπί).
     *
     * @param WC_Order $order
     * @param string   $status_code
     * @param string   $status_desc
     * @param string   $action_code
     * @param string   $source      'auto' | 'bulk' | 'manual'
     * @param array    $info        το ShipmentInfo της απάντησης (References για την «ΑΣΚΟΠΗ ΠΡΟΣΕΛΕΥΣΗ»)
     */
    public static function apply_status( $order, $status_code, $status_desc, $action_code, $source, $info = array() ) {
        $old_status = $order->get_meta( '_cc_shipment_status' );
        $changed    = ( $old_status !== $status_code );

        $order->update_meta_data( '_cc_shipment_status', $status_code );
        $order->update_meta_data( '_cc_shipment_status_desc', $status_desc );
        $order->update_meta_data( '_cc_shipment_action_code', $action_code );
        $order->update_meta_data( '_cc_status_updated_at', current_time( 'Y-m-d H:i' ) );

        // Mark as final if in final status list
        if ( in_array( $status_code, self::FINAL_STATUSES, true ) ) {
            $order->update_meta_data( '_cc_shipment_final', '1' );
        }

        $order->save();

        // Add order note only if status changed
        if ( $changed ) {
            $action_text = $action_code ? " (Action: $action_code)" : '';
            if ( 'bulk' === $source ) {
                $note = sprintf( '📍 [Bulk] Status: %s - %s', $status_code, $status_desc );
            } elseif ( 'manual' === $source ) {
                $note = sprintf( '📍 Status ενημερώθηκε: %s - %s%s', $status_code, $status_desc, $action_text );
            } else {
                $note = sprintf( '📍 [Auto] Status: %s - %s%s', $status_code, $status_desc, $action_text );
            }
            $order->add_order_note( $note );
        }

        self::maybe_mark_picked_up( $order, (string) $status_code, (string) $action_code, self::is_futile_visit( (array) $info ), $source );

        // Auto-complete order if delivered — μόνο από ενεργές καταστάσεις, ώστε να
        // μην αναιρείται ακύρωση ή επιστροφή χρημάτων που έκανε ο έμπορος. Η κατάσταση
        // «σε παραλαβή» (π.χ. ένα «Εστάλη» άλλου plugin) μετράει κι αυτή ως ενεργή.
        $active = array( 'processing', 'on-hold', 'pending' );
        if ( ! in_array( self::pickup_status(), array( '', 'completed' ), true ) ) {
            $active[] = self::pickup_status();
        }
        if ( in_array( $status_code, array( '29', '87' ), true )
             && in_array( $order->get_status(), $active, true ) ) {

            if ( ! $changed ) {
                // Η παράδοση είχε ήδη καταγραφεί (παραγγελία που είχε μείνει πίσω) —
                // ολοκλήρωση χωρίς καθυστερημένο email «Completed» στον πελάτη.
                $order->update_meta_data( '_cc_silent_complete_ts', time() );
                $order->save();
            }

            $completed_note = ( 'bulk' === $source )
                ? '🚚 Αυτόματη ολοκλήρωση - Παραδόθηκε.'
                : '🚚 Αυτόματη ολοκλήρωση - Η αποστολή παραδόθηκε.';
            $order->update_status( 'completed', $completed_note );
        }
    }

    /**
     * Η κατάσταση της ρύθμισης «Σε παραλαβή → κατάσταση» ('' = ανενεργό). Οι καταστάσεις
     * «πριν» από την αποστολή ή «χαμένες» δεν έχουν νόημα εδώ και θεωρούνται ανενεργό.
     *
     * @return string slug χωρίς το «wc-»
     */
    public static function pickup_status() {
        $status = sanitize_key( preg_replace( '/^wc-/', '', (string) get_option( 'cc_wc_pickup_status', '' ) ) );
        return in_array( $status, self::PICKUP_STATUS_EXCLUDED, true ) ? '' : $status;
    }

    /** Κάποιο Reference της αποστολής γράφει ΑΣΚΟΠΗ (άσκοπη προσέλευση), όπως το διαβάζουν οι επιστροφές. */
    public static function is_futile_visit( array $info ) {
        foreach ( array( 'Reference1', 'Reference2', 'Reference3', 'Reference4' ) as $key ) {
            if ( CC_Returns_Rules::contains_askopi( $info[ $key ] ?? '' ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ο courier έχει το δέμα: η παραλαβή έκλεισε (CollectionStatus 10) και όχι ως άσκοπη προσέλευση, ή το δέμα
     * παραδόθηκε. Το ShipmentStatus μόνο του δεν αρκεί: κάθε voucher περνά από 0 «None» σε 15 «NewShipment» λίγα
     * λεπτά μετά τη δημιουργία, είτε παραλήφθηκε είτε όχι (και το BOX NOW μένει στο 15 μέχρι την παράδοση).
     */
    public static function is_picked_up( $status_code, $collection = '', $futile = false ) {
        $code = (string) $status_code;
        if ( in_array( $code, array( '29', '87' ), true ) ) {
            return true;
        }
        if ( '' === $code || '0' === $code || in_array( $code, self::FINAL_STATUSES, true ) ) {
            return false;
        }
        return (string) CC_Returns_Rules::COLLECTION_COMPLETED === (string) $collection && ! $futile;
    }

    /**
     * Μία φορά ανά αποστολή (meta _cc_picked_up_at): το πρώτο status που δείχνει ότι ο courier
     * παρέλαβε το δέμα περνά μια ενεργή παραγγελία στην κατάσταση της ρύθμισης. Και χωρίς αλλαγή
     * status, ώστε όσες είχαν ήδη παραληφθεί πριν ενεργοποιηθεί η ρύθμιση να ακολουθήσουν. Δέμα που
     * παραδόθηκε το αφήνει στην αυτόματη ολοκλήρωση· μη ενεργή παραγγελία (ακυρωμένη, ολοκληρωμένη,
     * αλλαγμένη με το χέρι) μόνο σημειώνεται, δεν αλλάζει.
     *
     * @param WC_Order $order
     * @param string   $status_code
     * @param string   $collection CollectionStatus
     * @param bool     $futile     άσκοπη προσέλευση
     * @param string   $source 'auto' | 'bulk' | 'manual'
     * @return bool αν άλλαξε η κατάσταση της παραγγελίας
     */
    private static function maybe_mark_picked_up( $order, $status_code, $collection, $futile, $source ) {
        $pickup = self::pickup_status();
        if ( '' === $pickup || ! self::is_picked_up( $status_code, $collection, $futile ) || $order->get_meta( '_cc_picked_up_at' ) ) {
            return false;
        }
        $order->update_meta_data( '_cc_picked_up_at', current_time( 'mysql' ) );
        $order->save();
        if ( in_array( $status_code, array( '29', '87' ), true )
             || ! in_array( $order->get_status(), array( 'processing', 'on-hold', 'pending' ), true )
             || $order->get_status() === $pickup ) {
            return false;
        }

        $created = strtotime( (string) $order->get_meta( '_cc_created_at' ) );
        $recent  = $created && ( current_time( 'timestamp' ) - $created ) <= self::PICKUP_NOTIFY_MAX_AGE;
        // Το «Ολοκληρώθηκε» έχει δικό του email από το WooCommerce (με το κουτί αποστολής).
        $notify  = $recent && '1' === get_option( 'cc_wc_pickup_notify', '1' ) && 'completed' !== $pickup;

        $label = ( 'bulk' === $source ) ? '[Bulk] ' : ( ( 'manual' === $source ) ? '' : '[Auto] ' );
        $order->update_status( $pickup, sprintf( '🚚 %sΟ courier παρέλαβε την αποστολή %s.%s',
            $label, $order->get_meta( '_cc_voucher_number' ), $notify ? ' Ο πελάτης ενημερώθηκε με email.' : '' ) );
        if ( $notify ) {
            self::send_shipped_email( $order );
        }
        return true;
    }

    /**
     * Email «Η παραγγελία στάλθηκε» στον πελάτη, με τη μορφή των emails του WooCommerce.
     *
     * @param WC_Order $order
     */
    private static function send_shipped_email( $order ) {
        $to = $order->get_billing_email();
        if ( ! $to || ! function_exists( 'WC' ) ) {
            return;
        }
        $first = $order->get_billing_first_name();
        $body  = '<p>' . esc_html( $first ? sprintf( 'Γεια σας %s,', $first ) : 'Γεια σας,' ) . '</p>'
            . '<p>' . esc_html( sprintf( 'Η παραγγελία #%s στάλθηκε: ο courier της Courier Center παρέλαβε το δέμα σας.', $order->get_order_number() ) ) . '</p>';
        if ( '1' === $order->get_meta( '_cc_boxnow' ) ) {
            $body .= '<p>' . esc_html( 'Θα παραδοθεί σε BOX NOW locker· τον κωδικό για να ανοίξετε τη θυρίδα θα τον λάβετε στο κινητό σας.' ) . '</p>';
        }
        $tracking = CC_Email_Integration::tracking_block_html( $order );
        // Με κλειστή την ένδειξη στα emails, ο πελάτης παίρνει τουλάχιστον τον αριθμό αποστολής.
        $body .= '' !== trim( $tracking ) ? $tracking
            : '<p>' . esc_html( sprintf( 'Αριθμός αποστολής: %s', $order->get_meta( '_cc_voucher_number' ) ) ) . '</p>';
        try {
            $mailer = WC()->mailer();
            $mailer->send( $to, sprintf( 'Η παραγγελία #%s στάλθηκε', $order->get_order_number() ),
                $mailer->wrap_message( 'Η παραγγελία σας στάλθηκε', $body ) );
        } catch ( \Throwable $e ) {
            // πρόβλημα στο email δεν σταματά ποτέ την ενημέρωση status
        }
    }

    /**
     * Μπλοκάρει ΜΙΑ φορά το email «Completed order» μιας παραγγελίας που
     * ολοκληρώθηκε σιωπηλά από το apply_status(). Το σημάδι σβήνεται στην πρώτη
     * απόπειρα αποστολής — λειτουργεί και με deferred emails (Action Scheduler,
     * αργότερα) και αφήνει ελεύθερη μια μεταγενέστερη επαναποστολή από τον έμπορο.
     * Σημάδι παλαιότερο από 24 ώρες αγνοείται.
     */
    public function maybe_block_completed_email( $enabled, $order ) {
        if ( ! $order instanceof WC_Order ) {
            return $enabled;
        }
        $ts = (int) $order->get_meta( '_cc_silent_complete_ts' );
        if ( ! $ts ) {
            return $enabled;
        }

        $order->delete_meta_data( '_cc_silent_complete_ts' );
        $order->save_meta_data();

        if ( ( time() - $ts ) < DAY_IN_SECONDS ) {
            return false;
        }
        return $enabled;
    }

    /**
     * Unschedule cron on plugin deactivation
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'cc_status_tracking_cron' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'cc_status_tracking_cron' );
        }
    }
}