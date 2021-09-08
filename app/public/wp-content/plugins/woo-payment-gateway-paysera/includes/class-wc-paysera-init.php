<?php

defined('ABSPATH') or exit;

class Wc_Paysera_Init
{
    protected $errors;

    const PAYSERA_DOC_LINK = 'https://developers.paysera.com/en/checkout/basic';
    const ADMIN_SETTINGS_LINK = 'admin.php?page=wc-settings&tab=checkout&section=paysera';
    const QUALITY_SIGN_JS = 'assets/js/frontend/sign.js';

    public function __construct()
    {
        $this->errors = [];
    }

    public function hooks()
    {
        add_action('plugins_loaded', [$this, 'loadPayseraGateway']);
        add_action('admin_notices', [$this, 'displayAdminNotices']);
        add_filter('woocommerce_payment_gateways', [$this, 'addPayseraGatewayMethod']);
        add_filter(
            'plugin_action_links_' . WCGatewayPayseraPluginPath . '/paysera.php',
            [$this, 'addPayseraGatewayActionLinks']
        );
        add_action('wp_head', [$this, 'addMetaTags']);
        add_action('wp_head', [$this, 'addQualitySign']);
    }

    public function loadPayseraGateway()
    {
        load_plugin_textdomain(
            'woo-payment-gateway-paysera',
            false,
            WCGatewayPayseraPluginPath . '/languages/'
        );

        if (class_exists('woocommerce') === false) {
            $this->addError('WooCommerce is not active');
            return false;
        }

        require_once "class-wc-paysera-gateway.php";

        return true;
    }

    public function getInstallErrors()
    {
        return [
            'prefix' => __('WooCommerce Payment Gateway - Paysera', 'woo-payment-gateway-paysera'),
            'messages' => implode(PHP_EOL, $this->getErrors()),
        ];
    }

    public function displayAdminNotices()
    {
        $notices = $this->getInstallErrors();

        if (!empty($notices['messages'])) {
            echo wp_kses(
                '<div class="error"><p><b>' . $notices['prefix'] . ': </b><br>' . $notices['messages'] . '</p></div>',
                ['div' => ['class' => []], 'p' => [], 'b' => [], 'br' => []]
            );
        }
    }

    public function addPayseraGatewayMethod($methods)
    {
        $methods[] = 'WC_Paysera_Gateway';

        return $methods;
    }

    public function addPayseraGatewayActionLinks($links)
    {
        wp_enqueue_style('custom-frontend-style', WCGatewayPayseraPluginUrl . 'assets/css/paysera.css');

        if (class_exists('woocommerce') === true) {
            $adminSettingsTranslations = __('Main Settings', 'woo-payment-gateway-paysera');

            $htmlSettingsLink = '<a href="' . admin_url(self::ADMIN_SETTINGS_LINK) . '">' . $adminSettingsTranslations
                . '</a>'
            ;
        } else {
            $htmlSettingsLink = '<a class="paysera-error-link" ">WooCommerce is not active</a>';
        }

        array_unshift($links, $htmlSettingsLink, '<a href="' . self::PAYSERA_DOC_LINK . '" target="_blank">Docs</a>');

        return $links;
    }

    public function addMetaTags()
    {
        $settings = get_option('woocommerce_paysera_settings');

        if (
            (isset($settings['enableOwnershipCode']) && isset($settings['ownershipCode']))
            && ($settings['enableOwnershipCode'] === 'yes')
        ) {
            echo wp_kses(
                '<meta name="verify-paysera" content="' . $settings['ownershipCode'] . '">',
                ['meta' => ['name' => [], 'content' => []]]
            );
        }
    }

    public function addQualitySign()
    {
        $settings = get_option('woocommerce_paysera_settings');

        if (isset($settings['enableQualitySign']) && ($settings['enableQualitySign'] === 'yes')) {
            $this->addQualitySignScript($settings['projectid']);
        }
    }

    /**
     * @param array $errors
     * @return self
     */
    public function setErrors($errors)
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * @param string $errorText
     * @param string $pluginPath
     * @return self
     */
    public function addError($errorText, $pluginPath = 'woo-payment-gateway-paysera')
    {
        $this->errors[] = __($errorText, $pluginPath);

        return $this;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return array
     */
    private function getLang()
    {
        return explode('_', get_locale())[0];
    }

    /**
     * @param int $projectId
     */
    private function addQualitySignScript($projectId)
    {
        wp_enqueue_script('quality-sign-js', WCGatewayPayseraPluginUrl . self::QUALITY_SIGN_JS, ['jquery']);

        wp_localize_script(
            'quality-sign-js',
            'data',
            [
                'project_id' => $projectId,
                'language'=> $this->getLang(),
            ]
        );
    }
}
