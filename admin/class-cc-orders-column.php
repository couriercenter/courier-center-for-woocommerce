<?php
/**
 * Στήλη "Μέθοδος Αποστολής" στη λίστα παραγγελιών (HPOS + legacy),
 * με ένδειξη αν η μέθοδος διαχειρίζεται από το plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Orders_Column {

    const COLUMN_KEY = 'cc_shipping_method';

    public function __construct() {
        // HPOS orders table
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ) );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column_hpos' ), 10, 2 );

        // Legacy posts table
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column_legacy' ), 10, 2 );
    }

    /**
     * Πρόσθεσε τη στήλη μετά τη "status" αν υπάρχει, αλλιώς στο τέλος.
     */
    public function add_column( $columns ) {
        $label = __( 'Μέθοδος Αποστολής', 'courier-center-woocommerce' );

        if ( ! isset( $columns['order_status'] ) ) {
            $columns[ self::COLUMN_KEY ] = $label;
            return $columns;
        }

        $new = array();
        foreach ( $columns as $key => $value ) {
            $new[ $key ] = $value;
            if ( 'order_status' === $key ) {
                $new[ self::COLUMN_KEY ] = $label;
            }
        }
        return $new;
    }

    public function render_column_hpos( $column, $order ) {
        if ( self::COLUMN_KEY !== $column ) {
            return;
        }
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order );
        }
        echo $this->column_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within column_html
    }

    public function render_column_legacy( $column, $post_id ) {
        if ( self::COLUMN_KEY !== $column ) {
            return;
        }
        echo $this->column_html( wc_get_order( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within column_html
    }

    private function column_html( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return '&mdash;';
        }

        $method = $order->get_shipping_method();
        if ( '' === $method ) {
            $method = '—';
        }

        $handled = CC_Shipment_Builder::is_handled_order( $order );
        $color   = $handled ? '#5DC122' : '#999';
        $title   = $handled
            ? __( 'Διαχειρίζεται από το plugin', 'courier-center-woocommerce' )
            : __( 'Δεν διαχειρίζεται από το plugin', 'courier-center-woocommerce' );

        return sprintf(
            '<span title="%s" style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;line-height:1.6;color:#fff;background:%s;">%s</span>',
            esc_attr( $title ),
            esc_attr( $color ),
            esc_html( $method )
        );
    }
}
