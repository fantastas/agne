<?php

defined('ABSPATH') or exit;

class Wc_Paysera_Settings extends WC_Payment_Gateway
{
    const DEFAULT_PROJECT_ID = 0;
    const DEFAULT_CURRENCY = 'EUR';
    const DEFAULT_LANG = 'en';

    /**
     * @var int
     */
    protected $projectID;

    /**
     * @var string
     */
    protected $currency;

    /**
     * @var string
     */
    protected $lang;

    /**
     * @var array
     */
    protected $formFields;

    /**
     * @return self
     */
    public static function create()
    {
        return new self();
    }

    public function __construct()
    {
        $this->projectID = self::DEFAULT_PROJECT_ID;
        $this->currency = self::DEFAULT_CURRENCY;
        $this->lang = self::DEFAULT_LANG;
        $this->formFields = [
            'enabled' => [
                'title' => __('Enable Paysera', 'woo-payment-gateway-paysera'),
                'label' => __('Enable Paysera payment', 'woo-payment-gateway-paysera'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ],
            'projectid' => [
                'title' => __('Project ID', 'woo-payment-gateway-paysera'),
                'type' => 'number',
                'description' => __('Project id', 'woo-payment-gateway-paysera'),
                'default' => __('', 'woo-payment-gateway-paysera'),
            ],
            'password' => [
                'title' => __('Sign', 'woo-payment-gateway-paysera'),
                'type' => 'text',
                'description' => __('Paysera sign password', 'woo-payment-gateway-paysera'),
                'default' => __('', 'woo-payment-gateway-paysera'),
            ],
            'test' => [
                'title' => __('Test', 'woo-payment-gateway-paysera'),
                'type' => 'checkbox',
                'label' => __('Enable test mode', 'woo-payment-gateway-paysera'),
                'default' => 'yes',
                'description' => __('Enable this to accept test payments', 'woo-payment-gateway-paysera'),
            ],
            'title' => [
                'title' => __('Title', 'woo-payment-gateway-paysera'),
                'type' => 'text',
                'description' => __(
                    'Payment method title that the customer will see on your website.',
                    'woo-payment-gateway-paysera'
                ),
                'default' => __('All popular payment methods', 'woo-payment-gateway-paysera'),
            ],
            'description' => [
                'title' => __('Description', 'woo-payment-gateway-paysera'),
                'type' => 'textarea',
                'css' => 'width: 400px;',
                'description' => __(
                    'This controls the description which the user sees during checkout.',
                    'woo-payment-gateway-paysera'
                ),
                'default' => __('Choose a payment method on the Paysera page', 'woo-payment-gateway-paysera'),
            ],
            'paymentType' => [
                'title' => __('List of payments', 'woo-payment-gateway-paysera'),
                'type' => 'checkbox',
                'label' => __('Display payment methods list', 'woo-payment-gateway-paysera'),
                'default' => 'yes',
                'description' => __(
                    'Enable this to display payment methods list at checkout page',
                    'woo-payment-gateway-paysera'
                ),
            ],
            'countriesSelected' => [
                'title' => __('Specific countries', 'woo-payment-gateway-paysera'),
                'type' => 'multiselect',
                'class'	=> 'wc-enhanced-select',
                'css' => 'width: 400px;',
                'default' => '',
                'description' => __(
                    'Select which country payment methods to display (empty means all)',
                    'woo-payment-gateway-paysera'
                ),
                'options' => [],
                'custom_attributes' => [
                    'data-placeholder' => __('All countries', 'woo-payment-gateway-paysera'),
                ],
            ],
            'style' => [
                'title' => __('Grid view', 'woo-payment-gateway-paysera'),
                'type' => 'checkbox',
                'label' => __('Enable grid view', 'woo-payment-gateway-paysera'),
                'default' => 'no',
                'description' => __('Enable this to use payment methods grid view', 'woo-payment-gateway-paysera'),
            ],
            'buyerConsent' => [
                'title' => __('Buyer consent', 'woo-payment-gateway-paysera'),
                'type' => 'checkbox',
                'label' => __('Enable buyer consent', 'woo-payment-gateway-paysera'),
                'default' => 'yes',
                'description' => __(
                    'Enable this to skip additional step when using Pis payment methods',
                    'woo-payment-gateway-paysera'
                ),
            ],
            'paymentNewOrderStatus' => [
                'title' => __('New Order Status', 'woo-payment-gateway-paysera'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'wc-processing',
                'description' => __('New order creation status', 'woo-payment-gateway-paysera'),
                'options' => [],
            ],
            'paymentCompletedStatus' => [
                'title' => __('Paid Order Status', 'woo-payment-gateway-paysera'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'wc-completed',
                'description' => __('Order status after completing payment', 'woo-payment-gateway-paysera'),
                'options' => [],
            ],
            'paymentPendingStatus' => [
                'title' => __('Pending checkout', 'woo-payment-gateway-paysera'),
                'type' => 'select',
                'class'	=> 'wc-enhanced-select',
                'default' => 'wc-pending',
                'description' => __('Order status with not finished checkout', 'woo-payment-gateway-paysera'),
                'options' => [],
            ],
            'enableOwnershipCode' => [
                'title' => __('Enable ownership code', 'woo-payment-gateway-paysera'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Enable this to add ownership code', 'woo-payment-gateway-paysera'),
            ],
            'ownershipCode' => [
                'title' => __('Ownership code', 'woo-payment-gateway-paysera'),
                'type' => 'text',
                'description' => __('Write your ownership code', 'woo-payment-gateway-paysera'),
                'default' => __('', 'woo-payment-gateway-paysera'),
            ],
            'enableQualitySign' => [
                'title' => __('Enable quality sign', 'woo-payment-gateway-paysera'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Enable this to add quality sign', 'woo-payment-gateway-paysera'),
            ],
        ];
    }

    /**
     * @param array $tabs
     */
    public function buildAdminFormHtml($tabs)
    {
        $htmlData = $this->generateFormFields($tabs);

        $html = '<div class="plugin_config">';
        $html .= '<h2>' . $htmlData['links'] . '</h2>';
        $html .= '<div style="clear:both;"><hr/></div>';
        $html .= $htmlData['tabs'];
        $html .= '</div>';

        print_r($html);
    }

    /**
     * @return array
     */
    public function generateNewSettings()
    {
        return [
            'countries' => $this->getPayseraListCountries(),
            'statuses' => $this->getStatusList(),
        ];
    }

    /**
     * @return array
     */
    protected function getPayseraListCountries()
    {
        try {
            $paymentMethods = WebToPay::getPaymentMethodList(
                $this->getValidProject($this->getProjectID()),
                $this->getCurrency()
            )
                ->setDefaultLanguage($this->getLang())
                ->getCountries()
            ;
        } catch (WebToPayException $exception) {
            error_log('[Paysera] Got an exception: ' . $exception);

            return [];
        }

        $countryList = [];

        foreach ($paymentMethods as $country) {
            $countryList[$country->getCode()] = $country->getTitle();
        }

        return $countryList;
    }

    /**
     * @return array
     */
    protected function getStatusList()
    {
        $orderStatus = [];

        foreach (array_keys(wc_get_order_statuses()) as $value) {
            $orderStatus[$value] = wc_get_order_status_name($value);
        }

        return $orderStatus;
    }

    /**
     * @param int $projectID
     * @return int
     */
    protected function getValidProject($projectID)
    {
        if (filter_var($projectID, FILTER_VALIDATE_INT) !== false) {
            return $projectID;
        }

        return self::DEFAULT_PROJECT_ID;
    }

    /**
     * @param array $tabs
     * @return array
     */
    protected function generateFormFields($tabs)
    {
        $tabsLink = '';
        $tabsContent = '';

        foreach ($tabs as $key => $value) {
            $tabsLink .= '<a href="javascript:void(0)"';
            $tabsLink .= ' id="tab' . $key . '" class="nav-tab"';
            $tabsLink .= ' data-cont="content' . $key . '">';
            $tabsLink .=  $value['name'] . '</a>';

            $tabsContent .= '<div id="content' . $key . '" class="tabContent">';
            $tabsContent .= '<table class="form-table">' . $value['slice'] . '</table>';
            $tabsContent .= '</div>';
        }

        return [
            'links' => $tabsLink,
            'tabs' => $tabsContent,
        ];
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
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     * @return self
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

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
     * @return array
     */
    public function getFormFields()
    {
        return $this->formFields;
    }
}
