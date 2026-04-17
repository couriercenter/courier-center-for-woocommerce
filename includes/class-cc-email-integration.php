<?php
/**
 * Email Integration - Προσθέτει τα στοιχεία Courier Center αποστολής
 * στα WooCommerce customer emails
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Email_Integration {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook στο WooCommerce email template - πάνω από τα order details
        add_action( 'woocommerce_email_before_order_table', array( $this, 'add_tracking_info' ), 10, 4 );
    }

    /**
     * Προσθέτει τα στοιχεία αποστολής στο email
     *
     * @param WC_Order $order         Η παραγγελία
     * @param bool     $sent_to_admin Αν το email στέλνεται στον admin/merchant
     * @param bool     $plain_text    Αν είναι plain text format
     * @param WC_Email $email         Το email object
     */
    public function add_tracking_info( $order, $sent_to_admin, $plain_text, $email ) {
        // Μόνο στα customer emails, όχι στον admin
        if ( $sent_to_admin ) {
            return;
        }

        // Έλεγχος αν το feature είναι ενεργό
        if ( get_option( 'cc_wc_email_tracking_enabled', '1' ) !== '1' ) {
            return;
        }

        // Πάρε τα στοιχεία αποστολής από την παραγγελία
        $awb       = $order->get_meta( '_cc_voucher_number' );
        $tracking  = $order->get_meta( '_cc_tracking_number' );
        $is_voided = $order->get_meta( '_cc_voided' ) === '1';

        // Αν δεν υπάρχει voucher ή έχει ακυρωθεί, μην εμφανίσεις τίποτα
        if ( empty( $awb ) || $is_voided ) {
            return;
        }

        // Χρησιμοποίησε το tracking number αν υπάρχει, αλλιώς το AWB
        $tracking_code = ! empty( $tracking ) ? $tracking : $awb;

        // Χτίσε το tracking URL
        $tracking_url_template = get_option(
            'cc_wc_tracking_url',
            'https://www.courier.gr/track/result?tracknr={{tracking}}'
        );
        $tracking_url = str_replace( '{{tracking}}', urlencode( $tracking_code ), $tracking_url_template );

        // Render ανάλογα με το format (HTML ή plain text)
        if ( $plain_text ) {
            $this->render_plain_text( $awb, $tracking_url );
        } else {
            $this->render_html( $awb, $tracking_url );
        }
    }

    /**
     * HTML version για modern email clients
     */
    private function render_html( $awb, $tracking_url ) {
        ?>
        <div style="margin: 0 0 30px; padding: 20px; background: #f6f9fc; border-left: 4px solid #2271b1; border-radius: 4px;">
            <h3 style="margin: 0 0 10px; color: #1d2327; font-size: 16px;">
                🚚 Στοιχεία Αποστολής
            </h3>
            <p style="margin: 0 0 10px; color: #2c3338; font-size: 14px; line-height: 1.5;">
                Η παραγγελία σας έχει δρομολογηθεί μέσω <strong>Courier Center</strong>.
            </p>
            <table cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 10px 0;">
                <tr>
                    <td style="padding: 6px 0; color: #50575e; font-size: 14px; width: 180px;">Αριθμός Αποστολής:</td>
                    <td style="padding: 6px 0; color: #1d2327; font-size: 14px; font-weight: 600;">
                        <?php echo esc_html( $awb ); ?>
                    </td>
                </tr>
            </table>
            <p style="margin: 15px 0 0;">
                <a href="<?php echo esc_url( $tracking_url ); ?>"
                   style="display: inline-block; padding: 10px 20px; background: #2271b1; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: 500;">
                    📍 Παρακολούθηση Αποστολής
                </a>
            </p>
            <p style="margin: 15px 0 0; color: #787c82; font-size: 12px; line-height: 1.4;">
                Μπορείτε να παρακολουθείτε την αποστολή σας πατώντας το παραπάνω κουμπί ή χρησιμοποιώντας τον αριθμό αποστολής στο
                <a href="<?php echo esc_url( $tracking_url ); ?>" style="color: #2271b1;">courier.gr</a>.
            </p>
        </div>
        <?php
    }

    /**
     * Plain text version για email clients χωρίς HTML
     */
    private function render_plain_text( $awb, $tracking_url ) {
        echo "\n";
        echo "=====================================\n";
        echo "ΣΤΟΙΧΕΙΑ ΑΠΟΣΤΟΛΗΣ - COURIER CENTER\n";
        echo "=====================================\n";
        echo "Αριθμός Αποστολής: " . $awb . "\n";
        echo "Παρακολούθηση: " . $tracking_url . "\n";
        echo "=====================================\n\n";
    }
}