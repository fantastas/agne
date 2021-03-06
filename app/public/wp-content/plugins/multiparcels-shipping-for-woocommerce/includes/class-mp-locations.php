<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Locations
 */
class MP_Locations
{
    const TYPE_TERMINAL = 'terminal';
    const TYPE_PICKUP_POINT = 'pickup_point';

    /** @var string */
    private $table = 'multiparcels_terminals';

    /**
     * @param string|null $carrier_code
     * @param string|null $country
     * @param string|null $type
     *
     * @return mixed
     */
	public function all( $carrier_code = null, $country = null, $type = null ) {
		global $wpdb;

		$wheres = [];

		if ( $carrier_code ) {
			$wheres['carrier_code'] = $carrier_code;
		}

		if ( $country ) {
			$wheres['country_code'] = $country;
		}

		if ( $type ) {
			$wheres['type'] = $type;
		}

		$query = "SELECT * FROM " . $this->table();

		if ( count( $wheres ) ) {
			$query .= ' WHERE ';

			foreach ( $wheres as $column => $value ) {
				$query .= sprintf( "`%s` = '%s' AND ", $column, esc_sql( $value ) );
			}

			$query = rtrim( $query, ' AND ' );
		}

		return $wpdb->get_results( $query, ARRAY_A );
	}

    /**
     * @param string $identifier
     * @param string $carrier_code
     *
     * @return array
     */
    public function get($identifier, $carrier_code, $post_office = null)
    {
        global $wpdb;

        $type_sql = "AND `type` != 'post_office'";

        if ($post_office) {
            $type_sql = "AND `type` = 'post_office'";
        }

        $query = $wpdb->prepare("SELECT * FROM " . $this->table() . " WHERE `identifier` = %s AND `carrier_code` = %s " . $type_sql,
            $identifier, $carrier_code);

        return $wpdb->get_row($query, ARRAY_A);
    }

    public function grouped_by_city($carrier_code, $country, $normalKeys = false, $type = null)
    {
        $groups    = [];
        $locations = $this->all($carrier_code, $country, $type);

        foreach ($locations as $location) {
            if (MultiParcels()->options->get('show_all_cities') === "2" || MultiParcels()->options->get('show_all_cities') === 2) {
                $city                        = $location['city'];
            }else {
                $city = $this->latin_only($location['city']);
            }
            $location['city_simplified'] = $city;

            $groups[$city][$location['name'] . $location['address']] = $location;
        }

        foreach ($groups as $key => $data){
            ksort($groups[$key]);
        }

        ksort($groups);
        // For now only Lithuanian
        $priorityCities = [
            'Vilnius',
            'Kaunas',
            'Klaip??da',
        ];

        // needed for sorting properly
        $priorityCities = array_reverse($priorityCities);

        foreach ($priorityCities as $city) {
            if (array_key_exists($city, $groups)) {
                $groups = [$city => $groups[$city]] + $groups;
            }
        }

        if ($normalKeys) {
            foreach ($groups as $city => $items) {
                $first_city = reset($items);

                unset($groups[$city]);

                $groups[$first_city['city']] = $items;
            }
        }

        return $groups;
    }

	/**
	 * @param      $carrier_code
	 * @param      $city
	 * @param      $country
	 * @param null $type
	 *
	 * @return array
	 */
    public function get_for_city($carrier_code, $city, $country, $type = null)
    {
        $city   = $this->latin_only($city);
        $groups = $this->grouped_by_city($carrier_code, $country, false, $type);

        if (MultiParcels()->options->getBool('show_all_cities')) {
            return $groups;
        }

        if (isset($groups[$city])) {
            return [
                $city => $groups[$city],
            ];
        }

        return $this->grouped_by_city($carrier_code, $country, true, $type);
    }

    public function latin_only($text)
    {
        return self::latin($text);
    }

	public static function latin($text)
	{
		$table = array(
			'??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A',
			'??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a',

			'??'=>'B', '??'=>'b', '??'=>'Ss',

			'??'=>'C', '??'=>'C', '??'=>'C', '??'=>'C', '??'=>'C',
			'??'=>'c', '??'=>'c', '??'=>'c', '??'=>'c', '??'=>'c',

			'??'=>'Dj', '??'=>'D',
			'??'=>'dj', '??'=>'d',

			'??'=>'E', '??'=>'E', '??'=>'E', '??'=>'E', '??'=>'E', '??'=>'E', '??'=>'E', '??'=>'E',
			'??'=>'e', '??'=>'e', '??'=>'e', '??'=>'e', '??'=>'e', '??'=>'e', '??'=>'e', '??'=>'e',

			'??'=>'G', '??'=>'G', '??'=>'G', '??'=>'G',
			'??'=>'g', '??'=>'g', '??'=>'g', '??'=>'g',

			'??'=>'H', '??'=>'H',
			'??'=>'h', '??'=>'h',

			'??'=>'I', '??'=>'I', '??'=>'I', '??'=>'I', '??'=>'I', '??'=>'I', '??'=>'I', '??'=>'I', '??'=>'I',
			'??'=>'i', '??'=>'i', '??'=>'i', '??'=>'i', '??'=>'i', '??'=>'i', '??'=>'i', '??'=>'i', '??'=>'i',

			'??'=>'J',
			'??'=>'j',

			'??'=>'K',
			'??'=>'k', '??'=>'k',

			'??'=>'L', '??'=>'L', '??'=>'L', '??'=>'L', '??'=>'L',
			'??'=>'l', '??'=>'l', '??'=>'l', '??'=>'l', '??'=>'l',

			'??'=>'N', '??'=>'N', '??'=>'N', '??'=>'N', '??'=>'N',
			'??'=>'n', '??'=>'n', '??'=>'n', '??'=>'n', '??'=>'n', '??'=>'n',

			'??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O',
			'??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o',

			'??'=>'R', '??'=>'R',
			'??'=>'r', '??'=>'r', '??'=>'r',

			'??'=>'S', '??'=>'S', '??'=>'S', '??'=>'S',
			'??'=>'s', '??'=>'s', '??'=>'s', '??'=>'s',

			'??'=>'T', '??'=>'T', '??'=>'T',
			'??'=>'t', '??'=>'t', '??'=>'t',

			'??'=>'U', '??'=>'U', '??'=>'U', '??'=>'U', '??'=>'U', '??'=>'U', '??'=>'U', '??'=>'U', '??'=>'U', '??'=>'U',
			'??'=>'u', '??'=>'u', '??'=>'u', '??'=>'u', '??'=>'u', '??'=>'u', '??'=>'u', '??'=>'u', '??'=>'u', '??'=>'u',

			'??'=>'W', '???'=>'W', '???'=>'W', '???'=>'W',
			'??'=>'w', '???'=>'w', '???'=>'w', '???'=>'w',

			'??'=>'Y', '??'=>'Y', '??'=>'Y',
			'??'=>'y', '??'=>'y', '??'=>'y',

			'??'=>'Z', '??'=>'Z', '??'=>'Z',
			'??'=>'z', '??'=>'z', '??'=>'z',
		);

		$text = strtr($text, $table);

		// lowercase
		$text = strtolower($text);

		if (empty($text)) {
			return 'n-a';
		}

		return $text;
	}

    /**
     * Returns prefixed table name
     *
     * @return string
     */
    private function table()
    {
        global $wpdb;

        return $wpdb->prefix . $this->table;
    }

    /**
     * Create a location
     *
     * @param array $data
     */
    public function create($data)
    {
        global $wpdb;

        $wpdb->insert($this->table(), $data);
    }

    public function clear()
    {
        global $wpdb;

        $wpdb->query("TRUNCATE " . $this->table());
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public function type_name($type)
    {
        if ($type == self::TYPE_TERMINAL) {
            return __('Terminal', 'multiparcels-shipping-for-woocommerce');
        }
        if ($type == self::TYPE_PICKUP_POINT) {
            return __('Pickup Point', 'multiparcels-shipping-for-woocommerce');
        }

        return $type;
    }

    /**
     * @param array $location
     *
     * @return string
     */
    public function selected_text($location)
    {
        $selected_text = __("Selected terminal", 'multiparcels-shipping-for-woocommerce');

        if ($location['type'] == MP_Locations::TYPE_PICKUP_POINT) {
            $selected_text = __("Selected pickup point", 'multiparcels-shipping-for-woocommerce');
        }

        return $selected_text;
    }

    public function update()
    {
    	// check if there are no errors
	    $test_response = MultiParcels()->api_client->request('locations_for_shops', 'GET', [
		    'limit'      => 1,
	    ]);

	    if ( ! $test_response->was_successful() ) {
		    MultiParcels()->options->set_other( 'last_update', 'test failed' );

		    return;
	    }

	    // make sure there are no problems before clearing
        $test_api = MultiParcels()->api_client->request('locations_for_shops', 'GET', [
            'limit'      => 1,
        ]);

        if ( ! $test_api->was_successful()) {
            return;
        }

        $this->clear();

        // Make sure the table exists
        MP_Install::table();

        $carriers = MultiParcels()->carriers->all_enabled();

        foreach ($carriers as $carrier_code) {
            $search   = [];
            $search[] = 'courier_code:' . $carrier_code;

            $response = MultiParcels()->api_client->request('locations_for_shops', 'GET', [
                'limit'      => 9999,
                'search'     => implode(';', $search),
            ]);

            if ($response->was_successful()) {
                $locations = $response->get_data();

                if (count($locations) > 0) {
                    foreach ($locations as $locationData) {
                        $location = [
                            'carrier_code'  => $locationData['courier_code'],
                            'type'          => $locationData['type'],
                            'name'          => $locationData['name'],
                            'address'       => $locationData['address'],
                            'postal_code'   => $locationData['postal_code'],
                            'city'          => $locationData['city'],
                            'country_code'  => $locationData['country_code'],
                            'identifier'    => $locationData['identifier'],
                            'comment'       => $locationData['comment'],
                            'working_hours' => $locationData['working_hours'],
                            'latitude'      => $locationData['latitude'],
                            'longitude'     => $locationData['longitude'],
                        ];

                        MultiParcels()->locations->create($location);
                    }
                }

                MultiParcels()->options->set('last_update', current_time('Y-m-d H:i:s'), true);
            }
        }
    }

    /**
     * @param int|WC_Order $order
     *
     * @return array|false
     */
    public function get_location_for_order($order)
    {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return false;
        }

        $order_shipping_methods = $order->get_shipping_methods();
        /** @var WC_Order_Item_Shipping $order_shipping_method */
        $order_shipping_method = reset($order_shipping_methods);

        $method = '';

        if ($order_shipping_method) {
            $method = $order_shipping_method->get_method_id();
        }

        $courier_code = MultiParcels()->carriers->extract_from_method($method);

        if ($courier_code) {
            $location_identifier = get_post_meta($order->get_id(), 'multiparcels_location_identifier', true);

            $location = MultiParcels()->locations->get($location_identifier, $courier_code, MultiParcels()->locations->is_delivery_to_latvian_post_office($method));

            if ($location) {
                return $location;
            }
        }

        return false;
    }

    public function google_maps_enabled()
    {
        if (MultiParcels()->options->get('google_maps_api_key') == '') {
            return false;
        }

        return true;
    }

	/**
	 * @param WC_Order|int $order
	 *
	 * @return bool
	 */
	public function is_delivery_to_pickup_point( $order, $allow_post_office = false) {
		if (is_numeric($order)) {
			$order = wc_get_order($order);
		}

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$order_shipping_methods = $order->get_shipping_methods();
		/** @var WC_Order_Item_Shipping $order_shipping_method */
		$order_shipping_method = reset($order_shipping_methods);

		$method = '';

		if ($order_shipping_method) {
			$method = $order_shipping_method->get_method_id();
		}

		if ( strpos( $method, WC_MP_Shipping_Method::SUFFIX_PICKUP_POINT ) !== false || strpos( $method,
				WC_MP_Shipping_Method::SUFFIX_TERMINAL ) !== false ) {
			return true;
		}

		if ($allow_post_office) {
		    return $this->is_delivery_to_latvian_post_office($method);
        }

		return false;
    }

    /**
     * @param string|WC_Order $method
     * @return bool
     */
    public function is_delivery_to_latvian_post_office($method)
    {
        if ($method instanceof WC_Order) {
            $shipping_methods = $method->get_shipping_methods();
            $shipping_methods = reset($shipping_methods);

            $method = $shipping_methods['method_id'];
        }

        if (MultiParcels()->carriers->extract_from_method($method) != WC_MP_Shipping_Helper::CARRIER_POST_LV) {
            return false;
        }

        $without_instance = explode(':', $method)[0];
        $post_ending = '_'.WC_MP_Shipping_Method::SUFFIX_POST;

        if (substr($without_instance, strlen($post_ending) * -1) == $post_ending) {
            return true;
        }

        return false;
    }
}

return new MP_Locations();
