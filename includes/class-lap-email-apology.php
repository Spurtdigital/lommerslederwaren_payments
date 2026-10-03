<?php
if (!defined('ABSPATH')) exit;

/**
 * Excuusmail voor onterecht verstuurde betaalherinneringen.
 * Deze mail is NIET gekoppeld aan een order — hij wordt verstuurd naar
 * een los e-mailadres via de bulk-trigger in de instellingenpagina.
 *
 * @since 1.7.1
 */
if (!class_exists('LAP_Email_Apology')) :

class LAP_Email_Apology extends WC_Email {

    public function __construct()
    {
        $this->id             = 'lap_apology';
        $this->title          = __('Excuusmail (onterechte herinnering)', 'lommers-approval');
        $this->description    = __('Verstuur een excuus naar klanten die onterecht een betaalherinnering hebben ontvangen.', 'lommers-approval');
        $this->template_html  = 'emails/apology.php';
        $this->template_plain = 'emails/plain/apology.php';
        $this->customer_email = true;
        $this->enabled        = 'yes';

        $this->subject = get_option(
            'lap_email_apology_subject',
            __('Onze excuses voor de onterechte herinnering', 'lommers-approval')
        );
        $this->heading = get_option(
            'lap_email_apology_heading',
            __('Onze excuses', 'lommers-approval')
        );

        parent::__construct();

        // Forceer enabled na parent::__construct() zodat DB-waarde niet wint
        $this->enabled = 'yes';
    }

    /**
     * Altijd enabled — negeer WooCommerce admin toggle.
     *
     * @return bool
     */
    public function is_enabled()
    {
        return true;
    }

    /**
     * Verstuur excuusmail naar een los e-mailadres (geen order vereist).
     *
     * @param string $recipient E-mailadres van de ontvanger
     * @return bool
     */
    public function trigger($recipient)
    {
        if (empty($recipient) || !is_email($recipient)) {
            lap_log('apology email skip: ongeldig e-mailadres: ' . $recipient, 'warning');
            return false;
        }

        $this->recipient = $recipient;
        $this->object    = null;

        lap_log('apology trigger: sending to ' . $recipient . ' | enabled=' . ($this->is_enabled() ? 'yes' : 'no') . ' | subject=' . $this->get_subject());

        // Vang wp_mail fouten op voor betere diagnostiek
        $mail_error = null;
        $error_handler = function ($wp_error) use (&$mail_error) {
            $mail_error = $wp_error->get_error_message();
        };
        add_action('wp_mail_failed', $error_handler);

        $result = $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );

        remove_action('wp_mail_failed', $error_handler);

        if (!$result && $mail_error) {
            lap_log('apology wp_mail_failed: ' . $mail_error, 'warning');
            // Reset PHPMailer instantie zodat de volgende mail een frisse verbinding krijgt
            if (isset(WC()->mailer()->mailer)) {
                WC()->mailer()->mailer = null;
            }
        }

        lap_log('apology trigger result: ' . ($result ? 'success' : 'failed') . ' for ' . $recipient, $result ? 'info' : 'warning');

        // Reset PHPMailer na elke mail zodat volgende mail frisse SMTP-verbinding krijgt
        if (isset(WC()->mailer()->mailer)) {
            WC()->mailer()->mailer = null;
        }

        return $result;
    }

    /**
     * @return string HTML content
     */
    public function get_content_html()
    {
        ob_start();
        wc_get_template(
            $this->template_html,
            array(
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
     * @return string Plain text content
     */
    public function get_content_plain()
    {
        ob_start();
        wc_get_template(
            $this->template_plain,
            array(
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

endif;
