<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var string $email_heading */

$order_no = $order->get_order_number();

echo esc_html($email_heading) . "\n\n";
?>
Beste <?php echo esc_html($order->get_billing_first_name()); ?>,

Je bestelling #<?php echo esc_html($order_no); ?> is geannuleerd.

Als je vragen hebt over deze annulering, neem dan gerust contact met ons op.
