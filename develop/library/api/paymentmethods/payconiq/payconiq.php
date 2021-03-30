<?php
require_once(dirname(__FILE__).'/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooPayconiq extends BuckarooPaymentMethod {

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "payconiq";
        $this->version = 1;
        $this->mode = BuckarooConfig::getMode('PAYCONIQ');
    }
}
