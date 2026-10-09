<?php
/**
 * Email πελάτη: Το αίτημα απορρίφθηκε (plain text). Παράκαμψη: yourtheme/woocommerce/emails/plain/cc-return-rejected.php
 *
 * @var WC_Order $order
 * @var array    $return_request
 * @var string[] $lines
 * @var string   $instructions
 * @var string[] $items
 * @var string   $method_label
 * @var string   $email_heading
 * @var string   $additional_content
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '= ' . wp_strip_all_tags( $email_heading ) . " =

";
echo wp_strip_all_tags( sprintf( 'Γεια σας %s,', $order->get_billing_first_name() ) ) . "

";
echo wp_strip_all_tags( sprintf( 'Το αίτημα επιστροφής για την παραγγελία #%s απορρίφθηκε.', $order->get_order_number() ) ) . "

";

foreach ( $items as $line ) {
    echo '- ' . wp_strip_all_tags( $line ) . "
";
}
if ( '' !== $method_label ) {
    echo "
" . wp_strip_all_tags( 'Τρόπος επιστροφής: ' . $method_label ) . "
";
}
echo "
" . wp_strip_all_tags( 'Για περισσότερες πληροφορίες μπορείτε να επικοινωνήσετε με το κατάστημα.' ) . "
";

if ( $additional_content ) {
    echo "
" . wp_strip_all_tags( wptexturize( $additional_content ) ) . "
";
}

echo "
----------------------------------------

";
echo wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
