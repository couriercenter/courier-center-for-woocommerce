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
     * Constructor
     */
    public function __construct( WC_Order $order ) {
        $this->order    = $order;
        $this->settings = $this->load_settings();
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
            'shipper_name'     => get_option( 'cc_wc_shipper_name', '' ),
            'shipper_address'  => get_option( 'cc_wc_shipper_address', '' ),
            'shipper_postal'   => get_option( 'cc_wc_shipper_postal_code', '' ),
            'shipper_city'     => get_option( 'cc_wc_shipper_city', '' ),
            'shipper_phone'    => get_option( 'cc_wc_shipper_phone', '' ),
            'shipper_station'  => CC_City_Scope::get_station_for_postcode( get_option( 'cc_wc_shipper_postal_code', '' ) ),
        );
    }

    /**
     * Validate that all required settings are present
     *
     * @return true|WP_Error
     */
    public function validate_settings() {
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
     * Validate the order itself (consignee data, weight limits etc)
     *
     * @return true|WP_Error
     */
    public function validate_order() {
        $billing_first = $this->order->get_billing_first_name();
        $billing_last  = $this->order->get_billing_last_name();
        $address       = $this->order->get_billing_address_1();
        $city          = $this->order->get_billing_city();
        $postcode      = $this->order->get_billing_postcode();
        $phone         = $this->order->get_billing_phone();

        if ( empty( $billing_first ) && empty( $billing_last ) ) {
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

        // COD international check
        $country = $this->order->get_billing_country();
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
                $this->order->get_billing_postcode()
            );
            $basic_service = $result['service_code'];
        } else {
            $basic_service = self::SERVICE_CODES[ $service_type ] ?? '211';
        }

        $payload = array(
            'Context'      => array(
                'UserAlias'       => $this->settings['user_alias'],
                'CredentialValue' => $this->settings['credential_value'],
                'ApiKey'          => $this->settings['api_key'],
            ),
            'shipmentDate' => date( 'Y-m-d' ),
            'comments'     => 'Order #' . $this->order->get_id(),
            'Requestor'    => array(
                'CarrierBillingAccount' => $this->settings['billing_account'],
            ),
            'Shipper'      => array(
                'CarrierBillingAccount' => $this->settings['billing_account'],
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
            'Items'        => $this->build_items(),
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
            $payload['LockerDeliveryInfo'] = array(
                'Prefix' => 'BOXNOW',
            );
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
     * Build the Consignee block from order billing details
     */
    private function build_consignee() {
        $first = $this->order->get_billing_first_name();
        $last  = $this->order->get_billing_last_name();
        $name  = trim( $first . ' ' . $last );

        $company = $this->order->get_billing_company();
        if ( empty( $company ) ) {
            $company = $name;
        }

        $address_1 = $this->order->get_billing_address_1();
        $address_2 = $this->order->get_billing_address_2();
        $address   = trim( $address_1 . ' ' . $address_2 );

        $city = $this->order->get_billing_city();

        $country_code = $this->order->get_billing_country() ?: 'GR';

        return array(
            'CompanyName' => $company,
            'ContactName' => $name,
            'Address'     => $address,
            'City'        => $city,
            'Area'        => $city, // WooCommerce δεν έχει ξεχωριστό area
            'ZipCode'     => $this->order->get_billing_postcode(),
            'Country'     => $this->get_country_name( $country_code ),
            'CountryCode' => $country_code,
            'Mobile1'     => $this->order->get_billing_phone(),
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
        $items = array();

        foreach ( $this->order->get_items() as $line_item ) {
            $product = $line_item->get_product();
            $qty     = (int) $line_item->get_quantity();

            // Πάρε το βάρος ανά τεμάχιο
            $weight_per_unit = self::DEFAULT_WEIGHT_KG;
            if ( $product && $product->has_weight() ) {
                $weight_per_unit = (float) $product->get_weight();
                if ( $weight_per_unit <= 0 ) {
                    $weight_per_unit = self::DEFAULT_WEIGHT_KG;
                }
            }

            for ( $i = 0; $i < $qty; $i++ ) {
                $items[] = $this->build_single_item( $weight_per_unit );
            }
        }

        // Αν η παραγγελία είναι κενή (test), βάλε ένα default item
        if ( empty( $items ) ) {
            $items[] = $this->build_single_item( self::DEFAULT_WEIGHT_KG );
        }

        return $items;
    }

    /**
     * Build a single Item block
     */
    private function build_single_item( $weight_kg ) {
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
        );
    }

    /**
     * Check if the order uses Cash on Delivery payment method
     */
    private function is_cod() {
        return $this->order->get_payment_method() === 'cod';
    }
}