<?php
if (!defined('ABSPATH')) exit;

/**
 * Bestelling geannuleerd e-mail (klant).
 *
 * Verstuurd wanneer een bestelling wordt geannuleerd.
 * Nu met {iban} en {account_name} placeholders via LAP_Email_Base.
 *
 * @since 1.7.0
 */
class LAP_Email_Cancelled extends LAP_Email_Base {

    public function __construct()
    {
        $this->id             = 'lap_order_cancelled';
        $this->title          = __('Bestelling geannuleerd (klant)', 'lommers-approval');
        $this->description    = __('Verstuurd wanneer een bestelling wordt geannuleerd.', 'lommers-approval');
        $this->customer_email = true;

        $custom_subject = get_option('lap_email_cancelled_subject', '');
        $custom_heading = get_option('lap_email_cancelled_heading', '');

        $this->heading = $custom_heading ? $custom_heading : __('Bestelling geannuleerd', 'lommers-approval');
        $this->subject = $custom_subject ? $custom_subject : __('Bestelling #{order_number} geannuleerd', 'lommers-approval');

        $this->template_html  = 'emails/order-cancelled.php';
        $this->template_plain = 'emails/plain/order-cancelled.php';

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
            lap_log('order_cancelled no recipient #' . $order->get_id(), 'warning');
            return;
        }

        lap_log('Triggering order_cancelled email for #' . $order->get_id());

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
     * Cancelled email gebruikt link ipv button voor {payment_url}.
     *
     * @return string
     */
    public function get_content_html()
    {
        return $this->get_custom_or_template_html('lap_email_cancelled_content', false);
    }

    /**
     * Plain text content met custom template support.
     *
     * @return string
     */
    public function get_content_plain()
    {
        return $this->get_custom_or_template_plain('lap_email_cancelled_content');
    }
}
