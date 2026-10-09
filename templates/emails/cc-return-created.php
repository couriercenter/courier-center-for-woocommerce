<?php
/**
 * Email πελάτη: Δημιουργήθηκε η αποστολή (HTML). Μπορεί να παρακαμφθεί από το θέμα:
 * yourtheme/woocommerce/emails/cc-return-created.php
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

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php echo esc_html( sprintf( 'Γεια σας %s,', $order->get_billing_first_name() ) ); ?></p>

<p>
<?php
$first = true;
foreach ( $lines as $line ) {
    if ( ! $first ) {
        echo '<br>';
    }
    echo $first ? '<strong>' . esc_html( $line ) . '</strong>' : esc_html( $line );
    $first = false;
}
?>
</p>

<?php if ( '' !== $instructions ) : ?>
    <?php echo wp_kses_post( wpautop( esc_html( $instructions ) ) ); ?>
<?php endif; ?>

<p><?php echo esc_html( sprintf( 'Παραγγελία #%s', $order->get_order_number() ) ); ?></p>
<ul>
<?php foreach ( $items as $line ) : ?>
    <li><?php echo esc_html( $line ); ?></li>
<?php endforeach; ?>
</ul>
<?php if ( '' !== $method_label ) : ?>
    <p><?php echo esc_html( 'Τρόπος επιστροφής: ' . $method_label ); ?></p>
<?php endif; ?>

<?php if ( $additional_content ) : ?>
    <p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_footer', $email );
