<?php

defined('ABSPATH') or exit;

class Wc_Paysera_Request
{
    const DEFAULT_LANG = 'ENG';
    const EMPTY_CODE = '';
    const DEFAULT_LOCAL = 'en';

    /**
     * @var int
     */
    protected $projectID;

    /**
     * @var string
     */
    protected $signature;

    /**
     * @var string
     */
    protected $returnUrl;

    /**
     * @var string
     */
    protected $callbackUrl;

    /**
     * @var bool
     */
    protected $test;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var array
     */
    protected $translationLang;

    /**
     * @var bool
     */
    protected $buyerConsent;

    /**
     * @return self
     */
    public static function create()
    {
        return new self();
    }

    public function __construct()
    {
        $this->projectID = 0;
        $this->signature = self::EMPTY_CODE;
        $this->returnUrl = self::EMPTY_CODE;
        $this->callbackUrl = self::EMPTY_CODE;
        $this->test = false;
        $this->locale = self::DEFAULT_LOCAL;
    }

    /**
     * @param array $parameters
     * @return string
     * @throws WebToPayException
     */
    public function buildUrl($parameters)
    {
        if ($parameters['prebuild'] === true) {
            $parameters = $this->buildParameters($parameters);
        }

        $request = WebToPay::buildRequest($parameters);
        $url = WebToPay::PAY_URL . '?' . http_build_query($request);

        return preg_replace('/[\r\n]+/is', '', $url);
    }

    /**
     * @param object $order
     * @param string $payment
     * @return array
     */
    public function getWooParameters($order, $payment)
    {
        $lang = self::DEFAULT_LANG;

        if ($this->getTranslationLang()[$this->getLocale()]) {
            $lang = $this->getTranslationLang()[$this->getLocale()];
        }

        return [
            'prebuild' => true,
            'order' => $order->get_id(),
            'amount' => intval(number_format($order->get_total(), 2, '', '')),
            'currency' => $order->get_currency(),
            'country' => $order->get_billing_country(),
            'cancel' => htmlspecialchars_decode($order->get_cancel_order_url()),
            'payment' => $payment,
            'firstname' => $order->get_billing_first_name(),
            'lastname' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'street' => $order->get_billing_address_1(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'zip' => $order->get_billing_postcode(),
            'countrycode' => $order->get_billing_country(),
            'lang' => $lang,
        ];
    }

    /**
     * @param array $parameters
     * @return array
     */
    protected function buildParameters($parameters)
    {
        return [
            'projectid' => $this->limitLength($this->getProjectID(), 11),
            'sign_password' => $this->limitLength($this->getSignature()),
            'orderid' => $this->limitLength($parameters['order'], 40),
            'amount' => $this->limitLength($parameters['amount'], 11),
            'currency' => $this->limitLength($parameters['currency'], 3),
            'country' => $this->limitLength($parameters['country'], 2),
            'accepturl' => $this->limitLength($this->getReturnUrl()),
            'cancelurl' => $this->limitLength($parameters['cancel']),
            'callbackurl' => $this->limitLength($this->getCallbackUrl()),
            'p_firstname' => $this->limitLength($parameters['firstname']),
            'p_lastname' => $this->limitLength($parameters['lastname']),
            'p_email' => $this->limitLength($parameters['email']),
            'p_street' => $this->limitLength($parameters['street']),
            'p_countrycode' => $this->limitLength($parameters['country'], 2),
            'p_city' => $this->limitLength($parameters['city']),
            'p_state' => $this->limitLength($parameters['state'], 20),
            'payment' => $this->limitLength($parameters['payment'], 20),
            'p_zip' => $this->limitLength($parameters['zip'], 20),
            'lang' => $this->limitLength($parameters['lang'], 3),
            'test' => $this->limitLength((int) $this->getTest(), 1),
            'buyer_consent' => $this->limitLength((int) $this->getBuyerConsent(), 1),
        ];
    }

    /**
     * @param  string  $string
     * @param  int $limit
     * @return string
     */
    protected function limitLength($string, $limit = 255)
    {
        if (strlen($string) > $limit) {
            return substr($string, 0, $limit);
        }

        return $string;
    }

    /**
     * @return int
     */
    public function getProjectID()
    {
        return $this->projectID;
    }

    /**
     * @param int $projectID
     * @return self
     */
    public function setProjectID($projectID)
    {
        $this->projectID = $projectID;

        return $this;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     * @return self
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * @return string
     */
    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    /**
     * @param string $returnUrl
     * @return self
     */
    public function setReturnUrl($returnUrl)
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getCallbackUrl()
    {
        return $this->callbackUrl;
    }

    /**
     * @param string $callbackUrl
     * @return self
     */
    public function setCallbackUrl($callbackUrl)
    {
        $this->callbackUrl = $callbackUrl;

        return $this;
    }

    /**
     * @return bool
     */
    public function getTest()
    {
        return $this->test;
    }

    /**
     * @param bool $test
     * @return self
     */
    public function setTest($test)
    {
        $this->test = $test;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     * @return self
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return array
     */
    public function getTranslationLang()
    {
        return $this->translationLang;
    }

    /**
     * @param array $translationLang
     * @return self
     */
    public function setTranslationLang($translationLang)
    {
        $this->translationLang = $translationLang;

        return $this;
    }

    /**
     * @return bool
     */
    public function getBuyerConsent()
    {
        return $this->buyerConsent;
    }

    /**
     * @param bool $buyerConsent
     * @return self
     */
    public function setBuyerConsent($buyerConsent)
    {
        $this->buyerConsent = $buyerConsent;

        return $this;
    }
}
