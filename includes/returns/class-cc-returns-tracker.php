<?php
/**
 * Επιστροφές: παρακολούθηση status των αποστολών επιστροφής.
 *
 * Ανανεώνει τα πεδία status του αιτήματος (shipment_status, shipment_status_desc,
 * collection_status, display_status) από το GetShipmentDetails και εφαρμόζει τον κανόνα
 * «ΑΣΚΟΠΗ ΠΡΟΣΕΛΕΥΣΗ». Ο tracker δεν αγγίζει ποτέ την παραγγελία (status, meta, notes):
 * η επιστροφή και η εμπορική κατάσταση της παραγγελίας είναι ανεξάρτητα.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Tracker {

    const LOG_SOURCE      = 'cc-returns';
    const ERROR_MAX       = 300;           // χαρακτήρες μηνύματος σφάλματος στο log
    const FINAL_AFTER     = 5184000;       // 60 ημέρες από τη δημιουργία → τέλος παρακολούθησης
    const RECHECK_AFTER   = HOUR_IN_SECONDS; // ελάχιστο διάστημα μεταξύ δύο ανανεώσεων της ίδιας γραμμής
    const CRON_REFRESH    = 100;           // μέγιστες ανανεώσεις ανά τρέξιμο
    const CRON_VERIFY     = 50;            // μέγιστοι έλεγχοι needs_verification ανά τρέξιμο
    const SLEEP_US        = 500000;        // 0.5s μεταξύ κλήσεων API

    /** @var CC_API */
    private $api;

    public function __construct( ?CC_API $api = null ) {
        $this->api = $api ? $api : new CC_API();
    }

    /**
     * Ανανεώνει το status μιας επιστροφής από το API.
     *
     * @return array|WP_Error Η ενημερωμένη γραμμή, ή WP_Error: cc_return_not_trackable (δεν είναι
     *                        shipment_created / δεν έχει AWB), ή το σφάλμα του API / cc_return_tracking_failed
     *                        (η γραμμή δεν αλλάζει).
     */
    public function refresh( $id ) {
        $row = CC_Returns_Repo::get( $id );
        if ( ! $row || CC_Returns_Rules::ST_CREATED !== $row['status'] || '' === trim( (string) $row['return_awb'] ) ) {
            return new WP_Error( 'cc_return_not_trackable', 'Η επιστροφή δεν έχει αποστολή για παρακολούθηση.' );
        }

        $res = $this->api->get_shipment_details( $row['return_awb'] );
        if ( is_wp_error( $res ) ) {
            $this->log( 'warning', sprintf( 'Επιστροφή #%d: αποτυχία παρακολούθησης — %s', $row['id'], self::clip( $res->get_error_message() ) ) );
            return $res;
        }

        $info = null;
        if ( is_array( $res ) && isset( $res['Result'] ) && 'Success' === $res['Result']
            && ! empty( $res['ShipmentDetails'][0]['ShipmentInfo'] ) && is_array( $res['ShipmentDetails'][0]['ShipmentInfo'] ) ) {
            $info = $res['ShipmentDetails'][0]['ShipmentInfo'];
        }
        if ( null === $info ) {
            $msg = is_array( $res ) && ! empty( $res['Errors'][0]['Message'] ) ? (string) $res['Errors'][0]['Message'] : 'Μη έγκυρη απάντηση του API';
            $this->log( 'warning', sprintf( 'Επιστροφή #%d: αποτυχία παρακολούθησης — %s', $row['id'], self::clip( $msg ) ) );
            return new WP_Error( 'cc_return_tracking_failed', $msg );
        }

        $status     = isset( $info['ShipmentStatus'] ) ? (string) $info['ShipmentStatus'] : '';
        $desc       = isset( $info['ShipmentStatusDesc'] ) ? (string) $info['ShipmentStatusDesc'] : '';
        $collection = isset( $info['CollectionStatus'] ) ? (string) $info['CollectionStatus'] : '';
        $refs       = array();
        foreach ( array( 'Reference1', 'Reference2', 'Reference3', 'Reference4' ) as $key ) {
            $refs[] = isset( $info[ $key ] ) ? (string) $info[ $key ] : '';
        }

        $final = in_array( $status, CC_Status_Tracker::FINAL_STATUSES, true )
            || ( '' !== $collection && CC_Returns_Rules::COLLECTION_CANCELLED === (int) $collection )
            || self::is_expired( $row );

        CC_Returns_Repo::update( $row['id'], array(
            'shipment_status'      => $status,
            'shipment_status_desc' => $desc,
            'collection_status'    => $collection,
            'display_status'       => CC_Returns_Rules::display_status( $status, $desc, $collection, $refs ),
            'tracking_final'       => $final ? 1 : 0,
            'last_tracking_at'     => current_time( 'mysql' ),
        ) );
        return CC_Returns_Repo::get( $row['id'] );
    }

    /**
     * Callback του cron 'cc_status_tracking_cron' (ξεχωριστό από τον CC_Status_Tracker):
     * ελευθέρωση ληγμένων κλειδωμάτων, έλεγχος των needs_verification και ανανέωση των ενεργών επιστροφών.
     *
     * @param CC_API|null $api Για tests· στην παραγωγή δεν δίνεται.
     */
    public static function cron( ?CC_API $api = null ) {
        $api     = $api ? $api : new CC_API();
        $service = new CC_Returns_Service( $api );
        $tracker = new self( $api );

        $service->release_stale_locks();

        foreach ( CC_Returns_Repo::query( array( 'status' => CC_Returns_Rules::ST_VERIFY, 'limit' => self::CRON_VERIFY, 'orderby' => 'id', 'order' => 'ASC' ) ) as $row ) {
            $service->verify( $row['id'] );
        }

        $first = true;
        foreach ( $tracker->due_ids( self::CRON_REFRESH ) as $id ) {
            if ( ! $first ) {
                $tracker->pause();
            }
            $first = false;
            $tracker->refresh( $id );
        }
    }

    // ── Εσωτερικά ────────────────────────────────────────────────────────────

    /** Τα ids shipment_created, με tracking_final = 0 και last_tracking_at NULL ή παλαιότερο της 1 ώρας (παλαιότερη ανανέωση πρώτα). */
    private function due_ids( $limit ) {
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - self::RECHECK_AFTER );
        $sql    = $wpdb->prepare(
            'SELECT id FROM ' . CC_Returns_Repo::table() . ' WHERE status = %s AND tracking_final = 0 AND return_awb <> %s'
            . ' AND ( last_tracking_at IS NULL OR last_tracking_at < %s ) ORDER BY ( last_tracking_at IS NULL ) DESC, last_tracking_at ASC, id ASC LIMIT %d', // phpcs:ignore
            CC_Returns_Rules::ST_CREATED,
            '',
            $cutoff,
            max( 1, (int) $limit )
        );
        return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore
    }

    /** Παύση μεταξύ κλήσεων API (0.5s)· προσαρμόσιμη/μηδενική μέσω του φίλτρου cc_returns_tracking_sleep_us. */
    private function pause() {
        $us = (int) apply_filters( 'cc_returns_tracking_sleep_us', self::SLEEP_US );
        if ( $us > 0 ) {
            usleep( $us );
        }
    }

    /** Πέρασαν 60 ημέρες από τη δημιουργία του αιτήματος. */
    private static function is_expired( array $row ) {
        $created = ! empty( $row['created_at'] ) ? strtotime( $row['created_at'] ) : false;
        return false !== $created && $created < strtotime( current_time( 'mysql' ) ) - self::FINAL_AFTER;
    }

    private static function clip( $text ) {
        $text = (string) $text;
        return mb_strlen( $text, 'UTF-8' ) > self::ERROR_MAX ? mb_substr( $text, 0, self::ERROR_MAX, 'UTF-8' ) . '…' : $text;
    }

    /** Μόνο id αιτήματος και κωδικός/μήνυμα σφάλματος — ποτέ στοιχεία πελάτη ή Context. */
    private function log( $level, $message ) {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
        }
    }
}
