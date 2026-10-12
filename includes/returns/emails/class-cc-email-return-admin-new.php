<?php
/**
 * Επιστροφές: email προς το κατάστημα «Νέο αίτημα επιστροφής».
 *
 * Στέλνεται για κάθε αίτημα που υποβάλλει πελάτης (cc_return_request_submitted) — με έγκριση («Περιμένει την
 * έγκρισή σας») ή χωρίς (η αποστολή επιστροφής δημιουργήθηκε αυτόματα). Οι επιστροφές που δημιουργεί το ίδιο
 * το κατάστημα δεν το στέλνουν. Παραλήπτης/ενεργοποίηση από WooCommerce → Ρυθμίσεις → Emails.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Email_Return_Admin_New extends WC_Email {

    /** @var array|null */
    public $return_request = null;

    public function __construct() {
        $this->id             = 'cc_return_admin_new';
        $this->title          = 'Επιστροφή: νέο αίτημα (προς το κατάστημα)';
        $this->description    = 'Στέλνεται στο κατάστημα όταν ένας πελάτης υποβάλλει αίτημα επιστροφής.';
        $this->template_html  = 'emails/cc-return-admin-new.php';
        $this->template_plain = 'emails/plain/cc-return-admin-new.php';
        $this->template_base  = CC_WC_PLUGIN_DIR . 'templates/';
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        add_action( 'cc_return_request_submitted_notification', array( $this, 'trigger' ), 10, 1 );

        parent::__construct();

        $this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
    }

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields = array_merge(
            array_slice( $this->form_fields, 0, 1, true ),
            array(
                'recipient' => array(
                    'title'       => 'Παραλήπτες',
                    'type'        => 'text',
                    /* translators: %s: admin email */
                    'description' => sprintf( 'Διευθύνσεις χωρισμένες με κόμμα. Προεπιλογή: %s.', esc_attr( get_option( 'admin_email' ) ) ),
                    'placeholder' => '',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            ),
            array_slice( $this->form_fields, 1, null, true )
        );
    }

    public function get_default_subject() {
        return 'Νέο αίτημα επιστροφής για την παραγγελία #{order_number}';
    }

    public function get_default_heading() {
        return 'Νέο αίτημα επιστροφής';
    }

    /** @param int $request_id */
    public function trigger( $request_id ) {
        $this->setup_locale();

        $this->object         = null;
        $this->return_request = null;

        $row   = CC_Returns_Repo::get( (int) $request_id );
        $order = $row ? wc_get_order( (int) $row['order_id'] ) : false;
        if ( $row && $order instanceof WC_Order ) {
            $this->object         = $order;
            $this->return_request = $row;

            $this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
            $this->placeholders['{order_number}'] = $order->get_order_number();
        }

        if ( $this->object && $this->is_enabled() && $this->get_recipient() ) {
            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        }

        $this->restore_locale();
    }

    /** Οι γραμμές του email ως απλό κείμενο (κοινές για HTML και plain). */
    public function summary_lines() {
        $row   = $this->return_request;
        $order = $this->object;
        if ( ! $row || ! $order instanceof WC_Order ) {
            return array();
        }
        $name  = trim( $order->get_formatted_billing_full_name() );
        $lines = array(
            sprintf( 'Ο πελάτης %s ζήτησε επιστροφή για την παραγγελία #%s.', '' !== $name ? $name : $order->get_billing_email(), $order->get_order_number() ),
        );
        foreach ( (array) $row['items'] as $line ) {
            $item    = $order->get_item( isset( $line['item_id'] ) ? (int) $line['item_id'] : 0 );
            $lines[] = '• ' . ( $item ? $item->get_name() : 'Προϊόν' ) . ' × ' . ( isset( $line['qty'] ) ? (int) $line['qty'] : 0 );
        }
        $lines[] = 'Τρόπος επιστροφής: ' . CC_Returns_Service::method_label( $row['method'] );
        if ( CC_Returns_Rules::METHOD_PICKUP === $row['method'] && '' !== CC_Returns_Service::pickup_label( $row ) ) {
            $lines[] = 'Παραλαβή: ' . CC_Returns_Service::pickup_label( $row );
        }
        if ( '' !== trim( (string) $row['reason'] ) ) {
            $lines[] = 'Αιτιολογία: ' . trim( (string) $row['reason'] );
        }
        if ( CC_Returns_Rules::ST_PENDING === $row['status'] ) {
            $lines[] = 'Περιμένει την έγκρισή σας.';
        } elseif ( '' !== trim( (string) $row['return_awb'] ) ) {
            $lines[] = 'Η αποστολή επιστροφής δημιουργήθηκε αυτόματα. Αριθμός αποστολής επιστροφής: ' . $row['return_awb'];
        } else {
            $lines[] = 'Κατάσταση: ' . CC_Returns_Service::status_label( $row['status'] );
        }
        return $lines;
    }

    protected function template_args( $plain_text ) {
        return array(
            'order'              => $this->object,
            'return_request'     => $this->return_request,
            'lines'              => $this->summary_lines(),
            'requests_url'       => CC_Returns_Admin::page_url(),
            'order_url'          => $this->object ? $this->object->get_edit_order_url() : '',
            'email_heading'      => $this->get_heading(),
            'additional_content' => $this->get_additional_content(),
            'sent_to_admin'      => true,
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
