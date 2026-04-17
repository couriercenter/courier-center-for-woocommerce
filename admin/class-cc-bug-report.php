<?php
/**
 * Bug Report Page - Αναφορά Προβλήματος
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Bug_Report {

    const DASHBOARD_URL = 'https://courier-center-dashboard.onrender.com/api/report';

    public function __construct() {
        add_action( 'wp_ajax_cc_submit_bug_report', array( $this, 'ajax_submit_report' ) );
    }

    public function add_submenu() {
        add_submenu_page(
            'courier-center',
            'Αναφορά Προβλήματος',
            '🐛 Αναφορά Προβλήματος',
            'manage_woocommerce',
            'courier-center-bug-report',
            array( $this, 'render_page' )
        );
    }

    public function render_page() {
        global $wp_version;
        $plugin_version = CC_WC_VERSION;
        $site_url       = get_site_url();
        $wc_version     = defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A';
        $php_version    = PHP_VERSION;
        ?>
        <div class="wrap">
            <h1>🐛 Αναφορά Προβλήματος</h1>
            <p style="color:#666;">Χρησιμοποιήστε αυτή τη φόρμα για να αναφέρετε τεχνικά προβλήματα στην ομάδα του Courier Center.</p>

            <div id="cc-bug-result" style="display:none; margin-bottom:16px;"></div>

            <div class="cc-bug-form-wrap" style="max-width:700px; background:#fff; padding:24px; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.1);">

                <table class="form-table" style="margin-bottom:0;">
                    <tr>
                        <th style="width:180px;">Τίτλος Προβλήματος <span style="color:red;">*</span></th>
                        <td>
                            <input type="text" id="cc-bug-title" class="regular-text" style="width:100%;"
                                   placeholder="π.χ. Το voucher δεν δημιουργείται για παραγγελίες με αντικαταβολή" />
                        </td>
                    </tr>
                    <tr>
                        <th>Περιγραφή <span style="color:red;">*</span></th>
                        <td>
                            <textarea id="cc-bug-description" rows="6" style="width:100%;"
                                      placeholder="Περιγράψτε το πρόβλημα αναλυτικά:&#10;- Τι ακριβώς συνέβη;&#10;- Πώς μπορεί να αναπαραχθεί;&#10;- Τι αναμένατε να συμβεί;"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Σοβαρότητα <span style="color:red;">*</span></th>
                        <td>
                            <select id="cc-bug-severity" style="min-width:200px;">
                                <option value="low">🟢 Χαμηλή — Μικρό πρόβλημα, υπάρχει workaround</option>
                                <option value="medium" selected>🟡 Μεσαία — Επηρεάζει λειτουργία</option>
                                <option value="critical">🔴 Κρίσιμη — Δεν λειτουργεί καθόλου</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <hr style="margin:20px 0;">

                <h3 style="margin-top:0; font-size:13px; color:#666; text-transform:uppercase; letter-spacing:.05em;">Πληροφορίες Συστήματος (αποστέλλονται αυτόματα)</h3>
                <table class="widefat" style="border-radius:4px; overflow:hidden;">
                    <tr>
                        <td style="width:180px; color:#666;">Site URL</td>
                        <td><code><?php echo esc_html( $site_url ); ?></code></td>
                    </tr>
                    <tr>
                        <td style="color:#666;">Έκδοση Plugin</td>
                        <td><code><?php echo esc_html( $plugin_version ); ?></code></td>
                    </tr>
                    <tr>
                        <td style="color:#666;">WordPress</td>
                        <td><code><?php echo esc_html( $wp_version ); ?></code></td>
                    </tr>
                    <tr>
                        <td style="color:#666;">WooCommerce</td>
                        <td><code><?php echo esc_html( $wc_version ); ?></code></td>
                    </tr>
                    <tr>
                        <td style="color:#666;">PHP</td>
                        <td><code><?php echo esc_html( $php_version ); ?></code></td>
                    </tr>
                </table>

                <div style="margin-top:20px;">
                    <button id="cc-bug-submit" class="button button-primary button-large">
                        📤 Αποστολή Αναφοράς
                    </button>
                    <span id="cc-bug-spinner" class="spinner" style="float:none; margin-top:0; vertical-align:middle; display:none;"></span>
                </div>

            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {

            $('#cc-bug-submit').on('click', function(e) {
                e.preventDefault();

                var title       = $('#cc-bug-title').val().trim();
                var description = $('#cc-bug-description').val().trim();
                var severity    = $('#cc-bug-severity').val();

                if ( ! title ) {
                    showResult('error', '❌ Παρακαλώ συμπληρώστε τον τίτλο του προβλήματος.');
                    $('#cc-bug-title').focus();
                    return;
                }
                if ( ! description ) {
                    showResult('error', '❌ Παρακαλώ συμπληρώστε την περιγραφή του προβλήματος.');
                    $('#cc-bug-description').focus();
                    return;
                }

                $('#cc-bug-submit').prop('disabled', true).text('⏳ Αποστολή...');
                $('#cc-bug-spinner').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action:      'cc_submit_bug_report',
                        nonce:       '<?php echo wp_create_nonce( 'cc_bug_report_nonce' ); ?>',
                        title:       title,
                        description: description,
                        severity:    severity,
                    },
                    success: function(response) {
                        if ( response.success ) {
                            showResult('success', '✅ Η αναφορά στάλθηκε επιτυχώς! Η ομάδα του Courier Center θα επικοινωνήσει μαζί σας.');
                            $('#cc-bug-title').val('');
                            $('#cc-bug-description').val('');
                            $('#cc-bug-severity').val('medium');
                        } else {
                            showResult('error', '❌ Σφάλμα: ' + (response.data.message || 'Άγνωστο σφάλμα'));
                        }
                    },
                    error: function() {
                        showResult('error', '❌ Σφάλμα σύνδεσης. Ελέγξτε το internet και δοκιμάστε ξανά.');
                    },
                    complete: function() {
                        $('#cc-bug-submit').prop('disabled', false).text('📤 Αποστολή Αναφοράς');
                        $('#cc-bug-spinner').hide();
                    }
                });
            });

            function showResult(type, message) {
                var $result = $('#cc-bug-result');
                var cls = type === 'success' ? 'notice-success' : 'notice-error';
                $result
                    .removeClass('notice-success notice-error')
                    .addClass('notice ' + cls)
                    .html('<p>' + message + '</p>')
                    .show();
                $('html, body').animate({ scrollTop: $result.offset().top - 60 }, 300);
            }

        });
        </script>
        <?php
    }

    /**
     * AJAX handler — στέλνει το report στο Render dashboard
     */
    public function ajax_submit_report() {
        if ( ! check_ajax_referer( 'cc_bug_report_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $title       = sanitize_text_field( $_POST['title'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $severity    = sanitize_text_field( $_POST['severity'] ?? 'low' );

        if ( empty( $title ) || empty( $description ) ) {
            wp_send_json_error( array( 'message' => 'Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία' ) );
        }

        if ( ! in_array( $severity, array( 'low', 'medium', 'critical' ), true ) ) {
            $severity = 'low';
        }

        global $wp_version;

        $payload = array(
            'site_url'       => get_site_url(),
            'wp_version'     => $wp_version,
            'plugin_version' => CC_WC_VERSION,
            'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
            'php_version'    => PHP_VERSION,
            'title'          => $title,
            'description'    => $description,
            'severity'       => $severity,
        );

        $response = wp_remote_post(
            self::DASHBOARD_URL,
            array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Σφάλμα σύνδεσης: ' . $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 201 ) {
            wp_send_json_success( array( 'message' => 'Η αναφορά στάλθηκε επιτυχώς' ) );
        } else {
            $body = wp_remote_retrieve_body( $response );
            wp_send_json_error( array( 'message' => 'Απόκριση API: ' . $code . ' — ' . $body ) );
        }
    }
}