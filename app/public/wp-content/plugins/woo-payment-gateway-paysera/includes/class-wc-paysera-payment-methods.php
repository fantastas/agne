<?php

defined('ABSPATH') or exit;

if (!class_exists('Wc_Paysera_Html_Form')) {
    require_once 'class-wc-paysera-html-form.php';
}

class Wc_Paysera_Payment_Methods
{
    const EMPTY_CODE = '';
    const LINE_BREAK = '<div style="clear:both"><br/></div>';
    const COUNTRY_SELECT_MIN = 1;
    const DEFAULT_LANG = 'en';
    const DEFAULT_CURRENCY = 'EUR';

    /**
     * @var int
     */
    protected $projectID;

    /**
     * @var string
     */
    protected $billingCountry;

    /**
     * @var string
     */
    protected $lang;

    /**
     * @var bool
     */
    protected $displayList;

    /**
     * @var array
     */
    protected $countriesSelected;

    /**
     * @var bool
     */
    protected $gridView;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var double
     */
    protected $cartTotal;

    /**
     * @var string
     */
    protected $cartCurrency;

    /**
     * @var array
     */
    protected $availableLang;

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
        $this->lang = self::DEFAULT_LANG;
        $this->billingCountry = self::EMPTY_CODE;
        $this->displayList = false;
        $this->countriesSelected = self::EMPTY_CODE;
        $this->gridView = false;
        $this->description = false;
        $this->cartTotal = 0;
        $this->cartCurrency = self::DEFAULT_CURRENCY;
        $this->buyerConsent = true;
    }

    public function build()
    {
        $buildHtml = Wc_Paysera_Html_Form::create();

        if ($this->isDisplayList() === true) {
            $payseraCountries = $this->getPayseraCountries(
                $this->getProjectID(),
                $this->getCartTotal(),
                $this->getCartCurrency(),
                $this->listLang()
            );

            $countries = $this->getCountriesList($payseraCountries);

            if (count($countries) > self::COUNTRY_SELECT_MIN) {
                $paymentsHtml = $buildHtml->buildCountriesList($countries, $this->getBillingCountry());
                $paymentsHtml .= self::LINE_BREAK;
            } else {
                $paymentsHtml = self::EMPTY_CODE;
            }

            $paymentsHtml .= $buildHtml->buildPaymentsList($countries, $this->isGridView(), $this->getBillingCountry());
            $paymentsHtml .= self::LINE_BREAK;
        } else {
            $paymentsHtml = $this->getDescription();
        }

        if ($this->isBuyerConsent() === true) {
            $paymentsHtml .= self::LINE_BREAK;
            $paymentsHtml .= sprintf(
                __('Please be informed that the account information and payment initiation services will be provided to you by Paysera in accordance with these %s. By proceeding with this payment, you agree to receive this service and the service terms and conditions.', 'woo-payment-gateway-paysera'),
                '<a href="' . __('https://www.paysera.lt/v2/en-LT/legal/pis-rules-2020', 'woo-payment-gateway-paysera') . '
                        " target="_blank" rel="noopener noreferrer"> ' . __('rules', 'woo-payment-gateway-paysera')  .'</a>'
            );
        }

        return $paymentsHtml;
    }

    /**
     * @param int $project
     * @param string $amount
     * @param string $currency
     * @param string $lang
     * @return WebToPay_PaymentMethodCountry[]
     */
    protected function getPayseraCountries($project, $amount, $currency, $lang)
    {
        try {
            $countries = WebToPay::getPaymentMethodList($project, $currency)
                ->filterForAmount($amount, $currency)
                ->setDefaultLanguage($lang)
                ->getCountries()
            ;
        } catch (WebToPayException $exception) {
            error_log('[Paysera] Got an exception: ' . $exception);

            return [];
        }

        return $countries;
    }

    /**
     * @param array $countries
     * @return array
     */
    protected function getCountriesList($countries)
    {
        $countriesList = [];

        foreach ($countries as $country) {
            $checkForCountry = true;

            if (is_array($this->getCountriesSelected()) === true) {
                $checkForCountry = in_array($country->getCode(), $this->getCountriesSelected());
            }

            if ($checkForCountry === true) {
                $countriesList[] = [
                    'code' => $country->getCode(),
                    'title' => $country->getTitle(),
                    'groups' => $country->getGroups(),
                ];
            }
        }

        return $countriesList;
    }

    /**
     * @return string
     */
    protected function listLang()
    {
        if (in_array($this->getLang(), $this->getAvailableLang()) === true) {
            return $this->getLang();
        }

        return self::DEFAULT_LANG;
    }

    /**
     * @return string
     */
    public function getBillingCountry()
    {
        return $this->billingCountry;
    }

    /**
     * @param string $billingCountry
     * @return self
     */
    public function setBillingCountry($billingCountry)
    {
        $this->billingCountry = $billingCountry;

        return $this;
    }

    /**
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param string $lang
     * @return self
     */
    public function setLang($lang)
    {
        $this->lang = $lang;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDisplayList()
    {
        return $this->displayList;
    }

    /**
     * @param bool $displayList
     * @return self
     */
    public function setDisplayList($displayList)
    {
        $this->displayList = $displayList;

        return $this;
    }

    /**
     * @return array
     */
    public function getCountriesSelected()
    {
        return $this->countriesSelected;
    }

    /**
     * @param array $countriesSelected
     * @return self
     */
    public function setCountriesSelected($countriesSelected)
    {
        $this->countriesSelected = $countriesSelected;

        return $this;
    }

    /**
     * @return bool
     */
    public function isGridView()
    {
        return $this->gridView;
    }

    /**
     * @param bool $gridView
     * @return self
     */
    public function setGridView($gridView)
    {
        $this->gridView = $gridView;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return double
     */
    public function getCartTotal()
    {
        return $this->cartTotal;
    }

    /**
     * @param double $cartTotal
     * @return self
     */
    public function setCartTotal($cartTotal)
    {
        $this->cartTotal = $cartTotal;

        return $this;
    }

    /**
     * @return string
     */
    public function getCartCurrency()
    {
        return $this->cartCurrency;
    }

    /**
     * @param string $cartCurrency
     * @return self
     */
    public function setCartCurrency($cartCurrency)
    {
        $this->cartCurrency = $cartCurrency;

        return $this;
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
     * @return array
     */
    public function getAvailableLang()
    {
        return $this->availableLang;
    }

    /**
     * @param array $availableLang
     * @return self
     */
    public function setAvailableLang($availableLang)
    {
        $this->availableLang = $availableLang;

        return $this;
    }

    /**
     * @return bool
     */
    public function isBuyerConsent()
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
