<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Woocommerce_Order_Shipping
 *
 * Enabling this does not actually make these features work.
 * All of the features here are actually doing all the work on the API, sorry :(
 */
class MP_Woocommerce_Order_Shipping
{
    /**
     * Package sizes
     */
    const PACKAGE_SIZE_EXTRA_SMALL = 'extra-small';
    const PACKAGE_SIZE_SMALL = 'small';
    const PACKAGE_SIZE_MEDIUM = 'medium';
    const PACKAGE_SIZE_LARGE = 'large';
    const PACKAGE_SIZE_EXTRA_LARGE = 'extra-large';

    const PACKAGE_SIZES = [
        self::PACKAGE_SIZE_EXTRA_SMALL,
        self::PACKAGE_SIZE_SMALL,
        self::PACKAGE_SIZE_MEDIUM,
        self::PACKAGE_SIZE_LARGE,
        self::PACKAGE_SIZE_EXTRA_LARGE,
    ];

    /**
     * Shipping methods
     */
    const SHIPPING_HANDS_TO_HANDS = 'hands_to_hands';
    const SHIPPING_HANDS_TO_TERMINAL = 'hands_to_terminal';
    const SHIPPING_TERMINAL_TO_TERMINAL = 'terminal_to_terminal';
    const SHIPPING_TERMINAL_TO_HANDS = 'terminal_to_hands';
    const SHIPPING_HANDS_TO_POST_OFFICE = 'hands_to_post_office';
    const SHIPPING_POST_OFFICE_TO_POST_OFFICE = 'post_office_to_post_office';
    const SHIPPING_HANDS_TO_BUS_STATION = 'hands_to_bus_station';
    const SHIPPING_BUS_STATION_TO_BUS_STATION = 'bus_station_to_bus_station';
    const SHIPPING_BUS_STATION_TO_HANDS = 'bus_station_to_hands';


    const SHIPPING_METHODS = [
        self::SHIPPING_HANDS_TO_HANDS,
        self::SHIPPING_HANDS_TO_TERMINAL,
        self::SHIPPING_TERMINAL_TO_TERMINAL,
        self::SHIPPING_TERMINAL_TO_HANDS,
        self::SHIPPING_HANDS_TO_POST_OFFICE,
        self::SHIPPING_POST_OFFICE_TO_POST_OFFICE,
        self::SHIPPING_HANDS_TO_BUS_STATION,
        self::SHIPPING_BUS_STATION_TO_BUS_STATION,
        self::SHIPPING_BUS_STATION_TO_HANDS,
    ];

    /**
     * Other constants
     */
    const CONFIRMED_KEY = 'multiparcels_confirmed';
    const EXTERNAL_ID_KEY = 'multiparcels_external_id';
    const TRACKING_CODE_KEY = 'multiparcels_tracking_code';
    const TRACKING_LINK_KEY = 'multiparcels_tracking_link';
    const LABEL_LINK_KEY = 'multiparcels_label_link';
    const ERRORS_KEY = 'multiparcels_errors';
    const SERVICES_HISTORY_KEY = 'multiparcels_services_history';
    const PACKAGES_COUNT_KEY = 'multiparcels_packages_count';
    const AUTOMATIC_CONFIRMATION_FAILED = 'multiparcels_automatic_confirmation_failed';

    /** @var WC_Order */
    private $order;

    /** @var array */
    private $order_meta;

    /** @var array|null */
    private $location;

    /** @var string */
    private $courier;

    /** @var int|null */
    private $order_id;

    /** @var array */
    private $products = [];

    /** @var float */
    private $total_weight = 0;

    /** @var null|string */
    private $delivery_type = null;

    /** @var int */
    private $packages = 1;

    /** @var int */
    private $total_product_item_count = 0;

    /** @var int */
    private $items_per_package = 0;

    /** @var string[] */
    private $default_services = [];

    /** @var string */
    private $shipping_method;

    /** @var string */
    private $delivery_shipping_method;

    /** @var string */
    private $shipping_method_name;

    /** @var boolean $location_error has location identifier but location not found */
    private $location_error;

    /** @var null|string $location_identifier */
    private $location_identifier;

    /** @var null|string $preferred_pickup_type */
    private $preferred_pickup_type;

    /**
     * @param  int  $order_id
     * @param  bool  $redirect
     * @param  bool  $change_status
     */
    public function reset($order_id, $redirect = true, $change_status = false)
    {
        $this->load_order($order_id);

        if ($this->get_external_id()) {
            $delete_link = sprintf('shipments/%s', $this->get_external_id());
            MultiParcels()->api_client->request($delete_link, 'DELETE');
        }

        $this->set_confirmed(false);
        $this->set_external_id(null);
        $this->set_tracking_code(null);
        $this->set_label_link(null);
        $this->set_errors([]);

        if ($change_status) {
            $this->order->update_status('processing');
        }

        if ($redirect) {
            wp_redirect(admin_url('post.php?post='.$this->order_id.'&action=edit'));
            exit;
        }
    }


    /**
     * @param  int  $order_id
     */
    public function show($order_id)
    {
        $this->load_order($order_id);

        if (isset($_GET['multiparcels_debug'])) {
            $this->ship_order($order_id);
        }

        if (MultiParcels()->options->in_array('skip_methods_for_dispatching', $this->shipping_method)) {
            echo sprintf("<p>%s</p>",
                __('Shipping disabled due to your settings', 'multiparcels-shipping-for-woocommerce'));

            echo sprintf("<a href='%s'>%s</a>", MultiParcels()->settings_url().'#skip_dispatching_for_specific_methods', __('Settings', 'multiparcels-shipping-for-woocommerce'));
            return;
        }

        $this->display_status();
        echo str_repeat("<br/>", 2);

        $this->display_errors();

        if ( ! $this->is_confirmed()) {
            $sending_locations = MultiParcels()->options->get_sender_locations();

            if (count($sending_locations) == 0) {
                echo str_repeat("<br/>", 2);

                ?>
                <style>
                    .multiparcels-block-contents {
                        position: relative;
                        -webkit-filter: blur(2px);
                        filter: blur(2px);
                        padding: 15px;
                    }

                    .multiparcels-block-contents:before {
                        content: '';
                        display: block;
                        width: 100%;
                        height: 100%;
                        position: absolute;
                        top: 0;
                        right: 0;
                        bottom: 0;
                        left: 0;
                        background: black;
                        opacity: 0.5;
                    }
                </style>
                <?php

                echo "<div style='position: relative;'>";
                $this->display_sending_location_selector();

                echo "<div class='multiparcels-block-contents'>";
            }

            $this->display_package_count();
            echo str_repeat("<br/>", 2);

            $this->display_carrier_selection();
            echo str_repeat("<br/>", 2);

            $this->display_size();
            echo str_repeat("<br/>", 2);

            $this->display_shipping();
            echo str_repeat("<br/>", 2);

            $this->display_products();
            echo str_repeat("<br/>", 2);

            $this->display_services();
            echo str_repeat("<br/>", 2);

            if (count($sending_locations) == 0) {
                echo "<div>";
                echo "<div>";
            }


            $cod_service = false;

            // Probably from mass shipping
            if (count($_POST) == 0) {
                if (array_key_exists('_payment_method',
                        $this->order_meta) && $this->order_meta['_payment_method'][0] == 'cod') {
                    $_POST['service_cod'] = 1;
                }
            }

            foreach ($_POST as $key => $value) {
                if (substr($key, 0, 8) == 'service_') {
                    $service = substr($key, 8);

                    if ($service == 'cod') {
                        $cod_service = true;
                    }
                }
            }

            $change_order_status_to_completed = ! MultiParcels()->options->getBool('not_change_order_status_after_dispatch');

            if ($cod_service) {
                $change_order_status_to_completed = ! MultiParcels()->options->getBool('not_change_order_status_after_dispatch_cod');
            }

            if ($change_order_status_to_completed && $this->order->get_status() == 'completed') {
                ?>
                <div id="message" class="error inline">
                    <p>
                        <strong>
                            <?php
                            _e("This order is already completed. The buyer will not get an email with the tracking code if you dispatch an order when the status is \"Completed\"",
                                'multiparcels-shipping-for-woocommerce'); ?>
                        </strong>
                    </p>
                </div>
                <?php
            }

            $disabled = false;

            if ($this->location_error) {
                echo "<span style='color:red'>";
                echo sprintf(__('Pickup location is selected(%s) but it was not found. Try updating the pickup location list or select a new one.',
                    'multiparcels-shipping-for-woocommerce'), $this->location_identifier);
                echo "</span>";
                echo "<br/><br/>";

                $url = "post.php?post='.$this->order_id.'&action=edit&multiparcels_action=remove_pickup_point";
                echo sprintf("<a href='%s' class='button button-primary'>%s</a>", $url, __('Remove pickup point',
                    'multiparcels-shipping-for-woocommerce'));
                echo "<br/><br/>";

                $disabled = true;
            }

            ?>
            <input type="hidden" id="multiparcels-submit-field" name="multiparcels_shipping[submit]" value="0">
            <input id="multiparcels-submit" type="button" class="button button-hero button-primary" name="save"
                   value="<?php _e('Confirm', 'multiparcels-shipping-for-woocommerce'); ?>" <?php if($disabled) echo 'disabled'; ?>> <br><br>

            <div>
                <input id="multiparcels-submit-unique" type="button" class="button" name="save"
                       value="<?php _e('Confirm unique shipment', 'multiparcels-shipping-for-woocommerce'); ?>" <?php if($disabled) echo 'disabled'; ?>>

                <small style="line-height: 28px;">
                    <?php _e('This allows to create a dublicate shipment for the same order',
                        'multiparcels-shipping-for-woocommerce'); ?>
                </small>
            </div>

            <script>
                jQuery(document).ready(function ($) {
                    $('#multiparcels-submit').on('click', function () {
                        $("#multiparcels-submit-field").val(1);
                        $("#post").submit();
                    });
                    $('#multiparcels-submit-unique').on('click', function () {
                        $("#multiparcels-submit-field").val(3);
                        $("#post").submit();
                    });
                });
            </script>
            <?php
        } else {
            $this->display_services_history();

            ?>
            <input type="hidden" id="multiparcels-submit-field" name="multiparcels_shipping[submit]" value="0">

            <input id="multiparcels-reset-change-status" type="button" class="button button-primary" name="save"
                   value="<?php esc_attr_e("Reset and set status to \"Processing\"", 'multiparcels-shipping-for-woocommerce'); ?>">

            <input id="multiparcels-reset" type="button" class="button button-primary" name="save"
                   value="<?php _e('Reset and do not change status', 'multiparcels-shipping-for-woocommerce'); ?>">

            <script>
                jQuery(document).ready(function ($) {
                    $('#multiparcels-reset').on('click', function () {
                        $("#multiparcels-submit-field").val('reset');
                        $("#post").submit();
                    });

                    $('#multiparcels-reset-change-status').on('click', function () {
                        $("#multiparcels-submit-field").val('reset-and-change-status');
                        $("#post").submit();
                    });
                });
            </script>
            <?php
        }
    }

    /**
     * @return bool
     */
    public function is_confirmed()
    {
        if (array_key_exists(self::CONFIRMED_KEY, $this->order_meta)) {
            $confirmed = $this->order_meta[self::CONFIRMED_KEY][0];
            if ($confirmed) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string|null
     */
    public function get_external_id()
    {
        if (array_key_exists(self::EXTERNAL_ID_KEY, $this->order_meta)) {
            $id = $this->order_meta[self::EXTERNAL_ID_KEY][0];
            if ($id) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  bool  $bool
     */
    public function set_confirmed($bool)
    {
        update_post_meta($this->order_id, self::CONFIRMED_KEY, (int) $bool);
    }

    /**
     * @param  array  $history
     */
    public function set_services_history($history)
    {
        update_post_meta($this->order_id, self::SERVICES_HISTORY_KEY, json_encode($history));
    }


    public function get_services_history()
    {
        if (array_key_exists(self::SERVICES_HISTORY_KEY, $this->order_meta)) {
            return json_decode($this->order_meta[self::SERVICES_HISTORY_KEY][0], true);
        }

        return null;
    }

    /**
     * @param  int  $packages
     */
    public function set_packages_count($packages)
    {
        update_post_meta($this->order_id, self::PACKAGES_COUNT_KEY, $packages);
    }


    public function get_packages_count()
    {
        if (array_key_exists(self::PACKAGES_COUNT_KEY, $this->order_meta)) {
            return json_decode($this->order_meta[self::PACKAGES_COUNT_KEY][0], true);
        }

        return 1;
    }

    /**
     * @param  array  $errors
     */
    public function set_errors($errors)
    {
        update_post_meta($this->order_id, self::ERRORS_KEY, json_encode($errors));
    }

    /**
     * @return bool
     */
    public function get_label_link()
    {
        if (array_key_exists(self::LABEL_LINK_KEY, $this->order_meta)) {
            $link = $this->order_meta[self::LABEL_LINK_KEY][0];
            if ($link) {
                return $link;
            }
        }

        return false;
    }

    /**
     * @param  string  $link
     */
    public function set_label_link($link)
    {
        update_post_meta($this->order_id, self::LABEL_LINK_KEY, $link);
    }

    /**
     * @param  string  $external_id
     */
    public function set_external_id($external_id)
    {
        update_post_meta($this->order_id, self::EXTERNAL_ID_KEY, $external_id);
    }

    public function get_tracking_code()
    {
        if (array_key_exists(self::TRACKING_CODE_KEY, $this->order_meta)) {
            $tracking_code = $this->order_meta[self::TRACKING_CODE_KEY][0];
            if ($tracking_code) {
                return $tracking_code;
            }
        }

        return null;
    }

    public function get_tracking_link()
    {
        if (array_key_exists(self::TRACKING_LINK_KEY, $this->order_meta)) {
            $tracking_link = $this->order_meta[self::TRACKING_LINK_KEY][0];
            if ($tracking_link) {
                return $tracking_link;
            }
        }

        return null;
    }

    /**
     * @param  string  $tracking_code
     */
    public function set_tracking_code($tracking_code)
    {
        update_post_meta($this->order_id, self::TRACKING_CODE_KEY, $tracking_code);
    }

    /**
     * @param  string  $tracking_link
     */
    public function set_tracking_link($tracking_link)
    {
        update_post_meta($this->order_id, self::TRACKING_LINK_KEY, $tracking_link);
    }

    /**
     * @param $bool
     *
     * @return string
     */
    private function bool_text($bool)
    {
        if ($bool) {
            return __('Yes', 'multiparcels-shipping-for-woocommerce');
        }

        return __('No', 'multiparcels-shipping-for-woocommerce');
    }

    private function display_status()
    {
        if ($this->is_confirmed()) {
            $external_ids = explode(',', $this->get_external_id());

            echo sprintf("<strong>%s:</strong> %s",
                __('Confirmed', 'multiparcels-shipping-for-woocommerce'),
                esc_attr($this->bool_text($this->is_confirmed()))
            );

            echo '( ';
            foreach ($external_ids as $external_id) {
                echo sprintf("<a href='%s' target='_blank'>%s</a> ",
                    esc_attr('https://platform.multiparcels.com/shipments/'.$external_id),
                    __('View on Platform', 'multiparcels-shipping-for-woocommerce')
                );
            }

            echo ')<br/>';

            echo sprintf("<strong>%s:</strong> %s<br/>",
                __('Tracking code', 'multiparcels-shipping-for-woocommerce'),
                esc_html($this->get_tracking_code()));

            $download_labels_enabled = ! MultiParcels()->options->getBool('disable_label_downloading');

            if ($download_labels_enabled) {
                if ($label_link = $this->get_label_link()) {
                    $label_link = wp_upload_dir()['baseurl'].'/'.$label_link;

                    echo sprintf("<strong>%s:</strong> <a href='%s' target='_blank'>%s</a><br/>",
                        __('Label', 'multiparcels-shipping-for-woocommerce'),
                        esc_attr($label_link),
                        _x('View', 'label', 'multiparcels-shipping-for-woocommerce')
                    );
                } else {
                    echo sprintf("<strong>%s:</strong> %s<br/>",
                        __('Label', 'multiparcels-shipping-for-woocommerce'),
                        __('No', 'multiparcels-shipping-for-woocommerce'));
                }
            } else {
                echo sprintf("<strong>%s:</strong> %s<br/>",
                    __('Label', 'multiparcels-shipping-for-woocommerce'),
                    __('Feature disabled', 'multiparcels-shipping-for-woocommerce'));
            }

        } else {
            echo sprintf("<strong>%s:</strong> %s<br/>",
                __('Confirmed', 'multiparcels-shipping-for-woocommerce'),
                esc_html($this->bool_text($this->is_confirmed())));
        }

        echo sprintf("<strong>%s:</strong> %s<br/>", __('Items', 'multiparcels-shipping-for-woocommerce'),
            $this->total_product_item_count);

        $items_per_package = __('Unlimited', 'multiparcels-shipping-for-woocommerce');
        if ($this->items_per_package > 0) {
            $items_per_package = $this->items_per_package;
        }

        echo sprintf("<strong>%s:</strong> %s<br/>",
            __('Items per package', 'multiparcels-shipping-for-woocommerce'), $items_per_package);

        if ($this->is_confirmed()) {
            $packages = $this->packages;

            if ($customPackages = $this->get_packages_count()) {
                $packages = $customPackages;
            }

            echo sprintf("<strong>%s:</strong> %s<br/>", __('Packages', 'multiparcels-shipping-for-woocommerce'),
                $packages);
        }

        echo sprintf("<strong>%s:</strong> %s%s<br/>", __('Total weight', 'multiparcels-shipping-for-woocommerce'),
            $this->total_weight, get_option('woocommerce_weight_unit'));
    }

    public function show_after_shipping_address_info($order_id)
    {
        $this->load_order($order_id);

        if ( ! $this->is_confirmed()) {
            echo sprintf('<div style="clear: both;"><a class="button button-primary" href="%s">%s</a></div>',
                esc_attr('#multiparcels-shipping-box'),
                __('Dispatch order', 'multiparcels-shipping-for-woocommerce'));
        }
    }

    private function display_sending_location_selector()
    {
        echo sprintf(
            "<a href='%s' style='%s'>%s</a>",
            MultiParcels()->settings_url(['tab' => MP_Admin::TAB_SENDER_DETAILS]),
            'color: white;
    display: inline-block;
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    margin: auto;
    z-index: 99999;
    text-align: center;
    height: 30px;
    font-weight: bold;
    font-size: 28px;',
            __('Please add your sending location first', 'multiparcels-shipping-for-woocommerce')
        );
    }

    private function display_package_count()
    {
        echo sprintf("<strong>%s</strong><br><br>", __('Packages quantity', 'multiparcels-shipping-for-woocommerce'));

        ?>
        <input type="number" step="1" min="1" max="99" name="multiparcels_shipping[package_count]"
               value="<?php echo $this->packages; ?>" required>
        <?php
    }

    private function display_carrier_selection()
    {
        echo sprintf("<strong>%s</strong><br><br>", __('Carrier selection', 'multiparcels-shipping-for-woocommerce'));
        $selected_carrier         = $this->courier;
        $selected_delivery_method = null;
        $receiver_country = null;

        if (array_key_exists('_billing_country', $this->order_meta)) {
            $receiver_country = sanitize_text_field($this->order_meta['_billing_country'][0]);
        }

        if (array_key_exists('_shipping_country', $this->order_meta)) {
            $receiver_country = sanitize_text_field($this->order_meta['_shipping_country'][0]);
        }

        $method_name       = MultiParcels()->carriers->method_name($this->order);
        $carrier_selection = MultiParcels()->carrier_selections->get($receiver_country, $method_name);

        if ($carrier_selection) {
            $selected_carrier         = $carrier_selection['carrier'];
            $selected_delivery_method = $carrier_selection['method'];
        }

        if (!$selected_delivery_method && $this->delivery_shipping_method) {
            $selected_delivery_method = $this->delivery_shipping_method;
        }

        ?>
        <select name="multiparcels_shipping[carrier]" id="carrier-selection">
            <?php
            foreach (MultiParcels()->carriers->all() as $carrier) {
                $selected = '';
                $enabled  = MultiParcels()->options->getBool($carrier['carrier_code']);

                if ($carrier['carrier_code'] == $selected_carrier) {
                    $selected = ' selected';
                }

                if ($enabled) {
                    echo sprintf("<option value='%s'%s>%s</option>", $carrier['carrier_code'], $selected,
                        $carrier['name']);
                }
            }
            ?>
        </select>

        <?php

        foreach (MultiParcels()->carriers->all() as $carrier) {
            $enabled = MultiParcels()->options->getBool($carrier['carrier_code']);

            if ($enabled && isset($carrier['delivery_methods']) && count($carrier['delivery_methods']) > 1) {
                ?>
                <select name="multiparcels_shipping[delivery_method]" class="carrier-delivery-method-selection"
                        data-carrier="<?php echo $carrier['carrier_code']; ?>" style="display: none;">
                    <?php
                    foreach ($carrier['delivery_methods'] as $delivery_method) {
                        $selected = '';
                        if ($delivery_method == $selected_delivery_method) {
                            $selected = ' selected';
                        }
                        echo sprintf("<option value='%s'%s>%s</option>", $delivery_method,
                            $selected,
                            MultiParcels()->carriers->delivery_method_name($delivery_method));
                    }
                    ?>
                </select>
                <?php
            }
        }

        echo "<br/>";
        echo "<div style='margin-top: 8px;'>";
        echo sprintf("<input name='multiparcels_shipping[remember_delivery_method]' type='checkbox' value='1'/>");
        echo __('Remember this selection for future orders', 'multiparcels-shipping-for-woocommerce');
        echo sprintf(" (%s - %s)<br/>", $receiver_country, $method_name);
        echo "</div>";

        ?>
        <script>
            jQuery(document).ready(function ($) {
                $('#carrier-selection').on('change', function () {
                    $(".carrier-delivery-method-selection").hide();
                    $(".carrier-delivery-method-selection").prop('disabled', true);

                    $(".carrier-delivery-method-selection[data-carrier='" + $(this).val() + "']").show();
                    $(".carrier-delivery-method-selection[data-carrier='" + $(this).val() + "']").prop('disabled', false);

                    return false;
                }).trigger('change');
            });
        </script>
        <?php
    }

    private function display_size()
    {
        $sizes = [
            self::PACKAGE_SIZE_EXTRA_SMALL,
            self::PACKAGE_SIZE_SMALL,
            self::PACKAGE_SIZE_MEDIUM,
            self::PACKAGE_SIZE_LARGE,
            self::PACKAGE_SIZE_EXTRA_LARGE,
        ];

        echo sprintf("<strong>%s</strong><br><br>", __('Package size', 'multiparcels-shipping-for-woocommerce'));

        echo "<div style='display: flex;'>";
        foreach ($sizes as $size) {
            ?>
            <div style="margin-right: 30px;">
                <input type="radio" name="multiparcels_shipping[package_sizes]"
                       value="<?php echo $size; ?>" <?php echo checked($size,
                    MultiParcels()->options->get('default_package_size', null, 'small')); ?>><br>
                <img class="check_closest"
                     src='<?php echo MultiParcels()->public_plugin_url('images/boxes/'.$size.'.png'); ?>'
                     style="width: 150px;cursor: pointer"/>
            </div>
            <?php
        }
        echo "</div>";

        ?>
        <script>
            jQuery(document).ready(function ($) {
                $('.check_closest').on('click', function () {
                    $(this).parent().find('input').prop('checked', true);

                    return false;
                });
            });
        </script>
        <?php
    }

    private function display_shipping()
    {
        $types = [
            self::SHIPPING_HANDS_TO_HANDS,
            self::SHIPPING_TERMINAL_TO_HANDS,
        ];

        $carrier_settings = MultiParcels()->carriers->get($this->courier);

        if (array_key_exists('has_post_office_delivery',
                $carrier_settings) && $carrier_settings['has_post_office_delivery'] == true) {
            $types = [
                self::SHIPPING_HANDS_TO_HANDS,
                self::SHIPPING_TERMINAL_TO_HANDS,
                self::SHIPPING_HANDS_TO_POST_OFFICE,
            ];
        }

        $selected = $types[0];

        if ($this->location != null) {
            $selected = self::SHIPPING_HANDS_TO_TERMINAL;

            $types = [
                self::SHIPPING_HANDS_TO_HANDS,
                self::SHIPPING_TERMINAL_TO_HANDS,
                self::SHIPPING_HANDS_TO_TERMINAL,
                self::SHIPPING_TERMINAL_TO_TERMINAL,
            ];
        }

        $preferred_pickup_type = MultiParcels()->options->get('preferred_pickup_type', false, 'hands');

        if ($this->preferred_pickup_type) {
            // override the type from shipping method
            $preferred_pickup_type = $this->preferred_pickup_type;
        }

        if ($preferred_pickup_type != 'hands') {
            if ($this->location != null) {
                $selected = self::SHIPPING_TERMINAL_TO_TERMINAL;
            } else {
                $selected = self::SHIPPING_TERMINAL_TO_HANDS;
            }
        }

        if ($this->courier == WC_MP_Shipping_Helper::CARRIER_LP_EXPRESS && $this->delivery_type == WC_MP_Shipping_Method::SUFFIX_POST) {
            $selected = self::SHIPPING_HANDS_TO_POST_OFFICE;

            $types = [
                self::SHIPPING_HANDS_TO_POST_OFFICE,
            ];
        }

        if ($this->courier == WC_MP_Shipping_Helper::CARRIER_POST_LT) {
            $types = [
                self::SHIPPING_POST_OFFICE_TO_POST_OFFICE,
            ];

            $selected = self::SHIPPING_POST_OFFICE_TO_POST_OFFICE;
        }

        if ($this->courier == WC_MP_Shipping_Helper::CARRIER_SIUNTOS_AUTOBUSAIS) {
            $types = [
                self::SHIPPING_HANDS_TO_HANDS,
                self::SHIPPING_HANDS_TO_BUS_STATION,
                self::SHIPPING_BUS_STATION_TO_BUS_STATION,
                self::SHIPPING_BUS_STATION_TO_HANDS,
            ];

            $selected = self::SHIPPING_HANDS_TO_HANDS;

            if ($this->delivery_type == WC_MP_Shipping_Method::SUFFIX_BUS_STATION) {
                $selected = self::SHIPPING_HANDS_TO_BUS_STATION;
            }

            if ($preferred_pickup_type == 'bus_station') {
                $selected = self::SHIPPING_BUS_STATION_TO_HANDS;

                if ($this->delivery_type == WC_MP_Shipping_Method::SUFFIX_BUS_STATION) {
                    $selected = self::SHIPPING_BUS_STATION_TO_BUS_STATION;
                }
            }
        }

        echo sprintf("<strong>%s</strong><br><br>", __('Shipping method', 'multiparcels-shipping-for-woocommerce'));
        echo "<div style='display: flex;'>";
        foreach ($types as $type) {
            $title = $this->shipping_type_title($type);
            ?>

            <div style="margin-right: 30px;text-align: center;">
                <input type="radio" name="multiparcels_shipping[shipping]"
                       value="<?php echo $type; ?>" <?php echo checked($type,
                    $selected); ?>> <?php echo $title; ?> <br>
                <img src='<?php echo MultiParcels()->public_plugin_url('images/shipping_methods/'.$type.'.png'); ?>'
                     style="width: 150px;cursor: pointer;margin-top: 10px;" class="check_closest"/>
            </div>
            <?php
        }
        echo "</div>";
    }

    /**
     * @param  int  $order_id
     * @param  array  $post
     * @param  bool  $redirect
     * @param  bool  $randomIdentifier
     */
    public function ship_order($order_id, $post = [], $redirect = true, $randomIdentifier = false)
    {
        $this->load_order($order_id);

        // Probably from mass shipping
        if (count($post) == 0) {
            $type = self::SHIPPING_HANDS_TO_HANDS;

            if ($this->location != null) {
                $type = self::SHIPPING_HANDS_TO_TERMINAL;
            }

            $preferred_pickup_type = MultiParcels()->options->get('preferred_pickup_type', false, 'hands');

            if ($this->preferred_pickup_type) {
                // override the type from shipping method
                $preferred_pickup_type = $this->preferred_pickup_type;
            }

            if ($preferred_pickup_type != 'hands') {
                if ($this->location != null) {
                    $type = self::SHIPPING_TERMINAL_TO_TERMINAL;
                } else {
                    $type = self::SHIPPING_TERMINAL_TO_HANDS;
                }
            }

            if ($this->courier == WC_MP_Shipping_Helper::CARRIER_LP_EXPRESS && $this->delivery_type == WC_MP_Shipping_Method::SUFFIX_POST) {
                $type = self::SHIPPING_HANDS_TO_POST_OFFICE;
            }

            if ($this->courier == WC_MP_Shipping_Helper::CARRIER_POST_LT) {
                $type = self::SHIPPING_POST_OFFICE_TO_POST_OFFICE;
            }

            if ($this->courier == WC_MP_Shipping_Helper::CARRIER_SIUNTOS_AUTOBUSAIS) {
                $type = self::SHIPPING_HANDS_TO_HANDS;

                if ($this->delivery_type == WC_MP_Shipping_Method::SUFFIX_BUS_STATION) {
                    $type = self::SHIPPING_HANDS_TO_BUS_STATION;
                }

                if ($preferred_pickup_type == 'bus_station') {
                    $type = self::SHIPPING_BUS_STATION_TO_HANDS;

                    if ($this->delivery_type == WC_MP_Shipping_Method::SUFFIX_BUS_STATION) {
                        $type = self::SHIPPING_BUS_STATION_TO_BUS_STATION;
                    }
                }
            }

            $post['shipping']      = $type;
            $post['package_sizes'] = MultiParcels()->options->get('default_package_size', null, 'small');

            if ( ! (array_key_exists('_shipping_company',
                        $this->order_meta) && $this->order_meta['_shipping_company'][0]) && ! (array_key_exists('_billing_company',
                        $this->order_meta) && $this->order_meta['_billing_company'][0])) {
                $post['service_b2c'] = 1;
            }

            if (array_key_exists('_payment_method',
                    $this->order_meta) && $this->order_meta['_payment_method'][0] == 'cod') {
                $post['service_cod'] = 1;
            }

            foreach ($this->default_services as $service) {
                if (array_search($service, $this->default_services) !== false) {
                    $post['service_'.$service] = 1;
                }
            }
        }

        if ($this->location_error) {
            $locationErrors = [
                'pickup_location_not_found' => [
                    [
                        'text' => sprintf(__('Pickup location is selected(%s) but it was not found. Try updating the pickup location list or select a new one.',
                            'multiparcels-shipping-for-woocommerce'), 123),
                        'rule' => 'required',
                    ],
                ],
            ];

            $this->set_errors($locationErrors);

            if ($redirect) {
                $query = http_build_query([
                    'errors' => $locationErrors,
                ]);

                $url = 'post.php?post='.$this->order_id.'&action=edit&'.$query.'#multiparcels-shipping-box';

                wp_redirect(admin_url($url));
                exit;
            }

            return;
        }

        if (array_key_exists('package_count', $post)) {
            $this->packages = (int) $post['package_count'];
        }

	    $methods  = $this->parse_methods( $post['shipping'] );
	    $receiver = $this->get_receiver();
	    /** @var WC_DateTime $created_at */
	    $created_at = $this->order->get_date_created()->date( 'Y-m-d H:i:s' );
	    $currency   = $this->order_meta['_order_currency'][0];

        if (array_key_exists('remember_delivery_method', $post) && $post['remember_delivery_method'] == 1) {
            $delivery_method = null;

            if (array_key_exists('delivery_method', $post)) {
                $delivery_method = $post['delivery_method'];
            }

            MultiParcels()->carrier_selections->create($receiver['country_code'], $this->shipping_method_name,
                $post['carrier'], $delivery_method);
        }

        if ($this->location) {
            $receiver['location_postal_code'] = $this->location['postal_code'];
            $receiver['location_identifier']  = $this->location['identifier'];
        }

        if (array_key_exists(WC_MP_Shipping_Method::INPUT_DOOR_CODE, $this->order_meta)) {
            $receiver['door_code'] = $this->order_meta[WC_MP_Shipping_Method::INPUT_DOOR_CODE][0];
        }

        $data                            = [];
        $data['source']                  = 'woocommerce';
        $data['source_identifier']       = str_replace('www.', '', parse_url(get_bloginfo('wpurl'), PHP_URL_HOST));
        $data['receiver']                = $receiver;
        $data['order']                   = [];
        $data['order']['id']             = $this->order->get_order_number();
        $data['order']['currency']       = $currency;
        $data['order']['total_value']    = $this->order_meta['_order_total'][0];
        $data['order']['shipping_name']  = $this->order->get_shipping_method();
        $data['order']['shipping_value'] = $this->order_meta['_order_shipping'][0];
        $data['order']['created_at']     = $created_at;

        $data['pickup']['type']          = $methods['pickup'];
        $data['pickup']['weight']        = $this->total_weight;
        $data['pickup']['packages']      = $this->packages;
        $data['pickup']['package_sizes'] = array_fill(0, $this->packages, $post['package_sizes']);

        $data['delivery']['type']     = $methods['delivery'];
        $data['delivery']['courier']  = $this->courier;
        $data['delivery']['comments'] = sanitize_text_field($this->order->get_customer_note());

        // Carrier selection
        if (array_key_exists('delivery_method', $post) || array_key_exists('carrier', $post)) {
            if (array_key_exists('delivery_method', $post)) {
                $data['delivery']['method'] = $post['delivery_method'];
            }

            if (array_key_exists('carrier', $post)) {
                $data['delivery']['courier'] = $post['carrier'];
            }
        } elseif (MultiParcels()->carriers->is_not_multiparcels_shipping_method($this->shipping_method) && $carrier_selection = MultiParcels()->carrier_selections->get($receiver['country_code'],
                $this->shipping_method_name)) {
            $data['delivery']['courier'] = $carrier_selection['carrier'];

            if ($carrier_selection['method']) {
                $data['delivery']['method'] = $carrier_selection['method'];
            }
        } elseif ($this->delivery_shipping_method) {
            // from shipping zone method
            $data['delivery']['method'] = $this->delivery_shipping_method;
        }


if (array_key_exists(WC_MP_Shipping_Method::INPUT_PREFERRED_DELIVERY_TIME,
                $this->order_meta) && $methods['delivery'] == 'hands') {

            $carrier_settings               = MultiParcels()->carriers->get($this->courier);
            $preferred_delivery_time_cities = [];

            if (array_key_exists('preferred_delivery_time_cities', $carrier_settings)) {

                foreach ($carrier_settings['preferred_delivery_time_cities']['LT'] as $city) {
                    $preferred_delivery_time_cities[] = MP_Locations::latin($city);
                }
            }

            if (in_array(MP_Locations::latin($data['receiver']['city']), $preferred_delivery_time_cities)) {
                $data['delivery']['time_frame'] = $this->order_meta[WC_MP_Shipping_Method::INPUT_PREFERRED_DELIVERY_TIME][0];
            }
        }

        $data['services'] = [];

        $sender_locations = [];

        foreach ($this->products as $product) {
            $name = $product['name'];
            $code = $product['code'];
            $quantity = $product['quantity'];
            $total_value = $product['total_value'];

            $comment = apply_filters('multiparcels_order_shipping_product_comments', '', $product['product_id']);

            if (array_key_exists('product_name_'.$product['id'].'_'.$product['unique_hash'], $post)) {
                $name = $post['product_name_'.$product['id'].'_'.$product['unique_hash']];
            }

            if (array_key_exists('product_comment_'.$product['id'].'_'.$product['unique_hash'], $post)) {
                $comment = $post['product_comment_'.$product['id'].'_'.$product['unique_hash']];
            }

            if (array_key_exists('product_code_'.$product['id'].'_'.$product['unique_hash'], $post)) {
                $code = $post['product_code_'.$product['id'].'_'.$product['unique_hash']];
            }

            if (array_key_exists('product_quantity_'.$product['id'].'_'.$product['unique_hash'], $post)) {
                $quantity = (int)$post['product_quantity_'.$product['id'].'_'.$product['unique_hash']];
            }

            if (array_key_exists('product_total_value_'.$product['id'].'_'.$product['unique_hash'], $post)) {
                $total_value = (float)$post['product_total_value_'.$product['id'].'_'.$product['unique_hash']];
            }

            if (array_key_exists('product_sender_location_'.$product['id'].'_'.$product['unique_hash'],
                $post)) {
                $location = $post['product_sender_location_'.$product['id'].'_'.$product['unique_hash']];

                if ( ! array_key_exists($location, $sender_locations)) {
                    $sender_locations[$location] = [];
                }

                $sender_locations[$location][] = $this->order->get_item($product['item_id']);
            } else {
                $location = apply_filters('multiparcels_order_shipping_product_sender_location',
                    MultiParcels()->options->get_default_sender_location(), $product['product_id']);

                if ( ! array_key_exists($location, $sender_locations)) {
                    $sender_locations[$location] = [];
                }

                $sender_locations[$location][] = $this->order->get_item($product['item_id']);
            }

            if ($quantity < 1) {
                continue;
            }

            $data['products'][] = [
                'title'          => $name,
                'code'           => $code,
                'quantity'       => $quantity,
                'comments'       => $comment,
                'warehouse_code' => $location,
                'total_value'    => $total_value,
                'currency'       => $currency,
            ];
        }

        foreach ($post as $key => $value) {
            $fromKey = strlen('custom_products_code_');
            if ($from = strpos($key, 'custom_products_code_') !== false) {
                $custom_product_post_key = substr($key, $fromKey);

                $name     = $post['custom_products_name_'.$custom_product_post_key];
                $code     = $value;
                $quantity = $post['custom_products_quantity_'.$custom_product_post_key];
                $comment  = $post['custom_products_comment_'.$custom_product_post_key];

                $data['products'][] = [
                    'title'    => $name,
                    'code'     => $code,
                    'quantity' => $quantity,
                    'comments' => $comment,
                ];
            }
        }

        $cod_service = false;

        foreach ($post as $key => $value) {
            if (substr($key, 0, 8) == 'service_') {
                $service = substr($key, 8);

                $service_data = [
                    "enabled" => 1,
                    "code"    => $service,
                ];

                if ($service == 'cod') {
                    $cod_service = true;
                    $codValue = $this->order_meta['_order_total'][0];

                    if (isset($post['cod_value'])) {
                        $codValue = $post['cod_value'];
                    }

                    $service_data = [
                        "enabled"  => 1,
                        "code"     => $service,
                        "currency" => $this->order_meta['_order_currency'][0],
                        "value"    => $codValue,
                    ];
                }

                $data['services'][] = $service_data;
            }
        }

        $one_sender_location = count($sender_locations) == 1;

        $sender_locations_status = array_fill(1, count($sender_locations), [
            'confirmed'         => null,
            'external_id'       => null,
            'tracking_code'     => null,
            'tracking_link'     => null,
            'validation_errors' => [],
        ]);

        $key                              = 1;
        $originalData                     = $data;
        $change_order_status_to_completed = ! MultiParcels()->options->getBool('not_change_order_status_after_dispatch');

        if ($cod_service) {
            $change_order_status_to_completed = !MultiParcels()->options->getBool('not_change_order_status_after_dispatch_cod');
        }

        foreach ($sender_locations as $sender_location => $items) {
            $data           = $originalData;
            $this->packages = (int) apply_filters('multiparcels_order_shipping_packages', $this->packages, $this->order,
                $items, $this->order_id);

            $data['pickup']['packages']      = $this->packages;
            $data['pickup']['package_sizes'] = array_fill(0, $this->packages, $post['package_sizes']);
            $data['products']                = array_filter($data['products'],
                function ($product) use ($sender_location) {
                    return $product['warehouse_code'] == $sender_location;
                });

            $data['sender'] = $this->get_sender($sender_location);

            $lastNumbers = date('m');

            if ($randomIdentifier) {
                $lastNumbers = mt_rand(10, 99);
            }

            $data['identifier'] = sprintf("%s-%d-%d(%d)",
                str_replace('www.', '', parse_url(get_bloginfo('wpurl'), PHP_URL_HOST)),
                $order_id,
                $key,
                $lastNumbers);

            if (isset($_GET['multiparcels_debug'])) {
                // Used by support to check what data will be sent
                echo "<pre>";
                var_dump($data);
                echo "</pre>";
                return;
            }

            MultiParcels()->logger->log($data, MP_Logger::TYPE_SHIPMENT_CREATE, $order_id);
            $response = MultiParcels()->api_client->request('shipments', 'POST', $data);
            MultiParcels()->logger->log($response, MP_Logger::TYPE_SHIPMENT_CREATE, $order_id);

            if ($response->was_successful()) {
                $shipment = $response->get_data();

                if ($one_sender_location) {
                    // reset
                    $this->set_confirmed(false);
                    $this->set_external_id(null);
                    $this->set_tracking_code(null);
                    $this->set_tracking_link(null);
                    $this->set_label_link(null);
                    $this->set_errors([]);
                    $this->set_services_history([]);
                    $this->set_packages_count(null);
                    // reset
                }

                $confirm_link = sprintf('shipments/%s/confirm', $shipment['id']);

                MultiParcels()->logger->log($confirm_link, MP_Logger::TYPE_SHIPMENT_CONFIRM, $order_id);
                $confirm_response = MultiParcels()->api_client->request($confirm_link, 'POST');
                MultiParcels()->logger->log($confirm_response, MP_Logger::TYPE_SHIPMENT_CONFIRM, $order_id);

                if ($confirm_response->was_successful()) {
                    $confirm_data = $confirm_response->get_data();

                    if ($one_sender_location) {
                        $this->set_confirmed(true);
                        $this->set_external_id($shipment['id']);
                        $this->set_tracking_code($confirm_data['tracking_codes'][0]);
                        $this->set_tracking_link($confirm_data['tracking_link']);
                        $this->set_services_history($this->build_services_history($data));
                        $this->set_packages_count($this->packages);

                        if ($change_order_status_to_completed) {
                            $this->order->update_status('completed');
                        }
                    } else {
                        $sender_locations_status[$key]['confirmed']     = true;
                        $sender_locations_status[$key]['external_id']   = $shipment['id'];
                        $sender_locations_status[$key]['tracking_code'] = $confirm_data['tracking_codes'][0];
                        $sender_locations_status[$key]['tracking_link'] = $confirm_data['tracking_link'];
                    }

                    $download_labels_enabled = ! MultiParcels()->options->getBool('disable_label_downloading');

                    if ($one_sender_location && $download_labels_enabled) {
                        $labels_link = sprintf('shipments/%s/labels', $shipment['id']);

                        MultiParcels()->logger->log($labels_link, MP_Logger::TYPE_SHIPMENT_DOWNLOAD_LABEL, $order_id);
                        $labels_response = MultiParcels()->api_client->request($labels_link, 'GET');
                        MultiParcels()->logger->log($labels_response, MP_Logger::TYPE_SHIPMENT_DOWNLOAD_LABEL,
                            $order_id);

                        if ($labels_response->was_successful()) {
                            $label_data = $labels_response->get_data();

                            $file_name = sprintf("label_%d.pdf", $this->order_id);

                            $upload = wp_upload_bits($file_name, null, base64_decode($label_data['content']));
                            $this->set_label_link(explode('/wp-content/uploads/', $upload['url'])[1]);
                        } else {
                            MultiParcels()->logger->log('label download failed',
                                MP_Logger::TYPE_SHIPMENT_DOWNLOAD_LABEL,
                                $order_id);
                        }
                    }
                } else {
                    $errors = [];

                    $this->reset($order_id, false);

                    $confirm_response_array = $confirm_response->get_full_response();

                    if (array_key_exists('errors', $confirm_response_array)
                        && count($confirm_response_array['errors'])) {
                        $errors = [
                            'confirmation_error' => [
                                [
                                    'text' => $confirm_response_array['errors'][0],
                                    'rule' => 'CONFIRMATION_ERROR',
                                ],
                            ],
                        ];
                    }

                    if ($confirm_response->has_error()) {
                        $errors = [
                            'curl_error' => [
                                [
                                    'text' => $confirm_response->get_error_message(),
                                    'rule' => 'CURL_ERROR',
                                ],
                            ],
                        ];
                    }

                    $this->set_errors($errors);

                    if ( ! $one_sender_location) {
                        $sender_locations_status[$key]['validation_errors'] = $errors;
                    }

                    if ($one_sender_location) {
                        MultiParcels()->api_client->request(sprintf('shipments/%s', $shipment['id']), 'DELETE');

                        if ($redirect) {
                            $query = http_build_query([
                                'errors' => $errors,
                            ]);

                            $url = 'post.php?post='.$this->order_id.'&action=edit&'.$query.'#multiparcels-shipping-box';

                            wp_redirect(admin_url($url));
                            exit;
                        }
                    }

                    MultiParcels()->logger->log('confirm failed', MP_Logger::TYPE_SHIPMENT_CONFIRM, $order_id);
                }
            } else {
                $errors = [];
                if ($response->has_validation_errors()) {
                    $errors = $response->get_validation_errors();
                } elseif ($response->has_error()) {
                    $errors = [
                        'curl_error' => [
                            [
                                'text' => $response->get_error_message(),
                                'rule' => 'CURL_ERROR',
                            ],
                        ],
                    ];
                }

                $this->set_errors($errors);

                if ( ! $one_sender_location) {
                    $sender_locations_status[$key]['validation_errors'] = $errors;
                }

                if ($one_sender_location && $redirect) {
                    $query = http_build_query([
                        'errors' => $errors,
                    ]);

                    $url = 'post.php?post='.$this->order_id.'&action=edit&'.$query.'#multiparcels-shipping-box';

                    wp_redirect(admin_url($url));
                    exit;
                }
            }

            $key++;
        }

        if ($one_sender_location && $redirect) {
            wp_redirect(admin_url('post.php?post='.$this->order_id.'&action=edit#multiparcels-shipping-box'));
            exit;
        }

        if ( ! $one_sender_location) {
            $all_confirmed     = true;
            $validation_errors = [];

            foreach ($sender_locations_status as $shipment) {
                if ( ! $shipment['confirmed']) {
                    $validation_errors = $shipment['validation_errors'];
                    $all_confirmed     = false;
                    break;
                }
            }

            if ( ! $all_confirmed) {
                foreach ($sender_locations_status as $shipment) {
                    if ($shipment['confirmed']) {
                        $delete_link = sprintf('shipments/%s', $shipment['external_id']);
                        MultiParcels()->api_client->request($delete_link, 'DELETE');
                    }
                }

                if ($redirect) {
                    $query = http_build_query([
                        'errors' => $validation_errors,
                    ]);

                    $url = 'post.php?post='.$this->order_id.'&action=edit&'.$query.'#multiparcels-shipping-box';

                    wp_redirect(admin_url($url));
                    exit;
                }
            }


            if ($all_confirmed) {
                if ($change_order_status_to_completed) {
                    $this->order->update_status('completed');
                }
                $this->set_confirmed(true);
                $this->set_external_id(implode(',', array_column($sender_locations_status, 'external_id')));
                $this->set_tracking_code(implode(',', array_column($sender_locations_status, 'tracking_code')));
                $this->set_tracking_link($sender_locations_status[1]['tracking_link']);
                $this->set_services_history($this->build_services_history($data));
                $this->set_packages_count($this->packages);
            } else {
                // reset
                $this->set_confirmed(false);
                $this->set_external_id(null);
                $this->set_tracking_code(null);
                $this->set_tracking_link(null);
                $this->set_label_link(null);
                // reset
            }
        }

        if ($redirect) {
            wp_redirect(admin_url('post.php?post='.$this->order_id.'&action=edit#multiparcels-shipping-box'));
            exit;
        }
    }

    /**
     * @return array|null
     */
    private function get_location()
    {
        return $this->location = MultiParcels()->locations->get_location_for_order($this->order);
    }

    /**
     * @param  string|null  $id
     *
     * @return array
     */
    private function get_sender($id = null)
    {
        if ( ! $id) {
            $id = MultiParcels()->options->get_default_sender_location();
        }

        return MultiParcels()->options->get_sender_location($id);
    }

    /**
     * @return array
     */
    private function get_receiver()
    {

        $first_name = 'Name';
        $last_name = 'LastName';
        $phone = '';
        $address = '';
        $city = '';
        $postal_code = '';

        // first name
        if (isset($this->order_meta['_billing_first_name'])) {
            $first_name = sanitize_text_field($this->order_meta['_billing_first_name'][0]);
            $last_name = sanitize_text_field($this->order_meta['_billing_last_name'][0]);
        }

        if (isset($this->order_meta['_shipping_first_name'])) {
            $first_name = sanitize_text_field($this->order_meta['_shipping_first_name'][0]);
            $last_name = sanitize_text_field($this->order_meta['_shipping_last_name'][0]);
        }

        // phone
        if (isset($this->order_meta['_billing_phone'][0]) && $this->order_meta['_billing_phone'][0]) {
           $phone = $this->order_meta['_billing_phone'][0];
        }

        if (isset($this->order_meta['_shipping_phone'][0]) && $this->order_meta['_shipping_phone'][0]) {
           $phone = $this->order_meta['_shipping_phone'][0];
        }

        // address
        if (isset($this->order_meta['_billing_address_1'][0])) {
            $address = $this->order_meta['_billing_address_1'][0];

            if (isset($this->order_meta['_billing_address_2'][0])) {
                $address = trim($this->order_meta['_billing_address_1'][0].' '.$this->order_meta['_billing_address_2'][0]);
            }
        }

        if (isset($this->order_meta['_shipping_address_1'][0])) {
            $address = $this->order_meta['_shipping_address_1'][0];

            if (isset($this->order_meta['_shipping_address_2'][0])) {
                $address = trim($this->order_meta['_shipping_address_1'][0].' '.$this->order_meta['_shipping_address_1'][0]);
            }
        }

        // city
        if (isset($this->order_meta['_billing_city'][0])) {
            $city = $this->order_meta['_billing_city'][0];
        }

        if (isset($this->order_meta['_shipping_city'][0])) {
            $city = $this->order_meta['_shipping_city'][0];
        }

        // postal code
        if (isset($this->order_meta['_billing_postcode'][0])) {
            $postal_code = $this->order_meta['_billing_postcode'][0];
        }

        if (isset($this->order_meta['_shipping_postcode'][0])) {
            $postal_code = $this->order_meta['_shipping_postcode'][0];
        }

        $receiver                 = [];
        $receiver['name']         = substr(sanitize_text_field(sprintf("%s %s", $first_name, $last_name)), 0, 80);
        $receiver['phone_number'] = $phone;
        $receiver['street']       = sanitize_text_field($address);
        $receiver['city']         = sanitize_text_field($city);
        $receiver['postal_code']  = sanitize_text_field($postal_code);
        $receiver['email']        = sanitize_text_field($this->order_meta['_billing_email'][0]);

        $receiver_country = null;

        if (array_key_exists('_billing_country', $this->order_meta)) {
            $receiver_country = sanitize_text_field($this->order_meta['_billing_country'][0]);
        }

        if (array_key_exists('_shipping_country', $this->order_meta)) {
            $receiver_country = sanitize_text_field($this->order_meta['_shipping_country'][0]);
        }
        
        $receiver['country_code'] = $receiver_country;

        return $receiver;
    }

    /**
     * @param $shipping
     *
     * @return array
     */
    private function parse_methods($shipping)
    {
        $check = in_array($shipping, self::SHIPPING_METHODS);

        if ($check) {
            $explode = explode('_to_', $shipping);

            return [
                'pickup'   => $explode[0],
                'delivery' => $explode[1],
            ];
        }

        return [
            'pickup'   => '',
            'delivery' => '',
        ];
    }

    /**
     * @param $order_id
     */
    public function load_order($order_id)
    {
        $this->order      = wc_get_order($order_id);
        $this->order_meta = get_post_meta($order_id);
        $this->order_id   = $this->order->get_id();

        $this->courier = MultiParcels()->carriers->extract_from_method($this->order);

        $shipping_methods = $this->order->get_shipping_methods();
        $shipping_methods = reset($shipping_methods);

        $method                     = $shipping_methods['method_id'];
        $this->shipping_method      = $shipping_methods['method_id'];
        $this->shipping_method_name = $shipping_methods['name'];

        $possibleDeliveryTypes = WC_MP_Shipping_Method::SUFFIXES;

        foreach ($possibleDeliveryTypes as $possibleDeliveryType) {
            if (substr($method, strlen($possibleDeliveryType) * -1) == $possibleDeliveryType) {
                $this->delivery_type = $possibleDeliveryType;
            }
        }

        if (isset($_GET['multiparcels_action']) && $_GET['multiparcels_action'] == 'remove_pickup_point') {
            delete_post_meta($this->order_id, WC_MP_Terminal_Shipping_Method::INPUT_NAME);
        }

        $this->location = $this->get_location();
        $this->location_error = false;
        $this->location_identifier = get_post_meta($this->order->get_id(), 'multiparcels_location_identifier', true);
        if ($this->location_identifier && $this->location == null) {
            $this->location_error = true;
        }

        $this->total_product_item_count = 0;
        $this->total_weight             = 0;
        $this->products                 = [];

        /**
         * @var int $item_id
         * @var WC_Order_Item_Product $item
         */
        foreach ($this->order->get_items() as $item_id => $item) {

            if ($item['product_id'] > 0) {
                $_product = $item->get_product();

                // WPC Product Bundles for WooCommerce support
                if ($_product && (get_class($_product) == 'WC_Product_Yith_Bundle' || get_class($_product) == 'WC_Product_Woosb')) {
                    continue; // don't add the main bundle product and just add its items
                }

                if ( ! $_product->needs_shipping()) {
                    continue;
                }

                if ( ! $_product->is_virtual()) {
                    $weight = (float) $_product->get_weight();

                    if ( ! $weight) {
                        $weight = (float) MultiParcels()->options->get('default_product_weight');

                        if ($weight <= 0) {
                            $weight = 0.1; // default dummy weight
                        }
                    }

                    $this->total_weight += $weight * $item['qty'];
                }
            }

            $_product = $item->get_product();
            $name     = $item->get_name();
            $sku      = '';

            // WPC Product Bundles for WooCommerce support
            if ($_product && (get_class($_product) == 'WC_Product_Yith_Bundle' || get_class($_product) == 'WC_Product_Woosb')) {
                continue; // don't add the main bundle product and just add its items
            }

            if ($_product) {
                $sku  = $_product->get_sku();
                $name = $_product->get_name();
            }

            if ($delimiter = MultiParcels()->options->get('split_sku_delimiter')) {
                $sku_parts = explode($delimiter, $sku);

                if (count($sku_parts) > 1) {
                    foreach ($sku_parts as $value) {
                        $this->products[] = [
                            'id'          => $item_id,
                            'unique_hash' => md5($item_id.$value),
                            'name'        => $name,
                            'code'        => $value,
                            'quantity'    => $item->get_quantity(),
                            'product_id'  => $item->get_product_id(),
                            'item_id'     => $item->get_id(),
                            'total_value' => $item->get_total(),
                        ];
                    }
                } else {
                    $this->products[] = [
                        'id'          => $item_id,
                        'unique_hash' => md5($item_id),
                        'name'        => $name,
                        'code'        => $sku,
                        'quantity'    => $item->get_quantity(),
                        'product_id'  => $item->get_product_id(),
                        'item_id'     => $item->get_id(),
                        'total_value' => $item->get_total(),
                    ];
                }
            } else {
                $this->products[] = [
                    'id'          => $item_id,
                    'unique_hash' => md5($item_id),
                    'name'        => $name,
                    'code'        => $sku,
                    'quantity'    => $item->get_quantity(),
                    'product_id'  => $item->get_product_id(),
                    'item_id'     => $item->get_id(),
                    'total_value' => $item->get_total(),
                ];
            }

            foreach ($this->products as $key => $product) {
                $this->products[$key]['code'] = apply_filters('multiparcels_shipping_product_code', $this->products[$key]['code'], $product['product_id'], $this->order);
            }

            $this->total_product_item_count += $item->get_quantity();
        }

        // We always use kg
        if (get_option('woocommerce_weight_unit') == 'g') {
            $this->total_weight /= 1000;
        }

        $instance_id_from_shipping = 0;
        $shipping                  = $this->order->get_shipping_methods();
        $shipping                  = reset($shipping);
        /** @var WC_Order_Item_Shipping $shipping */
        $method_id = $shipping['method_id'];

        if ($shipping) {

            $shipping_data = $shipping->get_data();

            if (array_key_exists('instance_id', $shipping_data)) {
                $instance_id_from_shipping = $shipping_data['instance_id'];
            }

            $instance_id = 0;
            $explode     = explode(':', $method_id);

            $shipping_id = $explode[0];

            if (count($explode) == 2) {
                $instance_id = $explode[1];
            }

            if ($instance_id_from_shipping != 0) {
                $instance_id = $instance_id_from_shipping;
            }

            $methods = WC()->shipping()->get_shipping_methods();

            if (array_key_exists($shipping_id, $methods)) {
                $class_name = get_class(WC()->shipping()->get_shipping_methods()[$shipping_id]);
                /** @var WC_MP_Shipping_Method $shipping_class */
                $shipping_class = new $class_name($instance_id);

                if ($shipping_class->get_option('shipping_method')) {
                    $this->delivery_shipping_method = $shipping_class->get_option('shipping_method');
                }

                $default_services = $shipping_class->get_option('default_services');

                if (is_array($default_services)) {
                    $this->default_services = $default_services;
                }

                $pickup_type = $shipping_class->get_option('pickup_type');

                if ($pickup_type == "hands" || $pickup_type == "terminal" || $pickup_type == "bus_station") {
                    $this->preferred_pickup_type = $pickup_type;
                }

                $maximum_weight          = $shipping_class->get_option('maximum_weight');
                $this->items_per_package = $shipping_class->get_option('maximum_items_per_package');

                if ($this->items_per_package == '') {
                    if ($default_maximum_items_per_package = MultiParcels()->options->get('default_maximum_items_per_package')) {
                        $this->items_per_package = $default_maximum_items_per_package;
                    }
                }

                if ($this->items_per_package > 0) {
                    $packages_required = ceil($this->total_product_item_count / $this->items_per_package);
                    $this->packages    = $packages_required;
                }
            }

            $this->packages = (int) apply_filters('multiparcels_order_shipping_packages', $this->packages, $this->order,
                $this->order->get_items(), $this->order_id);
        }
    }

    private function shipping_type_title($type)
    {
        if ($type == self::SHIPPING_HANDS_TO_HANDS) {
            $type = __('Hands to hands', 'multiparcels-shipping-for-woocommerce');
        }

        if ($type == self::SHIPPING_HANDS_TO_TERMINAL) {
            $type = __('Hands to terminal', 'multiparcels-shipping-for-woocommerce');
        }

        if ($type == self::SHIPPING_TERMINAL_TO_TERMINAL) {
            $type = __('Terminal to terminal', 'multiparcels-shipping-for-woocommerce');
        }

        if ($type == self::SHIPPING_TERMINAL_TO_HANDS) {
            $type = __('Terminal to hands', 'multiparcels-shipping-for-woocommerce');
        }

        if ($type == self::SHIPPING_HANDS_TO_POST_OFFICE) {
            $type = __('Hands to post office', 'multiparcels-shipping-for-woocommerce');
        }

        if ($type == self::SHIPPING_POST_OFFICE_TO_POST_OFFICE) {
            $type = __('Post office to post office', 'multiparcels-shipping-for-woocommerce');
        }

        if ($type == self::SHIPPING_HANDS_TO_BUS_STATION) {
            $type = __('Hands to bus station', 'multiparcels-shipping-for-woocommerce');
        }

        if ($type == self::SHIPPING_BUS_STATION_TO_BUS_STATION) {
            $type = __('Bus station to bus station', 'multiparcels-shipping-for-woocommerce');
        }

        if ($type == self::SHIPPING_BUS_STATION_TO_HANDS) {
            $type = __('Bus station to hands', 'multiparcels-shipping-for-woocommerce');
        }

        return $type;
    }

    private function display_products()
    {
        $sending_locations        = MultiParcels()->options->get_sender_locations();
        $sending_default_location = MultiParcels()->options->get_default_sender_location();

        ?>
        <table style="width: 100%;">
            <thead>
            <tr>
                <th style="text-align: left"><?php _e('Product name', 'multiparcels-shipping-for-woocommerce'); ?></th>
                <th style="text-align: left"><?php _e('Product code', 'multiparcels-shipping-for-woocommerce'); ?></th>
                <th style="text-align: left"><?php _e('All products value', 'multiparcels-shipping-for-woocommerce'); ?></th>
                <th style="text-align: left"><?php _e('Product quantity',
                        'multiparcels-shipping-for-woocommerce'); ?></th>
                <th style="text-align: left"><?php _e('Comment', 'multiparcels-shipping-for-woocommerce'); ?></th>
                <th style="text-align: left"><?php _e('Sending location',
                        'multiparcels-shipping-for-woocommerce'); ?></th>
            </tr>
            </thead>
            <tbody id="multiparcels-products-table">
            <?php
            foreach ($this->products as $product) {
                ?>
                <tr>
                    <td>
                        <input type="text"
                               name="multiparcels_shipping[product_name_<?php echo $product['id']; ?>_<?php echo $product['unique_hash']; ?>]"
                               value="<?php echo esc_html($product['name']) ?>">
                    </td>
                    <td>
                        <input type="text"
                               name="multiparcels_shipping[product_code_<?php echo $product['id']; ?>_<?php echo $product['unique_hash']; ?>]"
                               value="<?php echo $product['code'] ?>">
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="multiparcels_shipping[product_total_value_<?php echo $product['id']; ?>_<?php echo $product['unique_hash']; ?>]"
                               value="<?php echo $product['total_value'] ?>">
                        <?php echo $this->order_meta['_order_currency'][0];?>
                    </td>
                    <td>
                        <input type="number"
                               name="multiparcels_shipping[product_quantity_<?php echo $product['id']; ?>_<?php echo $product['unique_hash']; ?>]"
                               value="<?php echo $product['quantity'] ?>">
                    </td>
                    <td>
                        <textarea
                                name="multiparcels_shipping[product_comment_<?php echo $product['id']; ?>_<?php echo $product['unique_hash']; ?>]"
                                rows="2"
                                style="width: 100%;"><?php echo apply_filters('multiparcels_order_shipping_product_comments',
                                '', $product['product_id']); ?></textarea>
                    </td>
                    <td>
                        <?php
                        $default = apply_filters('multiparcels_order_shipping_product_sender_location',
                            $sending_default_location, $product['product_id']);

                        foreach ($sending_locations as $sending_location) {
                            $is_selected = '';

                            if ($default == $sending_location['code']) {
                                $is_selected = 'checked';
                            }
                            echo sprintf(
                                "<div style='margin-bottom: 8px;'><input type='radio' id='location_%s' name='multiparcels_shipping[product_sender_location_%s_%s]' value='%s' %s/> <label for='location_%s'>%s</label></div>",
                                $product['id'].'_'.$product['unique_hash'].'_'.$sending_location['code'],
                                $product['id'],
                                $product['unique_hash'],
                                $sending_location['code'],
                                $is_selected,
                                $product['id'].'_'.$product['unique_hash'].'_'.$sending_location['code'],
                                $sending_location['name']
                            );
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
            <tbody>
            <tr>
                <td colspan="4">
                    <a href="" id="multiparcels-add-a-product" style="text-decoration: none;">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Add a product', 'multiparcels-shipping-for-woocommerce'); ?>
                    </a>
                </td>
            </tr>
            </tbody>
        </table>

        <script>
            jQuery(document).ready(function ($) {
                $("#multiparcels-add-a-product").on('click', function () {
                    var key = Math.floor((Math.random() * 90) + 10);

                    $('#multiparcels-products-table').append('<tr>' +
                        '<td><input type="text" name="multiparcels_shipping[custom_products_name_' + key + ']" style="width: 100%;"/></td>' +
                        '<td><input type="text" name="multiparcels_shipping[custom_products_code_' + key + ']" style="width: 100%;"/></td>' +
                        '<td><input type="number" value="1" name="multiparcels_shipping[custom_products_quantity_' + key + ']" style="width: 100%;"/></td>' +
                        '<td><textarea name="multiparcels_shipping[custom_products_comment_' + key + ']" rows="2" style="width: 100%;"></textarea></td>' +
                        '</td>');

                    return false;
                });
            });
        </script>

        <?php
    }

    private function display_services()
    {
        $settings = MultiParcels()->carriers->get($this->courier);

        if (array_key_exists('services', $settings)) {
            $services = $settings['services'];

            if ($services) {
                echo sprintf("<strong>%s</strong><br/>", __('Services', 'multiparcels-shipping-for-woocommerce'));

                // always place COD service last
                if ($codKey = array_search('cod', $services)) {
                    unset($services[$codKey]);
                    $services[] = 'cod';
                }

                foreach ($services as $service) {
                    $title   = MultiParcels_Services::service_title($service);
                    $checked = '';

                    if ($service == 'b2c') {
                        if ( ! (array_key_exists('_shipping_company',
                                    $this->order_meta) && $this->order_meta['_shipping_company'][0]) && ! (array_key_exists('_billing_company',
                                    $this->order_meta) && $this->order_meta['_billing_company'][0])) {
                            $checked = 'checked';
                        }
                    }

                    if ($service == 'cod') {
                        $title .= sprintf(' (%s%s)', $this->order_meta['_order_total'][0],
                            $this->order_meta['_order_currency'][0]);
                        if (array_key_exists('_payment_method',
                                $this->order_meta) && $this->order_meta['_payment_method'][0] == 'cod') {
                            $checked = 'checked';
                        }
                    }

                    if (array_search($service, $this->default_services) !== false) {
                        $checked = 'checked';
                    }

                    echo sprintf("<input id='service_%s' type='checkbox' name='multiparcels_shipping[service_%s]' value='1' %s/> <label for='service_%s'>%s</label><br/>",
                        $service, $service, $checked, $service, $title);

                    if ($service == 'cod') {
                        echo sprintf("<input type='number' step='0.01' name='multiparcels_shipping[cod_value]' value='%s' style='width: 100px;'/>", $this->order_meta['_order_total'][0]);
                        echo "<br/>";
                    }
                }
            }
        }
    }

    public static function parse_validation_errors($validation_errors, $echo = true)
    {
        foreach ($validation_errors as $key => $errors) {

            foreach ($errors as $error_data) {
                $label = $key;
                $error = $error_data['rule'];

                if ($error == 'VALID_POSTAL_CODE_RULE') {
                    $text = __('Not valid or not found',
                        'multiparcels-shipping-for-woocommerce');
                } elseif ($error == 'MAXIMUM_PLAN_SHIPMENTS') {
                    $text = __('You have reached your plan limit', 'multiparcels-shipping-for-woocommerce');
                } elseif ($error == 'VALID_PHONE_NUMBER_RULE') {
                    $text = __('Not valid', 'multiparcels-shipping-for-woocommerce');
                } elseif ($error == 'REQUIRED' || $error == 'MAYBE_REQUIRED') {
                    $text = __('This field is required', 'multiparcels-shipping-for-woocommerce');
                } elseif ($error == 'EMAIL') {
                    $text = __('Not valid', 'multiparcels-shipping-for-woocommerce');
                } elseif ($error == 'FIND_ROUTE') {
                    $text = __('Such shipment route was not found. If you believe this shipment route should be enabled please contact us.',
                        'multiparcels-shipping-for-woocommerce');
                } elseif ($error == 'UNIQUE') {
                    $text = __('This value has already been used', 'multiparcels-shipping-for-woocommerce');
                } elseif ($error == 'PRODUCTS_CHECK') {
                    $text = __('Not enough products in the warehouse', 'multiparcels-shipping-for-woocommerce');
                } elseif ($error == 'ALLOWED_COURIER_FOR_COMPANY_RULE') {
                    $text = __('Your company is not allowed to use this carrier',
                        'multiparcels-shipping-for-woocommerce');
                } else {
                    $text = sprintf("<strong>%s:</strong> %s",
                        __('Unknown error occurred', 'multiparcels-shipping-for-woocommerce'),
                        $error_data['text']);
                }

                $split = explode('.', $key);

                if (count($split) == 2) {
                    $textA = $split[0];
                    $textB = $split[1];


                    if ($split[0] == 'sender') {
                        $textA = __('Sender', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[0] == 'receiver') {
                        $textA = __('Receiver', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[0] == 'pickup') {
                        $textA = __('Pickup', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[0] == 'delivery') {
                        $textA = __('Delivery', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[0] == 'services') {
                        $textA = __('Services', 'multiparcels-shipping-for-woocommerce');
                    }

                    if ($split[1] == 'name') {
                        $textB = __('Name', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[1] == 'street') {
                        $textB = __('Street', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[1] == 'city') {
                        $textB = __('City', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[1] == 'postal_code') {
                        $textB = __('Postal code', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[1] == 'country_code') {
                        $textB = __('Country', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[1] == 'phone_number') {
                        $textB = __('Phone number', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[1] == 'courier') {
                        $textB = __('Courier', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[1] == 'email') {
                        $textB = __('E-mail', 'multiparcels-shipping-for-woocommerce');
                    } elseif ($split[1] == 'weight') {
                        $textB = __('Weight', 'multiparcels-shipping-for-woocommerce');
                    }

                    $label = sprintf("<strong>%s</strong> - <strong>%s</strong>", $textA, $textB);
                } elseif (count($split) == 1) {
                    if ($split[0] == 'route') {
                        $label = __('Route', 'multiparcels-shipping-for-woocommerce');
                    }
                    if ($split[0] == 'plan') {
                        $label = __('Plan', 'multiparcels-shipping-for-woocommerce');
                    }
                    if ($split[0] == 'identifier') {
                        $label = __('Unique shipment identifier', 'multiparcels-shipping-for-woocommerce');
                    }
                    if ($split[0] == 'products') {
                        $label = __('Products', 'multiparcels-shipping-for-woocommerce');
                    }
                }

                if ($key == 'curl_error') {
                    $label = __("CURL error", 'multiparcels-shipping-for-woocommerce');
                    $text  = $error_data['text'];
                }

                if ($key == 'confirmation_error') {
                    $label = __("Confirmation error", 'multiparcels-shipping-for-woocommerce');
                    $text  = $error_data['text'];
                }

                if ($key == 'pickup_location_not_found') {
                    $label = __("Pickup location not found", 'multiparcels-shipping-for-woocommerce');
                    $text  = $error_data['text'];
                }

                $result = sprintf("<strong>%s</strong>: %s <br/>", $label, $text);

                if ($echo) {
                    echo $result;
                } else {
                    return $result;
                }
            }
        }
    }

    private function display_errors()
    {
        if (array_key_exists('errors', $_GET)) {
            $validation_errors = $_GET['errors'];

            echo sprintf("<h3 style='color: red;'>%s</h3>",
                __('Shipment creation failed', 'multiparcels-shipping-for-woocommerce'));

            echo self::parse_validation_errors($validation_errors);

            echo str_repeat("<br/>", 2);
        }
    }

    public static function package_name($code)
    {
        if ($code == self::PACKAGE_SIZE_EXTRA_SMALL) {
            return 'XS';
        }
        if ($code == self::PACKAGE_SIZE_SMALL) {
            return 'S';
        }
        if ($code == self::PACKAGE_SIZE_MEDIUM) {
            return 'M';
        }
        if ($code == self::PACKAGE_SIZE_LARGE) {
            return 'L';
        }
        if ($code == self::PACKAGE_SIZE_EXTRA_LARGE) {
            return 'XL';
        }
    }

    /**
     * @param  array  $data
     *
     * @return array
     */
    private function build_services_history($data)
    {

        $servicesHistory = [];

        foreach ($data['services'] as $s) {
            $extraData = [];

            if ($s['code'] == 'cod') {
                $codValue = $this->order_meta['_order_total'][0];

                if (isset($_POST['multiparcels_shipping']['cod_value'])) {
                    $codValue = $_POST['multiparcels_shipping']['cod_value'];
                }

                $extraData['value']    = $codValue;
                $extraData['currency'] = $this->order_meta['_order_currency'][0];
            }

            $servicesHistory[] = [
                'code' => $s['code'],
                'data' => $extraData,
            ];
        }

        return $servicesHistory;
    }

    private function display_services_history()
    {
        $services = $this->get_services_history();
        $text     = '';

        if (count($services)) {
            $text = sprintf("<strong>%s</strong>",
                    __('Services', 'multiparcels-shipping-for-woocommerce')).':<br/><ul>';

            foreach ($services as $service) {
                $title = MultiParcels()->services->title($service['code']);

                if ($service['code'] == 'cod') {
                    $title .= sprintf(' (%s%s)', $service['data']['value'], $service['data']['currency']);
                }

                $text .= sprintf("<li>%s</li>", $title);
            }
            $text .= '</ul>';
        }

        echo $text;
    }
}

return new MP_Woocommerce_Order_Shipping();
