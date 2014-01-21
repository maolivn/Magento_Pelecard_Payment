<?php

class Songnguyen_Pelecard_Block_Info extends Mage_Payment_Block_Info_Cc
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('songnguyen/pelecard/info.phtml');
    }
}
