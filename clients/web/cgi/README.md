# LinkLedger – PHP CGI-Style (Single Script)

Diese Variante ist absichtlich im **"CGI-Denken"** gebaut:

- Request kommt rein
- **index.php** läuft von oben nach unten
- DB-Verbindung auf → Daten holen/schreiben → HTML ausgeben → Script Ende
- **Keine .htaccess nötig** (keine Pretty URLs, keine Rewrite-Regeln)

Das läuft auf Shared Hosting (z.B. IONOS) oft am zuverlässigsten.

## Installation (IONOS)

1. Ordner z.B. nach `/linkledger/` hochladen (FTP / Git)
2. `.env.example` nach `.env` kopieren und DB-Zugangsdaten eintragen
3. In IONOS sicherstellen: **PHP 8.2** aktiv
4. DB anlegen (z.B. `linkledger`) – Tabellen legt die App beim ersten Request automatisch an.
5. Aufrufen: `https://deine-domain.tld/linkledger/index.php`

## Debugging

In `.env`:
- `APP_DEBUG=true`
- `APP_DEBUG_CONSOLE=true`

Dann bekommst du:
- eine detaillierte Fehlerseite (bei Exceptions)
- `console.log(...)` in der Browser-Konsole (DevTools → Console)
- optional: `storage/logs/app.log` (wenn Schreibrechte vorhanden)

## URLs

Da wir ohne Rewrite arbeiten, benutzen wir Query-Parameter:

- `index.php` (Dashboard)
- `index.php?action=login`
- `index.php?action=register`
- `index.php?action=export_json`
- `index.php?action=export_csv`

Die Buttons/Formulare setzen `action` automatisch.


## Hinweis zu `display_name`
Wenn in deiner bestehenden DB ein Feld `users.display_name` als NOT NULL definiert ist,
fragt die Register-Maske jetzt einen **Anzeigenamen** ab und speichert ihn.


## Mehrere Links anlegen (Neu-Modus)
Der Button **Neu** setzt `new=1`, damit kein vorhandener Link automatisch ausgewählt wird.
Dann speichert **Speichern** immer als neuer Datensatz (INSERT) statt Update.
