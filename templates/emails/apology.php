<?php
defined('ABSPATH') || exit;
/** @var string $email_heading */
/** @var WC_Email $email */

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>Geachte heer/mevrouw,</p>

<p>Onlangs heeft u van ons een betaalherinnering ontvangen. Wij willen u laten weten dat deze herinnering onterecht is verstuurd en dat u deze volledig kunt negeren.</p>

<p>Door een technische fout in onze website zijn de afgelopen 24 uur automatisch betaalherinneringen verstuurd naar klanten bij wie geen openstaande betalingen staan. Wij betreuren dit ten zeerste en bieden u hiervoor onze oprechte excuses aan.</p>

<p>U hoeft geen actie te ondernemen. Mocht u naar aanleiding van deze e-mail vragen hebben, dan staan wij uiteraard voor u klaar.</p>

<p>Met vriendelijke groet,<br>
<?php echo esc_html(get_option('lap_account_name', 'Lommers Lederwaren')); ?></p>

<?php
do_action('woocommerce_email_footer', $email);
