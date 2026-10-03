<?php
if (!defined('ABSPATH')) exit;

class LAP_Email_Shipped extends LAP_Email_Base {

    public function __construct()
    {
        $this->id             = 'lap_order_shipped';
        $this->title          = __('Bestelling onderweg (klant)', 'lommers-approval');
        $this->description    = __('Verstuurd wanneer de orderstatus op "Onderweg" komt.', 'lommers-approval');
        $this->customer_email = true;

        $custom_subject = get_option('lap_email_shipped_subject', '');
        $custom_heading = get_option('lap_email_shipped_heading', '');

        $this->heading = $custom_heading ? $custom_heading : __('Je bestelling is onderweg', 'lommers-approval');
        $this->subject = $custom_subject ? $custom_subject : __('Bestelling #{order_number} is onderweg', 'lommers-approval');

        $this->template_html  = 'emails/shipped.php';
        $this->template_plain = 'emails/plain/shipped.php';

        parent::__construct();

        $this->enabled = 'yes';
        $this->init_lap_placeholders();
    }

    public function trigger($order_id, $order = false)
    {
        if (!$order && $order_id) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        $this->object    = $order;
        $this->recipient = $order->get_billing_email();

        $this->fill_placeholders($order);

        if (!$this->get_recipient()) {
            lap_log('lap_order_shipped no recipient #' . $order->get_id(), 'warning');
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

    public function get_content_html()
    {
        return $this->get_custom_or_template_html('lap_email_shipped_content', false);
    }

    public function get_content_plain()
    {
        return $this->get_custom_or_template_plain('lap_email_shipped_content');
    }

    /**
     * Overschrijf placeholder vervanging om {tracking_code} toe te voegen.
     */
    protected function replace_placeholders_html($content, $order, $use_button = true)
    {
        $content = parent::replace_placeholders_html($content, $order, $use_button);
        $tracking = $order->get_meta('_lap_track_and_trace');
        $content = str_replace('{tracking_code}', esc_html($tracking ? $tracking : ''), $content);
        return $content;
    }

    protected function replace_placeholders_plain($content, $order)
    {
        $content = parent::replace_placeholders_plain($content, $order);
        $tracking = $order->get_meta('_lap_track_and_trace');
        $content = str_replace('{tracking_code}', $tracking ? $tracking : '', $content);
        return $content;
    }
}
