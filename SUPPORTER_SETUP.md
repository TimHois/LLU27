# LLU 27 Supporter Zone – Installation und Betrieb

## Verhalten

Die Supporter Zone nutzt eigene Konten, Session-Cookies und widerrufbare Remember-Tokens. Eine öffentliche Registrierung erzeugt niemals einen Eintrag in `users`. Läufer-Login, OAuth und Performance-Berechnungen bleiben bestehen. Die Challenge-Seite und Supporter-Auswertung verwenden dieselbe Funktion `challengeScore()`.

Wochentipps schließen **Montag 18:00 UTC** (20:00 Uhr MESZ / 19:00 Uhr MEZ). Eine neue Woche beginnt Montag 00:00 UTC, passend zum vorhandenen Challenge-Cronjob. Nach vollständiger Finalisierung aller drei Läufer wird die Tippwertung bei jedem Aufruf automatisch aus den gespeicherten Ergebnissen berechnet. Co-Sieger zählen jeweils als richtig; Null-Loop-Wochen haben keinen Sieger. Kein eigener Auswertungs-Cronjob, keine zusätzlichen Strava-Aufrufe.

Kilometer und Läufe sind Cache-Summen ab persönlichem Trainingsstart, kein Langzeitarchiv. Ein Loop ist die Summe der je Aktivität abgerundeten vollständigen 6.706 Meter. Fortschrittsbalken vergleichen verfügbare Kilometer im Team und sind keine medizinische oder Zielprognose. Challenge-Historie kommt aus `challenge_weekly`.

## Hostinger vorbereiten (manuell; kein Deployment wurde ausgeführt)

1. Datenbank sichern. Auf Hostinger PHP **8.2 oder neuer** mit PDO MySQL, mbstring, curl und OpenSSL aktivieren. HTTPS ist erforderlich; Cookies sind ausschließlich `Secure`.
2. In phpMyAdmin die Datei `backyard_private/sql/20261024_supporter.sql` in derselben Datenbank wie `users` importieren. Sie ist wiederholbar, löscht keine Trainingsdaten und setzt die bestehende `users`-Tabelle voraus. Bereits vorhandene Tabellen werden nicht umgebaut. `challenge_weekly` wird nur angelegt, falls sie fehlt. Bei bereits vorhandener Tabelle Spalten und Typen mit der Migration vergleichen.
3. Private Konfiguration in `domains/zjung-partyband.de/backyard_private/.env` ergänzen (siehe unten). Keine Secrets in öffentliche Verzeichnisse, Workflow-Inputs oder Repository schreiben.
4. Composer-Pakete installieren: im lokalen/private Arbeitsverzeichnis `composer install --no-dev --prefer-dist --optimize-autoloader`. GitHub Actions installiert und überträgt `vendor/` jetzt in das **private** Verzeichnis. SQL wird weiterhin nicht automatisch ausgeführt. Das bestehende manuelle Deployment hat weiterhin `dry_run: true` als Standard. Erst selbst Dry-Run prüfen, anschließend ein gewünschtes Deployment gesondert auslösen.
5. `APP_URL` muss die tatsächliche öffentliche Basis einschließlich Unterverzeichnis sein: `https://zjung-partyband.de/backyard`. Öffentliche Dateien liegen unter `public_html/backyard/`, private Dateien daneben unter `backyard_private/`. Nicht `backyard_private` unter `public_html` ablegen.
6. Nach Installation meldet sich jeder Läufer wie bisher an und aktiviert im Dashboard bei „Deine Supporter feuern dich an“ die ausdrückliche Freigabe seiner Trainingssummen. Ohne Freigabe werden keine Trainingssummen angezeigt. Das Team-Scoreboard benötigt alle drei Freigaben. Widerruf ist im selben Formular möglich.
7. Supporter registrieren sich unter `<APP_URL>/supporter/register.php` und melden sich unter `/supporter/login.php` an. Der öffentliche Läufer-Login verlinkt beide Seiten.
8. Hostinger-Cronjob **jede Minute**: `/usr/bin/php /home/u489447694/domains/zjung-partyband.de/backyard_private/bin/supporter_push.php`. Den tatsächlichen PHP-CLI-Pfad in hPanel prüfen. Bestehende Sync- und Challenge-Cronjobs beibehalten. Push ist verzögert bis zum nächsten Cronlauf.

Ohne Push-Schlüssel funktionieren Registrierung, Login, Dashboard, Nachrichten und Tipps weiterhin. Ohne SQL-Migration ist die neue Zone nicht benutzbar; das Läufer-Dashboard zeigt einen Hinweis im optionalen Supporter-Modul.

## Private `.env`-Variablen

Bestehende `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_URL` und `TOKEN_KEY` bleiben erforderlich. `TOKEN_KEY` ist der bestehende geheime Base64-Schlüssel mit 32 Bytes und verschlüsselt auch Push-Abonnements.

| Variable | Zweck |
|---|---|
| `SUPPORTER_RATE_KEY` | Separater zufälliger Schlüssel für gehashte Rate-Limit-Kennungen; mindestens 32 zufällige Bytes. Fallback ist der vorhandene `TOKEN_KEY`. |
| `SUPPORTER_PRIVACY_URL` | Optional: HTTPS-Link zum bereitgestellten Datenschutztext. |
| `SUPPORTER_TERMS_URL` | Optional: HTTPS-Link zu bereitgestellten Nutzungsbedingungen. Sobald einer der Links konfiguriert ist, muss das Registrierungsformular die Zustimmung erhalten. |
| `SUPPORTER_ADMIN_RUNNER_IDS` | Kommagetrennte IDs bestehender Läuferkonten, die Nachrichten moderieren dürfen. Leer bedeutet keine Moderatoren. Keine Supporter-ID verwenden. |
| `VAPID_PUBLIC_KEY` | Öffentlicher VAPID-Schlüssel; darf dem Browser übergeben werden. |
| `VAPID_PRIVATE_KEY` | Geheimer VAPID-Schlüssel; ausschließlich private `.env`. |
| `VAPID_SUBJECT` | Kontakt, beispielsweise `mailto:betreiber@example.org`. |

Zufallswert für `SUPPORTER_RATE_KEY`: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. Keine echten Schlüssel in Dokumentation eintragen.

## Composer und VAPID

Die private `composer.json` verwendet `minishlink/web-push:^9.0`, passend zu PHP 8.2+. Eine `composer.lock` konnte hier ohne ausführbares PHP/Composer nicht erzeugt werden. Vor dem ersten Produktivdeployment einmal `composer update --no-dev` in einer vertrauenswürdigen PHP-8.2+-Umgebung ausführen und die erzeugte `backyard_private/composer.lock` versionieren; ohne Lock löst Composer Versionen beim Installieren neu auf. Bei lokaler Installation `composer check-platform-reqs` ausführen. Pakete gehören ausschließlich nach `backyard_private/vendor/`.

Einmalig im privaten Composer-Verzeichnis ausführen:

```sh
php -r 'require "vendor/autoload.php"; $keys = Minishlink\WebPush\VAPID::createVapidKeys(); echo "VAPID_PUBLIC_KEY=".$keys["publicKey"].PHP_EOL."VAPID_PRIVATE_KEY=".$keys["privateKey"].PHP_EOL;'
```

Die Ausgabe direkt in der privaten `.env` speichern. Nicht über GitHub Actions generieren oder in Logs kopieren. Schlüssel nicht regelmäßig wechseln; nach Wechsel müssen Geräte erneut abonnieren. Technische Grundlage: [PHP-Web-Push-Bibliothek](https://github.com/web-push-libs/web-push-php/tree/v9.0.0).

## Push und Datenschutz

Erst ein Klick auf „Push aktivieren“ fragt die Browser-Erlaubnis ab. Auf iPhone/iPad ab iOS 16.4 muss die Seite über Safari zum Home-Bildschirm hinzugefügt und von dort geöffnet werden ([Apple-Dokumentation](https://developer.apple.com/documentation/usernotifications/sending-web-push-notifications-in-web-apps-and-browsers)). Beide Manifeste und der Service Worker berücksichtigen die Installation unter `/backyard/`. Der Worker speichert keine authentifizierten HTML-Seiten.

Abonnements sind pro Gerät gespeichert und mit `TOKEN_KEY` verschlüsselt. Ein Browser-Abonnement gehört jeweils genau einem Konto/einer Rolle; Aktivierung mit einem anderen Konto ordnet es neu zu. Läufer aktivieren Push im eigenen Dashboard, Supporter im Profil. Supporter haben derzeit keine auslösenden Ereignisse: Die angeforderte Hauptfunktion sendet Anfeuerungen ausschließlich an den gewählten Läufer. Ausschalten ist je Gerät und auf allen Geräten möglich. Abmelden widerruft Sitzungen; Push muss bei Bedarf separat deaktiviert werden.

Unterstützte Gateways: Google FCM, Mozilla, Apple und Windows Notify; fremde URLs, Ports und lokale Ziele werden abgelehnt. Der Versandjob entfernt HTTP-404/410-Abonnements über die Bibliothek. Temporäre Fehler werden bis zu fünfmal versucht; erfolgreich versandte Geräte werden bei regulären Wiederholungen übersprungen. Bei Prozessabbruch direkt nach erfolgreichem Versand kann eine Nachricht doppelt zugestellt werden; ein stabiler Notification-Tag begrenzt sichtbare Duplikate. Logs enthalten nur Job-/Subscription-ID, Status bzw. Fehlerklasse, keine URLs, Tokens oder Nachrichten. hPanel-Logs überwachen. Abgelaufene Rate-Limits und Remember-Tokens werden durch den Job bereinigt, auch ohne VAPID-Konfiguration. Nicht versandte Jobs verfallen nach einem Tag.

Anfeuerungen und Anzeigenamen sind für angemeldete Supporter sichtbar, der Highscore zeigt Anzeigenamen. Kontolöschung entfernt eigene Anfeuerungen, Tipps, Tokens und Abonnements. Nachrichtenmoderation: als konfigurierter Läufer `/supporter/moderation.php` öffnen; die letzten 200 Nachrichten sind löschbar. Datenschutz-/Nutzungstexte werden nicht erfunden und müssen vom Betreiber bereitgestellt werden.

## Tests und Abnahme

Automatisierter Regeltest: `php backyard_private/tests/supporter_test.php` (mbstring erforderlich). PHP-Syntaxprüfung: alle PHP-Dateien in `backyard/` und `backyard_private/`, ohne `vendor/`, mit `php -l` prüfen. Der Deployment-Workflow prüft ebenfalls alle öffentlichen PHP-Dateien und private App-/Bin-Dateien.

Staging-Abnahme mit eigener Datenbank und HTTPS:

- Migration zweimal importieren; unveränderte Trainingsdaten und referentielle Löschregeln prüfen.
- Registrieren; doppelte Namen, ungültige Namen, kurze Passwörter, fehlenden CSRF und wiederholte Anfragen ablehnen.
- Anmeldung mit richtigem/falschem Passwort; Remember-Cookie über Browser-Neustart, Rotation und Ablauf prüfen. Supporter- und Läufer-Login im selben Browser parallel prüfen; Supporter dürfen Läufer-Endpunkte nicht öffnen.
- Anzeigename ändern, Passwort ändern, alte Sitzungen auf anderem Gerät ablehnen, Konto löschen; abhängige Daten prüfen.
- Drei Freigaben einzeln setzen/widerrufen; nur freigegebene Summen anzeigen. Dashboard, Challenge und Performance der Läufer prüfen.
- Nachricht senden, Empfänger und Cooldown prüfen; XSS-Text muss als Text erscheinen. Läuferansicht und Moderation prüfen.
- Tipp vor/nach Montag 18:00 UTC prüfen, eine Auswahl pro Woche, Änderung bis Frist; finale Wochen mit Gleichstand, Null-Loops und fehlendem Läufer prüfen.
- Push auf echtem Desktop und iPhone-PWA aktivieren. Cronjob ausführen, richtigen Empfänger und mehrere Geräte prüfen. Verweigerte Erlaubnis, Abmelden, Gerätewechsel, Ausschalten sowie abgelaufene Abonnements prüfen.
- Responsive Navigation bei 360, 768 und 1280 Pixeln; Overlay, Escape, Tastaturfokus und Sidebar prüfen.

Live-Abnahme benötigt MySQL-Zugang, konfigurierte private `.env`, VAPID-Schlüssel und echte Browsergeräte. Diese Daten sind im Workspace nicht vorhanden; entsprechende Integrations-/Browserprüfungen dürfen daher nicht als bestanden gewertet werden. Konkrete lokale Ergebnisse stehen im Abschlussbericht.

## Dateien und Commit-Zusammenfassung

Neu ist außerdem `.github/workflows/supporter-checks.yml` für Syntax-, Regel- und isolierte MySQL-/HTTP-Integrationstests. Der Workflow führt kein Deployment aus und wurde in dieser lokalen Sitzung nicht ausgelöst.

Geändert: `.gitignore`, `.github/workflows/deploy.yml`, `backyard/index.php`, `backyard/dashboard.php`, `backyard/challenge.php`, `backyard/manifest.webmanifest`.

Neu: diese Anleitung; `backyard/supporter/{index,register,login,logout,profile,push,moderation}.php`, `backyard/supporter/{supporter.css,supporter.js,manifest.webmanifest}`, `backyard/assets/supporter-push.js`, `backyard/sw.js`, `backyard/partials/{supporter-runner,supporter-push}.php`, `backyard_private/app/{supporter,push,challenge_score}.php`, `backyard_private/bin/supporter_push.php`, `backyard_private/sql/20261024_supporter.sql`, `backyard_private/composer.json`, `backyard_private/tests/supporter_test.php` und `backyard_private/tests/supporter_integration.php`.

Vorgeschlagener Commit: `feat: add secure supporter zone with cheers, weekly predictions and web push`.

## Lokales Prüfergebnis (24.10.2026)

Korrektur nach dem ersten CI-Lauf: Der HTTP-Integrationstest bildet jetzt die Hostinger-Struktur `public_html/backyard/` neben `backyard_private/` in einem temporären Verzeichnis ab. Die öffentliche Basis-URL enthält auch im Test `/backyard`. Falsche Include-Tiefen in den beiden Supporter-Partials wurden berichtigt. Verbindungsfehler und HTTP 500 geben im Test das PHP-Serverlog aus; die Profilseite einschließlich Push-Partial wird zusätzlich geprüft. Ein erneuter CI-Lauf ist zur Bestätigung erforderlich.

Repository und Änderungen wurden statisch geprüft; git diff --check meldet keine Whitespace-Fehler. JSON-Manifeste und Composer-Konfiguration werden lokal mit PowerShell geparst. PHP und Composer sind nicht vorinstalliert. Eine offizielle PHP-8.3-Testlaufzeit wurde nach SHA256-Prüfung temporär geladen, ihre Ausführung jedoch vom Windows-Anwendungsschutz blockiert. Daher sind weder PHP-Lint noch Regel-/Integrationstests lokal ausgeführt; auch der neue CI-Workflow wurde nicht gestartet. MySQL, VAPID und echte Browsergeräte fehlen. Vor Freigabe müssen CI und Staging-Abnahme erfolgreich abgeschlossen werden. Kein Deployment, keine produktiven Datenänderungen.
