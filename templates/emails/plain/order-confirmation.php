<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var string $email_heading */

$order_no = $order->get_order_number();

echo esc_html($email_heading) . "\n\n";
?>
Beste <?php echo esc_html($order->get_billing_first_name()); ?>,

Bedankt voor je bestelling #<?php echo esc_html($order_no); ?>.
We gaan nu controleren of alle artikelen op voorraad zijn.

Je ontvangt zo snel mogelijk bericht van ons.
