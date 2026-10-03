---
name: wc-status
description: Instructies voor het toevoegen van custom WooCommerce order statussen aan de Lommers plugin
---

## Custom WooCommerce Order Status Toevoegen

### Bestaande Custom Statussen

| Status slug | Label | Registratie |
|-------------|-------|-------------|
| `wc-ter-goedkeuring` | Ter goedkeuring | `lommers-approval-payments.php:70` |
| `wc-betaling-verzocht` | Betaling verzocht | `lommers-approval-payments.php:77` |

### Nieuwe Status Toevoegen

#### Stap 1: Registreer de status via `register_post_status()`
In de `init` hook binnen `plugins_loaded` (`lommers-approval-payments.php:69`):

```php
register_post_status('wc-{slug}', array(
    'label' => _x('{Label}', 'Order status', 'lommers-approval'),
    'public' => true,
    'show_in_admin_all_list' => true,
    'show_in_admin_status_list' => true,
    'label_count' => _n_noop(
        '{Label} <span class="count">(%s)</span>',
        '{Label} <span class="count">(%s)</span>',
        'lommers-approval'
    ),
));
```

#### Stap 2: Voeg toe aan HPOS-compatibele registratie
In het `woocommerce_register_shop_order_post_statuses` filter (`lommers-approval-payments.php:85`):

```php
$st['wc-{slug}'] = array(
    'label' => _x('{Label}', 'Order status', 'lommers-approval'),
    'public' => true,
    'show_in_admin_all_list' => true,
    'show_in_admin_status_list' => true,
    'label_count' => _n_noop(
        '{Label} <span class="count">(%s)</span>',
        '{Label} <span class="count">(%s)</span>',
        'lommers-approval'
    ),
);
```

#### Stap 3: Voeg toe aan status dropdown
In het `wc_order_statuses` filter (`lommers-approval-payments.php:102`):

```php
$out['wc-{slug}'] = _x('{Label}', 'Order status', 'lommers-approval');
```

Plaats na de gewenste positie in de lijst (momenteel worden custom statussen na 'wc-processing' ingevoegd).

#### Stap 4: Update gateway visibility (indien nodig)
Als orders met deze status moeten kunnen betalen, voeg toe aan:
- `woocommerce_valid_order_statuses_for_payment` filter (`lommers-approval-payments.php:276`)
- Gateway zichtbaarheidsregels in `woocommerce_available_payment_gateways` (`lommers-approval-payments.php:295`)

```php
$allowed = array('betaling-verzocht', 'on-hold', 'pending', 'failed', 'cancelled', '{slug}');
```

#### Stap 5: Update paid-bump logica (indien nodig)
Als deze status als "onbetaald" moet tellen voor de paid-bump:
- `woocommerce_payment_complete` handler (`lommers-approval-payments.php:389`)
- `woocommerce_order_status_changed` handler (`lommers-approval-payments.php:404`)
- `lap_force_paid_bump_now()` functie (`lommers-approval-payments.php:424`)

```php
$unpaidish = array('betaling-verzocht', 'on-hold', 'pending', 'failed', 'cancelled', 'ter-goedkeuring', '{slug}');
```

#### Stap 6: Voeg e-mail triggers toe (indien nodig)
Koppel e-mail verzending aan statuswijziging:

```php
add_action('woocommerce_order_status_{slug}', function ($order_id, $order) {
    lap_send_wc_email('email_id', $order, 'status-{slug}');
}, 20, 2);
```

### Checklist na toevoegen
- [ ] Status verschijnt in admin order lijst
- [ ] Status verschijnt in order status dropdown
- [ ] Status label is correct (Nederlands)
- [ ] Gateway zichtbaarheid klopt voor deze status
- [ ] Paid-bump logica werkt correct
- [ ] E-mails worden verstuurd bij statuswijziging
- [ ] Dashboard statistieken tellen deze status correct mee
- [ ] Herinneringen/auto-cancel houden rekening met deze status
