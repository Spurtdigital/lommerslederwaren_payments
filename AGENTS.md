# AGENTS.md - Lommers Approval Before Payment Plugin

## Project Overview

**Type:** WordPress/WooCommerce Plugin  
**Name:** Lommers – Approval Before Payment  
**Version:** 1.7.0  
**Purpose:** Handmatige goedkeuringsworkflow voor Lommers Lederwaren. Orders worden gecontroleerd op voorraad voordat klanten een betaalverzoek krijgen. Inclusief custom statussen, e-mailnotificaties, automatische herinneringen, auto-annulering, Mollie-failsafes en een admin dashboard.  
**HPOS:** Compatibel (declaratie via `before_woocommerce_init`)

**Taal:** Nederlands (alle user-facing strings)

## File Map (Quick Reference)

| Bestand | Regels | Verantwoordelijkheid |
|---------|--------|---------------------|
| `lommers-approval-payments.php` | 945 | Bootstrap, hooks, core logic, status registratie, cron runners, versie-migratie |
| `includes/class-lap-settings.php` | 434 | Settings page, register_settings, render_page, email template instellingen |
| `includes/class-lap-dashboard.php` | 1906 | Dashboard, statistieken, grafieken, quick actions, donut chart, dummy data, transient caching |
| `includes/class-lap-email-base.php` | 157 | Gedeelde base class voor alle e-mails: placeholders, template rendering |
| `includes/class-lap-email-request-payment.php` | 87 | E-mail: Betaling verzocht (extends LAP_Email_Base) |
| `includes/class-lap-email-reminder.php` | 88 | E-mail: Herinnering (extends LAP_Email_Base) |
| `includes/class-lap-email-cancelled.php` | 90 | E-mail: Geannuleerd (extends LAP_Email_Base) |
| `includes/class-lap-email-order-confirmation.php` | 89 | E-mail: Bevestiging ter goedkeuring (extends LAP_Email_Base) |
| `includes/class-lap-gateway-approval.php` | 58 | "Betaalverzoek (na goedkeuring)" gateway |
| `includes/class-lap-gateway-testpay.php` | 62 | Test betaal-gateway (testmodus) |
| `templates/emails/*.php` | 4 stuks | HTML e-mail templates (request-payment, reminder, cancelled, order-confirmation) |
| `templates/emails/plain/*.php` | 4 stuks | Plain text e-mail templates |

## Build/Test/Lint Commands

### No Build System
Geen build process - pure PHP die direct in WordPress draait.

### Testing

**Geautomatiseerd testen:** Er is nog geen geautomatiseerd test suite. Zie "Test Strategie" hieronder voor hoe dit op te zetten.

**Handmatig testen (huidige workflow):**
1. Schakel testmodus in: Goedkeuren & Betalen -> Instellingen -> "Testmodus inschakelen"
2. Schakel debug logging in
3. Test order flow:
   - Maak order aan -> controleer status "Ter goedkeuring"
   - Keur goed via order-actie -> controleer "Betaling verzocht"
   - Controleer dat klant betaalmail ontvangt
4. Test betaling: order-actie "TEST: Markeer als betaald (simulatie)"
5. Test webhook: `?lap_sim_webhook={ORDER_ID}&_wpnonce={nonce}`
6. Test reminders: Klik "Nu uitvoeren" op instellingenpagina
7. Test auto-cancel: Klik "Nu uitvoeren" bij auto-cancel sectie
8. Controleer logs: WooCommerce -> Status -> Logs -> filter `lommers-approval`

### Linting
**Niet geconfigureerd.** Aanbevolen:
```bash
# PHP CodeSniffer (WordPress standaard)
phpcs --standard=WordPress --extensions=php --ignore=node_modules .

# PHPStan (statische analyse)
phpstan analyse --level=5 .
```

## Test Strategie

### Pre-deployment checklist

Voer deze checks uit VOOR elke deployment:

**1. Syntax check (alle PHP bestanden):**
```bash
find . -name "*.php" -exec php -l {} \;
```

**2. Functionele checks (handmatig in WP admin):**

| # | Test | Verwacht resultaat | Status |
|---|------|--------------------|--------|
| 1 | Nieuwe order plaatsen via checkout | Status wordt "Ter goedkeuring", bevestigingsmail naar klant, admin-mail "Nieuwe bestelling" |  |
| 2 | Order goedkeuren (order-actie) | Status wordt "Betaling verzocht", betaalmail verstuurd |  |
| 3 | Betaallink openen | `/order-pay` pagina toont echte gateways (Mollie etc.), NIET "Na goedkeuring" |  |
| 4 | Betaling simuleren (testmodus) | Order gaat naar "In behandeling" of "Voltooid" |  |
| 5 | Herinnering handmatig uitvoeren | Herinnering verstuurd voor kwalificerende orders, `_lap_reminder_sent` meta gezet |  |
| 6 | Auto-cancel handmatig uitvoeren | Oude onbetaalde orders geannuleerd, annuleringsmail verstuurd |  |
| 7 | Dashboard laden | Statistieken kloppen, grafieken (omzet, activiteit, donut) tonen, quick actions werken, geen PHP errors |  |
| 8 | Instellingen opslaan | Alle opties correct opgeslagen |  |
| 9 | Testmodus uit | Test-gateway verdwijnt, test admin-acties verdwijnen |  |
| 10 | E-mail placeholders | `{order_number}`, `{customer_name}`, `{payment_url}` correct vervangen |  |

**3. Regressie-checks:**

| Check | Locatie | Wat te verifiëren |
|-------|---------|-------------------|
| ABSPATH guard | Alle PHP bestanden | `if (!defined('ABSPATH')) exit;` bovenaan |
| Nonce verificatie | `lommers-approval-payments.php:371,692,705` | Alle admin URL-acties gebruiken nonce |
| Capability checks | Alle admin functies | `current_user_can('manage_woocommerce')` |
| Output escaping | Templates en dashboard | `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` |

**4. Edge cases om te testen:**
- Order zonder billing e-mail -> moet graceful skippen, niet crashen
- Dubbele goedkeuring (twee keer klikken) -> geen dubbele e-mails
- Order die al betaald is -> herinnering wordt overgeslagen
- Order met transaction ID -> auto-cancel wordt overgeslagen
- Mollie betaling terwijl status nog "betaling-verzocht" -> moet automatisch doorzetten

### Toekomstig: Geautomatiseerde tests

Voor geautomatiseerde tests wordt aanbevolen:
- **PHPUnit** met **WP_Mock** of **Brain Monkey** voor unit tests (mock WordPress functies)
- **WP-CLI** test commands voor integratie tests
- Focus eerst op de kritische functies: `lap_send_wc_email()`, `lap_force_paid_bump_now()`, reminder/cancel logica

## Code Style Guidelines

### Naming Conventions

**Prefix:** `lap_` (lowercase) of `LAP_` (uppercase) voor alle code.

| Type | Conventie | Voorbeeld |
|------|-----------|-----------|
| Constants | `LAP_UPPER_CASE` | `LAP_PLUGIN_DIR` |
| Functions | `lap_snake_case` | `lap_send_wc_email()` |
| Classes | `LAP_PascalCase` | `LAP_Gateway_Approval` |
| Email classes | `LAP_Email_*` | `LAP_Email_Request_Payment` |
| Files | `class-{kebab-case}.php` | `class-lap-gateway-approval.php` |
| Options | `lap_snake_case` | `lap_reminder_enabled` |
| Order meta | `_lap_snake_case` | `_lap_managed_order` |
| Custom statuses | `wc-kebab-case` | `wc-ter-goedkeuring` |
| Cron events | `lap_snake_case` | `lap_daily_reminders` |

### Formatting

**Indentation:** 4 spaces (geen tabs)

**Braces:**
```php
// Closures/anonymous functions - opening brace op zelfde regel
add_action('init', function () {
    // code
});

// Class methods - opening brace op volgende regel
public function process_payment($order_id)
{
    // code
}
```

**Array Syntax:** Gebruik `array()` (NIET `[]`) voor consistentie met bestaande code.
```php
$array = array('key' => 'value');  // Correct
$array = ['key' => 'value'];       // Vermijden
```
> Let op: er zijn enkele plekken waar `[]` wordt gebruikt (admin bar node, settings registratie). Bij nieuwe code altijd `array()` gebruiken.

**String Quotes:**
```php
$simple = 'Gebruik single quotes';
$interpolated = "Double quotes voor {$variables}";
```

### Imports and Dependencies

**Geen externe dependencies.** Geen Composer, geen npm.

**File inclusion:**
```php
require_once LAP_PLUGIN_DIR . 'includes/class-lap-settings.php';
```

### Documentation

**WordPress DocBlock style:**
```php
/**
 * Verstuur WooCommerce e-mail met fallback naar wc_mail
 *
 * @param string $email_id WC_Email class id (bijv. 'request_payment')
 * @param WC_Order|int $order Order object of ID
 * @param string $reason Reden voor logging
 * @return bool True als verzonden
 */
function lap_send_wc_email($email_id, $order, $reason = '') { }
```

### Error Handling

**Guard clauses (early return):**
```php
if (!$order instanceof WC_Order) {
    lap_log("Invalid order", 'error');
    return false;
}
if ($order->is_paid()) return;
```

**Logging levels:**
```php
lap_log("Informational message", 'info');    // Alleen bij debug logging aan
lap_log("Warning: check this", 'warning');   // Altijd gelogd
lap_log("Error occurred", 'error');          // Altijd gelogd
```

### Security

**VERPLICHT in elk nieuw PHP bestand:**
```php
if (!defined('ABSPATH')) exit;
```

**VERPLICHT bij admin acties:**
```php
wp_verify_nonce($nonce, 'lap_action_name');
if (!current_user_can('manage_woocommerce')) return;
```

**Output escaping:**
```php
echo esc_html($text);           // Plain text
echo esc_attr($attribute);      // HTML attributes
echo esc_url($url);             // URLs
echo wp_kses_post($content);    // HTML content met beperkte tags
```

**Input sanitization:**
```php
absint($value);                 // Positief integer
intval($value);                 // Integer
wc_clean($value);               // WooCommerce sanitization
sanitize_text_field($value);    // Tekst velden
wp_unslash($value);             // Slashes verwijderen
```

### Language and Internationalization

**Text Domain:** `'lommers-approval'` (altijd!)

**Alle user-facing strings in het Nederlands:**
```php
__('Tekst', 'lommers-approval');
_x('Tekst', 'Context', 'lommers-approval');
esc_html__('Tekst', 'lommers-approval');
```

## Architecture Patterns

### Bootstrap Flow

```
lommers-approval-payments.php
    |
    +-- define constants (LAP_PLUGIN_FILE, LAP_PLUGIN_DIR, LAP_PLUGIN_URL, LAP_VERSION)
    +-- require: class-lap-settings.php -> LAP_Settings::init()
    +-- require: class-lap-dashboard.php -> LAP_Dashboard::init()
    +--     LAP_Dashboard::handle_quick_actions() (admin_init hook)
    +--     Quick actions: goedkeuren (status -> betaling-verzocht + email)
    +--     Quick actions: herinnering sturen (lap_send_wc_email)
    +--     Nonce: lap_dash_{action}_{order_id}
    +-- define: lap_log()
    +-- admin_bar_menu: testmode badge
    +-- HPOS compatibiliteit declaratie (before_woocommerce_init)
    +-- Versie-migratie mechanisme (admin_init)
    |
    +-- plugins_loaded (priority 0):
        +-- Check WooCommerce exists
        +-- Register custom statuses (init hook)
        +-- Register status filters (wc_order_statuses, woocommerce_register_shop_order_post_statuses)
        +-- Checkout order processed -> set ter-goedkeuring
        +-- require: class-lap-email-base.php (EERST)
        +-- require: class-lap-email-request-payment.php
        +-- require: class-lap-email-reminder.php
        +-- require: class-lap-email-cancelled.php
        +-- require: class-lap-email-order-confirmation.php
        +-- Register email classes (woocommerce_email_classes)
        +-- Define lap_send_wc_email()
        +-- Register admin order actions
        +-- Payment gateway visibility (prio 9999)
        +-- Blocks gateway filter (prio 9999)
        +-- Status change -> send email
        +-- Webhook simulator (init)
        +-- Payment complete safety net
        +-- Paid-bump on status change (prio 999)
        +-- Mollie note detection (wp_insert_comment)
        +-- Thank you page detection (template_redirect)
        +-- Reminder scheduler + runner
        +-- Auto-cancel scheduler + runner (met stock restoration)
        +-- Manual run handlers (init)
    |
    +-- woocommerce_new_order: mark with _lap_managed_order
    +-- Status changed to ter-goedkeuring: send LAP_Email_Order_Confirmation + mark meta
    +-- Status pending_to_ter-goedkeuring: trigger WC_Email_New_Order
    +-- Status changed to processing: trigger WC_Email_Customer_Processing_Order
    +-- Status changed to cancelled: trigger LAP_Email_Cancelled
    +-- Cache invalidatie: woocommerce_order_status_changed verwijdert 9 transients
```

### Class Patterns

**Static Utility Classes** (Settings, Dashboard):
```php
class LAP_Settings {
    public static function init() { add_action(...); }
    public static function register_settings() { }
    public static function render_page() { }
}
LAP_Settings::init();
```

**Dashboard Class** (LAP_Dashboard):
```php
class LAP_Dashboard {
    public static function init() { }
    public static function handle_quick_actions() { }  // admin_init hook
    public static function render_page() { }
    // Quick actions: goedkeuren, herinnering sturen (nonce: lap_dash_{action}_{order_id})
    // Helpers: render_kpi_card(), get_svg_icon(), render_comparison(), render_status_donut()
    // Charts: omzetgrafiek (CSS tooltips), stacked activity bars (paid/cancelled/pending), SVG donut
    // Data: get_stats_data(), get_revenue_bars_data(), get_activity_bars_data() + dummy varianten
    // Caching: 9 transients (lap_stats_*, lap_revenue_bars_*, lap_activity_bars_*), 5 min TTL
}
```

**Gateway Classes** (extends WC_Payment_Gateway):
```php
class LAP_Gateway_Approval extends WC_Payment_Gateway {
    public function __construct() { }
    public function init_form_fields() { }
    public function process_payment($order_id) { }
    public function is_available() { }
}
```

**Email Classes** (extends LAP_Email_Base extends WC_Email):
```php
class LAP_Email_Request_Payment extends LAP_Email_Base {
    public function __construct() {
        // Set id, title, description, template
        // Set default subject/heading via get_option() met fallback
        parent::__construct();
        $this->init_lap_placeholders();
    }
    public function trigger($order_id, $order = false) {
        // Load order, fill_placeholders(), send
    }
    // get_content_html() en get_content_plain() via LAP_Email_Base
}
```

**Email Base Class** (gedeelde logica):
```php
class LAP_Email_Base extends WC_Email {
    protected function init_lap_placeholders() { }   // Registreert {order_number}, {customer_name}, etc.
    protected function fill_placeholders($order) { }  // Vult placeholders met order data
    protected function get_custom_or_template_html($option_key) { } // Custom content of template
    public function get_content_html() { }            // Gedeelde HTML rendering
    public function get_content_plain() { }           // Gedeelde plain text rendering
}
```

## Common Pitfalls

1. **ABSPATH vergeten** - Altijd bovenaan elk PHP bestand
2. **Nonce vergeten** - Verplicht voor alle admin URL-acties
3. **Output niet escapen** - Gebruik esc_html(), esc_attr(), esc_url()
4. **Verkeerde text domain** - Altijd `'lommers-approval'`
5. **`[]` ipv `array()`** - Gebruik altijd array() syntax
6. **`lap_` prefix vergeten** - Alle functies/opties/meta keys
7. **Order niet opslaan** - Na `update_meta_data()` altijd `$order->save()` aanroepen
8. **Dubbele emails** - Check of email niet al via status change hook wordt verstuurd
9. **Cron niet opruimen** - Bij deactivatie moeten cron events opgeruimd worden
10. **date() gebruiken** - Gebruik altijd `wp_date()` voor WordPress timezone-awareness

## Key Extension Points

### Nieuwe e-mail toevoegen
1. Maak class in `includes/class-lap-email-{naam}.php` (extend `LAP_Email_Base`)
2. Implementeer `get_default_subject()`, `get_default_heading()`, `get_default_content()`
3. Maak templates in `templates/emails/{naam}.php` en `templates/emails/plain/{naam}.php`
4. Registreer in `woocommerce_email_classes` filter (`lommers-approval-payments.php`)
5. Optioneel: voeg custom template settings toe in `class-lap-settings.php`

### Nieuwe order-actie toevoegen
1. Voeg actie toe aan `woocommerce_order_actions` filter (`lommers-approval-payments.php:211`)
2. Maak handler via `woocommerce_order_action_{actie_naam}` hook

### Nieuwe instelling toevoegen
1. Registreer via `register_setting()` in `LAP_Settings::register_settings()`
2. Voeg UI toe via `add_settings_field()` in dezelfde methode
3. Gebruik via `get_option('lap_{naam}')` in code

### Nieuwe status toevoegen
1. Registreer via `register_post_status()` in init hook
2. Voeg toe aan `woocommerce_register_shop_order_post_statuses` filter
3. Voeg toe aan `wc_order_statuses` filter
4. Update gateway visibility logic in `woocommerce_available_payment_gateways` filter

## Development Workflow

1. Schakel testmodus in via instellingen
2. Schakel debug logging in
3. Maak code wijzigingen
4. Voer `php -l` syntax check uit op gewijzigde bestanden
5. Test handmatig via WordPress admin (volg test checklist)
6. Controleer logs: WooCommerce -> Status -> Logs
7. Schakel testmodus uit voor productie

## Bekende technische schuld

1. **Inconsistente array syntax** - Merendeel `array()` maar sommige plekken `[]`
2. **Hoofdbestand te groot** - 945 regels in een bestand, zou opgesplitst kunnen worden in classes (LAP_Status, LAP_Order_Actions, LAP_Cron, LAP_Mollie, LAP_Email_Handler)
3. **Uitgecommentarieerde code** - `lap_send_wc_email` in approve-actie (regel ~230)
4. **Multi-reminder niet ondersteund** - Slechts 1 herinnering per order; `_lap_reminder_sent` is boolean ipv counter

## References

- WordPress Coding Standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/
- WooCommerce Gateway API: https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html
- WooCommerce Email API: https://woocommerce.github.io/code-reference/classes/WC-Email.html
- WooCommerce Order API: https://woocommerce.github.io/code-reference/classes/WC-Order.html
- WordPress Cron API: https://developer.wordpress.org/plugins/cron/
