<?php
if (!defined('ABSPATH')) exit;

/**
 * Shared base class voor alle LAP e-mails.
 * Bevat gedeelde placeholder-vervanging logica en standaard IBAN/account placeholders.
 *
 * @since 1.7.0
 */
abstract class LAP_Email_Base extends WC_Email {

    /**
     * Initialiseer standaard placeholders inclusief IBAN en account_name.
     */
    protected function init_lap_placeholders()
    {
        $this->placeholders = array(
            '{order_number}'  => '',
            '{customer_name}' => '',
            '{payment_url}'   => '',
            '{order_total}'   => '',
            '{iban}'          => get_option('lap_iban', 'NL23RABO0102107661'),
            '{account_name}'  => get_option('lap_account_name', 'Lommers Lederwaren'),
        );
    }

    /**
     * Vul placeholders met order data.
     *
     * @param WC_Order $order
     */
    protected function fill_placeholders($order)
    {
        $this->placeholders['{order_number}']  = $order->get_order_number();
        $this->placeholders['{customer_name}'] = $order->get_billing_first_name();
        $this->placeholders['{payment_url}']   = $order->get_checkout_payment_url();
        $this->placeholders['{order_total}']   = wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency())));
    }

    /**
     * Vervang placeholders in content voor HTML context.
     *
     * @param string   $content   Template content met placeholders
     * @param WC_Order $order     Order object
     * @param bool     $use_button True = payment_url wordt een button, False = een link
     * @return string
     */
    protected function replace_placeholders_html($content, $order, $use_button = true)
    {
        $payment_url = $order->get_checkout_payment_url();

        $content = str_replace('{order_number}', esc_html($order->get_order_number()), $content);
        $content = str_replace('{customer_name}', esc_html($order->get_billing_first_name()), $content);

        if ($use_button) {
            $button_html = '<table cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;"><tr><td align="center" bgcolor="#7f54b3" style="border-radius:4px;"><a href="' . esc_url($payment_url) . '" target="_blank" style="display:inline-block;padding:14px 30px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:4px;">Nu betalen</a></td></tr></table>';
            $content = str_replace('{payment_url}', $button_html, $content);
        } else {
            $content = str_replace('{payment_url}', '<a href="' . esc_url($payment_url) . '" style="color:#2271b1;text-decoration:underline;">' . esc_html($payment_url) . '</a>', $content);
        }

        $content = str_replace('{order_total}', esc_html(wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency())))), $content);
        $content = str_replace('{iban}', esc_html(get_option('lap_iban', 'NL23RABO0102107661')), $content);
        $content = str_replace('{account_name}', esc_html(get_option('lap_account_name', 'Lommers Lederwaren')), $content);

        return $content;
    }

    /**
     * Vervang placeholders in content voor plain text context.
     *
     * @param string   $content Template content met placeholders
     * @param WC_Order $order   Order object
     * @return string
     */
    protected function replace_placeholders_plain($content, $order)
    {
        $content = str_replace('{order_number}', $order->get_order_number(), $content);
        $content = str_replace('{customer_name}', $order->get_billing_first_name(), $content);
        $content = str_replace('{payment_url}', $order->get_checkout_payment_url(), $content);
        $content = str_replace('{order_total}', wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency()))), $content);
        $content = str_replace('{iban}', get_option('lap_iban', 'NL23RABO0102107661'), $content);
        $content = str_replace('{account_name}', get_option('lap_account_name', 'Lommers Lederwaren'), $content);

        return $content;
    }

    /**
     * Genereer HTML content met custom template support.
     *
     * @param string $option_key     De option key voor custom content (bijv. 'lap_email_request_payment_content')
     * @param bool   $use_button     True = payment_url wordt een button
     * @return string
     */
    protected function get_custom_or_template_html($option_key, $use_button = true)
    {
        $custom_content = get_option($option_key, '');

        if ($custom_content && $this->object) {
            $content = $this->replace_placeholders_html($custom_content, $this->object, $use_button);

            ob_start();
            do_action('woocommerce_email_header', $this->get_heading(), $this);
            echo wp_kses_post(wpautop($content));
            do_action('woocommerce_email_footer', $this);
            return ob_get_clean();
        }

        // Standaard template
        ob_start();
        wc_get_template(
            $this->template_html,
            array(
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this,
            ),
            '',
            LAP_PLUGIN_DIR . 'templates/'
        );
        return ob_get_clean();
    }

    /**
     * Genereer plain text content met custom template support.
     *
     * @param string $option_key De option key voor custom content
     * @return string
     */
    protected function get_custom_or_template_plain($option_key)
    {
        $custom_content = get_option($option_key, '');

        if ($custom_content && $this->object) {
            $content = $this->replace_placeholders_plain($custom_content, $this->object);
            return $this->get_heading() . "\n\n" . $content;
        }

        // Standaard template
        ob_start();
        wc_get_template(
            $this->template_plain,
            array(
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this,
            ),
            '',
            LAP_PLUGIN_DIR . 'templates/'
        );
        return ob_get_clean();
    }
}
