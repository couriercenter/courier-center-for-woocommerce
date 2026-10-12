<?php
/**
 * Επιστροφές: email πελάτη «Επιστροφή: το αίτημα απορρίφθηκε».
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Email_Return_Rejected extends CC_Email_Return_Base {

    /** Ο πελάτης δεν έκανε αίτημα όταν την επιστροφή τη δημιούργησε το κατάστημα → κανένα «απορρίφθηκε». */
    protected function allowed_for( array $row ) {
        return parent::allowed_for( $row ) && ( ! isset( $row['origin'] ) || 'shop' !== $row['origin'] );
    }

    public function __construct() {
        $this->id             = 'cc_return_rejected';
        $this->title          = 'Επιστροφή: το αίτημα απορρίφθηκε';
        $this->description    = 'Στέλνεται στον πελάτη όταν απορρίπτεται το αίτημα επιστροφής.';
        $this->template_html  = 'emails/cc-return-rejected.php';
        $this->template_plain = 'emails/plain/cc-return-rejected.php';
        $this->trigger_action = 'cc_return_rejected';

        parent::__construct();
    }

    public function get_default_subject() {
        return 'Επιστροφή παραγγελίας #{order_number}: το αίτημα απορρίφθηκε';
    }

    public function get_default_heading() {
        return 'Το αίτημα επιστροφής σας απορρίφθηκε';
    }
}
