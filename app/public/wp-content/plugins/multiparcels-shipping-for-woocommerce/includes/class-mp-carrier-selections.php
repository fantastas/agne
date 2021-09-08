<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MultiParcels_Carrier_Selections
 */
class MultiParcels_Carrier_Selections
{
    private $table_name = 'multiparcels_carrier_selections';

    public function get($country_code, $shipping_name)
    {
        global $wpdb;

        $query = $wpdb->prepare($x = "SELECT * FROM ".$this->table()." WHERE `country_code` = '%s' AND `shipping_name` = '%s'",
            $country_code, $shipping_name);

        $result = $wpdb->get_row($query, ARRAY_A);

        if (isset($result['id'])) {
            return $result;
        }

        return null;
    }

    public function create($country_code, $shipping_name, $carrier, $method)
    {
        global $wpdb;

        $criteria = [
            'country_code'  => $country_code,
            'shipping_name' => $shipping_name,
        ];

        $wpdb->delete($this->table(), $criteria); // cleanup

        $criteria['carrier'] = $carrier;
        $criteria['method']  = $method;

        return $wpdb->insert($this->table(), $criteria);
    }

    private function table()
    {
        global $wpdb;

        return $wpdb->prefix.$this->table_name;
    }
}

return new MultiParcels_Carrier_Selections();
