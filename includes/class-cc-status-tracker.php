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
        // Register custom cron schedule (every 2 hours)
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );

        // Register the cron event
        add_action( 'cc_status_tracking_cron', array( $this, 'run_status_update' ) );

        // Schedule on plugin activation / ensure it's scheduled
        if ( ! wp_next_scheduled( 'cc_status_tracking_cron' ) ) {
            wp_schedule_event( time(), 'cc_every_two_hours', 'cc_status_tracking_cron' );
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
        $args = array(
            'limit'      => 200,
            'status'     => array( 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-completed' ),
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

        if ( isset( $result['ShipmentDetails'][0]['ShipmentInfo'] ) ) {
            $info = $result['ShipmentDetails'][0]['ShipmentInfo'];
            $status_code = (string) ( $info['ShipmentStatus'] ?? '' );
            $status_desc = $info['ShipmentStatusDesc'] ?? '';
            $action_code = $info['CollectionStatus'] ?? '';
        }

        if ( empty( $status_code ) && empty( $status_desc ) ) {
            return;
        }

        $old_status = $order->get_meta( '_cc_shipment_status' );

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
        if ( $old_status !== $status_code ) {
            $action_text = $action_code ? " (Action: $action_code)" : '';
            $order->add_order_note( sprintf(
                '📍 [Auto] Status: %s - %s%s',
                $status_code,
                $status_desc,
                $action_text
            ) );

            // Auto-complete order if delivered
            if ( in_array( $status_code, array( '29', '87' ), true ) ) {
                if ( $order->get_status() !== 'completed' ) {
                    $order->update_status( 'completed', '🚚 Αυτόματη ολοκλήρωση - Η αποστολή παραδόθηκε.' );
                }
            }
        }
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