<?php
/**
 * Επιστροφές: καθαροί κανόνες (χωρίς βάση, χωρίς API).
 *
 * Σταθερές μεθόδων/καταστάσεων, κανόνες τηλεφώνων, μεγέθη BOX NOW locker,
 * σύγκριση κωδικών λογαριασμού και η ένδειξη «ΑΣΚΟΠΗ ΠΡΟΣΕΛΕΥΣΗ».
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Rules {

    // Μέθοδοι επιστροφής
    const METHOD_DROPOFF = 'COURIER_CENTER_DROPOFF';
    const METHOD_PICKUP  = 'COURIER_CENTER_PICKUP';
    const METHOD_BOXNOW  = 'BOXNOW';
    const METHODS_ORDER  = array( self::METHOD_DROPOFF, self::METHOD_PICKUP, self::METHOD_BOXNOW );

    // Καταστάσεις αιτήματος επιστροφής
    const ST_PENDING  = 'pending_approval';
    const ST_APPROVED = 'approved';
    const ST_CREATING = 'creating';
    const ST_VERIFY   = 'needs_verification';
    const ST_CREATED  = 'shipment_created';
    const ST_FAILED   = 'creation_failed';
    const ST_REJECTED = 'rejected';

    // Αποστολέας Drop Off (σταθερός, από το spike του API)
    const DROPOFF_SHIPPER = array(
        'ZipCode' => '91919',
        'Area'    => 'DROPOFF',
        'City'    => 'DROPOFF',
    );

    // Σταθερός αποστολέας για όλες τις επιστροφές BOX NOW (επιβεβαιωμένο από Courier Center).
    const BOXNOW_SHIPPER = array(
        'Address' => 'BOX NOW ATH',
        'ZipCode' => '99981',
    );

    // Μεγέθη BOX NOW locker (reference στο LockerPickupInfo)
    const LOCKER_SIZES = array(
        'small'  => array( 'reference' => '1', 'label' => 'Μικρό', 'dims' => '8 × 45 × 60 cm' ),
        'medium' => array( 'reference' => '2', 'label' => 'Μεσαίο', 'dims' => '17 × 45 × 60 cm' ),
        'large'  => array( 'reference' => '3', 'label' => 'Μεγάλο', 'dims' => '36 × 45 × 60 cm' ),
    );

    // CollectionStatus του API
    const COLLECTION_COMPLETED = 10;
    const COLLECTION_CANCELLED = 14;

    const ASKOPI_STATUS = 'ΑΣΚΟΠΗ ΠΡΟΣΕΛΕΥΣΗ';

    const MSG_PHONE  = 'Για τη δημιουργία της επιστροφής απαιτείται τουλάχιστον ένας έγκυρος αριθμός τηλεφώνου (σταθερό ή κινητό).';
    const MSG_MOBILE = 'Για επιστροφή μέσω BOX NOW απαιτείται έγκυρο ελληνικό κινητό τηλέφωνο (69XXXXXXXX).';

    /**
     * Μόνο ψηφία· κόβει το πρόθεμα +30 / 0030.
     */
    public static function normalize_phone( $raw ) {
        $d = preg_replace( '/\D+/', '', (string) $raw );
        if ( 0 === strpos( $d, '0030' ) ) {
            $d = substr( $d, 4 );
        } elseif ( 0 === strpos( $d, '30' ) && 12 === strlen( $d ) ) {
            $d = substr( $d, 2 );
        }
        return $d;
    }

    public static function is_gr_mobile( $raw ) {
        return 1 === preg_match( '/^69\d{8}$/', (string) $raw );
    }

    public static function is_gr_landline( $raw ) {
        return 1 === preg_match( '/^2\d{9}$/', (string) $raw );
    }

    /**
     * Drop Off / Pickup: κινητό ή σταθερό (προτιμάται το κινητό)· BOX NOW: μόνο κινητό.
     *
     * @return array|WP_Error array( 'phone' => string )
     */
    public static function pick_phone( $method, $mobile, $landline ) {
        $m = self::normalize_phone( $mobile );
        $l = self::normalize_phone( $landline );

        if ( self::METHOD_BOXNOW === $method ) {
            return self::is_gr_mobile( $m ) ? array( 'phone' => $m ) : new WP_Error( 'cc_return_mobile', self::MSG_MOBILE );
        }
        if ( self::is_gr_mobile( $m ) ) {
            return array( 'phone' => $m );
        }
        if ( self::is_gr_landline( $l ) ) {
            return array( 'phone' => $l );
        }
        // Σταθερό γραμμένο στο πεδίο κινητού (ή αντίστροφα) — μετράει κι αυτό
        if ( self::is_gr_landline( $m ) ) {
            return array( 'phone' => $m );
        }
        if ( self::is_gr_mobile( $l ) ) {
            return array( 'phone' => $l );
        }
        return new WP_Error( 'cc_return_phone', self::MSG_PHONE );
    }

    /**
     * Το reference του locker για το μέγεθος ('' αν άγνωστο).
     */
    public static function locker_reference( $size ) {
        $size = (string) $size;
        return isset( self::LOCKER_SIZES[ $size ] ) ? self::LOCKER_SIZES[ $size ]['reference'] : '';
    }

    /**
     * Ισότητα κωδικών λογαριασμού ανεξάρτητα από τα μηδενικά: 100-0120-0001 == 100-120-0001.
     */
    public static function codes_equal( $a, $b ) {
        $pa = explode( '-', trim( (string) $a ) );
        $pb = explode( '-', trim( (string) $b ) );
        if ( 3 !== count( $pa ) || 3 !== count( $pb ) ) {
            return false;
        }
        foreach ( array( 0, 1, 2 ) as $i ) {
            if ( ! ctype_digit( $pa[ $i ] ) || ! ctype_digit( $pb[ $i ] ) || (int) $pa[ $i ] !== (int) $pb[ $i ] ) {
                return false;
            }
        }
        return true;
    }

    public static function is_valid_account_code( $code ) {
        return 1 === preg_match( '/^\d{3}-\d{3,4}-\d{4}$/', trim( (string) $code ) );
    }

    /**
     * Κεφαλαία, χωρίς τόνους/διαλυτικά, λατινικά «ομοιόμορφα» → ελληνικά.
     * Μόνο mbstring (το intl μπορεί να λείπει).
     */
    private static function fold( $text ) {
        $s = mb_strtoupper( (string) $text, 'UTF-8' );
        if ( class_exists( 'Normalizer' ) ) {
            $s = preg_replace( '/\p{Mn}+/u', '', Normalizer::normalize( $s, Normalizer::FORM_D ) );
        }
        return strtr( $s, array(
            // Συνδυαστικά σημεία (π.χ. ΐ/ΰ μετά το mb_strtoupper)
            "\u{0301}" => '', "\u{0308}" => '',
            'Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ώ' => 'Ω', 'Ϊ' => 'Ι', 'Ϋ' => 'Υ',
            // Λατινικά που μοιάζουν με ελληνικά
            'A' => 'Α', 'B' => 'Β', 'E' => 'Ε', 'H' => 'Η', 'I' => 'Ι', 'K' => 'Κ', 'M' => 'Μ', 'N' => 'Ν',
            'O' => 'Ο', 'P' => 'Ρ', 'T' => 'Τ', 'X' => 'Χ', 'Y' => 'Υ', 'Z' => 'Ζ',
        ) );
    }

    public static function contains_askopi( $text ) {
        return null !== $text && '' !== (string) $text && false !== mb_strpos( self::fold( $text ), 'ΑΣΚΟΠΗ', 0, 'UTF-8' );
    }

    /**
     * Η κατάσταση που εμφανίζεται: «ΑΣΚΟΠΗ ΠΡΟΣΕΛΕΥΣΗ» όταν η παράδοση ολοκληρώθηκε (CollectionStatus 10)
     * και κάποιο Reference περιέχει ΑΣΚΟΠΗ· αλλιώς το ShipmentStatusDesc, όπως στις κανονικές αποστολές.
     */
    public static function display_status( $shipment_status, $shipment_status_desc, $collection_status, array $refs ) {
        if ( '' !== (string) $collection_status && (int) $collection_status === self::COLLECTION_COMPLETED ) {
            foreach ( $refs as $r ) {
                if ( self::contains_askopi( $r ) ) {
                    return self::ASKOPI_STATUS;
                }
            }
        }
        return (string) $shipment_status_desc;
    }

    /**
     * Η πρώτη ενεργή μέθοδος κατά METHODS_ORDER ('' αν καμία).
     */
    public static function default_method( array $enabled ) {
        foreach ( self::METHODS_ORDER as $m ) {
            if ( in_array( $m, $enabled, true ) ) {
                return $m;
            }
        }
        return '';
    }
}
