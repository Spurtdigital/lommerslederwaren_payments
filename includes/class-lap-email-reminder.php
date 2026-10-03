<?php
if (!defined('ABSPATH')) exit;

/**
 * Betalingsherinnering e-mail (klant).
 *
 * Herinneringsmail wanneer er na X werkdagen nog niet betaald is.
 * Nu met {iban} en {account_name} placeholders via LAP_Email_Base.
 *
 * @since 1.7.0
 */
class LAP_Email_Reminder extends LAP_Email_Base {

    public function __construct()
    {
        $this->id             = 'request_payment_reminder';
        $this->title          = __('Betalingsherinnering (klant)', 'lommers-approval');
        $this->description    = __('Herinneringsmail wanneer er na X werkdagen nog niet betaald is.', 'lommers-approval');
        $this->customer_email = true;

        $custom_subject = get_option('lap_email_reminder_subject', '');
        $custom_heading = get_option('lap_email_reminder_heading', '');

        $this->heading = $custom_heading ? $custom_heading : __('Herinnering: betaal je bestelling', 'lommers-approval');
        $this->subject = $custom_subject ? $custom_subject : __('Herinnering – bestelling #{order_number}', 'lommers-approval');

        $this->template_html  = 'emails/request-payment-reminder.php';
        $this->template_plain = 'emails/plain/request-payment-reminder.php';

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
        if ($order->is_paid()) return;

        $this->object    = $order;
        $this->recipient = $order->get_billing_email();

        $this->fill_placeholders($order);

        if (!$this->get_recipient()) {
            lap_log('request_payment_reminder no recipient #' . $order->get_id(), 'warning');
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
        return $this->get_custom_or_template_html('lap_email_reminder_content', true);
    }

    /**
     * Plain text content met custom template support.
     *
     * @return string
     */
    public function get_content_plain()
    {
        return $this->get_custom_or_template_plain('lap_email_reminder_content');
    }
}
