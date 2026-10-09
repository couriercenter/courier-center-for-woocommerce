<?php
/**
 * Επιστροφές: email πελάτη «Επιστροφή: το αίτημα ελήφθη».
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Email_Return_Received extends CC_Email_Return_Base {

    public function __construct() {
        $this->id             = 'cc_return_received';
        $this->title          = 'Επιστροφή: το αίτημα ελήφθη';
        $this->description    = 'Στέλνεται στον πελάτη όταν καταχωρείται αίτημα επιστροφής.';
        $this->template_html  = 'emails/cc-return-received.php';
        $this->template_plain = 'emails/plain/cc-return-received.php';
        $this->trigger_action = 'cc_return_request_received';

        parent::__construct();
    }

    public function get_default_subject() {
        return 'Επιστροφή παραγγελίας #{order_number}: το αίτημα ελήφθη';
    }

    public function get_default_heading() {
        return 'Το αίτημα επιστροφής σας ελήφθη';
    }
}
