<?php

class Songnguyen_Pelecard_Block_Form extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('songnguyen/pelecard/form.phtml');
    }

}
