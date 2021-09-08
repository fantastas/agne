<?php

defined('ABSPATH') or exit;

class WC_Paysera_Gateway extends WC_Payment_Gateway
{
    const PAYSERA_LOGO = 'assets/images/paysera.png';
    const PAYSERA_BACKEND_ACTION_JS = 'assets/js/backend/action.js';
    const PAYSERA_FRONTEND_ACTION_JS = 'assets/js/frontend/action.js';
    const PAYSERA_STYLESHEET = 'assets/css/paysera.css';

    /**
     * @var int
     */
    protected $projectID;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var bool
     */
    protected $paymentType;

    /**
     * @var bool
     */
    protected $gridView;

    /**
     * @var string|array
     */
    protected $countriesSelected;

    /**
     * @var bool
     */
    protected $test;

    /**
     * @var string
     */
    protected $paymentNewOrderStatus;

    /**
     * @var string
     */
    protected $paymentCompletedStatus;

    /**
     * @var string
     */
    protected $paymentPendingStatus;

    /**
     * @var Wc_Paysera_Settings
     */
    protected $pluginSettings;

    /**
     * @var bool
     */
    protected $buyerConsent;

    /**
     * @var bool
     */
    protected $enableOwnershipCode;

    /**
     * @var string
     */
    protected $ownershipCode;

    /**
     * @var bool
     */
    protected $enableQualitySign;

    public function __construct()
    {
        $this->id = 'paysera';
        $this->has_fields = true;
        $this->method_title = __('All popular payment methods', 'woo-payment-gateway-paysera');
        $this->method_description = __('Choose a payment method on the Paysera page', 'woo-payment-gateway-paysera');
        $this->icon = apply_filters('woocommerce_paysera_icon', WCGatewayPayseraPluginUrl . self::PAYSERA_LOGO);

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->projectID = $this->get_option('projectid');
        $this->password = $this->get_option('password');
        $this->paymentType = $this->get_option('paymentType') === 'yes';
        $this->gridView = $this->get_option('style') === 'yes';
        $this->countriesSelected = $this->get_option('countriesSelected');
        $this->test = $this->get_option('test') === 'yes';
        $this->paymentNewOrderStatus = $this->get_option('paymentNewOrderStatus');
        $this->paymentCompletedStatus = $this->get_option('paymentCompletedStatus');
        $this->paymentPendingStatus = $this->get_option('paymentPendingStatus');
        $this->buyerConsent = $this->get_option('buyerConsent') === 'yes';
        $this->enableOwnershipCode = $this->get_option('enableOwnershipCode');
        $this->ownershipCode = $this->get_option('ownershipCode');
        $this->enableQualitySign = $this->get_option('enableQualitySign');

        add_action('woocommerce_thankyou_paysera', [$this, 'thankyou']);
        add_action('woocommerce_api_wc_gateway_paysera', [$this, 'check_callback_request']);
        add_action('woocommerce_update_options_payment_gateways_paysera', [$this, 'process_admin_options']);
    }

    public function init_form_fields()
    {
        if (!class_exists('Wc_Paysera_Settings')) {
            require_once 'class-wc-paysera-settings.php';
        }

        $this->setPluginSettings(Wc_Paysera_Settings::create());

        $this->form_fields = $this->getPluginSettings()->getFormFields();
    }

    public function admin_options()
    {
        $this->getPluginSettings()->setLang($this->getLocalLang());
        $this->getPluginSettings()->setCurrency(get_woocommerce_currency());
        $this->getPluginSettings()->setProjectID($this->projectID);
        $this->updateAdminSettings($this->getPluginSettings()->generateNewSettings());
        $allFields = $this->get_form_fields();
        $tabs = $this->generateTabs(
            [
                [
                    'name' => __('Main Settings', 'woo-payment-gateway-paysera'),
                    'slice' => array_slice($allFields, 0, 4),
                ],
                [
                    'name' => __('Extra Settings', 'woo-payment-gateway-paysera'),
                    'slice' => array_slice($allFields, 4, 6),
                ],
                [
                    'name' => __('Order Status', 'woo-payment-gateway-paysera'),
                    'slice' => array_slice($allFields, 10, 3),
                ],
                [
                    'name' => __('Project additions', 'woo-payment-gateway-paysera'),
                    'slice' => array_slice($allFields, -3, count($allFields)),
                ],
            ]
        );

        $this->getPluginSettings()->buildAdminFormHtml($tabs);

        wp_enqueue_script(
            'custom-backend-script',
            WCGatewayPayseraPluginUrl . self::PAYSERA_BACKEND_ACTION_JS,
            ['jquery']
        );
    }

    public function validate_password_field($key, $value)
    {
        if (strlen($value) === 0) {
            WC_Admin_Settings::add_error(esc_html__(
                'Password (sign) must be Not Empty',
                'woo-payment-gateway-paysera'
            ));
        }

        return $value;
    }

    public function payment_fields()
    {
        if (!class_exists('Wc_Paysera_Payment_Methods')) {
            require_once 'class-wc-paysera-payment-methods.php';
        }

         $paymentFields = (Wc_Paysera_Payment_Methods::create())
            ->setProjectID($this->projectID)
            ->setLang($this->getLocalLang())
            ->setBillingCountry(strtolower(WC()->customer->get_billing_country()))
            ->setDisplayList($this->paymentType)
            ->setCountriesSelected($this->countriesSelected)
            ->setGridView($this->gridView)
            ->setDescription($this->description)
            ->setCartTotal(round(WC()->cart->total * 100))
            ->setCartCurrency(get_woocommerce_currency())
            ->setAvailableLang(['lt', 'lv', 'ru', 'en', 'pl', 'bg', 'et'])
            ->setBuyerConsent($this->buyerConsent)
            ->build()
        ;

        print_r($paymentFields);

        wp_enqueue_style('custom-frontend-style', WCGatewayPayseraPluginUrl . self::PAYSERA_STYLESHEET);

        wp_enqueue_script(
            'custom-frontend-script',
            WCGatewayPayseraPluginUrl . self::PAYSERA_FRONTEND_ACTION_JS,
            ['jquery']
        );
    }

    public function process_payment($order_id)
    {
        if (!class_exists('Wc_Paysera_Request')) {
            require_once 'class-wc-paysera-request.php';
        }

        $order = wc_get_order($order_id);
        $order->add_order_note(__('Paysera: Order checkout process is started', 'woo-payment-gateway-paysera'));
        $this->updateOrderStatus($order, $this->paymentPendingStatus);

        $payseraRequest = Wc_Paysera_Request::create()
            ->setProjectID($this->projectID)
            ->setSignature($this->password)
            ->setReturnUrl($this->get_return_url($order))
            ->setCallbackUrl(trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_paysera')
            ->setTest($this->test)
            ->setLocale($this->getLocalLang())
            ->setTranslationLang([
                'lt' => 'LIT',
                'lv' => 'LAV',
                'et' => 'EST',
                'ru' => 'RUS',
                'de' => 'GER',
                'pl' => 'POL',
                'en' => 'ENG',
            ])
            ->setBuyerConsent($this->buyerConsent)
        ;

        if ($this->paymentType === true) {
            $selectedPayment = esc_html($_REQUEST['payment']['pay_type']);
        } else {
            $selectedPayment = '';
        }

        wc_maybe_reduce_stock_levels($order_id);

        return [
            'result' => 'success',
            'redirect' => $payseraRequest->buildUrl($payseraRequest->getWooParameters($order, $selectedPayment)),
        ];
    }

    public function thankyou($order_id)
    {
        $order = wc_get_order($order_id);
        $currentStatus = 'wc-' . $order->get_status();

        $validToChange =
            $currentStatus === $this->paymentPendingStatus
            && $currentStatus !== $this->paymentNewOrderStatus
        ;

        if ($validToChange === true) {
            $order->add_order_note(__('Paysera: Customer came back to page', 'woo-payment-gateway-paysera'));
            $this->updateOrderStatus($order, $this->paymentNewOrderStatus);
        }
    }

    public function check_callback_request()
    {
        try {
            $response = WebToPay::validateAndParseData($_REQUEST, $this->projectID, $this->password);

            if ($response['status'] == 1) {
                $order = wc_get_order($response['orderid']);

                if ($this->checkPayment($order, $response) === true) {
                    $this->getOrderLogMsg($order, 'Payment confirmed with a callback', true);

                    $order->add_order_note(
                        __('Paysera: Callback order payment completed', 'woo-payment-gateway-paysera')
                    );
                    $this->updateOrderStatus($order, $this->paymentCompletedStatus);

                    print_r('OK');
                }
            }
        } catch (Exception $e) {
            $errorMsg = get_class($e) . ': ' . $e->getMessage();
            error_log($errorMsg);
            print_r($errorMsg);
        }

        exit();
    }

    public function checkPayment($order, $response)
    {
        if ((string) ($order->get_total() * 100) !== $response['amount']) {
            throw new Exception($this->getOrderLogMsg($order, 'Amounts do not match'));
        }

        if ($order->get_currency() !== $response['currency']) {
            throw new Exception($this->getOrderLogMsg($order, 'Currencies do not match'));
        }

        return true;
    }

    protected function getOrderLogMsg($order, $errorMsg, $sendLog = false)
    {
        $fullLog = $errorMsg . ':' . ' Order #' . $order->get_id() . ';' . ' Amount: ' . $order->get_total()
            . $order->get_currency()
        ;

        if ($sendLog === true) {
            error_log($fullLog);
        }

        return $fullLog;
    }

    protected function getLocalLang()
    {
        return explode('_', get_locale())[0];
    }

    protected function generateTabs($tabs)
    {
        $data = [];

        foreach ($tabs as $key => $value) {
            $data[$key]['name'] = $value['name'];
            $data[$key]['slice'] = $this->generate_settings_html($value['slice'], false);
        }

        return $data;
    }

    protected function updateAdminSettings($data)
    {
        $this->form_fields['countriesSelected']['options'] = $data['countries'];
        $this->form_fields['paymentNewOrderStatus']['options'] = $data['statuses'];
        $this->form_fields['paymentCompletedStatus']['options'] = $data['statuses'];
        $this->form_fields['paymentPendingStatus']['options'] = $data['statuses'];
    }

    protected function updateOrderStatus($order, $status)
    {
        $orderStatusFiltered = str_replace('wc-', '', $status);
        $order->update_status(
            $orderStatusFiltered,
            __('Paysera: Status changed to ', 'woo-payment-gateway-paysera') . $orderStatusFiltered,
            true
        );
    }

    /**
     * @return Wc_Paysera_Settings
     */
    public function getPluginSettings()
    {
        return $this->pluginSettings;
    }

    /**
     * @param Wc_Paysera_Settings $pluginSettings
     */
    public function setPluginSettings($pluginSettings)
    {
        $this->pluginSettings = $pluginSettings;
    }
}
