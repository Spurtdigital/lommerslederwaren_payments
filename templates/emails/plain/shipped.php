<?php
defined('ABSPATH') || exit;
/** @var WC_Order $order */
/** @var string $email_heading */

$order_no = $order->get_order_number();
$tracking = $order->get_meta('_lap_track_and_trace');

echo esc_html($email_heading) . "\n\n";
?>
Goed nieuws! Je bestelling #<?php echo esc_html($order_no); ?> is onderweg.
<?php if ($tracking): ?>

Track & trace code: <?php echo esc_html($tracking); ?>
<?php if (strpos($tracking, 'http://') === 0 || strpos($tracking, 'https://') === 0): ?>
Volg je pakket: <?php echo esc_url($tracking); ?>
<?php endif; ?>
<?php endif; ?>


Bedankt voor je bestelling en veel plezier ervan!

Met vriendelijke groet,
Lommers Lederwaren
