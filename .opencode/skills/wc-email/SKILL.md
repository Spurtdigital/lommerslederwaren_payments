---
name: wc-email
description: Instructies voor het toevoegen of aanpassen van WooCommerce e-mail classes in de Lommers plugin
---

## WooCommerce E-mail Class Toevoegen/Aanpassen

### Bestaande E-mail Classes

| Class | ID | Bestand | Template |
|-------|----|---------|----------|
| `WC_Email_Request_Payment` | `request_payment` | `class-wc-email-request-payment.php` | `emails/request-payment.php` |
| `WC_Email_Request_Payment_Reminder` | `request_payment_reminder` | `class-wc-email-request-payment-reminder.php` | `emails/request-payment-reminder.php` |
| `LAP_Email_Order_Cancelled` | `lap_order_cancelled` | `class-wc-email-order-cancelled.php` | `emails/order-cancelled.php` |

### Nieuwe E-mail Class Aanmaken

#### Stap 1: Class bestand
Maak `includes/class-wc-email-{naam}.php` of `includes/class-lap-email-{naam}.php`:

```php
<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('LAP_Email_{Naam}')) :

class LAP_Email_{Naam} extends WC_Email {

    public function __construct() {
        $this->id             = 'lap_{naam}';
        $this->title          = __('Titel (admin)', 'lommers-approval');
        $this->description    = __('Beschrijving voor admin', 'lommers-approval');

        $custom_subject = get_option('lap_email_{naam}_subject', '');
        $custom_heading = get_option('lap_email_{naam}_heading', '');

        $this->heading        = $custom_heading ? $custom_heading : __('Default heading', 'lommers-approval');
        $this->subject        = $custom_subject ? $custom_subject : __('Default subject #{order_number}', 'lommers-approval');

        $this->template_html  = 'emails/{naam}.php';
        $this->template_plain = 'emails/plain/{naam}.php';
        $this->customer_email = true;

        parent::__construct();

        $this->enabled = 'yes';
        $this->placeholders = array(
            '{order_number}' => '',
            '{customer_name}' => '',
            '{payment_url}' => '',
            '{order_total}' => '',
            '{iban}' => 'NL23RABO0102107661',
            '{account_name}' => 'Lommers Lederwaren'
        );
    }

    public function trigger($order_id, $order = false) {
        if (!$order && $order_id) { $order = wc_get_order($order_id); }
        if (!$order) return;

        $this->object    = $order;
        $this->recipient = $order->get_billing_email();

        // Vul placeholders
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{customer_name}'] = $order->get_billing_first_name();
        $this->placeholders['{payment_url}'] = $order->get_checkout_payment_url();
        $this->placeholders['{order_total}'] = wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency())));

        if (!$this->get_recipient()) {
            lap_log('email_{naam} no recipient #' . $order->get_id(), 'warning');
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

    public function get_content_html() {
        $custom_content = get_option('lap_email_{naam}_content', '');

        if ($custom_content && $this->object) {
            // Replace placeholders + wrap in WC template
            $content = $this->replace_placeholders($custom_content, true);
            ob_start();
            do_action('woocommerce_email_header', $this->get_heading(), $this);
            echo wpautop($content);
            do_action('woocommerce_email_footer', $this);
            return ob_get_clean();
        }

        ob_start();
        wc_get_template($this->template_html, array(
            'order'         => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => false,
            'email'         => $this,
        ), '', LAP_PLUGIN_DIR . 'templates/');
        return ob_get_clean();
    }

    public function get_content_plain() {
        $custom_content = get_option('lap_email_{naam}_content', '');

        if ($custom_content && $this->object) {
            $content = $this->replace_placeholders($custom_content, false);
            return $this->get_heading() . "\n\n" . $content;
        }

        ob_start();
        wc_get_template($this->template_plain, array(
            'order'         => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => true,
            'email'         => $this,
        ), '', LAP_PLUGIN_DIR . 'templates/');
        return ob_get_clean();
    }

    private function replace_placeholders($content, $html = true) {
        $content = str_replace('{order_number}', $this->object->get_order_number(), $content);
        $content = str_replace('{customer_name}', $this->object->get_billing_first_name(), $content);
        $content = str_replace('{order_total}', wp_strip_all_tags(wc_price($this->object->get_total(), array('currency' => $this->object->get_currency()))), $content);
        $content = str_replace('{iban}', 'NL23RABO0102107661', $content);
        $content = str_replace('{account_name}', 'Lommers Lederwaren', $content);

        if ($html) {
            $payment_url = $this->object->get_checkout_payment_url();
            $button = '<table cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;"><tr><td align="center" bgcolor="#7f54b3" style="border-radius:4px;"><a href="' . esc_url($payment_url) . '" style="display:inline-block;padding:14px 30px;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:4px;">Nu betalen</a></td></tr></table>';
            $content = str_replace('{payment_url}', $button, $content);
        } else {
            $content = str_replace('{payment_url}', $this->object->get_checkout_payment_url(), $content);
        }

        return $content;
    }
}

endif;
```

#### Stap 2: HTML Template
Maak `templates/emails/{naam}.php`:
- Begin met `defined('ABSPATH') || exit;`
- Gebruik `do_action('woocommerce_email_header', $email_heading, $email);`
- Eindig met `do_action('woocommerce_email_footer', $email);`
- Escape alle output: `esc_html()`, `esc_url()`, `wp_kses_post()`

#### Stap 3: Plain Text Template
Maak `templates/emails/plain/{naam}.php`:
- Zelfde structuur maar zonder HTML
- Begin met `echo $email_heading . "\n\n";`

#### Stap 4: Registreer de class
In `lommers-approval-payments.php`, voeg toe aan het `woocommerce_email_classes` filter (regel 134):
```php
require_once LAP_PLUGIN_DIR . 'includes/class-lap-email-{naam}.php';
if (class_exists('LAP_Email_{Naam}')) {
    $emails['LAP_Email_{Naam}'] = new LAP_Email_{Naam}();
}
```

#### Stap 5: Optioneel - Custom template settings
Voeg toe aan `LAP_Settings::register_settings()` in `class-lap-settings.php`:
```php
register_setting('lap_settings_group', 'lap_email_{naam}_subject', array('type' => 'string', 'default' => '', 'capability' => 'manage_woocommerce'));
register_setting('lap_settings_group', 'lap_email_{naam}_heading', array('type' => 'string', 'default' => '', 'capability' => 'manage_woocommerce'));
register_setting('lap_settings_group', 'lap_email_{naam}_content', array('type' => 'string', 'default' => '', 'capability' => 'manage_woocommerce'));
```

### Belangrijke regels
- Gebruik ALTIJD `lap_send_wc_email()` om e-mails te versturen (voor logging + fallback)
- Voorkom dubbele e-mails: check of status change hook al een mail stuurt
- IBAN `NL23RABO0102107661` en account `Lommers Lederwaren` zijn hardcoded
- Alle strings in het Nederlands met text domain `'lommers-approval'`
