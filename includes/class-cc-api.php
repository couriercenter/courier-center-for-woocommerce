<?php
/**
 * Courier Center API Wrapper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_API {
    
    /**
     * API Base URL
     */
    private $api_url = 'https://funship.cc.qualco.eu/ccservice/api';
    
    /**
     * API Credentials
     */
    private $credentials = array();
    
    /**
     * Constructor
     */
    public function __construct( $credentials = null ) {
        if ( $credentials ) {
            $this->credentials = $credentials;
        } else {
            // Load from settings
            $this->credentials = array(
                'UserAlias'         => get_option( 'cc_wc_user_alias', '' ),
                'CredentialValue'   => get_option( 'cc_wc_credential_value', '' ),
                'ApiKey'            => get_option( 'cc_wc_api_key', '' ),
                'CarrierBillingAccount' => get_option( 'cc_wc_billing_account', '' ),
            );
        }
    }
    
        /**
     * Test connection
     */
    public function test_connection() {
        try {
            $response = $this->request( 'POST', '/Station/GetStations', array(
                'Context' => $this->get_context(),
            ) );
            
            // Το API επιστρέφει StationDataInfo, όχι Stations
            if ( isset( $response['StationDataInfo'] ) && is_array( $response['StationDataInfo'] ) ) {
                return array(
                    'success' => true,
                    'message' => 'Η σύνδεση με το API λειτουργεί άψογα! ✅ Βρέθηκαν ' . count( $response['StationDataInfo'] ) . ' σταθμοί.',
                    'data'    => $response,
                );
            }
            
            return array(
                'success' => false,
                'message' => 'Το API απάντησε αλλά δεν επέστρεψε stations. Response: ' . print_r( $response, true ),
                'data'    => $response,
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Σφάλμα σύνδεσης: ' . $e->getMessage(),
                'data'    => null,
            );
        }
    }
        
    /**
     * Get context block for API requests
     */
    private function get_context() {
        return array(
            'UserAlias'       => $this->credentials['UserAlias'],
            'CredentialValue' => $this->credentials['CredentialValue'],
            'ApiKey'          => $this->credentials['ApiKey'],
        );
    }
    
    /**
     * Create a shipment
     *
     * @param array $payload Full request payload (από CC_Shipment_Builder)
     * @return array|WP_Error
     */
    public function create_shipment( $payload ) {
        error_log( 'CC PAYLOAD: ' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) );
        try {
            $response = $this->request( 'POST', '/Shipment', $payload );

            // Έλεγχος για επιτυχία
            if ( isset( $response['Result'] ) && $response['Result'] === 'Success' ) {
                return $response;
            }

            // Αν δεν υπάρχει Result=Success, ψάξε για error message
            $error_msg = 'Άγνωστο σφάλμα από το API';
            if ( ! empty( $response['Errors'][0]['Message'] ) ) {
                $error_msg = $response['Errors'][0]['Message'];
            } elseif ( isset( $response['ErrorMessage'] ) && ! empty( $response['ErrorMessage'] ) ) {
                $error_msg = $response['ErrorMessage'];
            } elseif ( isset( $response['Message'] ) && ! empty( $response['Message'] ) ) {
                $error_msg = $response['Message'];
            } elseif ( isset( $response['ContractorResultNote'] ) && ! empty( $response['ContractorResultNote'] ) ) {
                $error_msg = $response['ContractorResultNote'];
            }

            return new WP_Error( 'api_error', $error_msg, $response );

        } catch ( Exception $e ) {
            return new WP_Error( 'api_exception', $e->getMessage() );
        }
    }

    /**
     * Get voucher PDF for one or more shipments
     *
     * @param string|array $awb_numbers Single AWB or array of AWBs
     * @param string       $template    'cleanpdf' (default) or 'single_100x150' (BOX NOW)
     * @return string|WP_Error Binary PDF content on success, WP_Error on failure
     */
    public function get_voucher_pdf( $awb_numbers, $template = 'cleanpdf' ) {
        // Allow array or single string
        if ( is_array( $awb_numbers ) ) {
            $awb_string = implode( ',', $awb_numbers );
        } else {
            $awb_string = (string) $awb_numbers;
        }

        if ( empty( $awb_string ) ) {
            return new WP_Error( 'missing_awb', 'Δεν δόθηκε AWB για εκτύπωση' );
        }

        $payload = array(
            'context'        => $this->get_context(),
            'Template'       => $template,
            'ShipmentNumber' => $awb_string,
        );

        $url = $this->api_url . '/voucher';

        $response = wp_remote_post( $url, array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_request_failed', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error( 'api_http_error', "HTTP $code: " . substr( $body, 0, 500 ) );
        }

        // Το API μπορεί να επιστρέψει είτε raw PDF bytes, είτε JSON με base64
        // Έλεγξε αν είναι PDF (αρχίζει με %PDF)
        if ( substr( $body, 0, 4 ) === '%PDF' ) {
            // DEBUG - θα το αφαιρέσουμε
            error_log( 'CC PRINT DEBUG - Template: ' . $template . ' | AWBs: ' . $awb_string . ' | Response content-type: ' . wp_remote_retrieve_header( $response, 'content-type' ) );
            return $body;
        }

        // Αλλιώς προσπάθησε να το διαβάσεις ως JSON
        $json = json_decode( $body, true );
        if ( $json !== null ) {
            // Ψάξε για base64 PDF σε γνωστά πεδία
            $possible_fields = array( 'Voucher', 'PdfData', 'pdfData', 'Pdf', 'pdf', 'Data', 'data', 'Content', 'content' );
            foreach ( $possible_fields as $field ) {
                if ( ! empty( $json[ $field ] ) ) {
                    $decoded = base64_decode( $json[ $field ], true );
                    if ( $decoded !== false && substr( $decoded, 0, 4 ) === '%PDF' ) {
                        return $decoded;
                    }
                }
            }

            // Αν είναι error response
            $error_msg = $json['ErrorMessage'] ?? $json['Message'] ?? 'Άγνωστο σφάλμα από το API';
            return new WP_Error( 'api_voucher_error', $error_msg );
        }

        return new WP_Error( 'api_unexpected_response', 'Το API επέστρεψε μη αναμενόμενο response' );
    }

    /**
     * Void (cancel) a shipment
     *
     * @param string $awb Shipment number to cancel
     * @return true|WP_Error
     */
    public function void_shipment( $awb ) {
        if ( empty( $awb ) ) {
            return new WP_Error( 'missing_awb', 'Δεν δόθηκε AWB για ακύρωση' );
        }

        $payload = array(
            'Context' => $this->get_context(),
            'ShipmentNumber' => $awb,
        );

        try {
            $response = $this->request( 'POST', '/Shipment/Void', $payload );

            if ( isset( $response['Result'] ) && $response['Result'] === 'Success' ) {
                return true;
            }

            $error_msg = 'Αποτυχία ακύρωσης';
            if ( isset( $response['ErrorMessage'] ) && ! empty( $response['ErrorMessage'] ) ) {
                $error_msg = $response['ErrorMessage'];
            } elseif ( isset( $response['Message'] ) && ! empty( $response['Message'] ) ) {
                $error_msg = $response['Message'];
            }

            return new WP_Error( 'void_failed', $error_msg, $response );

        } catch ( Exception $e ) {
            return new WP_Error( 'api_exception', $e->getMessage() );
        }
    }

    /**
     * Get shipment details (status, tracking events)
     *
     * @param string $awb Shipment number
     * @return array|WP_Error
     */
    public function get_shipment_details( $awb ) {
        if ( empty( $awb ) ) {
            return new WP_Error( 'missing_awb', 'Δεν δόθηκε AWB' );
        }

        $payload = array(
            'Context'    => $this->get_context(),
            'Identifier' => $awb,
        );

        try {
            $response = $this->request( 'POST', '/Shipment/GetShipmentDetails', $payload );
            return $response;
        } catch ( Exception $e ) {
            return new WP_Error( 'api_exception', $e->getMessage() );
        }
    }
    
    /**
     * Get manifest PDF for a specific date
     *
     * @param string $date Ημερομηνία σε format YYYY-MM-DD
     * @return string|WP_Error Binary PDF content
     */
    public function get_manifest( $date ) {
        if ( empty( $date ) ) {
            return new WP_Error( 'missing_date', 'Δεν δόθηκε ημερομηνία' );
        }

        // Validate date format (YYYY-MM-DD)
        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) {
            return new WP_Error( 'invalid_date', 'Μη έγκυρη ημερομηνία. Χρησιμοποιήστε format YYYY-MM-DD.' );
        }

        $payload = array(
            'context' => $this->get_context(),
            'Date'    => $date,
        );

        $url = $this->api_url . '/Manifest';

        $response = wp_remote_post( $url, array(
            'method'  => 'POST',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_request_failed', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error( 'api_http_error', "HTTP $code: " . substr( $body, 0, 500 ) );
        }

        $json = json_decode( $body, true );
        if ( $json === null ) {
            return new WP_Error( 'api_invalid_json', 'Το API δεν επέστρεψε έγκυρο JSON' );
        }

        // Έλεγχος για error response
        if ( isset( $json['Result'] ) && $json['Result'] === 'Failure' ) {
            $error_msg = 'Σφάλμα από το API';
            if ( isset( $json['Errors'][0]['Message'] ) ) {
                $error_msg = $json['Errors'][0]['Message'];
            } elseif ( isset( $json['ErrorMessage'] ) ) {
                $error_msg = $json['ErrorMessage'];
            }
            return new WP_Error( 'api_error', $error_msg );
        }

        // Αποκωδικοποίηση base64 PDF από το πεδίο "Manifest"
        if ( empty( $json['Manifest'] ) ) {
            return new WP_Error( 'empty_manifest', 'Δεν υπάρχουν αποστολές για αυτή την ημερομηνία' );
        }

        $pdf = base64_decode( $json['Manifest'], true );

        if ( $pdf === false || substr( $pdf, 0, 4 ) !== '%PDF' ) {
            return new WP_Error( 'api_invalid_pdf', 'Το API επέστρεψε μη έγκυρο PDF' );
        }

        return $pdf;
    }

    /**
     * Make API request
     */
    private function request( $method, $endpoint, $body = null ) {
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );
        
        if ( $body ) {
            $args['body'] = json_encode( $body );
        }
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        if ( $code !== 200 ) {
            throw new Exception( "HTTP $code: $body" );
        }
        
        $data = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'Invalid JSON response: ' . json_last_error_msg() );
        }
        
        return $data;
    }
}