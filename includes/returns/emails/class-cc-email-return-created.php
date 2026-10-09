<?php
/**
 * Επιστροφές: email πελάτη «Επιστροφή: δημιουργήθηκε η αποστολή».
 *
 * Drop Off → μηνύματα §7.4 + οδηγίες Drop Off· BOX NOW → μηνύματα §10.6 (κωδικός, AWB, μέγεθος) + οδηγίες BOX NOW.
 * Τα μηνύματα είναι τα ίδια με του My Account (CC_Returns_Service::creation_message_lines).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Email_Return_Created extends CC_Email_Return_Base {

    public function __construct() {
        $this->id             = 'cc_return_created';
        $this->title          = 'Επιστροφή: δημιουργήθηκε η αποστολή';
        $this->description    = 'Στέλνεται στον πελάτη όταν δημιουργείται η αποστολή επιστροφής (με τα στοιχεία Drop Off ή BOX NOW).';
        $this->template_html  = 'emails/cc-return-created.php';
        $this->template_plain = 'emails/plain/cc-return-created.php';
        $this->trigger_action = 'cc_return_shipment_created';

        parent::__construct();
    }

    public function get_default_subject() {
        return 'Επιστροφή παραγγελίας #{order_number}: δημιουργήθηκε η αποστολή';
    }

    public function get_default_heading() {
        return 'Η επιστροφή σας δημιουργήθηκε';
    }

    protected function message_lines() {
        return $this->return_request ? CC_Returns_Service::creation_message_lines( $this->return_request ) : array();
    }

    protected function instructions() {
        if ( ! $this->return_request ) {
            return '';
        }
        return CC_Returns_Rules::METHOD_BOXNOW === $this->return_request['method']
            ? CC_Returns_Settings::boxnow_instructions()
            : CC_Returns_Settings::dropoff_instructions();
    }
}
