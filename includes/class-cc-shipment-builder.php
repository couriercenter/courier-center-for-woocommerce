<?php
/**
 * Shipment Builder - Μετατρέπει WooCommerce orders σε Courier Center API payloads
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Shipment_Builder {

    /**
     * Service type → BasicService code mapping
     */
    const SERVICE_CODES = array(
        'next_day'    => '211',
        'same_day_3h' => '031',
        'same_day_5h' => '051',
    );

    /**
     * Default weight when order has no products with weight (kg)
     */
    const DEFAULT_WEIGHT_KG = 1.0;

    /**
     * The order being processed
     *
     * @var WC_Order
     */
    private $order;

    /**
     * Plugin settings cache
     *
     * @var array
     */
    private $settings;

    /**
     * Number of parcels
     *
     * @var int
     */
    private $parcel_count = 1;

    /**
     * Λήξη κλειδώματος δημιουργίας voucher (δευτερόλεπτα). Πάνω από το timeout
     * του API (60s), ώστε να λήγει μόνο κλείδωμα αίτησης που διακόπηκε βίαια.
     */
    const VOUCHER_LOCK_TTL = 180;

    /**
     * Tokens των κλειδωμάτων που κρατά το τρέχον request (order_id => token)
     *
     * @var array
     */
    private static $voucher_locks = array();

    /**
     * Το σημείο αποστολής που επιλέχθηκε με use_pickup_point() (null = Έδρα).
     *
     * @var array|null
     */
    private $pickup_point = null;

    /**
     * Constructor
     */
    public function __construct( WC_Order $order, array $settings = array(), int $parcel_count = 1 ) {
        $this->order        = $order;
        $this->settings     = empty( $settings ) ? $this->load_settings() : $settings;
        // Custom $settings χωρίς τα νέα κλειδιά: ίδιος κωδικός με το billing_account (όπως σήμερα)
        foreach ( array( 'requestor_account', 'shipper_account' ) as $key ) {
            if ( ! isset( $this->settings[ $key ] ) ) {
                $this->settings[ $key ] = $this->settings['billing_account'] ?? '';
            }
        }
        $this->parcel_count = max( 1, $parcel_count );
    }

    /**
     * Τα instance IDs των shipping methods μιας παραγγελίας.
     *
     * @return int[]
     */
    public static function order_shipping_instance_ids( WC_Order $order ) {
        $ids = array();
        foreach ( $order->get_shipping_methods() as $item ) {
            $instance_id = method_exists( $item, 'get_instance_id' ) ? (int) $item->get_instance_id() : 0;
            if ( $instance_id ) {
                $ids[] = $instance_id;
            }
        }
        return $ids;
    }

    /**
     * Διαχειρίζεται το plugin αυτή την παραγγελία βάσει της μεθόδου αποστολής;
     *
     * Αν δεν έχει ρυθμιστεί καμία μέθοδος (κενό option) επιστρέφει true ("όλες"),
     * ώστε να μη σπάσει υπάρχουσα ροή. Το «τουλάχιστον 1» επιβάλλεται στο save.
     */
    public static function is_handled_order( WC_Order $order ) {
        $handled = array_map( 'absint', (array) get_option( 'cc_wc_handled_shipping_methods', array() ) );
        if ( empty( $handled ) ) {
            return true;
        }
        return (bool) array_intersect( self::order_shipping_instance_ids( $order ), $handled );
    }

    /**
     * Κλείδωμα ανά παραγγελία για τη δημιουργία voucher, ώστε δύο ταυτόχρονες
     * αιτήσεις (δύο χρήστες, διπλό webhook πληρωμής, μαζική + χειροκίνητη) να μη
     * φτιάξουν δύο AWB.
     *
     * INSERT IGNORE αντί για add_option(): το add_option κάνει ON DUPLICATE KEY
     * UPDATE και μπορεί να επιστρέψει true και στις δύο αιτήσεις. Απευθείας SQL,
     * ώστε να μην παρεμβάλλεται object cache. Ξεκλειδώνει αυτόματα στο τέλος του
     * request (και στα wp_send_json_*, που κάνουν exit).
     *
     * @param int $order_id
     * @return bool True αν πάρθηκε το κλείδωμα
     */
    public static function acquire_voucher_lock( $order_id ) {
        global $wpdb;

        $order_id = (int) $order_id;
        $name     = 'cc_voucher_lock_' . $order_id;
        $token    = time() . '|' . wp_generate_password( 12, false );

        for ( $attempt = 0; $attempt < 2; $attempt++ ) {
            $inserted = $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                $name,
                $token
            ) );

            if ( 1 === (int) $inserted ) {
                self::$voucher_locks[ $order_id ] = $token;
                register_shutdown_function( array( __CLASS__, 'release_voucher_lock' ), $order_id );
                return true;
            }

            if ( $attempt > 0 ) {
                break;
            }

            // Υπάρχει ήδη κλείδωμα — αν έχει λήξει (αίτηση που διακόπηκε), σβήσ' το και ξαναδοκίμασε
            $current = $wpdb->get_var( $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                $name
            ) );
            if ( null === $current ) {
                continue;
            }
            $locked_at = (int) strtok( (string) $current, '|' );
            if ( time() - $locked_at < self::VOUCHER_LOCK_TTL ) {
                return false;
            }
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
                $name,
                $current
            ) );
        }

        return false;
    }

    /**
     * Ξεκλειδώνει — σβήνει μόνο το δικό μας κλείδωμα (ίδιο token), ποτέ ξένο.
     *
     * @param int $order_id
     */
    public static function release_voucher_lock( $order_id ) {
        global $wpdb;

        $order_id = (int) $order_id;
        if ( empty( self::$voucher_locks[ $order_id ] ) ) {
            return;
        }

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            'cc_voucher_lock_' . $order_id,
            self::$voucher_locks[ $order_id ]
        ) );
        unset( self::$voucher_locks[ $order_id ] );
    }

    /**
     * Load plugin settings
     */
    private function load_settings() {
        return array(
            'user_alias'       => get_option( 'cc_wc_user_alias', '' ),
            'credential_value' => get_option( 'cc_wc_credential_value', '' ),
            'api_key'          => get_option( 'cc_wc_api_key', '' ),
            'billing_account'  => get_option( 'cc_wc_billing_account', '' ),
            // Χωρίς υποκωδικό: Requestor και Shipper είναι ο κεντρικός κωδικός (όπως πάντα)
            'requestor_account' => get_option( 'cc_wc_billing_account', '' ),
            'shipper_account'   => get_option( 'cc_wc_billing_account', '' ),
            'shipper_name'     => get_option( 'cc_wc_shipper_name', '' ),
            'shipper_address'  => get_option( 'cc_wc_shipper_address', '' ),
            'shipper_postal'   => get_option( 'cc_wc_shipper_postal_code', '' ),
            'shipper_city'     => get_option( 'cc_wc_shipper_city', '' ),
            'shipper_phone'    => get_option( 'cc_wc_shipper_phone', '' ),
            'shipper_station'  => CC_City_Scope::get_station_for_postcode( get_option( 'cc_wc_shipper_postal_code', '' ) ),
        );
    }

    /**
     * Βγάζει το voucher από άλλο σημείο αποστολής (υποκωδικό). Ο κωδικός του
     * σημείου πάει στο Shipper και ο κωδικός χρέωσης στο Requestor· τα στοιχεία
     * και ο σταθμός αποστολέα (για 111/211) παίρνονται από το σημείο.
     *
     * Κενός κωδικός ή ο κωδικός της Έδρας: καμία αλλαγή.
     *
     * @param string $code
     * @return true|WP_Error
     */
    public function use_pickup_point( $code ) {
        $code = trim( (string) $code );
        if ( '' === $code || CC_Pickup_Points::get_main_point()['code'] === $code ) {
            return true;
        }

        $point = CC_Pickup_Points::get_point( $code );
        if ( ! $point ) {
            return new WP_Error(
                'unknown_pickup_point',
                sprintf( 'Το σημείο αποστολής %s δεν υπάρχει στις ρυθμίσεις.', $code )
            );
        }

        $this->pickup_point                  = $point;
        $this->settings['requestor_account'] = $point['requestor'];
        $this->settings['shipper_account']   = $point['code'];
        $this->settings['shipper_name']      = $point['name'];
        $this->settings['shipper_address']   = $point['address'];
        $this->settings['shipper_postal']    = $point['postal'];
        $this->settings['shipper_city']      = $point['city'];
        // Το API βάζει έτσι κι αλλιώς το τηλέφωνο του υποκωδικού· αν το autofill δεν
        // έφερε τηλέφωνο, το τηλέφωνο της Έδρας αρκεί για να περάσει ο έλεγχος.
        if ( '' !== $point['phone'] ) {
            $this->settings['shipper_phone'] = $point['phone'];
        }
        $this->settings['shipper_station'] = CC_City_Scope::get_station_for_postcode( $point['postal'] );

        return true;
    }

    public function get_shipper_account() {
        return (string) $this->settings['shipper_account'];
    }

    public function get_requestor_account() {
        return (string) $this->settings['requestor_account'];
    }

    /**
     * Καταγράφει στην παραγγελία από πού βγήκε και πού χρεώνεται το voucher.
     * Γράφεται σε κάθε δημιουργία, άρα η επαναδημιουργία μετά από ακύρωση το αντικαθιστά.
     * Δεν κάνει save — το κάνει ο caller μαζί με τα υπόλοιπα meta.
     */
    public function save_pickup_point_meta() {
        $this->order->update_meta_data( '_cc_pickup_point', $this->get_shipper_account() );
        $this->order->update_meta_data( '_cc_requestor_account', $this->get_requestor_account() );
    }

    /**
     * Κομμάτι για το order note — μόνο όταν το κατάστημα έχει υποκωδικούς.
     *
     * @return string
     */
    public function pickup_point_note() {
        if ( ! CC_Pickup_Points::has_sub_points() ) {
            return '';
        }
        $code  = $this->get_shipper_account();
        $point = CC_Pickup_Points::get_point( $code );
        $label = $point ? CC_Pickup_Points::display_label( $point ) : $code;

        return ' | Σημείο: ' . $label;
    }

    /**
     * Validate that all required settings are present
     *
     * @return true|WP_Error
     */
    public function validate_settings() {
        // Υποκωδικός που δεν έχει περάσει από Test & Auto-fill
        if ( $this->pickup_point ) {
            foreach ( array( 'shipper_name', 'shipper_address', 'shipper_postal', 'shipper_city' ) as $key ) {
                if ( empty( $this->settings[ $key ] ) ) {
                    return new WP_Error(
                        'pickup_point_incomplete',
                        sprintf(
                            'Το σημείο αποστολής «%s» δεν έχει στοιχεία αποστολέα — κάνε Test & Auto-fill στις ρυθμίσεις.',
                            CC_Pickup_Points::display_label( $this->pickup_point )
                        )
                    );
                }
            }
        }

        $required = array(
            'user_alias'       => 'User Alias',
            'credential_value' => 'Credential Value',
            'api_key'          => 'API Key',
            'billing_account'  => 'Carrier Billing Account',
            'shipper_name'     => 'Επωνυμία αποστολέα',
            'shipper_address'  => 'Διεύθυνση αποστολέα',
            'shipper_postal'   => 'ΤΚ αποστολέα',
            'shipper_city'     => 'Πόλη αποστολέα',
            'shipper_phone'    => 'Τηλέφωνο αποστολέα',
        );

        $missing = array();
        foreach ( $required as $key => $label ) {
            if ( empty( $this->settings[ $key ] ) ) {
                $missing[] = $label;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'missing_settings',
                'Λείπουν ρυθμίσεις: ' . implode( ', ', $missing )
            );
        }

        return true;
    }

    /**
     * Η τελική διεύθυνση παραλήπτη όπως θα σταλεί στο API — για προεπισκόπηση στο admin.
     *
     * @return string
     */
    public function get_consignee_address_preview() {
        $c = $this->get_consignee_fields();
        return trim( $c['address'] . ', ' . $c['city'] . ' ' . $c['postcode'], ', ' );
    }

    /**
     * Validate the order itself (consignee data, weight limits etc)
     *
     * @return true|WP_Error
     */
    public function validate_order() {
        $c        = $this->get_consignee_fields();
        $address  = $c['address'];
        $city     = $c['city'];
        $postcode = $c['postcode'];
        $phone    = $c['phone'];

        if ( empty( $c['name'] ) ) {
            return new WP_Error( 'missing_consignee_name', 'Λείπει το όνομα παραλήπτη.' );
        }
        if ( empty( $address ) ) {
            return new WP_Error( 'missing_consignee_address', 'Λείπει η διεύθυνση παραλήπτη.' );
        }
        if ( empty( $city ) ) {
            return new WP_Error( 'missing_consignee_city', 'Λείπει η πόλη παραλήπτη.' );
        }
        if ( empty( $postcode ) ) {
            return new WP_Error( 'missing_consignee_postcode', 'Λείπει ο ΤΚ παραλήπτη.' );
        }
        if ( empty( $phone ) ) {
            return new WP_Error( 'missing_consignee_phone', 'Λείπει το τηλέφωνο παραλήπτη.' );
        }

        // Έλεγχος ΤΚ — πρέπει να είναι 5 ψηφία
        if ( ! preg_match( '/^\d{5}$/', $postcode ) ) {
            return new WP_Error( 'invalid_postcode', 'Μη έγκυρος ΤΚ παραλήπτη. Πρέπει να είναι 5 ψηφία (π.χ. 12241).' );
        }

        // Έλεγχος βάρους — max 30kg ανά τεμάχιο
        $weight_per_unit = $this->get_order_weight();
        if ( $weight_per_unit > 30 ) {
            return new WP_Error( 'weight_exceeded', sprintf( 'Το βάρος (%.1f kg) υπερβαίνει το μέγιστο των 30 kg ανά τεμάχιο.', $weight_per_unit ) );
        }

        // Έλεγχος διαστάσεων — max 180cm σε κάθε πλευρά
        // και max περίμετρος: μήκος + 2*(πλάτος+ύψος) ≤ 300cm
        $dimensions = $this->get_order_dimensions();
        if ( ! empty( $dimensions ) ) {
            $l = (float) $dimensions['length'];
            $w = (float) $dimensions['width'];
            $h = (float) $dimensions['height'];

            $max_side = max( $l, $w, $h );
            if ( $max_side > 180 ) {
                return new WP_Error(
                    'dimension_exceeded',
                    sprintf( 'Η μεγαλύτερη πλευρά (%.0f cm) υπερβαίνει το μέγιστο των 180 cm.', $max_side )
                );
            }

            // Girth check: μήκος + 2*(πλάτος+ύψος) ≤ 300cm
            $girth = $l + 2 * ( $w + $h );
            if ( $girth > 300 ) {
                return new WP_Error(
                    'girth_exceeded',
                    sprintf( 'Ο συνολικός όγκος αποστολής (%.0f cm) υπερβαίνει το μέγιστο. Επικοινωνήστε με την Courier Center.', $girth )
                );
            }
        }

        // COD international check
        $country = $c['country'];
        if ( $country !== 'GR' && $this->is_cod() ) {
            return new WP_Error(
                'cod_international',
                'Η αντικαταβολή δεν επιτρέπεται για αποστολές εξωτερικού.'
            );
        }

        return true;
    }

    /**
     * Build the full payload for /api/Shipment
     *
     * @param string $service_type 'next_day' | 'same_day_3h' | 'same_day_5h'
     * @param bool   $boxnow       Whether to use BOX NOW Find Locker
     * @return array
     */
     public function build_payload( $service_type = 'next_day', $boxnow = false, $return_option = 'none' ) {
        if ( $service_type === 'next_day' && ! empty( $this->settings['shipper_station'] ) ) {
            $result        = CC_City_Scope::resolve_next_day_service(
                $this->settings['shipper_station'],
                $this->get_consignee_fields()['postcode']
            );
            $basic_service = $result['service_code'];
        } else {
            $basic_service = self::SERVICE_CODES[ $service_type ] ?? '211';
        }

        $items_array  = $this->build_items();

        $payload = array(
            'Context'      => array(
                'UserAlias'       => $this->settings['user_alias'],
                'CredentialValue' => $this->settings['credential_value'],
                'ApiKey'          => $this->settings['api_key'],
            ),
            'shipmentDate' => date( 'Y-m-d' ),
            'comments'     => $this->build_comments(),
            'Requestor'    => array(
                'CarrierBillingAccount' => $this->settings['requestor_account'],
            ),
            'Shipper'      => array(
                'CarrierBillingAccount' => $this->settings['shipper_account'],
                'CompanyName'           => $this->settings['shipper_name'],
                'ContactName'           => $this->settings['shipper_name'],
                'Address'               => $this->settings['shipper_address'],
                'City'                  => $this->settings['shipper_city'],
                'Area'                  => $this->settings['shipper_city'],
                'ZipCode'               => $this->settings['shipper_postal'],
                'Country'               => 'GR',
                'Mobile1'               => $this->settings['shipper_phone'],
            ),
            'Consignee'    => $this->build_consignee(),
            'BillTo'       => 'Requestor',
            'BasicService' => $basic_service,
            'Reference1'   => 'WC-' . $this->order->get_id(),
            'NoOfItems'    => $this->parcel_count,
            'Items'        => $items_array,
        );

        // Add COD if applicable
        if ( $this->is_cod() ) {
            $payload['CODs'] = array(
                array(
                    'Type'   => 'Cash',
                    'Amount' => array(
                        'Currency' => 'EUR',
                        'Value'    => (float) $this->order->get_total(),
                    ),
                ),
            );
        }

        // Add BOX NOW if requested
        if ( $boxnow ) {
            $locker_id = $this->order->get_meta( '_boxnow_locker_id' );

            if ( ! empty( $locker_id ) ) {
                // Mode 3.2: Συγκεκριμένο locker επιλεγμένο από widget
                $payload['LockerDeliveryInfo'] = array(
                    'Prefix' => 'ATH',
                    'Code'   => (string) $locker_id,
                );
            } else {
                // Mode 3.1: Find Nearest — το API βρίσκει το κοντινότερο locker
                $payload['LockerDeliveryInfo'] = array(
                    'Prefix' => 'BOXNOW',
                );
            }
        }

        // Add return AWB options
        // 'optional'  = return AWB που μπορεί να ενεργοποιηθεί με drop-off ή κλήση API
        // 'mandatory' = return AWB + υποχρεωτική παραλαβή από τον courier κατά την παράδοση
        if ( $return_option === 'mandatory' ) {
            $payload['IsMandatoryPickup']  = true;
            $payload['GenerateReturnAWB']  = true;
        } elseif ( $return_option === 'optional' ) {
            $payload['IsMandatoryPickup']  = false;
            $payload['GenerateReturnAWB']  = true;
        }
        // 'none' = δεν προσθέτουμε τίποτα (default συμπεριφορά)

        return $payload;
    }

    /**
     * Σχόλια voucher: μόνο η σημείωση πελάτη από το checkout.
     *
     * Ο αριθμός παραγγελίας δεν μπαίνει εδώ — μεταφέρεται ήδη στο Reference1
     * (WC-<id>). Η σημείωση τυπώνεται στο voucher, οπότε καθαρίζεται από αλλαγές
     * γραμμής και κόβεται ώστε να μην ξεχειλίσει την ετικέτα.
     */
    private function build_comments() {
        $note = trim( (string) $this->order->get_customer_note() );
        if ( '' === $note ) {
            return '';
        }

        $note = preg_replace( '/\s+/u', ' ', $note );

        return function_exists( 'mb_substr' )
            ? mb_substr( $note, 0, 200 )
            : substr( $note, 0, 200 );
    }

    /**
     * Επιλέγει τιμή πεδίου παραλήπτη με fallback ανά πεδίο.
     *
     * Προτιμάται η διεύθυνση αποστολής όταν η παραγγελία έχει τέτοια, αλλά κάθε
     * πεδίο πέφτει πίσω στη χρέωση αν είναι κενό. Δεν αρκεί έλεγχος ανά μπλοκ:
     * το WooCommerce παρακάμπτει το shipping fieldset όταν ο πελάτης δεν επιλέξει
     * «αποστολή σε άλλη διεύθυνση», και το _shipping_phone είναι προαιρετικό
     * (συχνά κενό) ενώ το BOX NOW απαιτεί κινητό.
     */
    private function consignee_value( $shipping_getter, $billing_getter ) {
        // Το get_shipping_phone() υπάρχει από WooCommerce 5.6 και μετά.
        if ( $this->order->has_shipping_address() && is_callable( array( $this->order, $shipping_getter ) ) ) {
            $value = trim( (string) $this->order->{$shipping_getter}() );
            if ( '' !== $value ) {
                return $value;
            }
        }
        return trim( (string) $this->order->{$billing_getter}() );
    }

    /**
     * Ο αριθμός οδού, όταν το κατάστημα τον κρατά σε ξεχωριστό πεδίο.
     *
     * Το WooCommerce δεν έχει τέτοιο πεδίο — το προσθέτουν themes/plugins με
     * δικό τους meta key, οπότε το επιλέγει ο merchant από τις Ρυθμίσεις.
     * Κενή ρύθμιση = ο αριθμός είναι ήδη μέσα στη Διεύθυνση (προεπιλογή).
     */
    private function get_street_number() {
        $meta_key = trim( (string) get_option( 'cc_wc_street_number_field', '' ) );
        if ( '' === $meta_key ) {
            return '';
        }

        $candidates = array( $meta_key );

        // Αν η αποστολή γίνεται στη shipping διεύθυνση και ρυθμίστηκε billing_*
        // πεδίο, προτιμάται το αντίστοιχο shipping_* — ο αριθμός πρέπει να
        // ταιριάζει με την οδό που θα σταλεί, όχι με τη διεύθυνση χρέωσης.
        if ( $this->order->has_shipping_address() ) {
            $shipping_key = preg_replace( '/^(_?)billing_/', '$1shipping_', $meta_key );
            if ( $shipping_key !== $meta_key ) {
                array_unshift( $candidates, $shipping_key );
            }
        }

        foreach ( $candidates as $key ) {
            // Κάποια plugins αποθηκεύουν το ίδιο πεδίο με/χωρίς αρχικό underscore.
            $alt = ( 0 === strpos( $key, '_' ) ) ? substr( $key, 1 ) : '_' . $key;
            foreach ( array( $key, $alt ) as $k ) {
                $value = $this->order->get_meta( $k );
                if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                    return trim( (string) $value );
                }
            }
        }

        return '';
    }

    /**
     * Τα πεδία παραλήπτη όπως θα σταλούν — μία πηγή αλήθειας για validation,
     * payload και την προεπισκόπηση στο meta box.
     */
    public function get_consignee_fields() {
        $first = $this->consignee_value( 'get_shipping_first_name', 'get_billing_first_name' );
        $last  = $this->consignee_value( 'get_shipping_last_name', 'get_billing_last_name' );
        $name  = trim( $first . ' ' . $last );

        $company = $this->consignee_value( 'get_shipping_company', 'get_billing_company' );

        $address_1 = $this->consignee_value( 'get_shipping_address_1', 'get_billing_address_1' );
        $address_2 = $this->consignee_value( 'get_shipping_address_2', 'get_billing_address_2' );
        $address   = trim( $address_1 . ' ' . $address_2 );

        // Ο αριθμός μπαίνει μόνο αν δεν βρίσκεται ήδη μέσα στη διεύθυνση.
        // Ο έλεγχος γίνεται σε όρια λέξης ώστε το «15» να μην θεωρηθεί ότι
        // υπάρχει επειδή η διεύθυνση λέει «Βενιζέλου 155».
        $street_number = $this->get_street_number();
        if ( '' !== $street_number
             && ! preg_match( '/(^|\s)' . preg_quote( $street_number, '/' ) . '(\s|$)/u', $address ) ) {
            $address = trim( $address . ' ' . $street_number );
        }

        $city    = $this->consignee_value( 'get_shipping_city', 'get_billing_city' );
        $country = $this->consignee_value( 'get_shipping_country', 'get_billing_country' );

        return array(
            'name'     => $name,
            'company'  => '' !== $company ? $company : $name,
            'address'  => $address,
            'city'     => $city,
            'postcode' => $this->consignee_value( 'get_shipping_postcode', 'get_billing_postcode' ),
            'country'  => $country ?: 'GR',
            'phone'    => $this->consignee_value( 'get_shipping_phone', 'get_billing_phone' ),
        );
    }

    /**
     * Build the Consignee block from the resolved consignee fields
     */
    private function build_consignee() {
        $c = $this->get_consignee_fields();

        return array(
            'CompanyName' => $c['company'],
            'ContactName' => $c['name'],
            'Address'     => $c['address'],
            'City'        => $c['city'],
            'Area'        => $c['city'], // WooCommerce δεν έχει ξεχωριστό area
            'ZipCode'     => $c['postcode'],
            'Country'     => $c['country'],
            'CountryCode' => $c['country'],
            'Mobile1'     => $c['phone'],
        );
    }

    /**
     * Returns the full uppercase country name for a given ISO 3166-1 alpha-2 code.
     */
    private function get_country_name( $code ) {
        $countries = WC()->countries->get_countries();
        return strtoupper( $countries[ $code ] ?? $code );
    }

    /**
     * Build the Items array.
     *
     * Λογική (όπως στο Deliverd):
     * - Κάθε φυσικό τεμάχιο γίνεται ξεχωριστό Item.
     * - Αν ένα προϊόν έχει qty=3, γίνεται 3 Items με το ίδιο βάρος το καθένα.
     * - Αν δεν υπάρχει βάρος (test orders, dummy items), χρησιμοποιούμε default 1 kg.
     */
    private function build_items() {
        $dimensions = $this->get_order_dimensions();
        $weight     = $this->get_order_weight();

        $item = array(
            'GoodsType'        => 'NoDocs',
            'Content'          => 'ΔΕΜΑΤΑ',
            'IsDangerousGoods' => false,
            'IsDryIce'         => false,
            'IsFragile'        => false,
            'Weight'           => array( 'Unit' => 'kg', 'Value' => $weight ),
        );

        if ( ! empty( $dimensions ) ) {
            $item['Length'] = array( 'Unit' => 'cm', 'Value' => (float) $dimensions['length'] );
            $item['Width']  = array( 'Unit' => 'cm', 'Value' => (float) $dimensions['width'] );
            $item['Height'] = array( 'Unit' => 'cm', 'Value' => (float) $dimensions['height'] );
        }

        return array_fill( 0, $this->parcel_count, $item );
    }

    /**
     * Build a single Item block
     */
    private function build_single_item( $weight_kg, $volumetric_kg ) {
        return array(
            'GoodsType'        => 'NoDocs',
            'Content'          => 'ΔΕΜΑΤΑ',
            'IsDangerousGoods' => false,
            'IsDryIce'         => false,
            'IsFragile'        => false,
            'Weight'           => array(
                'Unit'  => 'kg',
                'Value' => $weight_kg,
            ),
            'VolumetricWeight' => array(
                'Unit'  => 'kg',
                'Value' => $volumetric_kg,
            ),
        );
    }

    private function get_order_weight() {
        $weight = 0;
        foreach ( $this->order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product && $product->get_weight() ) {
                // Το προϊόν αποθηκεύει βάρος στη μονάδα των ρυθμίσεων WooCommerce
                // (π.χ. g, lbs) — μετατροπή σε kg πριν χρησιμοποιηθεί.
                $weight_kg = wc_get_weight( (float) $product->get_weight(), 'kg' );
                $weight   += $weight_kg * $item->get_quantity();
            }
        }
        return $weight > 0 ? $weight : self::DEFAULT_WEIGHT_KG;
    }

    private function get_volumetric_weight() {
        $volume = 0;
        foreach ( $this->order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $l = wc_get_dimension( (float) $product->get_length(), 'cm' );
                $w = wc_get_dimension( (float) $product->get_width(), 'cm' );
                $h = wc_get_dimension( (float) $product->get_height(), 'cm' );
                if ( $l && $w && $h ) {
                    // Volumetric weight = (L x W x H) / 5000
                    $volume += ( $l * $w * $h / 5000 ) * $item->get_quantity();
                }
            }
        }
        return $volume > 0 ? $volume : $this->get_order_weight();
    }

    private function get_order_dimensions() {
        foreach ( $this->order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                // Το προϊόν αποθηκεύει διαστάσεις στη μονάδα των ρυθμίσεων
                // WooCommerce (π.χ. mm, in) — μετατροπή σε cm πριν χρησιμοποιηθεί.
                $l = wc_get_dimension( (float) $product->get_length(), 'cm' );
                $w = wc_get_dimension( (float) $product->get_width(), 'cm' );
                $h = wc_get_dimension( (float) $product->get_height(), 'cm' );
                if ( $l > 0 && $w > 0 && $h > 0 ) {
                    return array(
                        'length' => $l,
                        'width'  => $w,
                        'height' => $h,
                    );
                }
            }
        }
        return array();
    }

    /**
     * Check if the order uses Cash on Delivery payment method
     */
    private function is_cod() {
        return $this->order->get_payment_method() === 'cod';
    }
}