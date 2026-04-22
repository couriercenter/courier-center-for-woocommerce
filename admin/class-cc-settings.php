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
        add_action( 'wp_ajax_cc_clear_settings',    array( $this, 'ajax_clear_settings' ) );
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
            <button type="button" id="cc-clear-btn" class="button" style="margin-left: 8px; color: #b32d2e; border-color: #b32d2e;">
                🗑️ Εκκαθάριση
            </button>
            <span id="cc-autofill-status" style="margin-left: 12px; font-style: italic;"></span>

            <div id="cc-autofill-notice" style="display:none; margin-top: 12px; padding: 10px 14px; background: #e7f5e9; border-left: 4px solid #46b450; border-radius: 3px; font-size: 13px;">
                ✅ <strong>Στοιχεία από Courier Center API</strong> — τα πεδία συμπληρώθηκαν αυτόματα και αποθηκεύτηκαν.
                Επικοινωνήστε με τον διαχειριστή για αλλαγές.
            </div>
        </div>

        <script>
        console.log('CC Settings JS loaded');
        console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'NOT DEFINED');

        jQuery(document).ready(function($) {
            console.log('jQuery ready');
            console.log('Test button exists:', $('#cc-autofill-btn').length);
            console.log('Clear button exists:', $('#cc-clear-btn').length);

            $('#cc-autofill-btn').on('click', function() {
                var $btn    = $(this);
                var $status = $('#cc-autofill-status');
                var $notice = $('#cc-autofill-notice');

                $btn.prop('disabled', true).text('⏳ Παρακαλώ περιμένετε...');
                $status.css('color', '#666').text('Βήμα 1/3: Δημιουργία test αποστολής...');
                $notice.hide();

                var postData = {
                    action:           'cc_test_and_autofill',
                    nonce:            '<?php echo esc_js( $nonce ); ?>',
                    user_alias:       $('#cc_wc_user_alias').val(),
                    credential_value: $('#cc_wc_credential_value').val(),
                    api_key:          $('#cc_wc_api_key').val(),
                    billing_account:  $('#cc_wc_billing_account').val(),
                };
                console.log('CC Autofill POST data:', {
                    user_alias:       postData.user_alias      || '(empty)',
                    credential_value: postData.credential_value ? '(set, length=' + postData.credential_value.length + ')' : '(empty)',
                    api_key:          postData.api_key          ? '(set, length=' + postData.api_key.length + ')' : '(empty)',
                    billing_account:  postData.billing_account  || '(empty)',
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: postData,
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

            $('#cc-clear-btn').on('click', function() {
                if ( ! confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε όλες τις ρυθμίσεις;') ) {
                    return;
                }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cc_clear_settings',
                        nonce:  '<?php echo esc_js( wp_create_nonce( 'cc_clear_settings_nonce' ) ); ?>',
                    },
                    success: function() {
                        location.reload();
                    },
                    error: function(xhr, status, error) {
                        alert('❌ AJAX error: ' + error);
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
                ?>
            </form>

            <?php
            $next_cron = wp_next_scheduled( 'cc_status_tracking_cron' );
            $last_run  = get_option( 'cc_wc_cron_last_run', '' );
            ?>
            <div style="background:#f0f6fc; border-left:4px solid #2271b1; padding:12px 16px; margin-top:20px; max-width:700px; border-radius:3px;">
                <strong>⏰ Αυτόματη Ενημέρωση Status</strong><br>
                <small>
                    Επόμενη εκτέλεση: <strong><?php echo $next_cron ? human_time_diff( $next_cron ) . ' από τώρα (' . date( 'H:i', $next_cron ) . ')' : '❌ Δεν είναι προγραμματισμένο!'; ?></strong><br>
                    Τελευταία εκτέλεση: <strong><?php echo $last_run ? $last_run : 'Δεν έχει τρέξει ακόμα'; ?></strong>
                </small>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Δημιούργησε test αποστολή → GetShipmentDetails → Void → επέστρεψε στοιχεία αποστολέα
     */
    public function ajax_test_and_autofill() {
        error_log( 'CC AUTOFILL - Handler called!' );
        check_ajax_referer( 'cc_autofill_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $user_alias       = sanitize_text_field( $_POST['user_alias']       ?? '' );
        $credential_value = sanitize_text_field( $_POST['credential_value'] ?? '' );
        $api_key          = sanitize_text_field( $_POST['api_key']          ?? '' );
        $billing_account  = sanitize_text_field( $_POST['billing_account']  ?? '' );

        // Αν τα password fields ήρθαν κενά (browser δεν στέλνει masked τιμές), fallback στη βάση
        if ( empty( $credential_value ) ) {
            $credential_value = get_option( 'cc_wc_credential_value', '' );
        }
        if ( empty( $api_key ) ) {
            $api_key = get_option( 'cc_wc_api_key', '' );
        }
        if ( empty( $user_alias ) ) {
            $user_alias = get_option( 'cc_wc_user_alias', '' );
        }
        if ( empty( $billing_account ) ) {
            $billing_account = get_option( 'cc_wc_billing_account', '' );
        }

        // ── Βήμα 0: Αποθήκευσε credentials πρώτα ───────────────────────────
        update_option( 'cc_wc_user_alias',       $user_alias );
        update_option( 'cc_wc_credential_value', $credential_value );
        update_option( 'cc_wc_api_key',          $api_key );
        update_option( 'cc_wc_billing_account',  $billing_account );

        error_log( 'CC AUTOFILL credentials - alias: ' . $user_alias . ' | billing: ' . $billing_account . ' | api_key empty: ' . ( empty( $api_key ) ? 'YES' : 'NO' ) . ' | credential empty: ' . ( empty( $credential_value ) ? 'YES' : 'NO' ) );

        $api     = new CC_API();
        $context = array(
            'UserAlias'       => $user_alias,
            'CredentialValue' => $credential_value,
            'ApiKey'          => $api_key,
        );

        error_log( 'CC AUTOFILL Context: ' . wp_json_encode( $context ) );
        error_log( 'CC Autofill: Βήμα 1 — δημιουργία test αποστολής για billing_account=' . $billing_account );

        // ── Βήμα 1: Δημιουργία test αποστολής ──────────────────────────────
        $payload = array(
            'Context'      => $context,
            'shipmentDate' => date( 'Y-m-d' ),
            'comments'     => 'TEST AUTOFILL - DELETE',
            'Requestor'    => array( 'CarrierBillingAccount' => $billing_account ),
            'Shipper'      => array(
                'CarrierBillingAccount' => $billing_account,
                'CompanyName'           => 'TEST',
                'Address'               => 'TEST',
                'ZipCode'               => '10431',
                'City'                  => 'ΑΘΗΝΑ',
                'Phones'                => '2101234567',
                'Country'               => 'GREECE',
                'CountryCode'           => 'GR',
            ),
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

        // Ανακτούμε στοιχεία από ShipmentDetails[0]['ShipmentInfo']
        $info    = $details['ShipmentDetails'][0]['ShipmentInfo'] ?? array();
        $shipper = $info['Shipper'] ?? array();

        error_log( 'CC Autofill: ShipmentInfo=' . wp_json_encode( $info ) );
        error_log( 'CC Autofill: shipper block=' . wp_json_encode( $shipper ) );

        $name            = $shipper['CompanyName'] ?? '';
        $address         = $shipper['Address'] ?? '';
        $postal          = $shipper['ZipCode'] ?? '';
        $city            = $shipper['City'] ?? '';
        $phone           = $shipper['Phones'] ?? '';
        $shipper_station = $info['PickupStation']['Prefix'] ?? '';

        error_log( sprintf( 'CC Autofill: Εξήχθησαν → name=%s address=%s postal=%s city=%s phone=%s station=%s', $name, $address, $postal, $city, $phone, $shipper_station ) );

        // ── Βήμα 3: Void αποστολής ──────────────────────────────────────────
        $void_result = $api->void_shipment( $awb );
        if ( is_wp_error( $void_result ) ) {
            error_log( 'CC Autofill: Αποτυχία void AWB ' . $awb . ': ' . $void_result->get_error_message() );
        } else {
            error_log( 'CC Autofill: Void επιτυχής για AWB ' . $awb );
        }

        // ── Βήμα 4: Αποθήκευση στοιχείων αποστολέα ─────────────────────────
        update_option( 'cc_wc_shipper_name',        $name );
        update_option( 'cc_wc_shipper_address',     $address );
        update_option( 'cc_wc_shipper_postal_code', $postal );
        update_option( 'cc_wc_shipper_city',        $city );
        update_option( 'cc_wc_shipper_phone',       $phone );
        update_option( 'cc_wc_shipper_station',     $shipper_station );

        error_log( 'CC Autofill: Αποθήκευση ολοκληρώθηκε. Αποστολή response.' );

        wp_send_json_success( array(
            'shipper_name'         => $name,
            'shipper_address'      => $address,
            'shipper_postal_code'  => $postal,
            'shipper_city'         => $city,
            'shipper_phone'        => $phone,
            'shipper_station'      => $shipper_station,
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

    /**
     * AJAX: Διαγραφή όλων των plugin options
     */
    public function ajax_clear_settings() {
        check_ajax_referer( 'cc_clear_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $options = array(
            'cc_wc_user_alias',
            'cc_wc_credential_value',
            'cc_wc_api_key',
            'cc_wc_billing_account',
            'cc_wc_shipper_name',
            'cc_wc_shipper_address',
            'cc_wc_shipper_postal_code',
            'cc_wc_shipper_city',
            'cc_wc_shipper_phone',
            'cc_wc_shipper_station',
            'cc_wc_tracking_url',
            'cc_wc_email_tracking_enabled',
            'cc_wc_print_template',
            'cc_wc_print_template_boxnow',
        );

        foreach ( $options as $option ) {
            delete_option( $option );
        }

        wp_send_json_success();
    }
}
