<?php
class Songnguyen_Pelecard_Model_Source_Currency{
    protected $_options;

    public function toOptionArray($isMultiselect)
    {
        $this->_options = array(
            array(
            'label' => 'Israeli New Sheqel',
            'value' => 'ILS',
            ),
            array(
                'label' => 'US Dollar',
                'value' => 'USD',
            ),
            array(
                'label' => 'Euro',
                'value' => 'EUR',
            )
        );

        $options = $this->_options;
        return $options;
    }
}
