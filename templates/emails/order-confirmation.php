<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var WC_Email $email */
/** @var string $email_heading */

$order_no = $order->get_order_number();

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>Beste <?php echo esc_html($order->get_billing_first_name()); ?>,</p>

<p>Bedankt voor je bestelling <strong>#<?php echo esc_html($order_no); ?></strong>.
    We gaan nu controleren of alle artikelen op voorraad zijn.</p>

<p>Je ontvangt zo snel mogelijk bericht van ons.</p>

<?php
do_action('woocommerce_email_order_details', $order, false, false, $email);
do_action('woocommerce_email_footer', $email);
