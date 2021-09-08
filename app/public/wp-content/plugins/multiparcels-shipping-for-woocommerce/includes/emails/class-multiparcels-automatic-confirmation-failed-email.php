<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MultiParcels_Automatic_Confirmation_Failed_Email
 */
class MultiParcels_Automatic_Confirmation_Failed_Email extends WC_Email
{
    /**
     * @var array
     */
    private $orders;

    /**
     * MultiParcels_Automatic_Confirmation_Failed_Email constructor.
     */
    public function __construct()
    {
        $this->id = 'multiparcels_automatic_confirmation_failed';
        $this->title = __('Automatic confirmation failed', 'multiparcels-shipping-for-woocommerce');
        $this->description = __('This email is automatically sent when confirmation failed for one or more orders',
            'multiparcels-shipping-for-woocommerce');

        $this->heading = __('Automatic confirmation failed', 'multiparcels-shipping-for-woocommerce');
        $this->subject = __('Automatic confirmation failed', 'multiparcels-shipping-for-woocommerce');

        $this->template_html = 'emails/email-automatic-confirmation-failed.php';

        add_action('multiparcels_automatic_confirmation_failed', [$this, 'trigger']);

        parent::__construct();

        $this->recipient = $this->get_option('recipient');

        if (!$this->recipient) {
            $this->recipient = get_option('admin_email');
        }
    }


    /**
     * @param  int[]  $ids
     */
    public function trigger($ids)
    {
        if (!$this->is_enabled() || !$this->get_recipient()) {
            return;
        }

        $this->orders = [];

        foreach ($ids as $id) {
            $order = wc_get_order($id);

            if ($order) {
                $this->orders[] = [
                    'id' => $order->get_order_number(),
                    'receiver' => sprintf('%s %s', $order->get_shipping_first_name(), $order->get_shipping_last_name()),
                    'shipping' => $order->get_shipping_method(),
                    'link' => admin_url('post.php?post='.absint($order->get_id()).'&action=edit')
                ];
            }
        }

        if (!count($this->orders)) {
            return;
        }

        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(),
            $this->get_attachments());
    }

    public function get_content_html()
    {
        ob_start();
        wc_get_template($this->template_html, [
            'email_heading' => $this->get_heading(),
            'orders' => $this->orders,
            'email' => $this,
        ], '', MultiParcels()->plugin_path().'/woocommerce/');
        return ob_get_clean();
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'multiparcels-shipping-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'yes'
            ],
            'recipient' => [
                'title' => __('Recipient(s)', 'multiparcels-shipping-for-woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.',
                    'multiparcels-shipping-for-woocommerce'),
                    esc_attr(get_option('admin_email'))),
                'placeholder' => '',
                'default' => ''
            ],
            'email_type' => [
                'title' => __('Email type', 'multiparcels-shipping-for-woocommerce'),
                'type' => 'select',
                'default' => 'html',
                'class' => 'email_type',
                'options' => [
                    'html' => 'HTML',
                ]
            ]
        ];
    }
}
