<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var string $email_heading */

$payment_url  = $order->get_checkout_payment_url();
$amount_plain = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
$order_no     = $order->get_order_number();

$iban      = get_option('lap_iban', 'NL23RABO0102107661');
$acc_name  = get_option('lap_account_name', 'Lommers Lederwaren');
$reference = 'Bestelling ' . $order_no;

echo esc_html($email_heading) . "\n\n";
?>
Bedankt voor je bestelling. De tas is op voorraad en voor jou gereserveerd.

Wanneer je <?php echo esc_html($amount_plain); ?> overmaakt op <?php echo esc_html($iban); ?> t.n.v. <?php echo esc_html($acc_name); ?>,
verzenden wij de tas nadat het bedrag op onze rekening is bijgeschreven.

Vermeld bij de overschrijving: <?php echo esc_html($reference); ?>


Je kunt ook direct online betalen via het betaalverzoek hieronder:
<?php echo esc_url($payment_url); ?>


Na betaling ontvang je automatisch een bevestiging.
