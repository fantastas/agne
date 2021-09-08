<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Actions
 */
class MP_Actions
{
    public function __construct()
    {
        add_action('admin_post_multiparcels_request_api_key', [$this, 'request_api_key']);
        add_action('admin_post_multiparcels_update_data', [$this, 'update_data_with_redirect']);
        add_action('admin_post_multiparcels_run_automatic_confirmation', [$this, 'run_automatic_confirmation']);
        add_action('admin_post_multiparcels_carrier_change', [$this, 'carrier_change']);
        add_action('multiparcels_update_data_cron', [$this, 'update_data']);
    }

    function request_api_key()
    {
        MultiParcels()->options->set('email', sanitize_email($_POST['email']));
        MultiParcels()->api_client->request_api_key();
        $this->update_data();

        if ( ! wp_next_scheduled('multiparcels_update_data_cron')) {
            $time = sprintf('%d:%d', mt_rand(2, 6), mt_rand(0, 59));

            wp_schedule_event(strtotime("next day ".$time."am"), 'daily',
                'multiparcels_update_data_cron');
        }

        wp_redirect(MultiParcels()->settings_url());
        exit;
    }

    public static function update()
    {
        $instance = new self;
        $instance->update_data();
    }

    public function update_data($redirect = false)
    {
        MultiParcels()->permissions->update();

        /**
         * Only update if API can accessed
         */
        if (MultiParcels()->permissions->isLimited()) {
            MultiParcels()->carriers->update();
            MultiParcels()->locations->update();
        }

        if ($redirect) {
            wp_redirect(MultiParcels()->settings_url());
            exit;
        }
    }

    public function run_automatic_confirmation()
    {
        do_action('multiparcels_automatic_confirmation_cron');

        wp_redirect(MultiParcels()->settings_url(['tab' => MP_Admin::TAB_AUTOMATIC_CONFIRMATION], false));
        exit;
    }

    function update_data_with_redirect()
    {
        $this->update_data(true);
    }

    public function carrier_change()
    {
        $value   = false;
        $carrier = sanitize_text_field($_GET['carrier']);

        if (sanitize_text_field($_GET['change']) == 'enable') {
            $value = true;
        }

        MultiParcels()->options->set($carrier, $value);

        wp_redirect(MultiParcels()->settings_url());
        exit;
    }
}

return new MP_Actions();
