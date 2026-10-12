<?php
/**
 * Email καταστήματος: Νέο αίτημα επιστροφής (HTML). Μπορεί να παρακαμφθεί από το θέμα:
 * yourtheme/woocommerce/emails/cc-return-admin-new.php
 *
 * @var WC_Order $order
 * @var array    $return_request
 * @var string[] $lines
 * @var string   $requests_url
 * @var string   $order_url
 * @var string   $email_heading
 * @var string   $additional_content
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php foreach ( $lines as $line ) : ?>
    <p style="margin:0 0 8px;"><?php echo esc_html( $line ); ?></p>
<?php endforeach; ?>

<p>
    <a href="<?php echo esc_url( $requests_url ); ?>"><?php echo esc_html( 'Δείτε τα αιτήματα επιστροφής' ); ?></a>
    <?php if ( '' !== $order_url ) : ?>
        · <a href="<?php echo esc_url( $order_url ); ?>"><?php echo esc_html( 'Άνοιγμα παραγγελίας' ); ?></a>
    <?php endif; ?>
</p>

<?php if ( $additional_content ) : ?>
    <p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_footer', $email );
