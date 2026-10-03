<?php
if (!defined('ABSPATH')) exit;

/**
 * Betaling verzocht e-mail (klant).
 *
 * Verstuurd wanneer de orderstatus op "Betaling verzocht" komt.
 * Bevat betaallink als button, IBAN gegevens en order details.
 *
 * @since 1.7.0
 */
class LAP_Email_Request_Payment extends LAP_Email_Base {

    public function __construct()
    {
        $this->id             = 'request_payment';
        $this->title          = __('Betaling verzocht (klant)', 'lommers-approval');
        $this->description    = __('Verstuurd wanneer de orderstatus op "Betaling verzocht" komt.', 'lommers-approval');
        $this->customer_email = true;

        $custom_subject = get_option('lap_email_request_payment_subject', '');
        $custom_heading = get_option('lap_email_request_payment_heading', '');

        $this->heading = $custom_heading ? $custom_heading : __('Betaal je bestelling', 'lommers-approval');
        $this->subject = $custom_subject ? $custom_subject : __('Bestelling #{order_number} – betaalinstructies', 'lommers-approval');

        $this->template_html  = 'emails/request-payment.php';
        $this->template_plain = 'emails/plain/request-payment.php';

        parent::__construct();

        $this->enabled = 'yes';
        $this->init_lap_placeholders();
    }

    /**
     * Trigger de e-mail.
     *
     * @param int           $order_id
     * @param WC_Order|bool $order
     */
    public function trigger($order_id, $order = false)
    {
        if (!$order && $order_id) {
            $order = wc_get_order($order_id);
        }
        if (!$order) return;

        $this->object    = $order;
        $this->recipient = $order->get_billing_email();

        $this->fill_placeholders($order);

        if (!$this->get_recipient()) {
            lap_log('request_payment no recipient #' . $order->get_id(), 'warning');
            return;
        }

        $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );
    }

    /**
     * HTML content met custom template support.
     *
     * @return string
     */
    public function get_content_html()
    {
        return $this->get_custom_or_template_html('lap_email_request_payment_content', true);
    }

    /**
     * Plain text content met custom template support.
     *
     * @return string
     */
    public function get_content_plain()
    {
        return $this->get_custom_or_template_plain('lap_email_request_payment_content');
    }
}
