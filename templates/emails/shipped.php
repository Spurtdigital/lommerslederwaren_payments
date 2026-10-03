<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var WC_Email $email */
/** @var string $email_heading */

$order_no   = $order->get_order_number();
$tracking   = $order->get_meta('_lap_track_and_trace');

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>Goed nieuws! Je bestelling #<?php echo esc_html($order_no); ?> is onderweg.</p>

<?php if ($tracking): ?>
<p>Je kunt je pakket volgen met de volgende track & trace code:</p>

<p style="margin:20px 0;padding:14px 20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;text-align:center;font-size:18px;font-weight:600;font-family:monospace;">
    <?php if (strpos($tracking, 'http://') === 0 || strpos($tracking, 'https://') === 0): ?>
        <a href="<?php echo esc_url($tracking); ?>" target="_blank" style="color:#16a34a;text-decoration:underline;"><?php echo esc_html($tracking); ?></a>
    <?php else: ?>
        <?php echo esc_html($tracking); ?>
    <?php endif; ?>
</p>
<?php endif; ?>

<p>Bedankt voor je bestelling en veel plezier ervan!</p>

<p>Met vriendelijke groet,<br>
Lommers Lederwaren</p>

<?php
do_action('woocommerce_email_order_details', $order, false, false, $email);
do_action('woocommerce_email_footer', $email);
