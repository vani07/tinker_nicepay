<?php
class Nicepay_VirtualAccount_Block_Form extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('virtualaccount/form/virtualaccount.phtml');
    }
}
