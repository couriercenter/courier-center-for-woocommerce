<?php
/**
 * Σημεία αποστολής: η Έδρα (κεντρικός κωδικός) και οι υποκωδικοί του πελάτη.
 *
 * Κάθε υποκωδικός της Courier Center είναι διαφορετικό σημείο παραλαβής. Στο API
 * ο κωδικός του σημείου μπαίνει στο Shipper.CarrierBillingAccount και ο κωδικός
 * χρέωσης στο Requestor.CarrierBillingAccount (BillTo = Requestor).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Pickup_Points {

    const OPTION         = 'cc_wc_pickup_points';
    const DEFAULT_OPTION = 'cc_wc_default_pickup_point';

    /**
     * Η Έδρα, από τις υπάρχουσες ρυθμίσεις (κεντρικός κωδικός + στοιχεία αποστολέα).
     *
     * @return array
     */
    public static function get_main_point() {
        $code = trim( (string) get_option( 'cc_wc_billing_account', '' ) );

        return array(
            'code'      => $code,
            'label'     => 'Έδρα',
            'requestor' => $code,
            'bill_to'   => 'self',
            'name'      => (string) get_option( 'cc_wc_shipper_name', '' ),
            'address'   => (string) get_option( 'cc_wc_shipper_address', '' ),
            'postal'    => (string) get_option( 'cc_wc_shipper_postal_code', '' ),
            'city'      => (string) get_option( 'cc_wc_shipper_city', '' ),
            'phone'     => (string) get_option( 'cc_wc_shipper_phone', '' ),
            'is_main'   => true,
        );
    }

    /**
     * Οι υποκωδικοί όπως αποθηκεύτηκαν, με υπολογισμένο κωδικό χρέωσης.
     *
     * @return array[]
     */
    public static function get_sub_points() {
        $rows = get_option( self::OPTION, array() );
        if ( ! is_array( $rows ) ) {
            return array();
        }

        $main_code = self::get_main_point()['code'];
        $points    = array();

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || empty( $row['code'] ) ) {
                continue;
            }
            $code    = (string) $row['code'];
            $bill_to = ( isset( $row['bill_to'] ) && 'main' === $row['bill_to'] ) ? 'main' : 'self';
            $label   = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';

            $points[] = array(
                'code'      => $code,
                'label'     => '' === $label ? $code : $label,
                // Χρέωση στον κεντρικό μόνο όταν υπάρχει κεντρικός κωδικός
                'requestor' => ( 'main' === $bill_to && '' !== $main_code ) ? $main_code : $code,
                'bill_to'   => $bill_to,
                'name'      => (string) ( $row['name'] ?? '' ),
                'address'   => (string) ( $row['address'] ?? '' ),
                'postal'    => (string) ( $row['postal'] ?? '' ),
                'city'      => (string) ( $row['city'] ?? '' ),
                'phone'     => (string) ( $row['phone'] ?? '' ),
                'is_main'   => false,
            );
        }

        return $points;
    }

    /**
     * Όλα τα σημεία — πρώτη πάντα η Έδρα.
     *
     * @return array[]
     */
    public static function get_points() {
        return array_merge( array( self::get_main_point() ), self::get_sub_points() );
    }

    public static function has_sub_points() {
        return ! empty( self::get_sub_points() );
    }

    /**
     * @param string $code
     * @return array|null
     */
    public static function get_point( $code ) {
        $code = (string) $code;
        if ( '' === $code ) {
            return null;
        }
        foreach ( self::get_points() as $point ) {
            if ( $point['code'] === $code ) {
                return $point;
            }
        }
        return null;
    }

    /**
     * Η προεπιλογή των ρυθμίσεων αν υπάρχει ακόμα, αλλιώς η Έδρα.
     *
     * @return string
     */
    public static function get_default_code() {
        $default = (string) get_option( self::DEFAULT_OPTION, '' );
        if ( '' !== $default && self::get_point( $default ) ) {
            return $default;
        }
        return self::get_main_point()['code'];
    }

    /**
     * Κωδικός από request: ο ίδιος αν είναι γνωστό σημείο, αλλιώς ''.
     *
     * @param string $raw
     * @return string
     */
    public static function normalize_code( $raw ) {
        $raw = trim( (string) $raw );
        return ( '' !== $raw && self::get_point( $raw ) ) ? $raw : '';
    }

    public static function display_label( array $point ) {
        return $point['label'] . ' (' . $point['code'] . ')';
    }

    /**
     * Καθάρισμα των γραμμών της φόρμας ρυθμίσεων. Pure — τα μηνύματα επιστρέφονται,
     * δεν καταγράφονται εδώ.
     *
     * @param mixed  $rows      Γραμμές όπως ήρθαν από τη φόρμα (unslashed).
     * @param string $main_code Ο κεντρικός κωδικός που ισχύει σε αυτή την αποθήκευση.
     * @return array{rows: array[], errors: string[]}
     */
    public static function sanitize_rows( $rows, $main_code ) {
        $clean     = array();
        $errors    = array();
        $seen      = array();
        $main_code = trim( (string) $main_code );

        if ( ! is_array( $rows ) ) {
            return array( 'rows' => array(), 'errors' => array() );
        }

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $code = trim( sanitize_text_field( (string) ( $row['code'] ?? '' ) ) );
            if ( '' === $code ) {
                continue;
            }
            if ( $code === $main_code ) {
                $errors[] = sprintf( 'Ο υποκωδικός %s είναι ίδιος με τον κεντρικό κωδικό και αγνοήθηκε.', $code );
                continue;
            }
            if ( isset( $seen[ $code ] ) ) {
                $errors[] = sprintf( 'Ο υποκωδικός %s υπάρχει δύο φορές· κρατήθηκε μόνο η πρώτη γραμμή.', $code );
                continue;
            }
            $seen[ $code ] = true;

            $label = trim( sanitize_text_field( (string) ( $row['label'] ?? '' ) ) );
            $line  = array(
                'code'    => $code,
                'label'   => '' === $label ? $code : $label,
                'bill_to' => ( isset( $row['bill_to'] ) && 'main' === $row['bill_to'] ) ? 'main' : 'self',
            );
            foreach ( array( 'name', 'address', 'postal', 'city', 'phone' ) as $field ) {
                $line[ $field ] = trim( sanitize_text_field( (string) ( $row[ $field ] ?? '' ) ) );
            }
            $clean[] = $line;
        }

        return array( 'rows' => $clean, 'errors' => $errors );
    }
}
