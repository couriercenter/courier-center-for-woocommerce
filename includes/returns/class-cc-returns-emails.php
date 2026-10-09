<?php
/**
 * Επιστροφές: καταχώρηση των 3 emails πελάτη στο WooCommerce.
 *
 * - woocommerce_email_actions: οι actions της υπηρεσίας γίνονται "transactional" (δουλεύουν και με deferred emails).
 * - woocommerce_email_classes: προσθέτει τις 3 κλάσεις (τα αρχεία φορτώνονται εδώ, όταν το WC_Email υπάρχει ήδη).
 *   Έτσι εμφανίζονται και στις ρυθμίσεις WooCommerce → Emails (ενεργοποίηση, θέμα, επικεφαλίδα).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Emails {

    const ACTIONS = array(
        'cc_return_request_received',
        'cc_return_shipment_created',
        'cc_return_rejected',
    );

    public function __construct() {
        add_filter( 'woocommerce_email_actions', array( $this, 'register_actions' ) );
        add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );
    }

    public function register_actions( $actions ) {
        return array_values( array_unique( array_merge( (array) $actions, self::ACTIONS ) ) );
    }

    public function register_emails( $emails ) {
        $dir = CC_WC_PLUGIN_DIR . 'includes/returns/emails/';
        require_once $dir . 'class-cc-email-return-base.php';
        require_once $dir . 'class-cc-email-return-received.php';
        require_once $dir . 'class-cc-email-return-created.php';
        require_once $dir . 'class-cc-email-return-rejected.php';

        $emails['CC_Email_Return_Received'] = new CC_Email_Return_Received();
        $emails['CC_Email_Return_Created']  = new CC_Email_Return_Created();
        $emails['CC_Email_Return_Rejected'] = new CC_Email_Return_Rejected();
        return $emails;
    }
}
