---
name: lap-test
description: Teststrategie en handmatige testprocedures voor de Lommers Approval Before Payment plugin
---

## Teststrategie Lommers Plugin

### Overzicht
De plugin heeft momenteel geen geautomatiseerde tests. Alle tests worden handmatig uitgevoerd in een WordPress/WooCommerce omgeving. Deze skill beschrijft hoe je de plugin grondig test.

### Voorbereidingen

1. **Testmodus inschakelen:** Goedkeuren & Betalen -> Instellingen -> "Testmodus inschakelen"
2. **Debug logging inschakelen:** "Debug logging inschakelen" aanzetten
3. **E-mail controle:** Gebruik een tool als MailHog/Mailtrap of check WP mail log plugin
4. **Dashboard dummy data:** Optioneel inschakelen voor dashboard testen

### Syntax Check

Voer ALTIJD uit voor elke wijziging:
```bash
find . -name "*.php" -exec php -l {} \;
```

### Test Scenarios

#### Scenario 1: Nieuwe bestelling plaatsen
1. Ga naar de webshop als niet-ingelogde klant
2. Voeg product toe aan winkelwagen
3. Ga naar checkout
4. **Verwacht:** Alleen "Betaalverzoek (na goedkeuring)" als betaalmethode
5. Vul gegevens in en plaats bestelling
6. **Verwacht:** Order status = "Ter goedkeuring"
7. **Verwacht:** Klant ontvangt bevestigingsmail ("Bedankt, we controleren voorraad")
8. **Verwacht:** Admin ontvangt "Nieuwe bestelling" mail
9. **Verwacht:** Order heeft `_lap_managed_order` meta

#### Scenario 2: Order goedkeuren
1. Open order in WP admin
2. Kies order-actie "Keur goed & vraag betaling"
3. **Verwacht:** Status wordt "Betaling verzocht"
4. **Verwacht:** Klant ontvangt betaalmail met:
   - Correcte ordernummer
   - IBAN en rekeninghouder
   - Werkende betaallink
   - Correct bedrag
5. **Verwacht:** Order note "Order goedgekeurd; betaalverzoek verzonden."

#### Scenario 3: Betaallink testen
1. Open de betaallink uit de e-mail
2. **Verwacht:** `/order-pay` pagina toont echte gateways (Mollie, iDeal, etc.)
3. **Verwacht:** "Betaalverzoek (na goedkeuring)" is NIET zichtbaar
4. In testmodus: **Verwacht:** "Simuleer betaling (test)" IS zichtbaar

#### Scenario 4: Betaling simuleren (testmodus)
1. Optie A: Gebruik order-actie "TEST: Markeer als betaald"
2. Optie B: Gebruik test-gateway op `/order-pay`
3. Optie C: Gebruik webhook simulator `?lap_sim_webhook={ID}&_wpnonce={nonce}`
4. **Verwacht:** Order gaat naar "In behandeling" of "Voltooid"
5. **Verwacht:** Klant ontvangt bevestigingsmail

#### Scenario 5: Herinnering testen
1. Zorg voor een order met status "Betaling verzocht" ouder dan X werkdagen
2. Order moet `_lap_managed_order` meta hebben
3. Order mag GEEN `_lap_reminder_sent` meta hebben
4. Klik "Nu uitvoeren" bij herinneringen op instellingenpagina
5. **Verwacht:** Herinnering verstuurd
6. **Verwacht:** `_lap_reminder_sent` meta gezet
7. **Verwacht:** Order note "Herinnering gestuurd na X werkdagen"
8. Voer nogmaals uit: **Verwacht:** Geen dubbele herinnering

#### Scenario 6: Auto-cancel testen
1. Zorg voor een order met status "Betaling verzocht" ouder dan Y dagen
2. Klik "Nu uitvoeren" bij auto-cancel op instellingenpagina
3. **Verwacht:** Order status wordt "Geannuleerd"
4. **Verwacht:** Klant ontvangt annuleringsmail
5. **Verwacht:** `_lap_auto_cancelled` meta gezet
6. **Verwacht:** Order note "Automatisch geannuleerd na Y dagen"

#### Scenario 7: Mollie failsafe testen
1. Maak een order met status "Betaling verzocht"
2. Voeg handmatig een order note toe met tekst "Payment completed with Mollie"
3. **Verwacht:** Order wordt automatisch doorgezet naar "In behandeling"

#### Scenario 8: Dashboard testen
1. Ga naar Goedkeuren & Betalen -> Dashboard
2. **Verwacht:** Geen PHP errors
3. **Verwacht:** Statistieken laden correct
4. Test periode-selector (week/maand/jaar)
5. Schakel dummy data in: **Verwacht:** Realistische testdata
6. **Verwacht:** Grafieken tonen correct

#### Scenario 9: Instellingen testen
1. Wijzig alle instellingen
2. Sla op
3. **Verwacht:** Alle opties correct opgeslagen
4. Pas e-mail templates aan met placeholders
5. Trigger een e-mail
6. **Verwacht:** Placeholders correct vervangen

#### Scenario 10: Testmodus uitschakelen
1. Zet testmodus uit in instellingen
2. **Verwacht:** Test-gateway verdwijnt
3. **Verwacht:** "TEST: Markeer als betaald" verdwijnt uit order-acties
4. **Verwacht:** Admin bar badge verdwijnt
5. **Verwacht:** Dashboard dummy data optie verdwijnt

### Edge Cases

| Case | Verwacht | Locatie check |
|------|----------|---------------|
| Order zonder billing e-mail | Graceful skip, geen crash | `lap_send_wc_email()` |
| Dubbele goedkeuring (2x klikken) | Geen dubbele e-mails | Status change hook |
| Al betaalde order | Herinnering overgeslagen | Reminder runner |
| Order met transaction ID | Auto-cancel overgeslagen | Cancel runner |
| Mollie betaling bij "betaling-verzocht" | Auto-doorzettten | paid-bump logica |
| Order van voor 2026 | Geen herinnering | `_lap_managed_order` check |

### Logs Controleren
Na elke test, check:
- WooCommerce -> Status -> Logs -> filter `lommers-approval`
- Zoek naar `error` en `warning` level berichten
- Controleer dat verwachte `info` berichten aanwezig zijn

### Rapportage Template
```
## Test Rapport - [Datum]

### Omgeving
- WordPress versie: X.X
- WooCommerce versie: X.X
- PHP versie: X.X
- Mollie plugin versie: X.X

### Resultaten
| # | Test | Resultaat | Opmerkingen |
|---|------|-----------|-------------|
| 1 | Nieuwe bestelling | PASS/FAIL | ... |
| 2 | Goedkeuring | PASS/FAIL | ... |
| ... | ... | ... | ... |

### Gevonden issues
1. ...
2. ...

### Conclusie
DEPLOY / NIET DEPLOYEN
```
