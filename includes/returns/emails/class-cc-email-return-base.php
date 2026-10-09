<?php
/**
 * Επιστροφές: κοινή βάση των emails πελάτη (παραλήπτης = billing email της παραγγελίας).
 *
 * Κάθε υποκλάση ορίζει id, τίτλο, templates και το action της υπηρεσίας (π.χ. cc_return_rejected)·
 * το trigger() συνδέεται στο '<action>_notification' (πρότυπο WooCommerce, δουλεύει και με deferred emails).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class CC_Email_Return_Base extends WC_Email {

    /** @var string Το action της υπηρεσίας στο οποίο αντιδρά το email. */
    protected $trigger_action = '';

    /** @var array|null Η γραμμή του αιτήματος επιστροφής (CC_Returns_Repo::get). */
    public $return_request = null;

    public function __construct() {
        $this->customer_email = true;
        $this->template_base  = CC_WC_PLUGIN_DIR . 'templates/';
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        add_action( $this->trigger_action . '_notification', array( $this, 'trigger' ), 10, 1 );

        parent::__construct();
    }

    /** @param int $request_id */
    public function trigger( $request_id ) {
        $this->setup_locale();

        $this->object         = null;
        $this->return_request = null;
        $this->recipient      = '';

        $row   = CC_Returns_Repo::get( (int) $request_id );
        $order = $row ? wc_get_order( (int) $row['order_id'] ) : false;
        if ( $row && $order instanceof WC_Order ) {
            $this->object         = $order;
            $this->return_request = $row;
            $this->recipient      = $order->get_billing_email();

            $this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
            $this->placeholders['{order_number}'] = $order->get_order_number();
        }

        if ( $this->object && $this->is_enabled() && $this->get_recipient() ) {
            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        }

        $this->restore_locale();
    }

    /** Γραμμές μηνύματος (απλό κείμενο) που εμφανίζονται στο email. Οι υποκλάσεις τις ορίζουν. */
    protected function message_lines() {
        return array();
    }

    /** Οδηγίες προς τον πελάτη (απλό κείμενο). */
    protected function instructions() {
        return '';
    }

    /** Είδη του αιτήματος ως "Όνομα × ποσότητα". */
    protected function item_lines() {
        $lines = array();
        if ( ! $this->object || ! $this->return_request ) {
            return $lines;
        }
        foreach ( (array) $this->return_request['items'] as $line ) {
            $item    = $this->object->get_item( isset( $line['item_id'] ) ? (int) $line['item_id'] : 0 );
            $lines[] = ( $item ? $item->get_name() : 'Προϊόν' ) . ' × ' . ( isset( $line['qty'] ) ? (int) $line['qty'] : 0 );
        }
        return $lines;
    }

    protected function template_args( $plain_text ) {
        $method = $this->return_request ? $this->return_request['method'] : '';
        return array(
            'order'              => $this->object,
            'return_request'     => $this->return_request,
            'lines'              => $this->message_lines(),
            'instructions'       => $this->instructions(),
            'items'              => $this->item_lines(),
            'method_label'       => $method ? CC_Returns_Service::method_label( $method ) : '',
            'email_heading'      => $this->get_heading(),
            'additional_content' => $this->get_additional_content(),
            'sent_to_admin'      => false,
            'plain_text'         => $plain_text,
            'email'              => $this,
        );
    }

    public function get_content_html() {
        return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
    }

    public function get_content_plain() {
        return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
    }

    public function get_default_additional_content() {
        return '';
    }
}
