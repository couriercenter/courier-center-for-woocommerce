<?php
/**
 * Manifest Page - Εκτύπωση manifest παραλαβής ανά ημερομηνία
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Manifest_Page {

    public function __construct() {
        // PDF download handler
        add_action( 'admin_post_cc_download_manifest', array( $this, 'download_manifest' ) );
    }

    /**
     * Add submenu under Courier Center main menu
     */
    public function add_submenu() {
        add_submenu_page(
            'courier-center',
            'Manifest Παραλαβής',
            'Manifest Παραλαβής',
            'manage_woocommerce',
            'courier-center-manifest',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the manifest page
     */
    public function render_page() {
        $today         = date( 'Y-m-d' );
        $selected_date = isset( $_GET['manifest_date'] ) ? sanitize_text_field( $_GET['manifest_date'] ) : $today;

        // Validate date
        $d = DateTime::createFromFormat( 'Y-m-d', $selected_date );
        if ( ! $d || $d->format( 'Y-m-d' ) !== $selected_date ) {
            $selected_date = $today;
        }

        $download_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=cc_download_manifest&date=' . urlencode( $selected_date ) ),
            'cc_download_manifest'
        );

        // Count vouchers for this date from WC orders
        $vouchers_count = $this->count_vouchers_for_date( $selected_date );
        ?>
        <div class="wrap">
            <h1>🚚 Manifest Παραλαβής</h1>
            <p class="description">Εκτυπώστε τη λίστα αποστολών που θα παραληφθούν από τον οδηγό μια συγκεκριμένη ημέρα.</p>

            <style>
                .cc-manifest-card {
                    background: white;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    padding: 24px;
                    margin-top: 20px;
                    max-width: 700px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
                }
                .cc-manifest-card h2 {
                    margin-top: 0;
                    padding-bottom: 12px;
                    border-bottom: 1px solid #eee;
                }
                .cc-date-picker {
                    display: flex;
                    gap: 15px;
                    align-items: flex-end;
                    margin: 20px 0;
                    flex-wrap: wrap;
                }
                .cc-date-picker label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 5px;
                    color: #2c3338;
                }
                .cc-date-picker input[type="date"] {
                    padding: 8px 12px;
                    border: 1px solid #8c8f94;
                    border-radius: 4px;
                    font-size: 14px;
                    height: 36px;
                }
                .cc-date-shortcuts {
                    display: flex;
                    gap: 8px;
                    margin-top: 10px;
                }
                .cc-date-shortcuts a {
                    text-decoration: none;
                    color: #2271b1;
                    font-size: 13px;
                    padding: 4px 10px;
                    background: #f0f6fc;
                    border-radius: 3px;
                }
                .cc-date-shortcuts a:hover {
                    background: #dcf0ff;
                }
                .cc-info-box {
                    background: #f0f6fc;
                    border-left: 4px solid #2271b1;
                    padding: 12px 16px;
                    margin: 20px 0;
                    border-radius: 3px;
                }
                .cc-info-box strong {
                    display: block;
                    font-size: 16px;
                    margin-bottom: 4px;
                }
                .cc-print-actions {
                    margin-top: 20px;
                    display: flex;
                    gap: 10px;
                }
                .cc-print-actions .button {
                    height: 40px;
                    line-height: 38px;
                    padding: 0 20px;
                    font-size: 14px;
                }
                .cc-print-actions .button-primary {
                    background: #2271b1;
                    border-color: #2271b1;
                }
                .cc-tips {
                    margin-top: 25px;
                    padding: 14px 18px;
                    background: #fff8e1;
                    border-left: 4px solid #ffc107;
                    border-radius: 3px;
                    font-size: 13px;
                    line-height: 1.6;
                }
            </style>

            <div class="cc-manifest-card">
                <h2>📅 Επιλογή Ημερομηνίας</h2>

                <form method="get">
                    <input type="hidden" name="page" value="courier-center-manifest">

                    <div class="cc-date-picker">
                        <div>
                            <label for="manifest_date">Ημερομηνία παραλαβής:</label>
                            <input type="date" id="manifest_date" name="manifest_date"
                                   value="<?php echo esc_attr( $selected_date ); ?>"
                                   max="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
                        </div>
                        <div>
                            <button type="submit" class="button">Ενημέρωση</button>
                        </div>
                    </div>

                    <div class="cc-date-shortcuts">
                        <a href="<?php echo esc_url( add_query_arg( 'manifest_date', date( 'Y-m-d' ) ) ); ?>">📅 Σήμερα</a>
                        <a href="<?php echo esc_url( add_query_arg( 'manifest_date', date( 'Y-m-d', strtotime( '-1 day' ) ) ) ); ?>">⬅️ Χθες</a>
                        <a href="<?php echo esc_url( add_query_arg( 'manifest_date', date( 'Y-m-d', strtotime( '-2 day' ) ) ) ); ?>">⬅️ Προχθές</a>
                    </div>
                </form>

                <div class="cc-info-box">
                    <strong>📦 <?php echo esc_html( $vouchers_count ); ?> vouchers στο WooCommerce</strong>
                    <span>Για ημερομηνία: <strong><?php echo esc_html( $this->format_greek_date( $selected_date ) ); ?></strong></span>
                    <?php if ( $vouchers_count === 0 ) : ?>
                        <br><small style="color: #8b0000;">Δεν έχουν δημιουργηθεί vouchers για αυτή την ημερομηνία στο WooCommerce σας. Το manifest μπορεί να είναι κενό.</small>
                    <?php endif; ?>
                </div>

                <div class="cc-print-actions">
                    <a href="<?php echo esc_url( $download_url ); ?>" target="_blank" class="button button-primary">
                        📄 Λήψη Manifest PDF
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'download', '1', $download_url ) ); ?>" class="button">
                        💾 Αποθήκευση ως αρχείο
                    </a>
                </div>

                <div class="cc-tips">
                    <strong>💡 Πώς λειτουργεί:</strong><br>
                    • Το Manifest παράγεται απευθείας από το σύστημα της Courier Center με βάση τις αποστολές που έχετε καταχωρήσει.<br>
                    • Δώστε εκτυπωμένο το manifest στον οδηγό όταν έρθει για παραλαβή — θα το υπογράψει ως απόδειξη παραλαβής.<br>
                    • Αν η αποστολή έχει δημιουργηθεί αλλά δεν εμφανίζεται στο manifest, επικοινωνήστε με την Courier Center.
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle PDF download
     */
    public function download_manifest() {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cc_download_manifest' ) ) {
            wp_die( 'Invalid request' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized' );
        }

        $date = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : date( 'Y-m-d' );

        $api = new CC_API();
        $pdf = $api->get_manifest( $date );

        if ( is_wp_error( $pdf ) ) {
            wp_die( 'Σφάλμα λήψης manifest: ' . esc_html( $pdf->get_error_message() ) );
        }

        // Decide inline vs attachment
        $disposition = isset( $_GET['download'] ) ? 'attachment' : 'inline';

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: ' . $disposition . '; filename="manifest-' . $date . '.pdf"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        echo $pdf;
        exit;
    }

    /**
     * Count WooCommerce orders that have CC voucher with shipment_date = given date
     */
    private function count_vouchers_for_date( $date ) {
        $args = array(
            'limit'      => -1,
            'return'     => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_cc_voucher_number',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => '_cc_created_at',
                    'value'   => $date,
                    'compare' => 'LIKE',
                ),
            ),
        );

        $orders = wc_get_orders( $args );
        return is_array( $orders ) ? count( $orders ) : 0;
    }

    /**
     * Format date in Greek-friendly format (e.g. "14 Απριλίου 2026")
     */
    private function format_greek_date( $date ) {
        $months = array(
            '01' => 'Ιανουαρίου', '02' => 'Φεβρουαρίου', '03' => 'Μαρτίου',
            '04' => 'Απριλίου',   '05' => 'Μαΐου',       '06' => 'Ιουνίου',
            '07' => 'Ιουλίου',    '08' => 'Αυγούστου',   '09' => 'Σεπτεμβρίου',
            '10' => 'Οκτωβρίου',  '11' => 'Νοεμβρίου',   '12' => 'Δεκεμβρίου',
        );

        $parts = explode( '-', $date );
        if ( count( $parts ) !== 3 ) {
            return $date;
        }

        $year  = $parts[0];
        $month = $months[ $parts[1] ] ?? $parts[1];
        $day   = (int) $parts[2];

        return "$day $month $year";
    }
}