<?php
/**
 * Plugin Name: MultiParcels Shipping For WooCommerce
 * Description: Easiest, fastest and the cheapest way to integrate couriers with all deliveries methods to send parcels with just a few button clicks.
 * Version: 1.14.4
 * Author: MultiParcels
 * Author URI: https://multiparcels.com
 * WC tested up to: 5.5.2
 * WC requires at least: 3.0.0
 *
 * Text Domain: multiparcels-shipping-for-woocommerce
 * Domain Path: /languages/
 */

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MultiParcels
 *
 * @todo wp dashboard widget with un shipped order
 * @todo woocommerce menu - below orders - add un dispatched orders with count
 * @todo send uninstall message to multiparcels
 * @todo radio select for customer if there are 3 or less pickup locations in their city
 * @todo add tracking
 * @todo after shipment delivery - send email with questions about the order and to rate it, leave comments
 * @todo if woocommerce_shipping_debug_mode is enabled - display information about our shipping methods(why it is not showing up for customers etc.)
 * @todo add quickstart guide
 * @todo add minimum cart price and maximum cart price for shipping methods(like minimum weight/maximum weight)
 * @todo all products/categories that do not fit in pickup locations in admin
 */
class MultiParcels
{
    /**
     * The single instance of the class.
     *
     * @var MultiParcels
     * @since 0.1
     */
    protected static $_instance;

    /** @var string */
    public $version = '1.14.4';

    /** @var MP_Options */
    public $options;

    /** @var MP_Api_Client */
    public $api_client;

    /** @var MP_Locations */
    public $locations;

    /** @var MP_Carriers */
    public $carriers;

    /** @var MP_Permissions */
    public $permissions;

    /** @var MultiParcels_Services */
    public $services;

    /** @var MultiParcels_Delivery_Shippings */
    public $shippings;

    /** @var MultiParcels_Carrier_Selections */
    public $carrier_selections;

    /** @var MultiParcels_Helper */
    public $helper;

    /** @var string */
    public $contact_email = 'hello@multiparcels.com';

    /** @var string */
    public $plugin_title = 'MultiParcels Shipping For WooCommerce';

    /** @var array */
    public $shipping_methods = [];

    /** @var MP_Logger */
    public $logger;

    /** @var MP_Woocommerce */
    public $woocommerce;

    /**
	 * @since 0.1
	 *
	 * @return MultiParcels
	 */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

	/**
	 * MultiParcels constructor.
	 */
    function __construct()
    {
        $this->load_plugin_textdomain();

        if (get_locale() == 'lt_LT' || get_locale() == 'lt') {
            $this->contact_email = 'labas@multisiuntos.lt';
        }

        include_once 'includes/class-mp-install.php';

        register_activation_hook(__FILE__, ['MP_Install', 'install']);
        register_deactivation_hook(__FILE__, ['MP_Install', 'remove']);

        $this->cron_schedule();

        add_action('plugins_loaded', function () {
            if ($this->version_checks()) {
                $this->plugins_loaded();
            }
        });
    }

    private function version_checks()
    {
        if ( ! version_compare(PHP_VERSION, '5.6.0', '>=')) {
            add_action('admin_notices', [$this, 'php_version_check_notice']);

            return false;
        }

        if ( ! defined('WC_VERSION') || ! version_compare(WC_VERSION, '3.0.0', '>=')) {
            add_action('admin_notices', [$this, 'wc_version_check_notice']);

            return false;
        }

        return true;
    }

    public function php_version_check_notice()
    {
        ?>
        <div class="error notice">
            <p>
                <strong><?php _e(MultiParcels()->plugin_title, 'multiparcels-shipping-for-woocommerce') ?></strong>
            </p>
            <p>
                <?php _e('This plugin requires at least PHP version 5.6.0. Please contact your server administrator to upgrade your PHP version.',
                    'multiparcels-shipping-for-woocommerce'); ?>
            </p>
        </div>
        <?php
    }

    public function wc_version_check_notice()
    {
        ?>
        <div class="error notice">
            <p>
                <strong><?php _e(MultiParcels()->plugin_title, 'multiparcels-shipping-for-woocommerce') ?></strong>
            </p>
            <p>
                <?php _e('This plugin requires at least WooCommerce version 3.0.0.',
                    'multiparcels-shipping-for-woocommerce'); ?>
                <?php
                if (defined('WC_VERSION')) {
                    _e('Please upgrade your WooCommerce.', 'multiparcels-shipping-for-woocommerce');
                    ?>

                    <br>
                    <?php echo sprintf(__('Your WooCommerce version is %s.',
                        'multiparcels-shipping-for-woocommerce'), sprintf("<strong>%s</strong>", WC_VERSION)); ?>
                    <?php
                }
                ?>
            </p>
        </div>
        <?php
    }

    public function plugins_loaded()
    {
        $this->includes();
        add_action('admin_init', ['PAnD', 'init']);

        $this->init_hooks();

        $plugin_version           = $this->version;
        $installed_plugin_version = $this->options->get('version', true);

        if ($plugin_version != $installed_plugin_version) {
            call_user_func(['MP_Install', 'update']);
        }
    }

    private function init_hooks()
    {
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [__CLASS__, 'plugin_action_links']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts()
    {
        if (function_exists('is_checkout') && is_checkout()) {
            wp_enqueue_style('wp-mp-shipping-css', plugins_url('/public/css/style.css', __FILE__),
                false, MultiParcels()->version);
            wp_enqueue_script('wp-mp-shipping-js', plugins_url('/public/js/pickup-points.js', __FILE__),
                ['jquery'], MultiParcels()->version, true);

            $venipak_settings               = MultiParcels()->carriers->get('venipak');
            $preferred_delivery_time_cities = [];

            if (array_key_exists('preferred_delivery_time_cities', $venipak_settings)) {
                foreach ($venipak_settings['preferred_delivery_time_cities']['LT'] as $city) {
                    $preferred_delivery_time_cities[] = MP_Locations::latin($city);
                }
            }

            $autoCompleteText = null;
            if (MultiParcels()->permissions->addressAutoCompleteEnabled()) {
                wp_enqueue_script('wp-mp-autocomplete-js', plugins_url('/public/js/autocomplete.js', __FILE__),
                    ['jquery'], MultiParcels()->version, true);
                wp_enqueue_style('wp-mp-autocomplete-css', plugins_url('/public/css/autocomplete.css', __FILE__),
                    false, MultiParcels()->version);

                $address_autocomplete_settings = MultiParcels()->options->get('address_autocomplete', true);

                if (is_array($address_autocomplete_settings) && array_key_exists('display_notice',
                        $address_autocomplete_settings) && $address_autocomplete_settings['display_notice'] == 1) {
                    $autoCompleteText = __('Address suggestions are enabled. Start typing the street name, city or postal code.',
                        'multiparcels-shipping-for-woocommerce');
                }
            }

            wp_localize_script('wp-mp-shipping-js', 'multiparcels', [
                'ajax_url'                       => admin_url('admin-ajax.php'),
                'text'                           => [
                    'pickup_location_not_found' => __('Pickup location not found',
                        'multiparcels-shipping-for-woocommerce'),
                    'working_hours'             => __('Working hours', 'multiparcels-shipping-for-woocommerce'),
                    'address_autocomplete_on'   => $autoCompleteText,
                    'searching'                 => __('Searching...', 'multiparcels-shipping-for-woocommerce'),
                    'please_select_pickup_point_location' => __('Please select the pickup location',
                        'multiparcels-shipping-for-woocommerce')
                ],
                'preferred_delivery_time_cities' => $preferred_delivery_time_cities,
                'display_selected_pickup_location_information' => MultiParcels()->options->get( 'display_selected_pickup_location_information',
	                false, 'yes' ),
                'display_pickup_location_title' => MultiParcels()->options->get( 'display_pickup_location_title',
	                false, 'yes' ),
                'hide_not_required_terminal_fields' => MultiParcels()->options->get_other_setting('checkout',
                    'enabled') ? 'yes' : 'no',
                'hide_not_required_local_pickup_fields' => MultiParcels()->options->get_other_setting('checkout',
                    'hide_for_local_pickup') ? 'yes' : 'no',
            ]);
        }
    }

    private function includes()
    {
        include_once 'includes/interfaces/wc-mp-shipping-method-interface.php';
        include_once 'includes/class-mp-api-client-response.php';

        $this->options = include_once 'includes/class-mp-options.php';
        $this->logger  = include_once 'includes/class-mp-logger.php';
        $this->carrier_selections  = include_once 'includes/class-mp-carrier-selections.php';

        $this->api_client  = include_once 'includes/class-mp-api-client.php';
        $this->locations   = include_once 'includes/class-mp-locations.php';
        $this->carriers    = include_once 'includes/class-mp-carriers.php';
        $this->permissions = include_once 'includes/class-mp-permissions.php';
        $this->services    = include_once 'includes/class-multiparcels-service.php';
        $this->helper      = include_once 'includes/class-multiparcels-helper.php';
        include_once 'includes/class-mp-admin.php';
        include_once 'includes/class-mp-mass-shipping.php';
	    $this->shippings = include_once 'includes/class-mp-shippings.php';
        include_once 'includes/class-mp-amazing-shipping.php';
        include_once 'includes/abstracts/abstract-wc-mp-shipping-method.php';
        include_once 'includes/abstracts/abstract-wc-mp-courier-shipping-method.php';
        include_once 'includes/abstracts/abstract-wc-mp-pickup-point-shipping-method.php';
        include_once 'includes/abstracts/abstract-wc-mp-post-shipping-method.php';
        include_once 'includes/abstracts/abstract-wc-mp-bus-station-shipping-method.php';
        include_once 'includes/abstracts/abstract-wc-mp-terminal-shipping-method.php';

        // Dpd
        include_once 'includes/shipping/class-wc-mp-dpd-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-dpd-pickup-point-shipping.php';
        include_once 'includes/shipping/class-wc-mp-dpd-terminal-shipping.php';

        // Lp Express
        include_once 'includes/shipping/class-wc-mp-lp-express-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-lp-express-pickup-point-shipping.php';
        include_once 'includes/shipping/class-wc-mp-lp-express-post-shipping.php';

        // Omniva
        include_once 'includes/shipping/class-wc-mp-omniva-lt-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-omniva-lt-pickup-point-shipping.php';

        include_once 'includes/shipping/class-wc-mp-omniva-lv-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-omniva-lv-pickup-point-shipping.php';

        include_once 'includes/shipping/class-wc-mp-omniva-ee-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-omniva-ee-pickup-point-shipping.php';

        // Venipak
        include_once 'includes/shipping/class-wc-mp-venipak-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-venipak-pickup-point-shipping.php';

        // Venipak 3PL
        include_once 'includes/shipping/class-wc-mp-venipak-3pl-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-venipak-3pl-pickup-point-shipping.php';

        // Post LT
        include_once 'includes/shipping/class-wc-mp-post-lt-post-shipping.php';

        // Post LV
        include_once 'includes/shipping/class-wc-mp-post-lv-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-post-lv-pickup-point-shipping.php';
        include_once 'includes/shipping/class-wc-mp-post-lv-post-shipping.php';

        // SmartPOST
        include_once 'includes/shipping/class-wc-mp-smartpost-pickup-point-shipping.php';

        // TNT
        include_once 'includes/shipping/class-wc-mp-tnt-courier-shipping.php';

        // DHL
        include_once 'includes/shipping/class-wc-mp-dhl-courier-shipping.php';

        // UPS
        include_once 'includes/shipping/class-wc-mp-ups-courier-shipping.php';

        // InPost
        include_once 'includes/shipping/class-wc-mp-inpost-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-inpost-pickup-point-shipping.php';

        // Posti
        include_once 'includes/shipping/class-wc-mp-posti-pickup-point-shipping.php';
        include_once 'includes/shipping/class-wc-mp-posti-post-shipping.php';

        // Itella
        include_once 'includes/shipping/class-wc-mp-itella-pickup-point-shipping.php';
        include_once 'includes/shipping/class-wc-mp-itella-courier-shipping.php';

        // ParcelStars
        include_once 'includes/shipping/class-wc-mp-parcelstars-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-parcelstars-pickup-point-shipping.php';

        // ZITICITY
        include_once 'includes/shipping/class-wc-mp-ziticity-courier-shipping.php';

        // FedEx
        include_once 'includes/shipping/class-wc-mp-fedex-courier-shipping.php';

        // GLS
        include_once 'includes/shipping/class-wc-mp-gls-courier-shipping.php';

        // Hermes
        include_once 'includes/shipping/class-wc-mp-hermes-courier-shipping.php';

        // Hermes UK
        include_once 'includes/shipping/class-wc-mp-hermes-uk-courier-shipping.php';

        // Post DE
        include_once 'includes/shipping/class-wc-mp-post-de-post-shipping.php';

        // Siuntos autobusias
        include_once 'includes/shipping/class-wc-mp-siuntos-autobusais-courier-shipping.php';
        include_once 'includes/shipping/class-wc-mp-siuntos-autobusais-bus-station-shipping.php';

        include_once 'includes/shipping/class-wc-mp-shipping-helper.php';

        include_once 'includes/class-mp-actions.php';
        $this->woocommerce = include_once 'includes/class-mp-woocommerce.php';
        include_once 'includes/class-mp-notices.php';
        include_once 'includes/class-mp-woocommerce-order-shipping.php';
        include_once 'includes/persist-admin-notices-dismissal.php';
    }

    /**
     * Show action links on the plugin screen.
     *
     * @param    mixed $links Plugin Action links
     *
     * @return    array
     */
    public static function plugin_action_links($links)
    {
        $action_links = [
            sprintf('<a href="%s" title="%s">%s</a>',
                admin_url('admin.php?page=multiparcels-shipping-for-woocommerce'),
                esc_attr(__('View MultiParcels Settings', 'multiparcels-shipping-for-woocommerce')),
                __('Settings', 'multiparcels-shipping-for-woocommerce')
            ),
        ];

        return array_merge($action_links, $links);
    }

    public function load_plugin_textdomain()
    {
        load_textdomain('multiparcels-shipping-for-woocommerce',
            WP_LANG_DIR . '/multiparcels-shipping-for-woocommerce/multiparcels-shipping-for-woocommerce-' . get_locale() . '.mo');
        load_plugin_textdomain('multiparcels-shipping-for-woocommerce', false,
            plugin_basename(dirname(__FILE__)) . '/languages');
    }

    /**
     * @return string
     */
    public function plugin_path()
    {
        return untrailingslashit(plugin_dir_path(__FILE__));
    }

    /**
     * @param array $query
     * @param bool $escape
     *
     * @return string
     */
    function settings_url($query = [], $escape = true)
    {
        $url = admin_url('admin.php?page=multiparcels-shipping-for-woocommerce') . '&' . http_build_query($query);

        if ($escape) {
            return esc_url($url);
        }

        return $url;
    }

    /**
     * Get the public plugin url.
     *
     * @param string|null $sub_path
     *
     * @return string
     */
    public function public_plugin_url($sub_path = null)
    {
        $path = untrailingslashit(plugins_url('/', __FILE__)) . '/public';

        if ($sub_path) {
            $path .= '/' . $sub_path;
        }

        return esc_url($path);
    }

    private function cron_schedule()
    {
        add_filter('cron_schedules', 'multiparcels_intervals');

        function multiparcels_intervals($intervals)
        {
            $intervals['multiparcels_every_10min'] = ['interval' => 10 * 60, 'display' => 'MultiParcels: Every 10 minutes'];
            $intervals['multiparcels_every_30min'] = ['interval' => 30 * 60, 'display' => 'MultiParcels: Every 30 minutes'];
            $intervals['multiparcels_every_60min'] = ['interval' => 60 * 60, 'display' => 'MultiParcels: Every 60 minutes'];
            $intervals['multiparcels_every_24h'] = ['interval' => 60 * 60 * 24, 'display' => 'MultiParcels: Every 24 hours'];

            return $intervals;
        }
    }
}

/**
 * @return MultiParcels
 */
function MultiParcels()
{
    return MultiParcels::instance();
}

MultiParcels();
