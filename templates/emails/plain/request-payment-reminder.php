<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var string $email_heading */

$payment_url  = $order->get_checkout_payment_url();
$amount_plain = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
$order_no     = $order->get_order_number();

echo esc_html($email_heading) . "\n\n";
?>
We hebben je bestelling nog niet als betaald geregistreerd.
Rond je bestelling af via:
<?php echo esc_url($payment_url); ?>

Bedrag: <?php echo esc_html($amount_plain); ?>

Heb je al betaald? Dan kun je deze e-mail negeren.
