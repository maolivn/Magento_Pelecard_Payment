<?php
class Songnguyen_Pelecard_Model_Standard extends Mage_Payment_Model_Method_Cc
{
    protected $_code = 'pelecard';

    protected $_isGateway = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = false;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_canSaveCc = false;

    private function getUsername()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/username');
    }

    private function getPassword() {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/password');
    }

    private function getCurrency() {
        $currency = $this->getAcceptedCurrency();
        if($currency == 'ILS')
            return 1;
        elseif($currency == 'USD')
            return 2;
        elseif($currency == 'EUR')
            return 978;
    }

    private function getTeminal() {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/terminal_id');
    }

    private function getAcceptedCurrency()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/currency');
    }

    private function getCCDate($month, $year) {
        if($month < 10)
            $mm = '0' . $month;
        $yy = substr($year, 2,4);
        return $mm.$yy;
    }

    public function validate()
    {
        parent::validate();
        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            $currency_code = $paymentInfo->getOrder()->getBaseCurrencyCode();
        } else {
            $currency_code = $paymentInfo->getQuote()->getBaseCurrencyCode();
        }

        if ($currency_code != $this->getAcceptedCurrency()) {
//            Mage::throwException(Mage::helper('pelecard')->__('Selected currency code (' . $currency_code . ') is not compatabile with PeleCard'));
            Mage::throwException(Mage::helper('pelecard')->__('Selected currency code (' . $currency_code . ') is not compatabile with PeleCard (' . $this->getAcceptedCurrency() . ')'));
        }
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $this->setAmount($amount)
            ->setPayment($payment);

        $result = $this->callDoDirectPayment();
        if ($result['code'] != '000') {
            $e = $this->getError();
            if (isset($e['message'])) {
                $message = Mage::helper('pelecard')->__('There has been an error processing your payment. ') . $e['message'];
            } else {
                $message = Mage::helper('pelecard')->__('There has been an error processing your payment. Please try later or contact us for help.');
            }
            Mage::throwException($message);
        } else {
            $payment->setStatus(self::STATUS_APPROVED)->setLastTransId($result['RebillID']);
        }
        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        $payment->setStatus(self::STATUS_DECLINED);
        return $this;
    }

    /**
     * prepare params to send to gateway
     *
     * @return bool | array
     */
    public function callDoDirectPayment()
    {
        $payment = $this->getPayment();
        $billing = $payment->getOrder()->getBillingAddress();

        $invoiceDesc = '';
        $lengs = 0;
        foreach ($payment->getOrder()->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            if (Mage::helper('core/string')->strlen($invoiceDesc . $item->getName()) > 10000) {
                break;
            }
            $invoiceDesc .= $item->getName() . ', ';
        }
        $invoiceDesc = Mage::helper('core/string')->substr($invoiceDesc, 0, -2);

        $address = clone $billing;
        $address->unsFirstname();
        $address->unsLastname();
        $address->unsPostcode();
        $formatedAddress = '';
        $tmpAddress = explode(' ', str_replace("\n", ' ', trim($address->format('text'))));

        $username = $this->getUsername();
        $password = $this->getPassword();
        $termNo = $this->getTeminal();
        $cc = $payment->getCcNumber();
        $cc_date = $this->getCCDate($payment->getCcExpMonth(), $payment->getCcExpYear());
        $token = $this->createToken($username, $password, $termNo, $cc, $cc_date);
        $currency = $this->getCurrency();

        $operation = 'DebitRegularType';
        $data = array(
            'userName' => $username,
            'password' => $password,
            'termNo' => $termNo,
            'shopNo' => '001',
            'creditCard' => $cc,
            'creditCardDateMmyy' => $cc_date,
            'token' => $token,
            'total' => $this->getAmount(),
            'currency' => $currency,
            'cvv2' => $payment->getCcCid(),
            'id' => $payment->getOrder()->getIncrementId(),
            'authNum' => '12454',
            'parmx' => 'test'
        );
        list ($code, $result) = $this->do_post_request($operation, $data);

        $response['code'] = $code;
        $response['result'] = $result;
        return $response;

    }
    private function createToken($username, $password, $termno, $cc, $cc_date){
        $token_array = array(
            'userName' => 'biom123',
            'password' => 'biom123@2014',
            'termNo' => '0962210',
            'creditCard' => '4580000000000000',
            'creditCardDateMmyy' => '1215'
        );
        list($code, $result) = $this->do_post_request('ConvertToToken', $token_array);
        return $result;
    }

    ## Submit the data into pelecard servers
    private function do_post_request($operation, $data, $optional_headers = null)	{
        $params = array('http' => array(
            'method' => 'POST',
            'content' => http_build_query($data)
        ));

        $url = 'https://ws101.pelecard.biz/webservices.asmx/'.$operation;

        if ($optional_headers !== null) {
            $params['http']['header'] = $optional_headers;
        }
        $ctx = stream_context_create($params);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp) {
            throw new Exception("Problem with $url, $php_errormsg");
        }
        $response = @stream_get_contents($fp);
        if ($response === false) {
            throw new Exception("Problem reading data from $url, $php_errormsg");
        }

        return array(substr(trim(strip_tags($response)),0,3), trim(strip_tags($response)));
    }
}
