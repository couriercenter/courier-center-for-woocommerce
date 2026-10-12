<?php
/**
 * Επιστροφές: καθαροί κανόνες (χωρίς βάση, χωρίς API).
 *
 * Σταθερές μεθόδων/καταστάσεων, κανόνες τηλεφώνων, μεγέθη BOX NOW locker, ημερομηνίες/διάστημα
 * Pickup, χρέωση επιστροφής,
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
    // Pickup (παραλαβή από τη διεύθυνση του πελάτη)
    const PICKUP_HOUR_MIN     = 9;
    const PICKUP_HOUR_MAX     = 17;
    const PICKUP_MIN_HOURS    = 4;
    const PICKUP_DAYS         = 10;
    const PICKUP_DEFAULT_FROM = '09:00';
    const PICKUP_DEFAULT_TO   = '17:00';
    const COURIER_NOTES_MAX   = 200;

    const MSG_PICKUP_DATE   = 'Η ημερομηνία παραλαβής δεν είναι διαθέσιμη. Επιλέξτε μία από τη λίστα.';
    const MSG_PICKUP_HALF   = 'Συμπληρώστε και τα δύο άκρα του χρονικού διαστήματος ή κανένα.';
    const MSG_PICKUP_WINDOW = 'Το διάστημα παραλαβής πρέπει να είναι τουλάχιστον 4 ώρες, μεταξύ 09:00 και 17:00.';

    const DAY_NAMES = array( 1 => 'Δευτέρα', 2 => 'Τρίτη', 3 => 'Τετάρτη', 4 => 'Πέμπτη', 5 => 'Παρασκευή', 6 => 'Σάββατο', 7 => 'Κυριακή' );

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
    // ── Pickup ───────────────────────────────────────────────────────────────

    /** Η επόμενη ημέρα μετά το $now που δεν είναι Σάββατο/Κυριακή (Y-m-d, στη ζώνη του $now). */
    public static function next_working_day( DateTimeImmutable $now ) {
        $d = $now->setTime( 0, 0 )->modify( '+1 day' );
        while ( (int) $d->format( 'N' ) >= 6 ) {
            $d = $d->modify( '+1 day' );
        }
        return $d->format( 'Y-m-d' );
    }

    /** Οι PICKUP_DAYS εργάσιμες από την επόμενη εργάσιμη (Y-m-d). Αργίες δεν αφαιρούνται. */
    public static function pickup_dates( DateTimeImmutable $now ) {
        $d   = new DateTimeImmutable( self::next_working_day( $now ), $now->getTimezone() );
        $out = array();
        while ( count( $out ) < self::PICKUP_DAYS ) {
            if ( (int) $d->format( 'N' ) < 6 ) {
                $out[] = $d->format( 'Y-m-d' );
            }
            $d = $d->modify( '+1 day' );
        }
        return $out;
    }

    public static function validate_pickup_date( $date, DateTimeImmutable $now ) {
        return in_array( (string) $date, self::pickup_dates( $now ), true )
            ? true
            : new WP_Error( 'cc_return_pickup_date', self::MSG_PICKUP_DATE );
    }

    /** Και τα δύο κενά = χωρίς διάστημα. Αλλιώς HH:00, 09–17, τουλάχιστον 4 ώρες. */
    public static function validate_pickup_window( $from, $to ) {
        $from = trim( (string) $from );
        $to   = trim( (string) $to );
        if ( '' === $from && '' === $to ) {
            return true;
        }
        if ( '' === $from || '' === $to ) {
            return new WP_Error( 'cc_return_pickup_half', self::MSG_PICKUP_HALF );
        }
        if ( 1 !== preg_match( '/^\d{2}:00$/', $from ) || 1 !== preg_match( '/^\d{2}:00$/', $to ) ) {
            return new WP_Error( 'cc_return_pickup_window', self::MSG_PICKUP_WINDOW );
        }
        $f = (int) substr( $from, 0, 2 );
        $t = (int) substr( $to, 0, 2 );
        if ( $f < self::PICKUP_HOUR_MIN || $t > self::PICKUP_HOUR_MAX || $t - $f < self::PICKUP_MIN_HOURS ) {
            return new WP_Error( 'cc_return_pickup_window', self::MSG_PICKUP_WINDOW );
        }
        return true;
    }

    public static function pickup_hours_from() {
        return self::hours( self::PICKUP_HOUR_MIN, self::PICKUP_HOUR_MAX - self::PICKUP_MIN_HOURS );
    }

    public static function pickup_hours_to() {
        return self::hours( self::PICKUP_HOUR_MIN + self::PICKUP_MIN_HOURS, self::PICKUP_HOUR_MAX );
    }

    private static function hours( $first, $last ) {
        $out = array();
        for ( $h = $first; $h <= $last; $h++ ) {
            $out[] = sprintf( '%02d:00', $h );
        }
        return $out;
    }

    /**
     * BillTo της επιστροφής: Έδρα ή σημείο με χρέωση στον κεντρικό → Requestor· αλλιώς ο παραλήπτης
     * (λογαριασμός επιστροφών) → Consignee.
     *
     * @param array|null $point     Σημείο από CC_Pickup_Points::get_point() (code, bill_to).
     * @param string     $main_code cc_wc_billing_account
     */
    public static function bill_to( $point, $main_code ) {
        if ( ! is_array( $point ) || empty( $point['code'] ) ) {
            return 'Requestor';
        }
        if ( self::codes_equal( $point['code'], $main_code ) || ( isset( $point['bill_to'] ) && 'main' === $point['bill_to'] ) ) {
            return 'Requestor';
        }
        return 'Consignee';
    }

    /** «Τρίτη 13/10» ('' για άκυρη ημερομηνία). */
    public static function pickup_date_label( $date ) {
        $d = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date );
        if ( ! $d || $d->format( 'Y-m-d' ) !== (string) $date ) {
            return '';
        }
        return self::DAY_NAMES[ (int) $d->format( 'N' ) ] . ' ' . $d->format( 'd/m' );
    }

    /** «Τρίτη 13/10/2026, 09:00–13:00» ή «…, οποιαδήποτε ώρα 09:00–17:00» ('' χωρίς έγκυρη ημερομηνία). */
    public static function pickup_text( $date, $from, $to ) {
        $label = self::pickup_date_label( $date );
        if ( '' === $label ) {
            return '';
        }
        $label .= '/' . substr( (string) $date, 0, 4 );
        $from   = trim( (string) $from );
        $to     = trim( (string) $to );
        if ( '' === $from || '' === $to ) {
            return $label . ', οποιαδήποτε ώρα ' . self::PICKUP_DEFAULT_FROM . '–' . self::PICKUP_DEFAULT_TO;
        }
        return $label . ', ' . $from . '–' . $to;
    }
}
