<?php
/**
 * Email καταστήματος: Νέο αίτημα επιστροφής (απλό κείμενο).
 *
 * @var string[] $lines
 * @var string   $requests_url
 * @var string   $order_url
 * @var string   $email_heading
 * @var string   $additional_content
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

foreach ( $lines as $line ) {
    echo esc_html( wp_strip_all_tags( $line ) ) . "\n";
}

echo "\n" . esc_html( 'Αιτήματα επιστροφής: ' ) . esc_url_raw( $requests_url ) . "\n";
if ( '' !== $order_url ) {
    echo esc_html( 'Παραγγελία: ' ) . esc_url_raw( $order_url ) . "\n";
}

if ( $additional_content ) {
    echo "\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n" . wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
