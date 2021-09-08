<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Carriers
 */
class MP_Carriers
{
    protected $carriers = [];

    /**
     * MP_Carriers constructor.
     */
    public function __construct()
    {
        $this->load();
    }

    /**
     * @param string $code
     *
     * @return mixed
     */
    public function get($code)
    {
        if (array_key_exists($code, $this->carriers)) {
            return $this->carriers[$code];
        }

        return [];
    }

    /**
     * @param string $code
     *
     * @return string
     */
    public function name($code)
    {
        if ( ! array_key_exists($code, $this->carriers)) {
            return $code;
        }

        return __($this->carriers[$code]['name'], 'multiparcels-shipping-for-woocommerce');
    }

    private function load()
    {
        $this->carriers = MultiParcels()->options->get('carriers', true);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->carriers;
    }

    public function all_enabled()
    {
        $carriers = [];

        foreach ($this->all() as $item) {
            if (MultiParcels()->options->getBool($item['carrier_code'])) {
                $carriers[$item['carrier_code']] = $item['carrier_code'];
            }
        }

        return $carriers;
    }

    public function update()
    {
        $response = MultiParcels()->api_client->request('restricted_api/couriers', 'GET');

        if ($response->was_successful()) {
            $data     = $response->get_data();
            $carriers = $data['carriers'];

            MultiParcels()->options->set('carriers', $carriers, true);
            $this->carriers = $carriers;
        }
    }

    public function extract_from_method($method, $force_pickup_location = false)
    {
        if ($method instanceof WC_Order) {
            $shipping_methods = $method->get_shipping_methods();
            $shipping_methods = reset($shipping_methods);

            $method = $shipping_methods['method_id'];
        }

        if (substr($method, 0, strlen('multiparcels_')) != 'multiparcels_') {
            if ($default = MultiParcels()->options->get('default_carrier')) {
                return $default;
            }

            return null;
        }

        if ($force_pickup_location &&
            (strpos($method, WC_MP_Shipping_Method::SUFFIX_PICKUP_POINT) === false && strpos($method, WC_MP_Shipping_Method::SUFFIX_TERMINAL) === false)) {
            return null;
        }

        $courier = str_replace([
            'multiparcels_',
            '_' . WC_MP_Shipping_Method::SUFFIX_COURIER,
            '_' . WC_MP_Shipping_Method::SUFFIX_PICKUP_POINT,
            '_' . WC_MP_Shipping_Method::SUFFIX_POST,
            '_' . WC_MP_Shipping_Method::SUFFIX_TERMINAL,
            '_' . WC_MP_Shipping_Method::SUFFIX_BUS_STATION,
        ], '',
            $method);
        $courier = explode(':', $courier)[0]; // remove instance id

        return $courier;
    }

    public function is_not_multiparcels_shipping_method($method)
    {
        if ($method instanceof WC_Order) {
            $shipping_methods = $method->get_shipping_methods();
            $shipping_methods = reset($shipping_methods);

            $method = $shipping_methods['method_id'];
        }

        if (substr($method, 0, strlen('multiparcels_')) != 'multiparcels_') {
            return true;
        }

        return false;
    }

    /**
     * @param  WC_Order  $order
     *
     * @return mixed
     */
    public function method_name($order)
    {
        $shipping_methods = $order->get_shipping_methods();
        $shipping_methods = reset($shipping_methods);

        if (isset($shipping_methods['name'])) {
            return $shipping_methods['name'];
        }

        return '';
    }

    public function strict_extract_from_method($method)
    {
        if ($method instanceof WC_Order) {
            $shipping_methods = $method->get_shipping_methods();
            $shipping_methods = reset($shipping_methods);

            $method = $shipping_methods['method_id'];
        }

        if (strpos($method, 'multiparcels_') === false) {
            return null;
        }

        $courier = str_replace([
            'multiparcels_',
            '_' . WC_MP_Shipping_Method::SUFFIX_COURIER,
            '_' . WC_MP_Shipping_Method::SUFFIX_PICKUP_POINT,
            '_' . WC_MP_Shipping_Method::SUFFIX_POST,
            '_' . WC_MP_Shipping_Method::SUFFIX_TERMINAL,
            '_' . WC_MP_Shipping_Method::SUFFIX_BUS_STATION,
        ], '',
            $method);
        $courier = explode(':', $courier)[0]; // remove instance id

        return $courier;
    }

    public function delivery_method_name($code)
    {
        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_ECONOMY) {
            return 'Economy';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_ECONOMY_12H) {
            return 'Economy 12H';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_EXPRESS) {
            return 'Express';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_EXPRESS_09H) {
            return 'Express 09H';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_EXPRESS_10H) {
            return 'Express 10H';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_EXPRESS_12H) {
            return 'Express 12H';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_EXPRESS_SAVER) {
            return 'Express Saver';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_EXPRESS_PLUS) {
            return 'Express Plus';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_EXPEDITED) {
            return 'Expedited';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_POST_DE_PACKET_PRIORITY) {
            return 'Packet Priority';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_POST_DE_PACKET_PLUS) {
            return 'Packet Plus';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_POST_DE_PACKET_TRACKED) {
            return 'Packet Tracked';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_UPS_SUREPOST_LESS_THAN_1LB) {
            return 'SurePost less than 1LB';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_UPS_SUREPOST_1LB_OR_GREATER) {
            return 'SurePost 1LB or greater';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_UPS_SUREPOST_BOUND_PRINTED_MATTER) {
            return 'SurePost bound printed matter';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_UPS_SUREPOST_MEDIA_MAIL) {
            return 'SurePost media mail';
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_SAME_DAY) {
            return __( 'Same day delivery', 'multiparcels-shipping-for-woocommerce' );
        }

        if ($code == WC_MP_Shipping_Method::DELIVERY_METHOD_SIUNTOS_AUTOBUSAIS_TO_TERMINAL) {
            return __( 'To bus terminal', 'multiparcels-shipping-for-woocommerce' );
        }

        return $code;
    }
}

return new MP_Carriers();
