<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Mass_Shipping
 */
class MP_Mass_Shipping
{
    /** @var WC_Order[] */
    private $orders;

    public function __construct()
    {
	    if ( MultiParcels()->permissions->isFull() ) {
		    add_action( 'admin_menu', [ $this, 'admin_menu' ], 99 );

		    // Add bulk action
		    add_action( 'bulk_actions-edit-shop_order', [ $this, 'add_action' ] );

		    // Catch the action
		    add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'do_action' ], 10, 3 );
	    }
    }

    public function admin_menu()
    {
        // Add page without menu link
        add_submenu_page(null, 'MultiParcels', 'MultiParcels', 'manage_woocommerce',
            'multiparcels-shipping-for-woocommerce-mass-shipping', [$this, 'page']);
    }

    function page()
    {
	    MultiParcels()->shippings->create( [], $_GET['ids'] );
	    wp_redirect( MP_Amazing_shipping::link() );
	    exit;
        $this->load_orders($_GET['ids']);

        if (array_key_exists('submit', $_POST)) {
            foreach ($this->orders as $order) {
                $this->confirm_order($order);
            }

            $this->orders = [];
            $this->load_orders($_GET['ids']);
        }

        if (array_key_exists('print', $_POST)) {
            $this->print_orders();
        }

        ?>
        <h2><?php _e('Dispatch selected orders', 'multiparcels-shipping-for-woocommerce'); ?></h2>
        <?php

        $orders = [];

        $total           = 0;
        $total_confirmed = 0;

        foreach ($this->orders as $order_data) {
            $errors      = [];
            $name        = sanitize_text_field(sprintf("%s %s", $order_data['meta']['_shipping_first_name'][0],
                $order_data['meta']['_shipping_last_name'][0]));
            $address     = sanitize_text_field($order_data['meta']['_shipping_address_1'][0]);
            $city        = sanitize_text_field($order_data['meta']['_shipping_city'][0]);
            $postal_code = sanitize_text_field($order_data['meta']['_shipping_postcode'][0]);

            /** @var WC_Order $wc_order */
            $wc_order = $order_data['order'];

            $is_confirmed = $this->is_confirmed($order_data['meta']);
            $validations  = $this->get_errors($order_data['meta']);

            foreach ($validations as $validation_errors) {
                foreach ($validation_errors as $validation_error) {
                    $errors[] = $validation_error['text'];
                }
            }
            $confirmed_style = '';
            $confirmed       = __('No', 'multiparcels-shipping-for-woocommerce');

            if ($is_confirmed) {
                $confirmed       = __('Yes', 'multiparcels-shipping-for-woocommerce');
                $confirmed_style = 'color: green';
                $total_confirmed++;
            }

            $orders[] = [
                'id'              => $order_data['id'],
                'link'            => admin_url('post.php?post=' . $order_data['id'] . '&action=edit'),
                'receiver'        => sprintf("%s, %s, %s, %s", $name, $address, $city, $postal_code),
                'shipping_method' => $wc_order->get_shipping_method(),
                'is_confirmed'    => $is_confirmed,
                'confirmed'       => $confirmed,
                'errors'          => $errors,
                'confirmed_style' => $confirmed_style,
            ];
            $total++;
        }

        $all_done = $total == $total_confirmed;

        wc_get_template('order/mass-shipping.php', [
            'orders'   => $orders,
            'all_done' => $all_done,
        ], '', MultiParcels()->plugin_path().'/woocommerce/');
    }

    /**
     * @param array $order_meta
     *
     * @return bool
     */
    public function is_confirmed($order_meta)
    {
        if (array_key_exists(MP_Woocommerce_Order_Shipping::CONFIRMED_KEY, $order_meta)) {
            $confirmed = $order_meta[MP_Woocommerce_Order_Shipping::CONFIRMED_KEY][0];
            if ($confirmed) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $order_meta
     *
     * @return array
     */
    public function get_errors($order_meta)
    {
        if (array_key_exists(MP_Woocommerce_Order_Shipping::ERRORS_KEY, $order_meta)) {
            $errors = $order_meta[MP_Woocommerce_Order_Shipping::ERRORS_KEY][0];

            if ($errors) {
                return json_decode($errors, true);
            }
        }

        return [];
    }

    /**
     * @param array $actions
     *
     * @return mixed
     */
    public function add_action($actions)
    {
        $actions['multiparcels_mass_confirm'] = __('Dispatch selected orders', 'multiparcels-shipping-for-woocommerce');

        return $actions;
    }

    /**
     * @param $redirect_to
     * @param $action
     * @param $ids
     *
     * @return string
     */
    public function do_action($redirect_to, $action, $ids)
    {
        if ($action != 'multiparcels_mass_confirm') {
            return $redirect_to;
        }

        $base = admin_url('admin.php?page=multiparcels-shipping-for-woocommerce-mass-shipping');

        wp_redirect(add_query_arg([
	        'ids' => $ids,
        ], $base));
        exit;
    }

    private function load_orders($ids)
    {
        foreach ($ids as $order_id) {
            $order          = [];
            $order['order'] = wc_get_order($order_id);
            $order['meta']  = get_post_meta($order_id);
            $order['id']    = $order['order']->get_id();

            $this->orders[] = $order;
        }
    }

    private function confirm_order($order)
    {
        $shipping = new MP_Woocommerce_Order_Shipping();
        $shipping->load_order($order['id']);

        if ( ! $shipping->is_confirmed()) {
            $shipping->ship_order($order['id'], [], false);
        }
    }

    private function print_orders()
    {
        $external_ids = [];

        foreach ($this->orders as $order) {
            if (array_key_exists('multiparcels_external_id', $order['meta'])) {
                $external_ids[] = $order['meta']['multiparcels_external_id'][0];
            }
        }

        if (count($external_ids)) {
            $response = MultiParcels()->api_client->request('batch_labels', 'POST', [
                'shipments' => $external_ids,
            ]);

            if ($response->was_successful()) {
                $file_name = sprintf("labels_%d.pdf", mt_rand(10000, 99999));
                $upload    = wp_upload_bits($file_name, null, base64_decode($response->get_data()['content']));

                header('Location: ' . $upload['url']);
                exit;
            }
        }
    }
}

return new MP_Mass_Shipping();
