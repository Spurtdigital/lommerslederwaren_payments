---
name: wp-deploy
description: Pre-deployment checklist voor de Lommers plugin met PHP syntax checks, security audit en functionele verificatie
---

## Pre-deployment Checklist

Voer de volgende stappen uit VOOR elke deployment van de Lommers Approval Before Payment plugin:

### 1. PHP Syntax Check
Voer een syntax check uit op ALLE PHP bestanden in de plugin:
```bash
find . -name "*.php" -exec php -l {} \;
```
Alle bestanden moeten "No syntax errors detected" tonen.

### 2. ABSPATH Guard Check
Controleer dat elk PHP bestand begint met:
```php
if (!defined('ABSPATH')) exit;
```
Bestanden: `lommers-approval-payments.php`, alle files in `includes/`, alle files in `templates/`.

### 3. Security Audit
Controleer in alle gewijzigde bestanden:
- **Nonce verificatie** bij admin URL-acties (zoek naar `$_GET`, `$_POST` zonder `wp_verify_nonce`)
- **Capability checks** bij admin functies (`current_user_can('manage_woocommerce')`)
- **Output escaping** (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`)
- **Input sanitization** (`absint()`, `wc_clean()`, `sanitize_text_field()`)

### 4. Code Style Check
- Array syntax: `array()` niet `[]`
- Prefix: alle functies `lap_`, classes `LAP_`, options `lap_`, meta `_lap_`
- Text domain: `'lommers-approval'` voor alle __() en _x() calls
- Indentation: 4 spaces, geen tabs
- Strings: alle user-facing strings in het Nederlands

### 5. Functionele Tests
Rapporteer welke functionele tests handmatig moeten worden uitgevoerd op basis van de wijzigingen:
- Order flow (ter-goedkeuring -> betaling-verzocht -> betaald)
- E-mail verzending (controleer placeholders)
- Gateway zichtbaarheid (checkout vs order-pay)
- Herinnering/auto-cancel logica
- Dashboard statistieken
- Mollie failsafes

### 6. Edge Cases
Check specifiek:
- Order zonder billing e-mail -> geen crash
- Dubbele statuswijziging -> geen dubbele e-mails
- Al betaalde order -> herinnering/cancel skip
- Order met transaction ID -> geen onterechte annulering

### 7. Rapportage
Geef een duidelijk overzicht:
- Gevonden problemen (MUST FIX)
- Waarschuwingen (SHOULD FIX)
- Suggesties (NICE TO HAVE)
- Lijst van handmatige tests die uitgevoerd moeten worden
