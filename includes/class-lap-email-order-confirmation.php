<?php
if (!defined('ABSPATH')) exit;

/**
 * Bevestiging ter goedkeuring e-mail (klant).
 *
 * Verstuurd wanneer een order de status "ter-goedkeuring" krijgt.
 * Vervangt de eerdere raw wp_mail() implementatie.
 * Gebruikt WooCommerce email template systeem (header, footer, styling).
 *
 * @since 1.7.0
 */
class LAP_Email_Order_Confirmation extends LAP_Email_Base {

    public function __construct()
    {
        $this->id             = 'lap_order_confirmation';
        $this->title          = __('Bevestiging ter goedkeuring (klant)', 'lommers-approval');
        $this->description    = __('Verstuurd wanneer een order de status "Ter goedkeuring" krijgt.', 'lommers-approval');
        $this->customer_email = true;

        $custom_subject = get_option('lap_email_confirmation_subject', '');
        $custom_heading = get_option('lap_email_confirmation_heading', '');

        $this->heading = $custom_heading ? $custom_heading : __('Bedankt voor je bestelling', 'lommers-approval');
        $this->subject = $custom_subject ? $custom_subject : __('Bedankt voor je bestelling – wij gaan controleren', 'lommers-approval');

        $this->template_html  = 'emails/order-confirmation.php';
        $this->template_plain = 'emails/plain/order-confirmation.php';

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
            lap_log('order_confirmation no recipient #' . $order->get_id(), 'warning');
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
     * Confirmation email heeft geen betaalbutton nodig.
     *
     * @return string
     */
    public function get_content_html()
    {
        return $this->get_custom_or_template_html('lap_email_confirmation_content', false);
    }

    /**
     * Plain text content met custom template support.
     *
     * @return string
     */
    public function get_content_plain()
    {
        return $this->get_custom_or_template_plain('lap_email_confirmation_content');
    }
}
