<?php
/**
 * Επιστροφές: χτίσιμο του payload δημιουργίας αποστολής επιστροφής.
 *
 * Καθαρή κλάση (χωρίς βάση, χωρίς API): επικυρώνει το αίτημα και επιστρέφει το
 * payload για Drop Off, Pickup ή BOX NOW. Η αποστολή γίνεται από την υπηρεσία επιστροφών.
 * Το payload της κανονικής αποστολής (CC_Shipment_Builder) δεν αγγίζεται.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Return_Payload {

    /**
     * @param array      $req       Γραμμή αιτήματος: method, original_awb, reference_token, return_account_code,
     *                              requestor_account, bill_to, pickup_date, pickup_time_from, pickup_time_to,
     *                              shipper_details{name,address,city,postcode,phone,courier_notes}, locker_reference, order_id.
     * @param array      $ctx       array( 'UserAlias', 'CredentialValue', 'ApiKey' )
     * @param float|int  $weight_kg Βάρος του (ενός) τεμαχίου από τις ρυθμίσεις.
     * @return array|WP_Error
     */
    public static function build( array $req, array $ctx, $weight_kg ) {
        $method = isset( $req['method'] ) ? (string) $req['method'] : '';

        // Σειρά ελέγχων: μέθοδος, awb, token, λογαριασμός, όνομα, διεύθυνση (Drop Off/Pickup), πόλη/ΤΚ/ημερομηνία/διάστημα (Pickup), τηλέφωνο, locker
        if ( ! in_array( $method, array( CC_Returns_Rules::METHOD_DROPOFF, CC_Returns_Rules::METHOD_PICKUP, CC_Returns_Rules::METHOD_BOXNOW ), true ) ) {
            return new WP_Error( 'cc_return_method_unavailable', 'Η επιλεγμένη μέθοδος επιστροφής δεν είναι διαθέσιμη.' );
        }
        $pickup = CC_Returns_Rules::METHOD_PICKUP === $method;

        $awb = isset( $req['original_awb'] ) ? trim( (string) $req['original_awb'] ) : '';
        if ( '' === $awb ) {
            return new WP_Error( 'cc_return_missing_awb', 'Λείπει ο αριθμός αποστολής της αρχικής παραγγελίας.' );
        }

        $token = isset( $req['reference_token'] ) ? trim( (string) $req['reference_token'] ) : '';
        if ( '' === $token ) {
            return new WP_Error( 'cc_return_missing_token', 'Λείπει ο κωδικός αναφοράς του αιτήματος επιστροφής.' );
        }

        $account = isset( $req['return_account_code'] ) ? trim( (string) $req['return_account_code'] ) : '';
        if ( ! CC_Returns_Rules::is_valid_account_code( $account ) ) {
            return new WP_Error( 'cc_return_invalid_account', 'Ο λογαριασμός επιστροφών δεν είναι έγκυρος.' );
        }

        $requestor = isset( $req['requestor_account'] ) ? trim( (string) $req['requestor_account'] ) : '';
        if ( '' === $requestor ) {
            return new WP_Error( 'cc_return_missing_requestor', 'Λείπει ο κωδικός πελάτη (Requestor) του καταστήματος.' );
        }

        $details = ( isset( $req['shipper_details'] ) && is_array( $req['shipper_details'] ) ) ? $req['shipper_details'] : array();
        $name    = isset( $details['name'] ) ? trim( (string) $details['name'] ) : '';
        if ( '' === $name ) {
            return new WP_Error( 'cc_return_missing_name', 'Λείπει το όνομα του αποστολέα της επιστροφής.' );
        }

        $address = isset( $details['address'] ) ? trim( (string) $details['address'] ) : '';
        if ( CC_Returns_Rules::METHOD_BOXNOW !== $method && '' === $address ) {
            return new WP_Error( 'cc_return_missing_address', 'Λείπει η διεύθυνση του αποστολέα της επιστροφής.' );
        }

        $city     = isset( $details['city'] ) ? trim( (string) $details['city'] ) : '';
        $postcode = isset( $details['postcode'] ) ? preg_replace( '/\s+/', '', (string) $details['postcode'] ) : '';
        $date     = isset( $req['pickup_date'] ) ? (string) $req['pickup_date'] : '';
        $from     = isset( $req['pickup_time_from'] ) ? trim( (string) $req['pickup_time_from'] ) : '';
        $to       = isset( $req['pickup_time_to'] ) ? trim( (string) $req['pickup_time_to'] ) : '';
        if ( $pickup ) {
            if ( '' === $city ) {
                return new WP_Error( 'cc_return_missing_city', 'Λείπει η πόλη της διεύθυνσης παραλαβής.' );
            }
            if ( 1 !== preg_match( '/^\d{5}$/', $postcode ) ) {
                return new WP_Error( 'cc_return_invalid_postcode', 'Ο ΤΚ της διεύθυνσης παραλαβής πρέπει να έχει 5 ψηφία.' );
            }
            $d = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
            if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) {
                return new WP_Error( 'cc_return_pickup_date', CC_Returns_Rules::MSG_PICKUP_DATE );
            }
            $window = CC_Returns_Rules::validate_pickup_window( $from, $to );
            if ( is_wp_error( $window ) ) {
                return $window;
            }
        }

        // Το τηλέφωνο έχει ήδη επιλεγεί στην υποβολή· ξαναελέγχεται και κανονικοποιείται
        $phone = isset( $details['phone'] ) ? (string) $details['phone'] : '';
        if ( CC_Returns_Rules::METHOD_BOXNOW === $method ) {
            $picked = CC_Returns_Rules::pick_phone( $method, $phone, '' );
        } else {
            $picked = CC_Returns_Rules::pick_phone( $method, $phone, $phone );
        }
        if ( is_wp_error( $picked ) ) {
            return $picked;
        }

        $locker = '';
        if ( CC_Returns_Rules::METHOD_BOXNOW === $method ) {
            $locker = isset( $req['locker_reference'] ) && is_string( $req['locker_reference'] ) ? $req['locker_reference'] : '';
            if ( ! in_array( $locker, array( '1', '2', '3' ), true ) ) {
                return new WP_Error( 'cc_return_locker_size', 'Επιλέξτε μέγεθος BOX NOW locker (Μικρό, Μεσαίο ή Μεγάλο).' );
            }
        }

        $shipper = array(
            'CompanyName' => $name,
            'ContactName' => $name,
            'Country'     => 'GR',
            'Mobile1'     => $picked['phone'],
        );
        if ( CC_Returns_Rules::METHOD_BOXNOW === $method ) {
            $shipper = array_merge( $shipper, CC_Returns_Rules::BOXNOW_SHIPPER );
        } elseif ( $pickup ) {
            $shipper['Address'] = $address;
            $shipper['City']    = $city;
            $shipper['Area']    = $city;
            $shipper['ZipCode'] = $postcode;
        } else {
            $shipper['Address'] = $address;
            $shipper            = array_merge( $shipper, CC_Returns_Rules::DROPOFF_SHIPPER );
        }

        // Όπως το τεμάχιο της κανονικής αποστολής, αλλά ΕΝΑ μόνο (η αρχική μπορεί να είχε πολλά)
        $item = array(
            'GoodsType'        => 'NoDocs',
            'Content'          => 'ΔΕΜΑΤΑ',
            'IsDangerousGoods' => false,
            'IsDryIce'         => false,
            'IsFragile'        => false,
            'Weight'           => array( 'Unit' => 'kg', 'Value' => (float) $weight_kg ),
        );

        $payload = array(
            'Context'      => array(
                'UserAlias'       => isset( $ctx['UserAlias'] ) ? $ctx['UserAlias'] : '',
                'CredentialValue' => isset( $ctx['CredentialValue'] ) ? $ctx['CredentialValue'] : '',
                'ApiKey'          => isset( $ctx['ApiKey'] ) ? $ctx['ApiKey'] : '',
            ),
            'shipmentDate' => date( 'Y-m-d' ),
            'comments'     => 'ΕΠΙΣΤΡΟΦΗ WC-' . (int) ( isset( $req['order_id'] ) ? $req['order_id'] : 0 ),
            'Requestor'    => array( 'CarrierBillingAccount' => $requestor ),
            'Shipper'      => $shipper,
            // Παραλήπτης = ο λογαριασμός επιστροφών (μόνο CarrierBillingAccount)
            'Consignee'    => array( 'CarrierBillingAccount' => $account ),
            'BillTo'       => ( isset( $req['bill_to'] ) && 'Consignee' === $req['bill_to'] ) ? 'Consignee' : 'Requestor',
            'Reference1'   => $awb,
            'Reference2'   => $token,
            'NoOfItems'    => 1,
            'Items'        => array( $item ),
        );

        if ( CC_Returns_Rules::METHOD_BOXNOW === $method ) {
            $payload['LockerPickupInfo'] = array( 'reference' => $locker );
        }

        if ( $pickup ) {
            // Η ημερομηνία παραλαβής πάει στο ShipmentDate (επιβεβαιωμένο spike 2026-10-12)· χωρίς διάστημα → όλο το ωράριο
            unset( $payload['shipmentDate'] );
            $payload['ShipmentDate']           = $date;
            $notes                             = isset( $details['courier_notes'] ) ? trim( preg_replace( '/\s+/u', ' ', (string) $details['courier_notes'] ) ) : '';
            $payload['CollectionInstructions'] = array(
                'TimeFrom' => '' !== $from ? $from : CC_Returns_Rules::PICKUP_DEFAULT_FROM,
                'TimeTo'   => '' !== $to ? $to : CC_Returns_Rules::PICKUP_DEFAULT_TO,
                'Comments' => mb_substr( $notes, 0, CC_Returns_Rules::COURIER_NOTES_MAX, 'UTF-8' ),
            );
        }

        return $payload;
    }
}
