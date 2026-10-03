<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var WC_Email $email */
/** @var string $email_heading */

$payment_url = $order->get_checkout_payment_url();
$amount_html = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
$order_no    = $order->get_order_number();

do_action('woocommerce_email_header', $email_heading, $email);
?>

<strong>Je artikel staat nog steeds voor je gereserveerd!</strong>

<p>We hebben je betaling nog niet ontvangen. Wil je je bestelling afronden?
    Dat kan direct via het betaalverzoek hieronder.</p>
<p>
    Let op: als de betaling niet op tijd binnen is, komt het artikel weer beschikbaar voor andere klanten.
</p>
<p style="margin:16px 0;">
    <a href="<?php echo esc_url($payment_url); ?>"
        style="display:inline-block;padding:12px 18px;text-decoration:none;border-radius:6px;background:#0a84ff;color:#fff;">
        Direct betalen
    </a>
</p>


<?php
do_action('woocommerce_email_order_details', $order, false, false, $email);
do_action('woocommerce_email_footer', $email);