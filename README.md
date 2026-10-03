# Lommers – Approval Before Payment

**Versie:** 1.7.0  
**Voor:** WordPress + WooCommerce  
**Text Domain:** lommers-approval  
**Vereisten:** WordPress 5.8+, WooCommerce 6.0+, PHP 7.4+  
**Compatibel met:** Mollie for WooCommerce, WooCommerce Blocks, HPOS (High-Performance Order Storage)

---

## Wat doet deze plugin?

Deze plugin voegt een **handmatige goedkeuringsworkflow** toe aan WooCommerce, waarbij bestellingen eerst worden gecontroleerd voordat klanten kunnen betalen. Specifiek ontworpen voor Lommers Lederwaren: voorraadcontrole van tassen voor betaling, met automatische betaalverzoeken, herinneringen en annuleringen.

**Kernflow:**
1. Klant bestelt zonder direct te betalen
2. Admin controleert voorraad
3. Admin keurt goed -> klant krijgt betaallink per e-mail
4. Klant betaalt via iDeal/Mollie
5. Bij niet-betalen: automatische herinnering en eventueel annulering

---

## Welk probleem lost dit op?

### Het probleem

Standaard WooCommerce laat klanten direct betalen. Als een product niet op voorraad is, moet de shop de betaling terugstorten: administratieve rompslomp en teleurgestelde klanten.

### De oplossing

Klanten plaatsen een bestelling **zonder betaling**. De webshop controleert voorraad, keurt goed, en stuurt dan pas een betaalverzoek. Geen onnodige terugbetalingen, betere klantervaring.

---

## Functionaliteiten

### 1. Custom orderstatussen
- **Ter goedkeuring** (`wc-ter-goedkeuring`) - Order wacht op handmatige controle
- **Betaling verzocht** (`wc-betaling-verzocht`) - Order goedgekeurd, klant kan betalen

### 2. Aangepaste checkout
- Checkout toont alleen "Betaalverzoek (na goedkeuring)" (configureerbaar)
- Na goedkeuring zijn normale betaalmethoden (iDeal, Mollie, etc.) beschikbaar op `/order-pay`
- Ondersteuning voor WooCommerce Blocks checkout

### 3. Automatische e-mails (4 typen)

| E-mail | Wanneer | Class |
|--------|---------|-------|
| **Order ontvangen** | Bij status `ter-goedkeuring` | `LAP_Email_Order_Confirmation` |
| **Betaling verzocht** | Bij status `betaling-verzocht` | `LAP_Email_Request_Payment` |
| **Betalingsherinnering** | Na X werkdagen zonder betaling | `LAP_Email_Reminder` |
| **Order geannuleerd** | Bij annulering vanuit relevante status | `LAP_Email_Cancelled` |

Alle e-mails gebruiken `LAP_Email_Base` als gedeelde base class met gemeenschappelijke placeholders, template rendering en WooCommerce e-mail styling (header, footer).

Alle e-mailteksten zijn aanpasbaar via de instellingenpagina met placeholders:
- `{order_number}` - Bestelnummer
- `{customer_name}` - Voornaam klant
- `{payment_url}` - Betaallink (wordt button in HTML)
- `{order_total}` - Totaalbedrag
- `{iban}` - IBAN (configureerbaar via instellingen)
- `{account_name}` - Rekeninghouder (configureerbaar via instellingen)

### 4. Automatische herinneringen
- Dagelijkse cron (`lap_daily_reminders`) om 09:10
- Configureerbaar aantal werkdagen (standaard 4)
- Alleen voor orders met `_lap_managed_order` meta (vanaf 2026)
- Weekenden worden overgeslagen bij berekening

### 5. Automatisch annuleren
- Dagelijkse cron (`lap_daily_auto_cancel`) om 09:30
- Configureerbaar aantal dagen (standaard 14)
- Stuurt annuleringsmail naar klant
- Alleen voor managed orders

### 6. Mollie-failsafes
Automatische detectie van voltooide Mollie-betalingen via:
- Order notes (teksten als "Payment completed with Mollie")
- Transaction ID op order
- Mollie meta data (`_mollie_payment_id`, `_mollie_order_id`)
- Bedankpagina-detectie (`template_redirect`)
- `woocommerce_payment_complete` hook safety net

### 7. Admin order-acties

| Actie | Functie |
|-------|---------|
| Keur goed & vraag betaling | Status -> betaling-verzocht + betaalmail |
| Stuur uitnodiging nu | Verstuurt betaalmail handmatig |
| Stuur herinnering nu | Verstuurt herinnering direct |
| Forceer betaald-status | Handmatige failsafe naar processing/completed |
| TEST: Markeer als betaald | Simuleert betaling (alleen testmodus) |

### 8. Dashboard
- Realtime statistieken: ter goedkeuring, betaling verzocht, conversie, omzet
- Periode-selector (week/maand/jaar)
- Omzetgrafiek (30 dagen) met CSS tooltips
- Activiteitsgrafiek (7 dagen) met stacked bars: betaald (groen), geannuleerd (rood), open (paars)
- Statusverdeling donut chart (SVG): ter goedkeuring, betaling verzocht, verzendklaar
- Vergelijkingen met vorige periode (pijltjes omhoog/omlaag)
- Quick actions: direct goedkeuren en herinnering sturen vanuit dashboard (nonce-beveiligd)
- Betaallink kopieerknop per order (clipboard API)
- Configuratie-overzicht met status-indicators
- Dummy data modus voor testen
- Transient caching (9 keys, 5 min TTL) met automatische invalidatie bij statuswijzigingen

### 9. Testmodus
- Test-betaalgateway op `/order-pay` pagina's
- Webhook-simulator: `?lap_sim_webhook={ORDER_ID}&_wpnonce={nonce}`
- Admin-badge "TESTMODE AAN" in admin bar
- Dashboard dummy data optie
- Handmatige runners voor reminders en auto-cancel

### 10. Debug logging
Alle gebeurtenissen naar WooCommerce logs (bron: `lommers-approval`):
- E-mailverzendingen, statuswijzigingen, Mollie-detecties
- Herinnering- en annuleringsacties
- Logs: WooCommerce -> Status -> Logs -> filter `lommers-approval`

---

## Orderflow

```
Klant bestelt (gateway: "Na goedkeuring")
    |
    v
[Ter goedkeuring]
    |  Mail naar klant: "Bedankt, we controleren voorraad"
    |  Mail naar admin: WooCommerce "Nieuwe bestelling"
    |
    v
Admin controleert bestelling
    |
    +-- GOEDKEUREN --> [Betaling verzocht]
    |                     |  Mail naar klant: betaallink + IBAN
    |                     |
    |                     v
    |                  Klant betaalt via iDeal/Mollie
    |                     |
    |                     v
    |                  [In behandeling / Voltooid]
    |                     |  Mail naar klant: bevestiging
    |
    |                  --- OF ---
    |
    |                  Klant betaalt niet
    |                     |
    |                     v (na X werkdagen)
    |                  Herinnering verstuurd
    |                     |
    |                     v (na Y dagen)
    |                  [Geannuleerd]
    |                     |  Mail naar klant: annulering
    |
    +-- WEIGEREN --> [Geannuleerd]
```

---

## Installatie

1. Upload naar `/wp-content/plugins/lommers-payments/`
2. Activeer via WordPress admin -> Plugins
3. Ga naar **Goedkeuren & Betalen -> Instellingen**
4. Configureer herinneringen, auto-cancel en e-mailtemplates

---

## Instellingen

### Algemene instellingen

| Instelling | Beschrijving | Standaard |
|-----------|--------------|-----------|
| Checkout beperken | Alleen "Betaalverzoek (na goedkeuring)" op checkout | Aan |
| Testmodus | Test-gateway, simulaties, admin tools | Uit |
| Test alleen voor admins | Verberg testtools voor niet-admins | Aan |
| Dashboard dummy data | Testdata op dashboard (alleen in testmodus) | Uit |
| Status forceren na betaling | Auto-doorzettten naar processing/completed | Aan |
| Debug logging | Log naar WooCommerce logs | Uit |

### Herinneringen & Automatisering

| Instelling | Beschrijving | Standaard |
|-----------|--------------|-----------|
| Herinneringsmail | Auto herinnering bij niet-betalen | Uit |
| Werkdagen tot herinnering | Aantal werkdagen | 4 |
| Automatisch annuleren | Auto-annulering bij te lang niet-betalen | Uit |
| Dagen tot annuleren | Aantal kalenderdagen | 14 |

---

## Technische details

### Bestandsstructuur

```
lommers-payments/
├── lommers-approval-payments.php          # Hoofdbestand: bootstrap + core logic (945 regels)
├── README.md                              # Deze documentatie
├── AGENTS.md                              # Development guidelines voor AI agents
├── includes/
│   ├── class-lap-gateway-approval.php     # Gateway: "Betaalverzoek (na goedkeuring)" (58 regels)
│   ├── class-lap-gateway-testpay.php      # Test-gateway (62 regels)
│   ├── class-lap-settings.php             # Instellingenpagina + registratie (434 regels)
│   ├── class-lap-dashboard.php            # Dashboard met statistieken (1906 regels)
│   ├── class-lap-email-base.php           # Gedeelde e-mail base class (157 regels)
│   ├── class-lap-email-request-payment.php          # E-mail: Betaling verzocht (87 regels)
│   ├── class-lap-email-reminder.php                 # E-mail: Herinnering (88 regels)
│   ├── class-lap-email-cancelled.php                # E-mail: Geannuleerd (90 regels)
│   └── class-lap-email-order-confirmation.php       # E-mail: Bevestiging ter goedkeuring (89 regels)
└── templates/emails/
    ├── request-payment.php                # HTML template: betaalverzoek
    ├── request-payment-reminder.php       # HTML template: herinnering
    ├── order-cancelled.php                # HTML template: geannuleerd
    ├── order-confirmation.php             # HTML template: bevestiging ter goedkeuring
    └── plain/
        ├── request-payment.php            # Plain text: betaalverzoek
        ├── request-payment-reminder.php   # Plain text: herinnering
        ├── order-cancelled.php            # Plain text: geannuleerd
        └── order-confirmation.php         # Plain text: bevestiging ter goedkeuring
```

### WordPress Options (wp_options)

| Optie | Type | Default | Beschrijving |
|-------|------|---------|-------------|
| `lap_test_mode` | boolean | 0 | Testmodus |
| `lap_test_admin_only` | boolean | 1 | Test alleen voor admins |
| `lap_dashboard_dummy_data` | boolean | 0 | Dashboard dummy data |
| `lap_debug_log` | boolean | 0 | Debug logging |
| `lap_force_approval_checkout` | boolean | 1 | Checkout beperken |
| `lap_force_paid_bump` | boolean | 1 | Status forceren na betaling |
| `lap_reminder_enabled` | boolean | 0 | Herinneringen inschakelen |
| `lap_reminder_days` | integer | 4 | Werkdagen tot herinnering |
| `lap_auto_cancel_enabled` | boolean | 0 | Auto-cancel inschakelen |
| `lap_auto_cancel_days` | integer | 14 | Dagen tot auto-cancel |
| `lap_email_request_payment_subject` | string | '' | Custom e-mail onderwerp |
| `lap_email_request_payment_heading` | string | '' | Custom e-mail koptekst |
| `lap_email_request_payment_content` | string | '' | Custom e-mail inhoud |
| `lap_email_reminder_*` | string | '' | Herinnering templates |
| `lap_email_cancelled_*` | string | '' | Annulering templates |
| `lap_email_confirmation_subject` | string | '' | Bevestigingsmail onderwerp |
| `lap_email_confirmation_heading` | string | '' | Bevestigingsmail koptekst |
| `lap_email_confirmation_content` | string | '' | Bevestigingsmail inhoud |
| `lap_iban` | string | 'NL23RABO0102107661' | IBAN voor e-mails |
| `lap_account_name` | string | 'Lommers Lederwaren' | Rekeninghouder voor e-mails |
| `lap_version` | string | '' | Opgeslagen plugin versie (voor migraties) |

### Order Meta Data

| Meta key | Beschrijving |
|----------|-------------|
| `_lap_managed_order` | Timestamp wanneer order door plugin is aangemaakt (veiligheidsfilter) |
| `_lap_reminder_sent` | Timestamp wanneer herinnering is verstuurd |
| `_lap_auto_cancelled` | Timestamp wanneer automatisch geannuleerd |

### Cron Events

| Event | Schema | Tijdstip | Beschrijving |
|-------|--------|----------|-------------|
| `lap_daily_reminders` | daily | 09:10 | Herinneringen versturen |
| `lap_daily_auto_cancel` | daily | 09:30 | Onbetaalde orders annuleren |

### Hooks & Filters

**Custom hooks:**
- `lap_daily_reminders` - Dagelijkse cron voor herinneringen
- `lap_daily_auto_cancel` - Dagelijkse cron voor auto-annulering
- `woocommerce_order_action_lap_approve_and_request_payment` - Goedkeur-actie
- `woocommerce_order_action_lap_send_invitation_now` - Uitnodiging-actie
- `woocommerce_order_action_lap_send_reminder_now` - Herinnering-actie
- `woocommerce_order_action_lap_mark_paid_test` - Test betaald-actie
- `woocommerce_order_action_lap_force_paid_bump_action` - Forceer betaald-actie

**Gebruikte WooCommerce hooks:**
- `woocommerce_checkout_order_processed` - Zet nieuwe orders op "Ter goedkeuring"
- `woocommerce_order_status_changed` - Detecteert statuswijzigingen (paid-bump, mails)
- `woocommerce_payment_complete` - Safety net voor betaalstatussen
- `woocommerce_order_status_betaling-verzocht` - Verstuurt betaalmail
- `woocommerce_available_payment_gateways` - Gateway-zichtbaarheid (prio 9999)
- `woocommerce_blocks_payment_method_type_registration` - Blocks gateway filter
- `woocommerce_new_order` - Markeert order met `_lap_managed_order` meta
- `wp_insert_comment` - Detecteert Mollie order notes

---

## Bekende aandachtspunten

1. **Gemixte array syntax:** Merendeel `array()` maar sommige plaatsen gebruiken `[]` (admin bar, settings)
2. **Uitgecommentarieerde code:** `lap_send_wc_email` call in approve-actie is uitgecommentarieerd - email wordt via status change hook verstuurd
3. **Hoofdbestand groot:** 945 regels in `lommers-approval-payments.php` - zou opgesplitst kunnen worden in classes
4. **Multi-reminder niet ondersteund:** Slechts 1 herinnering per order; `_lap_reminder_sent` is boolean ipv counter

---

## Changelog

### 1.7.0 (Huidig)
- Email architectuur gerefactord: LAP_Email_Base als gedeelde base class
- 4 nieuwe email classes (LAP_Email_Request_Payment, LAP_Email_Reminder, LAP_Email_Cancelled, LAP_Email_Order_Confirmation)
- Bevestigingsmail ter goedkeuring nu via WooCommerce email systeem (was wp_mail)
- HPOS (High-Performance Order Storage) compatibiliteit
- Versie-migratie mechanisme voor toekomstige updates
- Dashboard chart data caching (transients, 5 min TTL)
- Dashboard charts dynamisch per periode (week/maand/jaar)
- Dashboard visuele overhaul: moderne card design, CSS variabelen, responsive layout
- Dashboard quick actions: goedkeuren en herinnering sturen direct vanuit dashboard
- Dashboard betaallink kopieerknop per order (clipboard API)
- Dashboard stacked activiteitsgrafiek (betaald/geannuleerd/open)
- Dashboard statusverdeling donut chart (SVG)
- Dashboard inline SVG icons (Feather icons stijl)
- IBAN en rekeninghouder configureerbaar via instellingen
- Hardcoded cutoff datum verwijderd
- Alle date() calls vervangen door wp_date() (timezone-aware)
- Deactivation hook: cron events en transients opruimen
- Security verbeteringen: escaping, nonce sanitization, capability checks
- Stock restoration bij auto-cancel
- Re-entry guard en duplicate email preventie
- Pagination bug fix in reminder en auto-cancel runners

### 1.6.0
- Dashboard met statistieken en grafieken
- Automatische annulering na X dagen
- E-mail templates aanpasbaar via instellingen
- Mollie-detectie op bedankpagina
- Meta veld `_lap_managed_order` voor veilige orderfiltering
- Dummy data optie voor testen
- Periode-selector (week/maand/jaar) op dashboard
- Vergelijkingen met vorige periode

### 1.5.0
- Automatische betalingsherinneringen
- Werkdagen-berekening
- Custom WooCommerce e-mail classes
- Handmatige test-runners

### 1.4.0
- Mollie failsafes
- Order notes detectie
- Payment complete safety net
- Testmodus met admin-badge

### 1.3.0
- Custom orderstatussen
- E-mailintegratie
- Admin order-acties
- Debug logging

### 1.2.0
- Gateway visibility rules
- Order-pay page filtering
- WooCommerce Blocks support

### 1.1.0
- Testgateway
- Webhook-simulator
- Settings page

### 1.0.0
- Eerste release
- Basis goedkeuringsworkflow

---

## Support & Ontwikkeling

**Text Domain:** `lommers-approval`  
**Ontwikkelaar:** Lommers Lederwaren

Zie `AGENTS.md` voor code style guidelines, architectuur en development workflow voor AI agents.
