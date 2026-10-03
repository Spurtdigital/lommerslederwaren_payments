---
description: Voer pre-deployment checks uit op de Lommers plugin
---

Laad de skill "wp-deploy" en voer de volledige pre-deployment checklist uit voor de Lommers Approval Before Payment plugin.

Controleer:
1. PHP syntax van alle bestanden
2. ABSPATH guards
3. Security (nonces, capability checks, escaping)
4. Code style (array syntax, prefixes, text domain)
5. Geef een overzicht van handmatige tests die nodig zijn

!`find . -name "*.php" -exec php -l {} \;`
