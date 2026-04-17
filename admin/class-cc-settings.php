<?php
/**
 * Settings Page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_cc_test_and_autofill', array( $this, 'ajax_test_and_autofill' ) );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // API Credentials section
        add_settings_section(
            'cc_wc_api_section',
            'API Credentials',
            array( $this, 'api_section_callback' ),
            'courier-center'
        );

        // UserAlias
        register_setting( 'cc_wc_settings', 'cc_wc_user_alias', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_user_alias',
            'User Alias',
            array( $this, 'text_field_callback' ),
            'courier-center',
            'cc_wc_api_section',
            array( 'label_for' => 'cc_wc_user_alias' )
        );

        // CredentialValue
        register_setting( 'cc_wc_settings', 'cc_wc_credential_value', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_credential_value',
            'Credential Value',
            array( $this, 'password_field_callback' ),
            'courier-center',
            'cc_wc_api_section',
            array( 'label_for' => 'cc_wc_credential_value' )
        );

        // ApiKey
        register_setting( 'cc_wc_settings', 'cc_wc_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_api_key',
            'API Key',
            array( $this, 'password_field_callback' ),
            'courier-center',
            'cc_wc_api_section',
            array( 'label_for' => 'cc_wc_api_key' )
        );

        // CarrierBillingAccount
        register_setting( 'cc_wc_settings', 'cc_wc_billing_account', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_billing_account',
            'Carrier Billing Account',
            array( $this, 'text_field_callback' ),
            'courier-center',
            'cc_wc_api_section',
            array( 'label_for' => 'cc_wc_billing_account' )
        );

        // ── Autofill section (button between API and Shipper sections) ──────
        add_settings_section(
            'cc_wc_autofill_section',
            '',
            array( $this, 'autofill_section_callback' ),
            'courier-center'
        );

        // Shipper Details section
        add_settings_section(
            'cc_wc_shipper_section',
            'Στοιχεία Αποστολέα',
            array( $this, 'shipper_section_callback' ),
            'courier-center'
        );

        // Shipper Name
        register_setting( 'cc_wc_settings', 'cc_wc_shipper_name', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_shipper_name',
            'Επωνυμία',
            array( $this, 'text_field_callback' ),
            'courier-center',
            'cc_wc_shipper_section',
            array( 'label_for' => 'cc_wc_shipper_name' )
        );

        // Shipper Address
        register_setting( 'cc_wc_settings', 'cc_wc_shipper_address', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_shipper_address',
            'Διεύθυνση',
            array( $this, 'text_field_callback' ),
            'courier-center',
            'cc_wc_shipper_section',
            array( 'label_for' => 'cc_wc_shipper_address' )
        );

        // Shipper Postal Code
        register_setting( 'cc_wc_settings', 'cc_wc_shipper_postal_code', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_shipper_postal_code',
            'Ταχυδρομικός Κώδικας',
            array( $this, 'text_field_callback' ),
            'courier-center',
            'cc_wc_shipper_section',
            array( 'label_for' => 'cc_wc_shipper_postal_code' )
        );

        // Shipper City
        register_setting( 'cc_wc_settings', 'cc_wc_shipper_city', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_shipper_city',
            'Πόλη',
            array( $this, 'text_field_callback' ),
            'courier-center',
            'cc_wc_shipper_section',
            array( 'label_for' => 'cc_wc_shipper_city' )
        );

        // Shipper Phone
        register_setting( 'cc_wc_settings', 'cc_wc_shipper_phone', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field(
            'cc_wc_shipper_phone',
            'Τηλέφωνο',
            array( $this, 'text_field_callback' ),
            'courier-center',
            'cc_wc_shipper_section',
            array( 'label_for' => 'cc_wc_shipper_phone' )
        );

        // Tracking section
        add_settings_section(
            'cc_wc_tracking_section',
            'Tracking & Emails',
            array( $this, 'tracking_section_callback' ),
            'courier-center'
        );

        // Tracking URL — custom sanitize to preserve {{tracking}} placeholder
        register_setting( 'cc_wc_settings', 'cc_wc_tracking_url', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_tracking_url' ),
            'default'           => 'https://www.courier.gr/track/result?tracknr={{tracking}}',
        ) );
        add_settings_field(
            'cc_wc_tracking_url',
            'Tracking URL',
            array( $this, 'tracking_url_field_callback' ),
            'courier-center',
            'cc_wc_tracking_section',
            array( 'label_for' => 'cc_wc_tracking_url' )
        );

        // Include tracking in customer emails
        register_setting( 'cc_wc_settings', 'cc_wc_email_tracking_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1',
        ) );
        add_settings_field(
            'cc_wc_email_tracking_enabled',
            'Ενημέρωση πελάτη',
            array( $this, 'email_tracking_field_callback' ),
            'courier-center',
            'cc_wc_tracking_section',
            array( 'label_for' => 'cc_wc_email_tracking_enabled' )
        );

        // Print settings section
        add_settings_section(
            'cc_wc_print_section',
            'Ρυθμίσεις Εκτύπωσης',
            array( $this, 'print_section_callback' ),
            'courier-center'
        );

        // Print template for normal vouchers
        register_setting( 'cc_wc_settings', 'cc_wc_print_template', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'pdf',
        ) );
        add_settings_field(
            'cc_wc_print_template',
            'Εκτύπωση Courier Center Voucher',
            array( $this, 'print_template_field_callback' ),
            'courier-center',
            'cc_wc_print_section',
            array( 'label_for' => 'cc_wc_print_template' )
        );

        // Print template for BOX NOW vouchers
        register_setting( 'cc_wc_settings', 'cc_wc_print_template_boxnow', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'singlepdf_100x150_4up',
        ) );
        add_settings_field(
            'cc_wc_print_template_boxnow',
            'Εκτύπωση BOX NOW Voucher',
            array( $this, 'print_template_boxnow_field_callback' ),
            'courier-center',
            'cc_wc_print_section',
            array( 'label_for' => 'cc_wc_print_template_boxnow' )
        );
    }

    /**
     * Section callbacks
     */
    public function api_section_callback() {
        echo '<p>Τα credentials που σας έχει δώσει η Courier Center για πρόσβαση στο API.</p>';
    }

    public function shipper_section_callback() {
        echo '<p>Τα στοιχεία του αποστολέα που θα εμφανίζονται στα vouchers.</p>';
    }

    /**
     * Autofill button — rendered as a standalone section between API and Shipper
     */
    public function autofill_section_callback() {
        $nonce = wp_create_nonce( 'cc_autofill_nonce' );
        ?>
        <div style="padding: 12px 0 4px;">
            <button type="button" id="cc-autofill-btn" class="button button-primary">
                🔍 Test &amp; Auto-fill στοιχεία
            </button>
            <span id="cc-autofill-status" style="margin-left: 12px; font-style: italic;"></span>

            <div id="cc-autofill-notice" style="display:none; margin-top: 12px; padding: 10px 14px; background: #e7f5e9; border-left: 4px solid #46b450; border-radius: 3px; font-size: 13px;">
                ✅ <strong>Στοιχεία από Courier Center API</strong> — τα πεδία συμπληρώθηκαν αυτόματα και αποθηκεύτηκαν.
                Επικοινωνήστε με τον διαχειριστή για αλλαγές.
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#cc-autofill-btn').on('click', function() {
                var $btn    = $(this);
                var $status = $('#cc-autofill-status');
                var $notice = $('#cc-autofill-notice');

                $btn.prop('disabled', true).text('⏳ Παρακαλώ περιμένετε...');
                $status.css('color', '#666').text('Βήμα 1/3: Δημιουργία test αποστολής...');
                $notice.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cc_test_and_autofill',
                        nonce:  '<?php echo esc_js( $nonce ); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('🔍 Test & Auto-fill στοιχεία');
                        console.log('CC Autofill response:', response);

                        if (!response.success) {
                            $status.css('color', '#b32d2e').text('❌ ' + (response.data && response.data.message ? response.data.message : 'Άγνωστο σφάλμα'));
                            return;
                        }

                        var d = response.data;
                        console.log('CC Autofill data:', d);

                        // Γεμίζουμε τα πεδία με name=
                        if (d.shipper_name)         $('input[name="cc_wc_shipper_name"]').val(d.shipper_name).prop('readonly', true);
                        if (d.shipper_address)       $('input[name="cc_wc_shipper_address"]').val(d.shipper_address).prop('readonly', true);
                        if (d.shipper_postal_code)   $('input[name="cc_wc_shipper_postal_code"]').val(d.shipper_postal_code).prop('readonly', true);
                        if (d.shipper_city)          $('input[name="cc_wc_shipper_city"]').val(d.shipper_city).prop('readonly', true);
                        if (d.shipper_phone)         $('input[name="cc_wc_shipper_phone"]').val(d.shipper_phone).prop('readonly', true);

                        // Στυλ για readonly πεδία
                        $('input[name^="cc_wc_shipper_"]').filter('[readonly]').css({
                            'background-color': '#f6f7f7',
                            'color': '#444',
                            'cursor': 'not-allowed'
                        });

                        $status.text('');
                        $notice.show();
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false).text('🔍 Test & Auto-fill στοιχεία');
                        $status.css('color', '#b32d2e').text('❌ AJAX error: ' + error);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Field callbacks
     */
    public function text_field_callback( $args ) {
        $option = get_option( $args['label_for'] );
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="regular-text">',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['label_for'] ),
            esc_attr( $option )
        );
    }

    public function password_field_callback( $args ) {
        $option = get_option( $args['label_for'] );
        $display_value = $option ? str_repeat( '•', 20 ) : '';
        printf(
            '<input type="password" id="%s" name="%s" value="%s" class="regular-text" placeholder="%s">',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['label_for'] ),
            esc_attr( $option ),
            esc_attr( $display_value )
        );
        if ( $option ) {
            echo '<p class="description">Αποθηκευμένο - αφήστε κενό για να μην αλλάξει</p>';
        }
    }

    /**
     * Sanitize tracking URL — preserves {{tracking}} placeholder, rejects values without it
     */
    public function sanitize_tracking_url( $value ) {
        // wp_kses με κενό array αφαιρεί HTML αλλά διατηρεί τα {{ και }}
        $value = wp_kses( trim( $value ), array() );

        if ( strpos( $value, '{{tracking}}' ) === false ) {
            add_settings_error(
                'cc_wc_tracking_url',
                'missing_placeholder',
                'Tracking URL: πρέπει να περιέχει <code>{{tracking}}</code> ως placeholder. Επαναφέρθηκε η προεπιλεγμένη τιμή.',
                'error'
            );
            // Επιστρέφει πάντα την hardcoded default (και για χαλασμένες αποθηκευμένες τιμές)
            return 'https://www.courier.gr/track/result?tracknr={{tracking}}';
        }

        return $value;
    }

    /**
     * Render settings page
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'cc_wc_settings' );
                do_settings_sections( 'courier-center' );
                submit_button( 'Αποθήκευση Ρυθμίσεων' );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX: Δημιούργησε test αποστολή → GetShipmentDetails → Void → επέστρεψε στοιχεία αποστολέα
     */
    public function ajax_test_and_autofill() {
        check_ajax_referer( 'cc_autofill_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $api             = new CC_API();
        $billing_account = get_option( 'cc_wc_billing_account', '' );
        $context         = array(
            'UserAlias'       => get_option( 'cc_wc_user_alias', '' ),
            'CredentialValue' => get_option( 'cc_wc_credential_value', '' ),
            'ApiKey'          => get_option( 'cc_wc_api_key', '' ),
        );

        error_log( 'CC Autofill: Βήμα 1 — δημιουργία test αποστολής για billing_account=' . $billing_account );

        // ── Βήμα 1: Δημιουργία test αποστολής ──────────────────────────────
        $payload = array(
            'Context'      => $context,
            'shipmentDate' => date( 'Y-m-d' ),
            'comments'     => 'TEST AUTOFILL - DELETE',
            'Requestor'    => array( 'CarrierBillingAccount' => $billing_account ),
            'Shipper'      => array( 'CarrierBillingAccount' => $billing_account ),
            'Consignee'    => array(
                'CompanyName' => 'TEST',
                'ContactName' => 'TEST',
                'Address'     => 'ΑΘΗΝΑ',
                'City'        => 'ΑΘΗΝΑ',
                'Area'        => 'ΑΘΗΝΑ',
                'ZipCode'     => '10431',
                'Country'     => 'GR',
                'Mobile1'     => '2101234567',
            ),
            'BillTo'       => 'Requestor',
            'BasicService' => '211',
            'Reference1'   => 'TEST-AUTOFILL',
            'Items'        => array(
                array(
                    'GoodsType'        => 'NoDocs',
                    'Content'          => 'TEST',
                    'IsDangerousGoods' => false,
                    'IsDryIce'         => false,
                    'IsFragile'        => false,
                    'Weight'           => array( 'Unit' => 'kg', 'Value' => 1.0 ),
                ),
            ),
        );

        $create_result = $api->create_shipment( $payload );

        error_log( 'CC Autofill: Αποτέλεσμα create_shipment: ' . wp_json_encode( $create_result ) );

        if ( is_wp_error( $create_result ) ) {
            wp_send_json_error( array( 'message' => 'Βήμα 1 — Αποτυχία δημιουργίας test αποστολής: ' . $create_result->get_error_message() ) );
        }

        $awb = $create_result['ShipmentNumber'] ?? '';
        error_log( 'CC Autofill: AWB=' . $awb );

        if ( empty( $awb ) ) {
            wp_send_json_error( array( 'message' => 'Βήμα 1 — Το API δεν επέστρεψε ShipmentNumber. Response: ' . wp_json_encode( $create_result ) ) );
        }

        // ── Βήμα 2: GetShipmentDetails → εξαγωγή στοιχείων αποστολέα ───────
        $details = $api->get_shipment_details( $awb );

        error_log( 'CC Autofill: get_shipment_details response: ' . wp_json_encode( $details ) );

        if ( is_wp_error( $details ) ) {
            $api->void_shipment( $awb );
            wp_send_json_error( array( 'message' => 'Βήμα 2 — Αποτυχία GetShipmentDetails: ' . $details->get_error_message() ) );
        }

        // Ανακτούμε το Shipper block
        // Πραγματική δομή: ShipmentDetails[0]['ShipmentInfo']['Shipper']
        $shipper = array();
        if ( ! empty( $details['ShipmentDetails'][0]['ShipmentInfo']['Shipper'] ) ) {
            $shipper = $details['ShipmentDetails'][0]['ShipmentInfo']['Shipper'];
        } elseif ( ! empty( $details['ShipmentDetails'][0]['Shipper'] ) ) {
            $shipper = $details['ShipmentDetails'][0]['Shipper'];
        } elseif ( ! empty( $details['Shipper'] ) ) {
            $shipper = $details['Shipper'];
        }

        error_log( 'CC Autofill: shipper block=' . wp_json_encode( $shipper ) );

        // Εξαγωγή πεδίων
        $name    = $shipper['CompanyName'] ?? '';
        $address = $shipper['Address'] ?? '';
        $postal  = $shipper['ZipCode'] ?? '';
        $city    = $shipper['City'] ?? '';
        $phone   = '';
        if ( ! empty( $shipper['Phones'] ) ) {
            $phone = is_array( $shipper['Phones'] ) ? ( $shipper['Phones'][0] ?? '' ) : $shipper['Phones'];
        } elseif ( ! empty( $shipper['Mobile1'] ) ) {
            $phone = $shipper['Mobile1'];
        } elseif ( ! empty( $shipper['Phone'] ) ) {
            $phone = $shipper['Phone'];
        }

        error_log( sprintf( 'CC Autofill: Εξήχθησαν → name=%s address=%s postal=%s city=%s phone=%s', $name, $address, $postal, $city, $phone ) );

        // ── Βήμα 3: Void αποστολής ──────────────────────────────────────────
        $void_result = $api->void_shipment( $awb );
        if ( is_wp_error( $void_result ) ) {
            error_log( 'CC Autofill: Αποτυχία void AWB ' . $awb . ': ' . $void_result->get_error_message() );
        } else {
            error_log( 'CC Autofill: Void επιτυχής για AWB ' . $awb );
        }

        // ── Βήμα 4: Αποθήκευση και επιστροφή στοιχείων ─────────────────────
        if ( ! empty( $name ) )    update_option( 'cc_wc_shipper_name', sanitize_text_field( $name ) );
        if ( ! empty( $address ) ) update_option( 'cc_wc_shipper_address', sanitize_text_field( $address ) );
        if ( ! empty( $postal ) )  update_option( 'cc_wc_shipper_postal_code', sanitize_text_field( $postal ) );
        if ( ! empty( $city ) )    update_option( 'cc_wc_shipper_city', sanitize_text_field( $city ) );
        if ( ! empty( $phone ) )   update_option( 'cc_wc_shipper_phone', sanitize_text_field( $phone ) );

        error_log( 'CC Autofill: Αποθήκευση ολοκληρώθηκε. Αποστολή response.' );

        wp_send_json_success( array(
            'shipper_name'         => $name,
            'shipper_address'      => $address,
            'shipper_postal_code'  => $postal,
            'shipper_city'         => $city,
            'shipper_phone'        => $phone,
        ) );
    }

    /**
     * Tracking section description
     */
    public function tracking_section_callback() {
        echo '<p>Ρυθμίσεις για τα emails που στέλνονται στους πελάτες με τα στοιχεία αποστολής.</p>';
    }

    /**
     * Tracking URL field with help text
     */
    public function tracking_url_field_callback( $args ) {
        $value = get_option( $args['label_for'], 'https://www.courier.gr/track/result?tracknr={{tracking}}' );
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="regular-text" style="width: 500px;">',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['label_for'] ),
            esc_attr( $value )
        );
        echo '<p class="description">';
        echo 'Το URL όπου ο πελάτης μπορεί να παρακολουθήσει την αποστολή του.<br>';
        echo 'Χρησιμοποιήστε <code>{{tracking}}</code> ως placeholder για το tracking number.<br>';
        echo '<strong>Προεπιλογή:</strong> <code>https://www.courier.gr/track/result?tracknr={{tracking}}</code>';
        echo '</p>';
    }

    /**
     * Email tracking enabled checkbox
     */
    public function email_tracking_field_callback( $args ) {
        $value = get_option( $args['label_for'], '1' );
        printf(
            '<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['label_for'] ),
            checked( $value, '1', false ),
            'Προσθήκη στοιχείων αποστολής στα emails του WooCommerce'
        );
        echo '<p class="description">';
        echo 'Όταν είναι ενεργό, τα WooCommerce emails που στέλνονται στον πελάτη (Processing, Completed κλπ) θα περιλαμβάνουν το AWB και link για παρακολούθηση της αποστολής.';
        echo '</p>';
    }

    /**
     * Print section description
     */
    public function print_section_callback() {
        echo '<p>Επιλέξτε το format εκτύπωσης για τα vouchers ανάλογα με τον εκτυπωτή σας.</p>';
    }

    /**
     * Print template dropdown for normal (non-BOX NOW) vouchers
     */
    public function print_template_field_callback( $args ) {
        $value = get_option( $args['label_for'], 'pdf' );
        $options = array(
            'pdf'                    => 'A4 - 3 θέσεις μη προεκτυπωμένο',
            'clean'                  => 'A4 - 3 θέσεις προεκτυπωμένο',
            'singlepdf_100x150_4up'  => 'A4 - 4 θέσεις 100x150mm (custom - FPDI)',
            'singlepdf'              => 'Θερμικός 205x100mm μη προεκτυπωμένο',
            'singleclean'            => 'Θερμικός 205x100mm προεκτυπωμένο',
            'singlepdf_100x150'      => 'Θερμικός 100x150mm',
            'singlepdf_100x170'      => 'Θερμικός 100x170mm',
        );
        printf( '<select id="%s" name="%s">', esc_attr( $args['label_for'] ), esc_attr( $args['label_for'] ) );
        foreach ( $options as $key => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $value, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }

    /**
     * Print template dropdown for BOX NOW vouchers
     */
    public function print_template_boxnow_field_callback( $args ) {
        $value = get_option( $args['label_for'], 'singlepdf_100x150_4up' );
        $options = array(
            'singlepdf_100x150_4up' => 'A4 - 4 θέσεις 100x150mm (default)',
            'singlepdf_100x150'     => 'Θερμικός 100x150mm',
        );
        printf( '<select id="%s" name="%s">', esc_attr( $args['label_for'] ), esc_attr( $args['label_for'] ) );
        foreach ( $options as $key => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $value, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }
}
