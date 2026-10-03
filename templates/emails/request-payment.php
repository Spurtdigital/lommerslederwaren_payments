<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var WC_Email $email */
/** @var string $email_heading */

$payment_url = $order->get_checkout_payment_url();
$amount_html = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
$order_no    = $order->get_order_number();

$iban      = get_option('lap_iban', 'NL23RABO0102107661');
$acc_name  = get_option('lap_account_name', 'Lommers Lederwaren');
$reference = 'Bestelling ' . $order_no;

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>Bedankt voor je bestelling. De tas is op voorraad en voor jou gereserveerd.</p>

<p>Wanneer je <strong><?php echo wp_kses_post($amount_html); ?></strong> overmaakt op
    <strong><?php echo esc_html($iban); ?></strong> t.n.v. <strong><?php echo esc_html($acc_name); ?></strong>,
    verzenden wij de tas nadat het bedrag op onze rekening is bijgeschreven.
</p>

<p><em>Vermeld bij de overschrijving:</em> <strong><?php echo esc_html($reference); ?></strong></p>

<hr style="border:0;border-top:1px solid #eee;margin:18px 0;" />

<p>Je kunt ook direct online betalen via iDeal of een van de andere betaalmogelijkheden</p>

<p style="margin:16px 0;">
    <a href="<?php echo esc_url($payment_url); ?>"
        style="display:inline-block;padding:12px 18px;text-decoration:none;border-radius:6px;background:#0a84ff;color:#fff;">
        Direct betalen
    </a>
</p>

<p>Na betaling ontvang je automatisch een bevestiging.</p>

<?php
do_action('woocommerce_email_order_details', $order, false, false, $email);
do_action('woocommerce_email_footer', $email);