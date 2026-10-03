<?php
defined('ABSPATH') || exit;
/** @var string $email_heading */

echo esc_html($email_heading) . "\n\n";
?>
Geachte heer/mevrouw,

Onlangs heeft u van ons een betaalherinnering ontvangen. Wij willen u laten weten dat deze herinnering onterecht is verstuurd en dat u deze volledig kunt negeren.

Door een technische fout in onze website zijn de afgelopen 24 uur automatisch betaalherinneringen verstuurd naar klanten bij wie geen openstaande betalingen staan. Wij betreuren dit ten zeerste en bieden u hiervoor onze oprechte excuses aan.

U hoeft geen actie te ondernemen. Mocht u naar aanleiding van deze e-mail vragen hebben, dan staan wij uiteraard voor u klaar.

Met vriendelijke groet,
<?php echo esc_html(get_option('lap_account_name', 'Lommers Lederwaren')); ?>
