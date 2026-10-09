<?php
/**
 * Επιστροφές: πίνακας αιτημάτων (cc_return_requests) και repository.
 *
 * Δημιουργία/αναβάθμιση του πίνακα με dbDelta, CRUD και ατομικές μεταβάσεις
 * κατάστασης (UPDATE ... WHERE status IN (...)) ώστε δύο ταυτόχρονα requests
 * να μην επεξεργαστούν το ίδιο αίτημα.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Repo {

    const DB_VERSION = '1';
    const DB_OPTION  = 'cc_wc_returns_db_version';
    const LOCK_TTL   = 180; // δευτερόλεπτα· πόσο ζει το κλείδωμα (locked_at) της κατάστασης «creating»

    // Στήλες που επιτρέπεται να γράψει ο καλών (whitelist). Το id δεν είναι ποτέ γράψιμο.
    const COLUMNS = array(
        'request_uid', 'reference_token', 'order_id', 'customer_id', 'status', 'method', 'items', 'reason',
        'original_awb', 'original_shipper_code', 'return_account_code', 'requestor_account', 'shipper_details',
        'pickup_date', 'pickup_time_from', 'pickup_time_to', 'locker_size', 'locker_reference',
        'return_awb', 'return_shipment_number', 'inbound_shipment_number', 'shipment_master_id',
        'shipment_status', 'shipment_status_desc', 'collection_status', 'display_status', 'tracking_final',
        'last_error', 'attempts', 'locked_at', 'decided_by',
        'created_at', 'updated_at', 'approved_at', 'shipment_created_at', 'last_tracking_at',
    );

    // Στήλες που αποθηκεύονται ως JSON
    const JSON_COLUMNS = array( 'items', 'shipper_details' );

    // Στήλες που επιστρέφονται ως int
    const INT_COLUMNS = array( 'id', 'order_id', 'customer_id', 'attempts', 'tracking_final', 'decided_by' );

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'cc_return_requests';
    }

    /** Δημιουργεί/αναβαθμίζει τον πίνακα όταν η αποθηκευμένη έκδοση διαφέρει από την DB_VERSION. */
    public static function maybe_upgrade() {
        if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        // dbDelta: κάθε στήλη σε δική της γραμμή, δύο κενά μετά το PRIMARY KEY.
        $sql = "CREATE TABLE $table (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_uid varchar(32) NOT NULL,
  reference_token varchar(20) NOT NULL,
  order_id bigint(20) unsigned NOT NULL,
  customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(24) NOT NULL,
  method varchar(32) NOT NULL,
  items longtext NOT NULL,
  reason text NULL,
  original_awb varchar(32) NOT NULL DEFAULT '',
  original_shipper_code varchar(32) NOT NULL DEFAULT '',
  return_account_code varchar(32) NOT NULL DEFAULT '',
  requestor_account varchar(32) NOT NULL DEFAULT '',
  shipper_details longtext NULL,
  pickup_date date NULL,
  pickup_time_from varchar(5) NULL,
  pickup_time_to varchar(5) NULL,
  locker_size varchar(10) NOT NULL DEFAULT '',
  locker_reference varchar(4) NOT NULL DEFAULT '',
  return_awb varchar(32) NOT NULL DEFAULT '',
  return_shipment_number varchar(32) NOT NULL DEFAULT '',
  inbound_shipment_number varchar(64) NOT NULL DEFAULT '',
  shipment_master_id varchar(64) NOT NULL DEFAULT '',
  shipment_status varchar(10) NOT NULL DEFAULT '',
  shipment_status_desc varchar(100) NOT NULL DEFAULT '',
  collection_status varchar(10) NOT NULL DEFAULT '',
  display_status varchar(100) NOT NULL DEFAULT '',
  tracking_final tinyint(1) NOT NULL DEFAULT 0,
  last_error text NULL,
  attempts int(11) NOT NULL DEFAULT 0,
  locked_at datetime NULL,
  decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  approved_at datetime NULL,
  shipment_created_at datetime NULL,
  last_tracking_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY request_uid (request_uid),
  UNIQUE KEY reference_token (reference_token),
  KEY order_id (order_id),
  KEY status (status)
) $charset;";

        dbDelta( $sql );
        update_option( self::DB_OPTION, self::DB_VERSION );
    }

    /**
     * Εισάγει αίτημα. Τα created_at/updated_at συμπληρώνονται εδώ· τα items/shipper_details
     * δίνονται ως arrays και αποθηκεύονται ως JSON.
     *
     * @return int|WP_Error id, ή WP_Error: 'duplicate' (ίδιο request_uid/reference_token), 'db_error'.
     */
    public static function insert( array $row ) {
        global $wpdb;

        $row = self::prepare_fields( $row );
        $now = current_time( 'mysql' );
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        if ( ! array_key_exists( 'items', $row ) ) {
            $row['items'] = '[]'; // NOT NULL χωρίς default
        }

        $prev = $wpdb->suppress_errors( true );
        $wpdb->last_error = '';
        $ok   = $wpdb->insert( self::table(), $row );
        $err  = $wpdb->last_error;
        $wpdb->suppress_errors( $prev );

        if ( false === $ok ) {
            if ( false !== stripos( $err, 'Duplicate entry' ) ) {
                return new WP_Error( 'duplicate', 'Το αίτημα επιστροφής υπάρχει ήδη.' );
            }
            return new WP_Error( 'db_error', $err );
        }
        return (int) $wpdb->insert_id;
    }

    public static function get( $id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
        return $row ? self::hydrate( $row ) : null;
    }

    public static function get_by_uid( $uid ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE request_uid = %s', (string) $uid ), ARRAY_A ); // phpcs:ignore
        return $row ? self::hydrate( $row ) : null;
    }

    /** Όλα τα αιτήματα μιας παραγγελίας, νεότερο πρώτο. */
    public static function for_order( $order_id ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE order_id = %d ORDER BY id DESC', (int) $order_id ), ARRAY_A ); // phpcs:ignore
        return array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() );
    }

    /**
     * Ενημερώνει πεδία (μόνο whitelisted στήλες· πάντα ορίζει updated_at). Δεν ελέγχει status.
     * Ειδικές τιμές: null → NULL, και για το attempts η τιμή '+1' → attempts = attempts + 1.
     *
     * @return bool false μόνο σε σφάλμα βάσης.
     */
    public static function update( $id, array $fields ) {
        global $wpdb;
        $set = self::build_set( $fields );
        if ( '' === $set ) {
            return false;
        }
        $res = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET $set WHERE id = %d", (int) $id ) ); // phpcs:ignore
        return false !== $res;
    }

    /**
     * Ατομική μετάβαση κατάστασης:
     * UPDATE ... SET status=$to, updated_at=now [, $fields] WHERE id=$id AND status IN ($from).
     *
     * $fields: μόνο whitelisted στήλες· null → NULL· για το attempts η τιμή '+1' → attempts = attempts + 1.
     *
     * @return bool true μόνο αν άλλαξε ακριβώς 1 γραμμή (αλλιώς το αίτημα δεν ήταν σε κατάσταση $from).
     */
    public static function transition( $id, array $from, $to, array $fields = array() ) {
        global $wpdb;
        $from = array_values( array_filter( array_map( 'strval', $from ), 'strlen' ) );
        if ( ! $from ) {
            return false;
        }
        unset( $fields['status'] ); // το $to κερδίζει
        $set = self::build_set( array_merge( array( 'status' => (string) $to ), $fields ) );

        $in  = implode( ',', array_fill( 0, count( $from ), '%s' ) );
        $sql = $wpdb->prepare(
            'UPDATE ' . self::table() . " SET $set WHERE id = %d AND status IN ($in)", // phpcs:ignore
            array_merge( array( (int) $id ), $from )
        );
        return 1 === $wpdb->query( $sql );
    }

    /**
     * Λίστα αιτημάτων. Κλειδιά $args: status (string|array), method, order_id, limit (20), offset (0),
     * orderby (id|created_at|updated_at|status, default id), order (ASC|DESC, default DESC).
     */
    public static function query( array $args ) {
        global $wpdb;
        list( $where, $params ) = self::build_where( $args );

        $orderby = ( isset( $args['orderby'] ) && in_array( $args['orderby'], array( 'id', 'created_at', 'updated_at', 'status' ), true ) ) ? $args['orderby'] : 'id';
        $order   = ( isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';
        $limit   = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 20;
        $offset  = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

        $sql = 'SELECT * FROM ' . self::table() . $where . " ORDER BY $orderby $order" . ( 'id' === $orderby ? '' : ' , id DESC' ) . ' LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore
        return array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() );
    }

    /** Πλήθος αιτημάτων με τα ίδια φίλτρα της query() (status, method, order_id). */
    public static function count( array $args ) {
        global $wpdb;
        list( $where, $params ) = self::build_where( $args );
        $sql = 'SELECT COUNT(*) FROM ' . self::table() . $where;
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore
        }
        return (int) $wpdb->get_var( $sql ); // phpcs:ignore
    }

    /** status => πλήθος (μόνο για statuses που υπάρχουν στον πίνακα). */
    public static function count_by_status() {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . self::table() . ' GROUP BY status', ARRAY_A ); // phpcs:ignore
        $out  = array();
        foreach ( $rows ? $rows : array() as $r ) {
            $out[ $r['status'] ] = (int) $r['n'];
        }
        return $out;
    }

    // ---------------------------------------------------------------------------------------------

    /** Κρατά μόνο whitelisted στήλες και κάνει JSON encode τα items/shipper_details όταν είναι arrays. */
    private static function prepare_fields( array $fields ) {
        $out = array();
        foreach ( $fields as $col => $val ) {
            if ( ! in_array( $col, self::COLUMNS, true ) ) {
                continue;
            }
            if ( in_array( $col, self::JSON_COLUMNS, true ) && is_array( $val ) ) {
                $val = wp_json_encode( $val, JSON_UNESCAPED_UNICODE );
            }
            $out[ $col ] = $val;
        }
        return $out;
    }

    /** Χτίζει το «col = %s, ...» (ήδη prepared) για UPDATE. Βάζει πάντα updated_at. */
    private static function build_set( array $fields ) {
        global $wpdb;
        $fields = self::prepare_fields( $fields );
        $fields['updated_at'] = current_time( 'mysql' );
        $parts = array();
        foreach ( $fields as $col => $val ) {
            if ( null === $val ) {
                $parts[] = "`$col` = NULL";
            } elseif ( 'attempts' === $col && '+1' === $val ) {
                $parts[] = '`attempts` = `attempts` + 1';
            } else {
                $parts[] = $wpdb->prepare( "`$col` = %s", $val ); // phpcs:ignore
            }
        }
        return implode( ', ', $parts );
    }

    /** @return array [ ' WHERE ...', $params ] */
    private static function build_where( array $args ) {
        $where  = array();
        $params = array();
        if ( isset( $args['status'] ) && '' !== $args['status'] && array() !== $args['status'] ) {
            $st      = array_values( array_map( 'strval', (array) $args['status'] ) );
            $where[] = 'status IN (' . implode( ',', array_fill( 0, count( $st ), '%s' ) ) . ')';
            $params  = array_merge( $params, $st );
        }
        if ( ! empty( $args['method'] ) ) {
            $where[]  = 'method = %s';
            $params[] = (string) $args['method'];
        }
        if ( ! empty( $args['order_id'] ) ) {
            $where[]  = 'order_id = %d';
            $params[] = (int) $args['order_id'];
        }
        return array( $where ? ' WHERE ' . implode( ' AND ', $where ) : '', $params );
    }

    /** Αποκωδικοποιεί τα JSON πεδία σε arrays και κάνει cast τα int πεδία. */
    private static function hydrate( array $row ) {
        foreach ( self::JSON_COLUMNS as $col ) {
            $dec         = isset( $row[ $col ] ) ? json_decode( (string) $row[ $col ], true ) : null;
            $row[ $col ] = is_array( $dec ) ? $dec : array();
        }
        foreach ( self::INT_COLUMNS as $col ) {
            if ( isset( $row[ $col ] ) ) {
                $row[ $col ] = (int) $row[ $col ];
            }
        }
        return $row;
    }
}
