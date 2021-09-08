<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Woocommerce
 */
class MP_Woocommerce
{
    /**
     * MP_Woocommerce constructor.
     */
    public function __construct()
    {
        add_filter('woocommerce_shipping_init', [$this, 'shipping_init']);
        add_filter('woocommerce_shipping_methods', [$this, 'register_shipping_methods']);

        add_action('woocommerce_after_checkout_form', [$this, 'add_jscript']);

        add_filter('woocommerce_order_get_formatted_shipping_address', [$this, 'get_formatted_shipping_address'], 10, 3);

        add_action('init', function () {

	        $default_location_hook = MultiParcels()->options->get( 'pickup_location_display_hook', false,
		        'woocommerce_review_order_before_payment' );

	        $filter_hook = apply_filters('multiparcels_location_selector_hook', $default_location_hook);
	        $filter_hook_priority = apply_filters('multiparcels_location_selector_priority', 10);

            if (MultiParcels()->helper->is_aerocheckout()) {
                $this->add_aerocheckout_actions();
            } elseif ( $filter_hook == 'woocommerce_after_shipping_rate' ) {
		        add_action(
			        $filter_hook,
			        [ $this, 'pickup_location_selector_display_after_shipping_rate' ],
			        $filter_hook_priority,
                    2
		        );
	        } else {
		        add_action(
			        $filter_hook,
			        [ $this, 'pickup_location_selector_display' ],
			        $filter_hook_priority
		        );
	        }
        });

        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'carrier_logo'], PHP_INT_MAX, 2);

        // add checkbox "Does not fit in pickup points" to product's shipping tab
        add_action( 'woocommerce_product_options_shipping', [$this, 'product_does_not_fit']);

        // Save the custom fields values as meta data
        add_action( 'woocommerce_process_product_meta', [$this, 'saving_product_meta'] );

        // Display fields for product category
        add_action('product_cat_add_form_fields',
            [$this, 'product_category_creating_new'], 10, 1);
        add_action('product_cat_edit_form_fields',
            [$this, 'product_category_editing'], 10, 1);

        // Save product category meta
        add_action('edited_product_cat', [$this, 'product_category_saving'], 10, 1);
        add_action('create_product_cat', [$this, 'product_category_saving'], 10, 1);

        if (MultiParcels()->helper->has_omnisend()) {
            add_filter('http_request_args', [$this, 'omnisend_filter'], 999, 3);
        }

        // Hide unneeded fields for terminal delivery
        add_filter('woocommerce_checkout_fields', [$this, 'hide_fields_for_terminal_delivery'], 9999);

        add_filter( 'woocommerce_email_classes', [$this, 'add_automatic_confirmation_failed_email']);

        // automatic confirmation
        add_action('multiparcels_automatic_confirmation_cron', [$this, 'multiparcels_automatic_confirmation_cron']);

        // pickup location selection in order view
        add_action('add_meta_boxes', [$this, 'add_pickup_location_meta_box_to_order'], 1);
        add_action('save_post', [$this, 'saving_order_for_pickup_location']);

        // Remove state fields, door code etc.
        add_action('init', function () {
            add_filter('woocommerce_checkout_fields', [$this, 'custom_override_checkout_fields'], apply_filters('multiparcels_override_checkout_fields_priority', 9999));
        });

        /**
         * Add full access features only if they are enabled.
         * Enabling this does not actually make them work.
         * All of the features here are actually doing all the work on the API, sorry :(
         */
        if (MultiParcels()->permissions->isFull()) {
            add_filter('woocommerce_checkout_fields', [$this, 'filter_checkout_fields']);
            add_filter('manage_edit-shop_order_columns', [$this, 'add_column_to_orders'], 20);
            add_action('manage_shop_order_posts_custom_column', [$this, 'add_content_to_column']);

            add_action('add_meta_boxes', [$this, 'add_shipping_meta_box_to_order'], 1);

            add_action('woocommerce_admin_order_data_after_shipping_address',
                [$this, 'woocommerce_admin_order_data_after_shipping_address'], 50);

            add_action('save_post', [$this, 'saving_order']);
        }
    }

    /**
     * @param array $email_classes
     * @return array
     */
    function add_automatic_confirmation_failed_email( $email_classes ) {

        include_once __DIR__.'/emails/class-multiparcels-automatic-confirmation-failed-email.php';

        $email_classes['MultiParcels_Automatic_Confirmation_Failed_Email'] = new MultiParcels_Automatic_Confirmation_Failed_Email();

        return $email_classes;
    }

    /**
     * @param string $address
     * @param array $raw_address
     * @param WC_Order $order
     */
    public function get_formatted_shipping_address($address, $raw_address, $order = null)
    {
        // to display "Ship to:" column because WC_order::has_shipping_address() fails without addresses
        if (!isset($raw_address['address_1']) || !isset($raw_address['address_2']) || (!$raw_address['address_1'] && !$raw_address['address_2'])) {
            if ($location = MultiParcels()->locations->get_location_for_order($order)) {
                $country = new WC_Countries();
                return $location['name'].'<br/>'. $country->get_formatted_address(['address_1' => $location['address'], 'city' => $location['city'], 'postcode' => $location['postal_code'], 'country' => $location['country_code']]);

                return $location['name'].'<br/>'.$location['address'];
            }
        }

        return $address;
    }

    public function multiparcels_automatic_confirmation_cron()
    {
        MultiParcels()->options->set('automatic_confirmation_last_update', current_time('Y-m-d H:i:s'), true);
        $days = MultiParcels()->options->get_other_setting('automatic_confirmation', 'run_days');

        if (!is_array($days) || !in_array(date('N'), $days)) {
            return;
        }

        $statuses = MultiParcels()->options->get_other_setting('automatic_confirmation', 'statuses');

        if (!is_array($statuses) || !count($statuses)) {
            $statuses = [
                'wc-processing'
            ];
        }

        $time = '-1 hour';

        $current_value = MultiParcels()->options->get_other_setting('automatic_confirmation', 'frequency');

        if ($current_value == '24 hour') {
            $time = '-24 hour';
        }

        $posts = get_posts([
            'numberposts' => -1,
            'post_type' => wc_get_order_types(),
            'post_status' => $statuses,
            'date_query' => [
                'relation'   => 'OR',
                [
                    'column'  => 'post_date',
                    'after'   => $time
                ],
                [
                    'column'  => 'post_modified',
                    'after'   => $time
                ]
            ]
        ]);

        if (!count($posts)) {
           return;
        }

        $failed = [];

        foreach ($posts as $post) {
            $is_confirmed = (bool) get_post_meta($post->ID, MP_Woocommerce_Order_Shipping::CONFIRMED_KEY, true);

            if ($is_confirmed) {
                continue;
            }

            $success = false;
            $exception = null;

            try {
                if (get_post_meta($post->ID, MP_Woocommerce_Order_Shipping::AUTOMATIC_CONFIRMATION_FAILED, true)) {
                    continue; // already failed
                }

                /** @var MP_Woocommerce_Order_Shipping $shippingClass */
                $shippingClass = new MP_Woocommerce_Order_Shipping();
                $shippingClass->ship_order($post->ID, [], false);

                $success = (bool) get_post_meta($post->ID, MP_Woocommerce_Order_Shipping::CONFIRMED_KEY, true);
            } catch (Exception $e) {
                $exception = $e->getMessage();
            }

            if (!$success) {
                $failed[] = $post->ID;
                $order = wc_get_order($post);

                update_post_meta($order->get_id(), MP_Woocommerce_Order_Shipping::AUTOMATIC_CONFIRMATION_FAILED, true); // remember that it failed

                if ($exception) {
                    $text = $exception;
                } else {
                    $errors = json_decode(get_post_meta($order->get_id(), MP_Woocommerce_Order_Shipping::ERRORS_KEY,
                        true), true);
                    $text = MP_Woocommerce_Order_Shipping::parse_validation_errors($errors, false);
                }

                $note = "MultiParcels: ".__('Automatic confirmation failed',
                        'multiparcels-shipping-for-woocommerce').": ".$text;

                $order->add_order_note($note);
            }
        }

        if (count($failed)) {
            WC()->mailer(); // without this the WC_Email class does not exist
            do_action('multiparcels_automatic_confirmation_failed', $failed);
        }
    }

    function omnisend_filter($parsed_args, $url)
    {
        global $post;

        // only omnisend
        if (strpos($url, 'api.omnisend.com/v3/orders/') === false) {
            return $parsed_args;
        }

        $orderId = $post->ID;

        $getOrderIdFromUrl = explode('api.omnisend.com/v3/orders/', $url);
        if (count($getOrderIdFromUrl) == 2) {
            $orderId = $getOrderIdFromUrl[1];
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return $parsed_args;
        }

        // only confirmed tracking code
        $is_confirmed = (bool) get_post_meta($orderId,
            MP_Woocommerce_Order_Shipping::CONFIRMED_KEY, true);

        $trackingCode = null;
        $trackingLink = null;
        $carrierName = null;

        if ($is_confirmed) {
            $carrier = MultiParcels()->carriers->extract_from_method($order);
            $carrierName = MultiParcels()->carriers->name($carrier);
            $trackingCode = get_post_meta($orderId,
                MP_Woocommerce_Order_Shipping::TRACKING_CODE_KEY, true);
            $trackingLink = get_post_meta($orderId,
                MP_Woocommerce_Order_Shipping::TRACKING_LINK_KEY, true);
        } else {

            return $parsed_args;
        }

        $body = json_decode($parsed_args['body'], true);
        $body['trackingCode'] = $trackingCode;
        $body['courierTitle'] = $carrierName;
        $body['courierUrl'] = $trackingLink;


        $parsed_args['body'] = json_encode($body);

        return $parsed_args;
    }

    function product_category_saving($term_id) {

        $multiparcels_does_not_fit = filter_input(INPUT_POST, 'multiparcels_does_not_fit');

        update_term_meta($term_id, 'multiparcels_does_not_fit', $multiparcels_does_not_fit);
    }

    function product_category_creating_new() {
        ?>
        <div class="form-field">
            <label for="multiparcels_does_not_fit"><?php _e( 'Does not fit in pickup points', 'multiparcels-shipping-for-woocommerce' ); echo ' (MultiParcels)'; ?></label>
            <input type="checkbox" name="multiparcels_does_not_fit" id="multiparcels_does_not_fit" value="yes">
            <p class="description"><?php _e( 'If at least one product which does not fit in parcel terminals is added to the cart - all pickup point shipping methods will be disabled', 'multiparcels-shipping-for-woocommerce' ); ?></p>
        </div>
        <?php
    }

    function product_category_editing($term) {

        $term_id = $term->term_id;

        $value = get_term_meta($term_id, 'multiparcels_does_not_fit', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label
                        for="multiparcels_does_not_fit"><?php
                    _e('Does not fit in pickup points',
                        'multiparcels-shipping-for-woocommerce');
                    echo ' (MultiParcels)'; ?></label></th>
            <td>
                <input type="checkbox" name="multiparcels_does_not_fit"
                       id="multiparcels_does_not_fit" value="yes" <?php if($value){echo 'checked'; } ?>>
                <p class="description"><?php
                    _e('If at least one product which does not fit in parcel terminals is added to the cart - all pickup point shipping methods will be disabled',
                        'multiparcels-shipping-for-woocommerce'); ?></p>
            </td>
        </tr>
        <?php
    }


    function saving_product_meta($post_id)
    {
        $value = filter_input(INPUT_POST, 'multiparcels_does_not_fit');

        update_post_meta($post_id, 'multiparcels_does_not_fit', $value);
    }

    public function product_does_not_fit()
    {
        global $post;

        echo '</div><div class="options_group">';

        woocommerce_wp_checkbox( array(
            'id'          => 'multiparcels_does_not_fit',
            'label'       => __( 'Does not fit in pickup points', 'multiparcels-shipping-for-woocommerce' ) . ' (MultiParcels)',
            'desc_tip'    => 'true',
            'description' => __( 'If at least one product which does not fit in parcel terminals is added to the cart - all pickup point shipping methods will be disabled', 'multiparcels-shipping-for-woocommerce' ),
            'value'       => get_post_meta( $post->ID, 'multiparcels_does_not_fit', true ),
        ) );
    }

    public function carrier_logo($label, $method)
    {
        if (MultiParcels()->options->get_other_setting('carrier_logos', 'disabled')) {
            return $label;
        }

        $visibility = MultiParcels()->options->get_other_setting('carrier_logos', 'icon_visibility');

        if ($visibility == 'only_checkout' && ! is_checkout()) {
            return $label;
        }

        if ($visibility == 'only_cart' && ! is_cart()) {
            return $label;
        }

        $carrier = MultiParcels()->carriers->strict_extract_from_method($method->method_id);

        if ($carrier) {
            $width = MultiParcels()->options->get_other_setting('carrier_logos', 'icon_width_cart', '100px');

            if (is_checkout()) {
                $width = MultiParcels()->options->get_other_setting('carrier_logos', 'icon_width_checkout', '100px');
            }


            $logo_url = MultiParcels()->public_plugin_url('images/carriers/'.$carrier.'.png');
            $logo_url = apply_filters('multiparcels_checkout_carrier_logo_url', $logo_url, $carrier, MultiParcels()->helper->extract_delivery_from_shipping_method($method->method_id));

            $img      = sprintf(
                "<div class='multiparcels-carrier-icon-image-holder'><img class='multiparcels_carrier_icon multiparcels_carrier_icon_%s' src='%s' style='max-width: %s'></div>",
                $carrier,
                $logo_url,
                $width
            );

            $position = MultiParcels()->options->get_other_setting('carrier_logos', 'icon_position');

            $grid_display = ! (bool) MultiParcels()->options->get_other_setting('carrier_logos', 'grid_display', 1);

            if ($grid_display) {
                $css = 'text-align: left;';

                if (MultiParcels()->options->get_other_setting('carrier_logos', 'grid_display_aligned')) {
                    $css = 'text-align: left;padding-left: 5px;';

                    ?>
                    <style lang="css">
                        .woocommerce-shipping-totals ul#shipping_method li {
                            display: flex;
                            justify-content: space-between;
                        }

                        .woocommerce-shipping-totals ul#shipping_method li > label {
                            flex: 1;
                        }

                        .woocommerce-shipping-totals .multiparcels-carrier-icon-image-holder {
                            margin-bottom: 8px;
                        }
                    </style>
                    <?php
                }

                $label = sprintf('<span class="multiparcels-grid-display-text" style="%s">%s</span>',$css, $label);

                if ($position == 'before_label') {
                    $label = $img.$label;
                } else {
                    $label = $label.$img;
                }

                $label = sprintf("<div style='display: -webkit-box;display: -ms-flexbox;display: flex;-webkit-box-pack: justify;-ms-flex-pack: justify;justify-content: space-between;'>%s</div>", $label);
            } else {
                if ($position == 'after_label') {
                    $label = $label.$img;
                } else {
                    $label = $img.$label;
                }
            }
        }

        return $label;
    }

    public function hide_fields_for_terminal_delivery($fields)
    {
        if (MultiParcels()->options->get_other_setting('checkout', 'enabled') || MultiParcels()->options->get_other_setting('checkout', 'hide_for_local_pickup')) {
            if (isset($_POST['shipping_method']) && is_array($_POST['shipping_method'])) {

                $check = 'multiparcels_';
                $shipping_method = array_values($_POST['shipping_method'])[0];

                $check1 = false;
                $check2 = false;

                if (MultiParcels()->options->get_other_setting('checkout', 'enabled')) {
                    // check if MultiParcels shipping
                    $check1 = substr($shipping_method, 0, strlen($check)) == $check;

                    // check if delivery to terminal or pickup point
                    $check2 = strpos($shipping_method, WC_MP_Shipping_Method::SUFFIX_PICKUP_POINT) !== false ||
                        strpos($shipping_method, WC_MP_Shipping_Method::SUFFIX_TERMINAL) !== false;
                }

                if (MultiParcels()->options->get_other_setting('checkout', 'hide_for_local_pickup') && explode(':', $shipping_method)[0] == 'local_pickup') {
                    $check1 = true;
                    $check2 = true;
                }

                if ($check1 && $check2) {
                    $fields['billing']['billing_city']['required'] = false;
                    $fields['billing']['billing_postcode']['required'] = false;
                    $fields['billing']['billing_address_1']['required'] = false;
                    $fields['shipping']['shipping_city']['required'] = false;
                    $fields['shipping']['shipping_postcode']['required'] = false;
                    $fields['shipping']['shipping_address_1']['required'] = false;
                }
            }
        }

        return $fields;
    }

    public function custom_override_checkout_fields($fields)
    {
        // Hardcoded to Venipak since we currently support only it
        $fields['billing']['door_code'] = [
            'label'        => __('Door code', 'multiparcels-shipping-for-woocommerce'),
            'placeholder'  => __('To make it easy and faster for courier to deliver your order',
                'multiparcels-shipping-for-woocommerce'),
            'required'     => false,
            'class'        => ['form-row-wide', 'multiparcels-door-code', 'multiparcels-door-code-invisible'],
            'clear'        => true,
            'priority'     => 61,
            'autocomplete' => 'off',
            'maxlength'    => 10,
        ];

        $fields['billing']['preferred_delivery_time'] = [
            'type'         => 'select',
            'label'        => __( 'Preferred delivery time', 'multiparcels-shipping-for-woocommerce' ),
            'required'     => false,
            'class'        => [ 'form-row-wide' ],
            'clear'        => true,
            'priority'     => 71,
            'autocomplete' => 'off',
            'options'      => [
                0 => _x( 'Any time', 'Preferred delivery time', 'multiparcels-shipping-for-woocommerce' ),
            ],
        ];


        if (isset($fields['billing']['billing_phone']) && MultiParcels()->options->get_other_setting('checkout',
                'force_phone_number_required')) {
            $fields['billing']['billing_phone']['required'] = true;
        }

        if (MultiParcels()->options->get_other_setting('checkout', 'hide_delivery_phone_number') != 1) {
            // when shipping to a different address there is no phone field
            $fields['shipping']['shipping_phone'] = [
                'label' => __('Phone', 'woocommerce'),
                'required' => true,
                'type' => 'text',
                // because a lot of themes have this styling and does not work for tel: input[type="text"], input#billing_phone, input#billing_email
                'class' => [
                    'form-row-wide'
                ],
                'validate' => [
                    'phone'
                ],
                'autocomplete' => 'tel',
                'priority' => 100,
            ];
        }

        if (MultiParcels()->permissions->addressAutoCompleteEnabled()) {
            $fields['billing']['billing_address_1']['autocomplete']      = 'super-secret-search';
            $fields['billing']['billing_address_1']['custom_attributes'] = [
                'autocorrect'    => 'off',
                'autocapitalize' => 'off',
                'spellcheck'     => 'off',
                'role'           => 'textbox',
            ];
        }

        $is_checkoutwc_active = false;

        if (in_array('checkout-for-woocommerce/checkout-for-woocommerce.php', get_option('active_plugins', []))) {
            $is_checkoutwc_active = true;
        }

        if ( ! $is_checkoutwc_active) {
            if (!MultiParcels()->options->get_other_setting('checkout', 'show_address_2_field')) {
                unset($fields['billing']['billing_address_2']);
                unset($fields['shipping']['shipping_address_2']);
            }

            unset($fields['billing']['billing_state']);
            unset($fields['shipping']['shipping_state']);
        }

	    return $fields;
    }

    function filter_checkout_fields($fields)
    {
	    $fields['order']['order_comments']['maxlength']        = 45;
	    $fields['billing']['billing_address_1']['maxlength']   = 64;
	    $fields['billing']['billing_city']['maxlength']        = 50;
	    $fields['shipping']['shipping_address_1']['maxlength'] = 64;
	    $fields['shipping']['shipping_city']['maxlength']      = 50;

        return $fields;
    }

    public function pickup_location_selector_display()
    {
        $aeroCheckoutStart = '';
        $aeroCheckoutEnd   = '';

        if (MultiParcels()->helper->is_aerocheckout()) {
            $aeroCheckoutStart = sprintf("<div class='%s' style='%s'>",
                'wfacp-section wfacp-hg-by-box wfacp_shipping_method wfacp_shipping_method',
                'margin-bottom: 15px;'
            );
            $aeroCheckoutEnd   = '</div>';
        }

        ?>
        <div id="mp-wc-pickup-point-shipping" style="display: none;padding-top: 0.5em;">
            <?php echo $aeroCheckoutStart; ?>
            <div class="form-row form-row-wide">
                <?php if (MultiParcels()->helper->is_aerocheckout()) {
                    ?>
                    <div class="wfacp_internal_form_wrap wfacp-comm-title none">
                        <h2 class="wfacp_section_heading wfacp_section_title wfacp-normal wfacp-text-left">
                            <?php _e('Pickup location', 'multiparcels-shipping-for-woocommerce') ?>
                        </h2>
                    </div>
                    <?php
                } else {
                    ?>
                    <strong class="mp-please-select-location">
                        <?php _e('Pickup location', 'multiparcels-shipping-for-woocommerce') ?> <br>
                    </strong>
                    <?php
                } ?>

                <select id="<?php echo esc_attr(WC_MP_Pickup_Point_Shipping_Method::INPUT_ID) ?>"
                        name="<?php echo esc_attr(WC_MP_Pickup_Point_Shipping_Method::INPUT_NAME) ?>"
                        style="width: 100%">
                    <option value=""><?php _e('Please select the pickup location',
                            'multiparcels-shipping-for-woocommerce') ?></option>
                </select>
            </div>

            <div class="form-row form-row-wide">
                <div class="mp-selected-pickup-point-info"></div>
            </div>

            <div id="mp-map-preview" style="display: none;">
                <div id="mp-gmap"></div>
            </div>
            <?php echo $aeroCheckoutEnd; ?>
        </div>
        <?php
    }

	/**
	 * @param WC_Shipping_Rate $method
	 * @param int $index
	 */
	public function pickup_location_selector_display_after_shipping_rate( $method, $index ) {
		$chosenMethods = WC()->session->get( 'chosen_shipping_methods' );

		// display only for selected method
		if ( array_key_exists( '0', $chosenMethods ) && $method->get_id() == $chosenMethods[0] ) {
			$this->pickup_location_selector_display();
		}
	}

	public function add_jscript() {
		if ( MultiParcels()->locations->google_maps_enabled() ) {
			$show = true;

			if ( wp_script_is( 'flatsome-maps', 'registered' ) || wp_script_is( 'google-maps', 'registered' ) ) {
				$show = false;
			}

			if ( $show ) {
				echo sprintf( '<script src="https://maps.googleapis.com/maps/api/js?key=%s"
    async defer></script>', MultiParcels()->options->get( 'google_maps_api_key' ) );
			}
		}
	}

    function saving_order($post_id)
    {
        if (isset($_POST['multiparcels_shipping']) && isset($_POST['multiparcels_shipping']['submit']) && $_POST['multiparcels_shipping']['submit'] == 1) {
	        $post_type = get_post_type($post_id);

	        if ($post_type != 'shop_order') {
		        return;
	        }

            $data = array_map('sanitize_text_field', wp_unslash($_POST['multiparcels_shipping']));

            /** @var MP_Woocommerce_Order_Shipping $shipping */
            $shipping = new MP_Woocommerce_Order_Shipping();
            $shipping->ship_order($post_id, $data);
            exit;
        }

        if (isset($_POST['multiparcels_shipping']) && isset($_POST['multiparcels_shipping']['submit']) && $_POST['multiparcels_shipping']['submit'] == 3) {
	        $post_type = get_post_type($post_id);

	        if ($post_type != 'shop_order') {
		        return;
	        }

            $data = array_map('sanitize_text_field', wp_unslash($_POST['multiparcels_shipping']));

            /** @var MP_Woocommerce_Order_Shipping $shipping */
            $shipping = new MP_Woocommerce_Order_Shipping();
            $shipping->ship_order($post_id, $data, true, true);
            exit;
        }

        if (isset($_POST['multiparcels_shipping']) && isset($_POST['multiparcels_shipping']['submit']) && $_POST['multiparcels_shipping']['submit'] == 'reset') {
	        $post_type = get_post_type($post_id);

	        if ($post_type != 'shop_order') {
		        return;
	        }

            /** @var MP_Woocommerce_Order_Shipping $shipping */
            $shipping = new MP_Woocommerce_Order_Shipping();
            $shipping->reset($post_id);
        }

        if (isset($_POST['multiparcels_shipping']) && isset($_POST['multiparcels_shipping']['submit']) && $_POST['multiparcels_shipping']['submit'] == 'reset-and-change-status') {
	        $post_type = get_post_type($post_id);

	        if ($post_type != 'shop_order') {
		        return;
	        }

            /** @var MP_Woocommerce_Order_Shipping $shipping */
            $shipping = new MP_Woocommerce_Order_Shipping();
            $shipping->reset($post_id, true, true);
        }
    }

    function saving_order_for_pickup_location($post_id) {
        if ( isset( $_POST['multiparcels_location'] ) ) {
            $post_type = get_post_type( $post_id );

            if ( $post_type != 'shop_order' ) {
                return;
            }

            $data = sanitize_text_field( $_POST['multiparcels_location'] );

            if ( $data ) {
                update_post_meta( $post_id, WC_MP_Pickup_Point_Shipping_Method::INPUT_NAME, $data );
            }
        }
    }

    /**
     * @param WC_Order $order
     */
    public function woocommerce_admin_order_data_after_shipping_address($order)
    {
        $shipping = new MP_Woocommerce_Order_Shipping();
        $order_id = $order->get_id();

        $shipping->show_after_shipping_address_info($order_id);
    }

    function add_shipping_meta_box_to_order()
    {
        /** @var WP_Post $post */
        global $post;

        $screens = ['shop_order'];

        foreach ($screens as $screen) {
	        if ( $post && $post->post_status != 'auto-draft' ) { // not creating new order
		        add_meta_box(
			        'multiparcels-shipping-box',
			        __( 'MultiParcels Platform', 'multiparcels-shipping-for-woocommerce' ),
			        [ $this, 'order_meta_box_content' ],
			        $screen
		        );
	        }
        }
    }

    function add_pickup_location_meta_box_to_order() {
        /** @var WP_Post $post */
        global $post;

        $screens = ['shop_order'];

        foreach ($screens as $screen) {

            $pickup_delivery = MultiParcels()->locations->is_delivery_to_pickup_point($post->ID, true);

            if ( $pickup_delivery ) {
                // Terrible, right? :/
                function enqueue_select2_jquery() {
                    wp_register_style( 'select2css', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/css/select2.css', false, '1.0', 'all' );
                    wp_register_script( 'select2', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/js/select2.js', array( 'jquery' ), '1.0', true );
                    wp_enqueue_style( 'select2css' );
                    wp_enqueue_script( 'select2' );
                }
                add_action( 'admin_enqueue_scripts', 'enqueue_select2_jquery' );

                add_meta_box(
                    'multiparcels-shipping-pickup-point-box',
                    __( 'Pickup Point', 'multiparcels-shipping-for-woocommerce' ),
                    [ $this, 'order_pickup_point_box_content' ],
                    $screen
                );
            }
        }
    }

	/**
	 * @param WP_Post $post
	 */
    function order_pickup_point_box_content($post) {
        $order_id     = $post->ID;
        $order        = wc_get_order($order_id);
        $order_meta   = get_post_meta($order_id);
        $courier      = MultiParcels()->carriers->extract_from_method($order);
        $country_code = null;

        if (array_key_exists('_billing_country', $order_meta)) {
            $country_code = sanitize_text_field($order_meta['_billing_country'][0]);
        }

        if (array_key_exists('_shipping_country', $order_meta)) {
            $country_code = sanitize_text_field($order_meta['_shipping_country'][0]);
        }

        $type = null;

        if (MultiParcels()->locations->is_delivery_to_latvian_post_office($order)) {
           $type = 'post_office';
        }

        $locations = MultiParcels()->locations->all( $courier, $country_code, $type );

        if ($type != 'post_office') {
            // remove post_offices from terminal search
            foreach ($locations as $key => $location) {
                if ($location['type'] == 'post_office') {
                    unset($locations[$key]);
                }
            }
        }

	    $selected_location = MultiParcels()->locations->get_location_for_order($order);

	    $location_error = false;

        $location_identifier = get_post_meta($order->get_id(), 'multiparcels_location_identifier', true);
        if ($location_identifier && $selected_location == null) {
            $location_error = true;
        }
        $output = '';

        if ($location_error) {
            $output .= "<span style='color:red'>";
            $output .= sprintf(__('Pickup location is selected(%s) but it was not found. Try updating the pickup location list or select a new one.',
                'multiparcels-shipping-for-woocommerce'), $location_identifier);
            $output .= "</span>";
            $output .= "<br/>";
        }

	    $output .= "<select name='multiparcels_location' id='multiparcels-pickup-point-selector'>";
	    $output .= "<option value=''>-</option>";

	    foreach ( $locations as $location ) {
	        $is_selected = '';

		    if ( $selected_location && $selected_location['identifier'] == $location['identifier'] ) {
			    $is_selected = 'selected';
		    }

		    $output .= sprintf('<option value="%s" %s>', $location['identifier'],  $is_selected);
		    $output .= $location['name'] . '  - ';
		    $output .= esc_html( sprintf( "%s, %s, %s", $location['address'], $location['city'],
			    $location['postal_code'] ) );
		    $output .= '</option>';
	    }
	    $output .= '</select>';

	    $output .= sprintf( ' <button type="submit" class="button button-primary">%s</button>',
		    __( 'Save', 'multiparcels-shipping-for-woocommerce' ) );

	    echo $output;

	    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#multiparcels-pickup-point-selector').select2();
            });
        </script>

	    <?php
    }

    /**
     * @param WP_Post $post
     */
    function order_meta_box_content($post)
    {
        /** @var MP_Woocommerce_Order_Shipping $shipping */
        $shipping = new MP_Woocommerce_Order_Shipping();
        $shipping->show($post->ID);
    }

    /**
     * @param string $column
     */
    function add_content_to_column($column)
    {
        global $post;

        if ($column === 'multiparcels-shipping') {
            $order_id = $post->ID;

            $order = wc_get_order($post);

            $is_confirmed = (bool)get_post_meta($order_id, MP_Woocommerce_Order_Shipping::CONFIRMED_KEY, true);
            $label_link   = get_post_meta($order_id, MP_Woocommerce_Order_Shipping::LABEL_LINK_KEY, true);

            if ($order->has_status(['processing', 'completed'])) {
                if ( ! $is_confirmed && $order->has_status(['processing'])) {
                    echo sprintf('<a class="button button-primary" href="%s">%s</a>',
                        esc_attr(admin_url('post.php?action=edit&post=' . $order_id) . '#multiparcels-shipping-box'),
                        __('Dispatch order', 'multiparcels-shipping-for-woocommerce'));
                } else {
                    if ($label_link) {
                        $label_link = esc_attr(wp_upload_dir()['baseurl'] . '/' . $label_link);

                        echo sprintf('<a class="button" href="%s" target="_blank">%s</a>',
                            $label_link,
                            __('Label', 'multiparcels-shipping-for-woocommerce'));
                    } elseif ( $is_confirmed ) {
                        echo sprintf("<span style='color: green;'>%s</span>", __('Order dispatched', 'multiparcels-shipping-for-woocommerce'));
                    }
                }
            }
        }
    }

    /**
     * @param array $columns
     *
     * @return array
     */
    function add_column_to_orders($columns)
    {
        $new_columns = [];

        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;

            if ('order_total' === $column_name) {
                $new_columns['multiparcels-shipping'] = __('MultiParcels', 'multiparcels-shipping-for-woocommerce');
            }
        }

        return $new_columns;
    }

    /**
     * @param $methods
     *
     * @return mixed
     */
    static function register_shipping_methods($methods)
    {
        if (class_exists('WC_MP_Shipping_Method')) {
            foreach (MultiParcels()->options->get('carriers', true) as $code => $settings) {
                $enabled = MultiParcels()->options->getBool($code);
                if ($enabled) {
                    if ($settings['has_courier_service']) {
                        $code_prepared = ucwords($code, '_');
                        $class_name     = 'WC_MP_' . $code_prepared . '_Courier_Shipping';

                        if (class_exists($class_name)) {
                            $key           = 'multiparcels_'.$code.'_'.WC_MP_Shipping_Method::SUFFIX_COURIER;
                            $methods[$key] = MultiParcels()->shipping_methods[$key];
                        }
                    }

                    if ($settings['has_pickup_points'] || $settings['has_terminals']) {
                        $key           = 'multiparcels_' . $code . '_' . WC_MP_Shipping_Method::SUFFIX_PICKUP_POINT;
                        $methods[$key] = MultiParcels()->shipping_methods[$key];
                    }

                    if ($settings['has_terminals'] && $code == 'dpd') {
                        $key           = 'multiparcels_' . $code . '_' . WC_MP_Shipping_Method::SUFFIX_TERMINAL;
                        $methods[$key] = MultiParcels()->shipping_methods[$key];
                    }

                    if (array_key_exists('has_post_office_delivery',
                            $settings) && $settings['has_post_office_delivery']) {
                        $key           = 'multiparcels_' . $code . '_' . WC_MP_Shipping_Method::SUFFIX_POST;
                        $methods[$key] = MultiParcels()->shipping_methods[$key];
                    }

                    if (array_key_exists('has_bus_station_delivery',
                            $settings) && $settings['has_bus_station_delivery']) {
                        $key           = 'multiparcels_' . $code . '_' . WC_MP_Shipping_Method::SUFFIX_BUS_STATION;
                        $methods[$key] = MultiParcels()->shipping_methods[$key];
                    }
                }
            }
        }

        return $methods;
    }

    static public function shipping_init()
    {
        if (class_exists('WC_MP_Shipping_Method')) {
            foreach (MultiParcels()->options->get('carriers', true) as $code => $settings) {
                $enabled = MultiParcels()->options->getBool($code);

                if ($enabled) {
                    $code_prepared = ucwords($code, '_');

                    if ($settings['has_courier_service']) {
                        $class_name     = 'WC_MP_' . $code_prepared . '_Courier_Shipping';

                        if (class_exists($class_name)) {
                            $shipping_class = new $class_name();

                            $key                                   = $shipping_class->id;
                            MultiParcels()->shipping_methods[$key] = $shipping_class;
                        }
                    }

                    if ($settings['has_pickup_points'] || $settings['has_terminals']) {
                        $class_name     = 'WC_MP_' . $code_prepared . '_Pickup_Point_Shipping';

                        if (class_exists($class_name)) {
                            $shipping_class = new $class_name();

                            $key = $shipping_class->id;
                            MultiParcels()->shipping_methods[$key] = $shipping_class;
                        }
                    }

	                if ( $settings['has_terminals'] && $code == 'dpd') {
                        $class_name = 'WC_MP_'.$code_prepared.'_Terminal_Shipping';

                        if (class_exists($class_name)) {
                            $shipping_class = new $class_name();

                            $key = $shipping_class->id;
                            MultiParcels()->shipping_methods[$key] = $shipping_class;
                        }
                    }

                    if (array_key_exists('has_post_office_delivery',
                            $settings) && $settings['has_post_office_delivery']) {
                        $class_name = 'WC_MP_'.$code_prepared.'_Post_Shipping';

                        if (class_exists($class_name)) {
                            $shipping_class = new $class_name();

                            $key = $shipping_class->id;
                            MultiParcels()->shipping_methods[$key] = $shipping_class;
                        }
                    }

                    if (array_key_exists('has_bus_station_delivery',
                            $settings) && $settings['has_bus_station_delivery']) {
                        $class_name = 'WC_MP_'.$code_prepared.'_Bus_Station_Shipping';

                        if (class_exists($class_name)) {
                            $shipping_class = new $class_name();

                            $key = $shipping_class->id;
                            MultiParcels()->shipping_methods[$key] = $shipping_class;
                        }
                    }
                }
            }
        }
    }

    private function add_aerocheckout_actions()
    {
        $callback = function ($step, $section_index, $section) {
            if (is_array($section) && array_key_exists('html_fields',
                    $section)) {
                $fields = $section['html_fields'];

                if (is_array($fields) && array_key_exists('shipping_calculator',
                        $fields)) {
                    MultiParcels()->woocommerce->pickup_location_selector_display();
                }
            }
        };

        $actions  = [
            'wfacp_template_section_0_single_step_end',
            'wfacp_template_section_1_single_step_end',
            'wfacp_template_section_2_single_step_end',
            'wfacp_template_section_0_two_step_end',
            'wfacp_template_section_1_two_step_end',
        ];

        foreach ($actions as $action) {
            add_action($action, $callback, 10, 3);
        }
    }
}

return new MP_Woocommerce();
