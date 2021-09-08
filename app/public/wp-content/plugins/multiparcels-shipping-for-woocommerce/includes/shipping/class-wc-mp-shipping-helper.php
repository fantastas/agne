<?php
if ( ! defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_MP_Shipping_Method')) {
    /**
     * Class WC_MP_Venipak_Courier_Shipping
     */
    class WC_MP_Shipping_Helper
    {
        const CARRIER_DPD = 'dpd';
        const CARRIER_LP_EXPRESS = 'lp_express';
        const CARRIER_VENIPAK = 'venipak';
        const CARRIER_VENIPAK_3PL = 'venipak_3pl';
        const CARRIER_POST_LT = 'post_lt';
        const CARRIER_POST_LV = 'post_lv';
        const CARRIER_OMNIVA_LT = 'omniva_lt';
        const CARRIER_SMARTPOST = 'smartpost';
        const CARRIER_ZITICITY = 'ziticity';
        const CARRIER_SIUNTOS_AUTOBUSAIS = 'siuntos_autobusais';

        public function __construct()
        {
            // Display the selected location on the emails near custom details
            add_action('woocommerce_email_customer_details', [$this, 'email'], 15, 1); // email

            // Display the selected location on admin order page
            if (is_admin()) {
                add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'admin_order_page']);
                add_action('woocommerce_admin_shipping_fields', [$this, 'woocommerce_admin_shipping_fields']);
            }

            // Display the selected location after checkout
            add_action('woocommerce_order_details_after_order_table', [$this, 'after_checkout'], 10);

            add_action('woocommerce_after_checkout_validation', [$this, 'check_for_selected_location'], 10, 1);
            add_action('woocommerce_checkout_update_order_meta', [$this, 'checkout_save_order_terminal_id_meta'],
                10, 2);

            add_action('wp_ajax_nopriv_multiparcels_checkout_get_pickup_points', [$this, 'checkout_get_pickup_points']);
            add_action('wp_ajax_multiparcels_checkout_get_pickup_points', [$this, 'checkout_get_pickup_points']);

            add_action('wp_ajax_nopriv_multiparcels_checkout_address_autocomplete', [$this, 'address_autocomplete']);
            add_action('wp_ajax_multiparcels_checkout_address_autocomplete', [$this, 'address_autocomplete']);

            add_action('wp_ajax_nopriv_multiparcels_is_preferred_delivery_time_available',
                [$this, 'is_preferred_delivery_time_available']);
            add_action('wp_ajax_multiparcels_is_preferred_delivery_time_available',
                [$this, 'is_preferred_delivery_time_available']);

            add_action('woocommerce_email_before_order_table', [$this, 'email_instructions'], 0, 4);
        }

        public function address_autocomplete()
        {
            $query = $_POST['query'];

            $response = MultiParcels()->api_client->request('address/autocomplete',
                'POST', [
                    'query'        => $query,
                    'country_code' => strtolower($_POST['country']),
                ]);

            if ($response->was_successful()) {
                echo json_encode($response->get_data());
            } else {
                echo json_encode(['results' => []]);
            }

            wp_die();
        }

        public function is_preferred_delivery_time_available()
        {
	        $response = [
		        'success' => false,
		        'times'   => [],
	        ];
            $shipping = WC()->session->get('chosen_shipping_methods')[0];

            $instance_id = 0;
            $explode     = explode(':', $shipping);

            $shipping_id = $explode[0];

            if (count($explode) == 2) {
                $instance_id = $explode[1];
            }

            $methods = WC()->shipping()->get_shipping_methods();

            if (array_key_exists($shipping_id, $methods)) {
                $class_name = get_class(WC()->shipping()->get_shipping_methods()[$shipping_id]);

                /** @var WC_MP_Shipping_Method $shipping_class */
                $shipping_class = new $class_name($instance_id);

                if ($shipping_class->get_option('allow_preferred_delivery_time') == 1) {

	                $carrier = MultiParcels()->carriers->extract_from_method($shipping);
	                $preferred_delivery_time_frames=[];
	                if ( $carrier ) {
		                $carrier_settings = MultiParcels()->carriers->get( $carrier );

		                if ( array_key_exists( 'preferred_delivery_time_frames', $carrier_settings ) ) {
			                $preferred_delivery_time_frames = [
				                0 => _x( 'Any time', 'Preferred delivery time', 'multiparcels-shipping-for-woocommerce' ),
			                ];
			                $preferred_delivery_time_frames += $carrier_settings['preferred_delivery_time_frames'];
		                }
		                }

	                $response = [
		                'success' => true,
		                'times'   => $preferred_delivery_time_frames,
	                ];
                }
            }

            echo json_encode($response);

            wp_die();
        }

        /**
         * @param  int  $order_id
         * @param $posted
         */
        function checkout_save_order_terminal_id_meta($order_id, $posted)
        {
            if (isset($_POST[WC_MP_Pickup_Point_Shipping_Method::INPUT_NAME]) && MultiParcels()->locations->is_delivery_to_pickup_point($order_id, true)) {
                $input = sanitize_text_field($_POST[WC_MP_Pickup_Point_Shipping_Method::INPUT_NAME]);
                update_post_meta($order_id, WC_MP_Pickup_Point_Shipping_Method::INPUT_NAME, $input);
            }

            if (isset($_POST[WC_MP_Shipping_Method::INPUT_DOOR_CODE])) {
                $input = sanitize_text_field($_POST[WC_MP_Shipping_Method::INPUT_DOOR_CODE]);
                update_post_meta($order_id, WC_MP_Shipping_Method::INPUT_DOOR_CODE, $input);
            }

            if (isset($_POST[WC_MP_Shipping_Method::INPUT_PREFERRED_DELIVERY_TIME])) {
                $input = sanitize_text_field($_POST[WC_MP_Shipping_Method::INPUT_PREFERRED_DELIVERY_TIME]);
                update_post_meta($order_id, WC_MP_Shipping_Method::INPUT_PREFERRED_DELIVERY_TIME, $input);
            }
        }

        public function email($order)
        {
            if ($location = MultiParcels()->locations->get_location_for_order($order)) {
                $selected_text = MultiParcels()->locations->selected_text($location);

                wc_get_template('emails/email-selected-pickup-location.php', [
                    'selected_text' => $selected_text,
                    'location'      => $location['name'],
                    'address'       => sprintf("%s, %s, %s", $location['address'],
                        $location['city'],
                        $location['postal_code']),
                ], '', MultiParcels()->plugin_path().'/woocommerce/');
            }
        }

        /**
         * @param WC_Order|int $order
         */
        function admin_order_page($order)
        {
        	$button_displayed = false;
        	$button_html = sprintf('<div style="%s"><a class="button button-primary" href="%s">%s</a></div>',
		        'margin-bottom:5px;clear: both;',
		        esc_attr('#multiparcels-shipping-pickup-point-box'),
		        __('Pickup location', 'multiparcels-shipping-for-woocommerce'));

            if ($location = MultiParcels()->locations->get_location_for_order($order)) {
                $selected_text = MultiParcels()->locations->selected_text($location);

                $output = '<div style="clear: both;">';
                $output .= sprintf('<div><strong>%s:</strong></div>', esc_html($selected_text));
                $output .= $location['name'] . '<br/>';
                $output .= esc_html(sprintf("%s, %s, %s", $location['address'], $location['city'],
                    $location['postal_code']));
                $output .= '</div>';
                $output .= '<br/>';

                $button_displayed = true;

	            $output .= $button_html;

	            echo $output;
            }

	        if ( ! $button_displayed && MultiParcels()->locations->is_delivery_to_pickup_point( $order ) ) {
		        echo $button_html;
	        }
        }

        public function woocommerce_admin_shipping_fields($fields)
        {
            $fields['phone'] = [
                'label' => __("Phone", "woocommerce"),
                'show' => true
            ];

            return $fields;
        }

        public function after_checkout($order)
        {
            if ($location = MultiParcels()->locations->get_location_for_order($order)) {
                $selected_text = MultiParcels()->locations->selected_text($location);

                wc_get_template('order/order-details-after-customer-v3.php', [
                    'selected_text' => $selected_text,
                    'location'      => $location['name'],
                    'address'       => sprintf("%s, %s, %s", $location['address'],
                        $location['city'],
                        $location['postal_code']),
                ], '', MultiParcels()->plugin_path().'/woocommerce/');
            }
        }

        /**
         * @param  array  $posted
         */
        function check_for_selected_location($posted)
        {
            if (isset($posted['shipping_method']) && is_array($posted['shipping_method'])) {
                $shipping_method = array_values($posted['shipping_method'])[0];
                $check = 'multiparcels_';
                if (substr($shipping_method, 0, strlen($check)) == $check) {
                    $pickup_point = strpos($shipping_method, WC_MP_Shipping_Method::SUFFIX_PICKUP_POINT) !== false;
                    $terminal = strpos($shipping_method, WC_MP_Shipping_Method::SUFFIX_TERMINAL) !== false;
                    $post_lv_post_delivery = MultiParcels()->locations->is_delivery_to_latvian_post_office($shipping_method);

                    if ($pickup_point || $terminal || $post_lv_post_delivery) {
                        if ( ! isset($_POST[WC_MP_Pickup_Point_Shipping_Method::INPUT_NAME]) || $_POST[WC_MP_Pickup_Point_Shipping_Method::INPUT_NAME] == '') {
                            wc_add_notice(__('Please select the pickup location.',
                                'multiparcels-shipping-for-woocommerce'),
                                'error');
                        } else {
                            $location_identifier = $_POST[WC_MP_Pickup_Point_Shipping_Method::INPUT_NAME];
                            $carrier = MultiParcels()->carriers->extract_from_method($shipping_method);

                            $location = MultiParcels()->locations->get($location_identifier, $carrier, $post_lv_post_delivery);

                            if (!$location) {
                                wc_add_notice(__('Please select the pickup location.',
                                    'multiparcels-shipping-for-woocommerce'),
                                    'error');
                            }
                        }
                    }
                }
            }
        }

        public function checkout_get_pickup_points()
        {
            $country = null;
            $city    = null;

            $shipping_method = array_values(WC()->session->get('chosen_shipping_methods'))[0];

            $carrier = MultiParcels()->carriers->extract_from_method($shipping_method, true);

            if (WC()->customer->get_shipping_city()) {
                $city = WC()->customer->get_shipping_city();
            }

            if (WC()->customer->get_billing_country()) {
                $country = WC()->customer->get_billing_country();
            }

            if (WC()->customer->get_shipping_country()) {
                $country = WC()->customer->get_shipping_country();
            }

            if (isset($_POST['city'])) {
                $city = $_POST['city'];
            }

            if (isset($_POST['country'])) {
                $country = $_POST['country'];
            }

            $city    = sanitize_text_field($city);
            $country = sanitize_text_field($country);

            if (isset($_POST['s_country']) && $_POST['s_country'] != $country) {
                $country = $_POST['s_country'];
            }

            $type = null;

            if (strpos($shipping_method, '_'.WC_MP_Shipping_Method::SUFFIX_TERMINAL) !== false) {
                $type = WC_MP_Shipping_Method::SUFFIX_TERMINAL;
            }

            if (MultiParcels()->locations->is_delivery_to_latvian_post_office($shipping_method)) {
                $type = 'post_office';
            }

            $options = MultiParcels()->locations->get_for_city($carrier, $city, $country, $type);

            if ($type != 'post_office') {
                // remove post_offices because we only need them when it is post_lv
                foreach ($options as $city => $locations) {
                    foreach ($locations as $key => $location) {
                        if ($location['type'] == 'post_office') {
                            unset($options[$city][$key]);
                        }
                    }
                }
            }

            $optionsForSelect2 = [];

            $optionsForSelect2ByIdentifier = [];

            $optionsForSelect2[] = [
                'id'          => '',
                'first_line'  => __('Please select the pickup location', 'multiparcels-shipping-for-woocommerce'),
                'second_line' => '',
                'text'        => __('Please select the pickup location', 'multiparcels-shipping-for-woocommerce'),
            ];

            foreach ($options as $city => $group) {
                $locations = [];
                foreach ($group as $item) {
                    $preparedItem        = [
                        'id'          => $item['identifier'],
                        'first_line'  => $item['name'],
                        'second_line' => sprintf("%s, %s, %s", $item['address'], $item['city'],
                            $item['postal_code']),
                        'text'        => sprintf("%s, %s, %s, %s", $item['name'], $item['address'],
                            $item['city'], $item['postal_code']),
                        'location'    => $item,
                    ];

                    if (MultiParcels()->options->get('show_all_cities') === "2" || MultiParcels()->options->get('show_all_cities') === 2) {
                        $locations[] = $preparedItem;
                    } else {
                        $optionsForSelect2[] = $preparedItem;
                    }

                    $optionsForSelect2ByIdentifier[$item['identifier']] = $preparedItem;
                }

                if (count($locations)) {
                    // group by city
                    $optionsForSelect2[] = [
                        'text'     => $city,
                        'children' => $locations,
                    ];
                }
            }

            echo json_encode([
                'all'           => $optionsForSelect2,
                'by_identifier' => $optionsForSelect2ByIdentifier,
            ]);

            wp_die();
        }


        /**
         * @param WC_Order $order
         * @param $sent_to_admin
         * @param bool $plain_text
         * @param WC_Email $email
         */
	    public function email_instructions( $order, $sent_to_admin, $plain_text = false, $email = false ) {
		    $tracking_code = get_post_meta( $order->get_id(), MP_Woocommerce_Order_Shipping::TRACKING_CODE_KEY, true );

		    if ( $tracking_code ) {
			    if ( ! $plain_text ) {
				    $tracking_code = sprintf( "<strong>%s</strong>", $tracking_code );

				    $tracking_link = get_post_meta( $order->get_id(),
					    MP_Woocommerce_Order_Shipping::TRACKING_LINK_KEY,
					    true
				    );

				    if ( $tracking_link ) {
					    $tracking_code = sprintf( "<a href='%s' target='_blank'>%s</a>",
						    $tracking_link,
						    $tracking_code
					    );
				    }
			    }

			    $text = __( 'Your order tracking number: %s', 'multiparcels-shipping-for-woocommerce' );
			    echo wpautop( wptexturize( sprintf( $text, $tracking_code ) ) ) . PHP_EOL;
		    }
	    }
    }

    new WC_MP_Shipping_Helper();
}
