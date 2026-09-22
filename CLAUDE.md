# CLAUDE.md — YNewsletter

Einstieg für die Arbeit **am AddOn selbst**. Fachliche Bedienung steht in `README.md`
(wird im Backend unter „ReadMe" gerendert), Versionshistorie in `CHANGELOG.md`.

## Repo-Situation

- Dieses Verzeichnis ist ein **eigenes Git-Repo** (`yakamara/ynewsletter`, Branch `master`),
  eingehängt in einen REDAXO-Core-Workspace, der `redaxo/src/addons/*` per `.gitignore` ausblendet.
  Commits, Tags und Releases laufen **hier**, nicht im Core-Repo.
- Die Befehle aus der übergeordneten `redaxo/CLAUDE.md` (`composer check`, `phpstan`, `cs`) gelten
  **nicht** für dieses AddOn: die Core-Konfiguration listet nur System-AddOns. Das AddOn hat ein
  eigenes `composer.json` mit `cs-fix`, `cs-dry` und `unit-test`, siehe „Prüfen" unten.
- Lokale Instanz im Workspace: REDAXO 5.21, YForm 5.0.2, YRewrite 2.12.1, Sprog 1.4. Das AddOn
  ist dort **nicht installiert**; zum manuellen Testen im Backend installieren, eine Gruppe auf eine
  vorhandene YForm-Tabelle zeigen lassen und den Testversand nutzen.

## Abhängigkeiten (Ist-Zustand, nicht nur package.yml)

| Paket | Deklariert | Tatsächlich |
|---|---|---|
| yform `>=4.0,<6.0.0-dev` | ja | alle vier Tabellen, Modelklassen, `data_edit.php`, Validator |
| yrewrite `^2` | ja | nur für `rex_yrewrite::getCurrentDomain()` im Abmeldelink |
| sprog | nein | optional: `{{ ynewsletter.unsubscribe }}` und README-Beispiele setzen es voraus |
| phpmailer | (Core) | Versand über `rex_mailer` |

## Architektur in 30 Sekunden

- **Vier YForm-Tabellen** (`rex_ynewsletter`, `_group`, `_exclusionlist`, `_log`), definiert
  ausschließlich im Tableset `install/tablesets/ynewsletter_tables.json`. Modelklassen werden in
  `boot.php` per `setModelClass` gebunden; die Backend-Seiten dazu sind `package.yml`-Subpages mit
  `yformTable`, gerendert über das generische `pages/data_edit.php`.
- **Versand** (`rex_ynewsletter::sendPackage`): Empfänger = Gruppe (`SELECT * FROM <table> WHERE <filter>`)
  minus Ausschlussliste minus bereits im Log stehende IDs. Pro Empfänger werden Subject, Preheader,
  Artikel-HTML (`getArticleTemplate`) und Textfassung (`getArticle`, stripped) durch `rex_var::parse`
  im Kontext `ynewsletter_template` gejagt, danach durch Sprog (falls installiert), dann per
  `rex_mailer` verschickt. Während `send()` ist die Newsletter-Sprache die aktuelle Sprache und
  `rex_ynewsletter::isSending()` true. Zwei EPs: `YNEWSLETTER_MAIL_BEFORE_SEND` (Subject
  `rex_mailer`) und `YNEWSLETTER_MAIL_SENT`. Jede Mail landet im Log.
- **Paketversand im Backend** ist ein GET-Formular plus JavaScript-Reload nach `send_delay` Sekunden.
- **Versandplanung**: `send_at` (YForm-Feld) macht einen Newsletter zum Konsolen-/Cronjob-Fall,
  `sending_started_at` (reine SQL-Spalte, kein YForm-Feld) ist die Versandsperre. Konsole
  `ynewsletter:send` und Cronjob-Typ rufen beide `rex_ynewsletter::sendDue()`. Die Versandseite
  filtert terminierte und gesperrte Newsletter aus dem Dropdown und bietet „Sperre aufheben".
- **Abmeldung**: `REX_YNEWSLETTER_UNSUBSCRIBE` baut eine URL mit AES-verschlüsseltem Payload
  (E-Mail, Gruppen, Redirect-Artikel). `initExclude()` läuft auf `PACKAGES_INCLUDED`, schreibt in die
  Ausschlussliste und leitet weiter.

Details: `.claude/references/01-versand-pipeline.md`, `02-datenmodell-und-install.md`,
`03-rex-vars-und-abmeldung.md`.

## Stolperfallen

- **Schema nur über das Tableset-JSON ändern**, nie per SQL oder im YForm-Backend der Instanz.
  `update.php` ist ein `require` von `install.php`; die löscht alle `select`-Felder der
  `rex_ynewsletter%`-Tabellen (YForm-3-Altlast), importiert das Tableset neu und sichert danach
  per `rex_sql_table` die Spalten ab, die bei Updates fehlen können. YForm-Spalten dort nur
  `if (!hasColumn())` anlegen, sonst streiten sich Import und `ensureColumn` um den Spaltentyp.
  Alles darin muss idempotent bleiben.
- **Leere YForm-datetime-Werte sind `0000-00-00 00:00:00`**, nicht NULL. `readDatetime()` in
  `rex_ynewsletter` behandelt beides als „nicht gesetzt"; SQL-Filter brauchen `NOT LIKE "0000-00-00%"`.
  Im Formular leert `pages/data_edit.php` solche Werte per JavaScript, sonst startet der YForm-
  Datumspicker mit NaN. Der Picker selbst ist nur das Feld-Attribut
  `data-yform-tools-datetimepicker="YYYY-MM-DD HH:ii:ss"` im Tableset (YForm `tools.js`).
- **Konsolen- und Cronjob-Meldungen mit `rex_i18n::rawMsg()`** bauen; `msg()` escaped die
  Argumente, und Betreffzeilen erscheinen sonst mit `&quot;` im Terminal und im Cronjob-Log.
- **`rex_sql::getRows()` nach UPDATE zählt nur geänderte Zeilen.** Ein UPDATE auf denselben Wert
  meldet 0. Die Sperre in `acquireSendLock()` verlässt sich deshalb auf die WHERE-Bedingung,
  nicht auf die Zeilenzahl allein; Tests übergeben einen abweichenden Zeitstempel.
- **`send()` gibt invertiert zurück**: `true` heißt „nichts mehr zu tun, Status auf versendet
  gesetzt", `false` heißt „Paket wurde verschickt, es kommen noch weitere". `pages/send.php`
  nennt das `$ready`.
- **Das Log ist der Fortschrittszustand.** Wer im Log löscht, löst erneuten Versand aus. Der
  Testversand nutzt genau das: `send()` für einen User, danach `deleteUserFromLog()`.
  `log.user_id` ist eine Textspalte, obwohl IDs drinstehen.
- **`ynewsletter_user_count` / `_sent_count` / `_log_count`** sind Laufzeit-Properties auf dem
  Dataset, keine Spalten. Sie werden erst durch `getUserOffset()` gefüllt.
- **REX_YNEWSLETTER_\*-Vars** liefern außerhalb des Kontexts `ynewsletter_template` `false` und
  bleiben deshalb beim Artikel-Caching literal stehen. Erst `send()` löst sie mit
  `contextData = ['user' => <Zeile als Array>, 'group' => <rex_ynewsletter_group>]` auf.
  Neue Vars brauchen dieselbe Kontextprüfung, sonst laufen sie im normalen Frontend.
- **`group.table` und `group.filter` sind rohes SQL.** Das ist gewollt und durch `perm: admin[]`
  auf der Gruppen-Seite abgesichert. Diese Felder niemals in ein Formular ohne Admin-Recht holen
  und nicht an anderer Stelle „komfortabel" nachbauen.
- **Abmelde-Payload**: `AES-128-ECB`, Schlüssel in `rex_config('ynewsletter','encryption_key')`,
  Inhalt `serialize()`d. Ein Wechsel von Verfahren oder Serialisierung macht **alle bereits
  verschickten Links ungültig**; nur mit Fallback auf das alte Format ändern. Die `is_array`-
  Prüfung in `initExclude()` ist der Schutz gegen Müll im Parameter (Fix zu Issue #52), nicht
  entfernen.
- **`initExclude()` feuert in jedem Request**, auch im Backend und in der Konsole; es prüft nur den
  Request-Parameter `rex_ynewsletter_unsubscribe`.
- **Anhänge** brauchen den Dateisystempfad (`rex_path::media()`), nicht `rex_url::media()`.
  Der URL-Pfad funktionierte bis 1.5.1 nur zufällig über das Arbeitsverzeichnis des Backends.
- **Sprachdateien**: `de_de.lang` und `en_gb.lang` haben denselben Key-Satz. Neue Keys in beiden
  anlegen, die Reihenfolge der deutschen Datei beibehalten.
- **Dataset-Werte über `getValue()` lesen**, nicht über magische Properties (`$nl->subject`), und
  die Gruppe über `getGroup()`. rexstan Level 5 kennt die magischen Properties nicht und meldet
  sie als undefiniert; `getRelatedDataset()` liefert nur den Basistyp.
- **Backend-Seiten** beginnen mit `/** @var rex_addon $this */`, sonst meldet rexstan `$this` als
  undefiniert.
- **Neue Newsletter-Spalten** in `send()` nur über `hasValue()` lesen: nach einem Git-Pull ohne
  Reinstall fehlt die Spalte, und `getValue()` läuft dann in einen undefinierten Array-Key.

## Konventionen

- Klassen im `rex_ynewsletter*`-Namensraum ohne Namespaces, YOrm-Datasets als Basis. Neue YForm-
  Feldtypen nach `lib/yform/<value|validate|action>/`, neue REX_VARs nach `lib/var/` (Autoloader
  findet beide über die Klassennamen `rex_yform_*` bzw. `rex_var_*`).
- Backend-Texte über `lang/`-Keys mit Präfix `ynewsletter_`, nie hart im PHP.
- Vor Änderungen an `lib/` in der Instanz den Cache leeren oder das AddOn reinstallieren; nach
  Änderungen am Tableset immer reinstallieren.
- Code-Kommentare und Commit-Messages deutsch, wie der Bestand. Commit-Regeln aus der globalen
  `~/.claude/CLAUDE.md` gelten (keine KI-Marker, keine persönlichen Daten).

## Prüfen

```bash
composer install          # einmalig, vendor/ ist gitignored
composer cs-dry           # php-cs-fixer prüfen, cs-fix zum Anwenden
composer unit-test        # PHPUnit; bootet die REDAXO-Instanz, in der das AddOn liegt
```

- Die Tests brauchen eine installierte Instanz mit YForm und dem installierten AddOn; der
  Bootstrap `.tools/bootstrap.php` läuft fünf Ebenen über dem AddOn-Ordner los. Der
  Ausschlusslisten-Test schreibt echte Datensätze und räumt sie im `tearDown` weg.
- rexstan lokal: `.tools/rexstan.php` mit `ADDON_KEY=ynewsletter` aus dem Projektverzeichnis
  ausführen, dann `bin/console rexstan:analyze`. Das überschreibt die rexstan-Konfiguration der
  Instanz (`data/addons/rexstan/user-config.neon`), also vorher sichern.
- CI (`.github/workflows/`): `code-style`, `phpunit` und `rexstan` laufen bei Push und PR gegen
  das aktuelle REDAXO-Release mit PHP 8.3 (REDAXO 5.21 verlangt mindestens 8.3; die YCom-Workflows
  mit 8.2 scheitern genau daran). `publish-to-redaxo` lädt ein veröffentlichtes GitHub-Release auf
  redaxo.org und braucht die Secrets `MYREDAXO_USERNAME` und `MYREDAXO_API_KEY`.

## Release

1. `version` in `package.yml` hochsetzen, `CHANGELOG.md` oben mit Datum ergänzen (deutsch,
   externe Beiträge namentlich nennen, wie bisher).
2. Tag **ohne** `v`-Präfix (`1.5.1`), GitHub-Release mit dem Changelog-Block als Text.
3. Das GitHub-Release stößt `publish-to-redaxo.yml` an, das die Version auf redaxo.org hochlädt und
   den Release-Text als Beschreibung mitgibt (KI-Kennzeichnung gehört deshalb von Anfang an in den
   Text). Neue Änderungen bis zum nächsten Release in einem Block „Unveröffentlicht" oben in
   der `CHANGELOG.md` sammeln und beim Release durch Version und Datum ersetzen.

## Referenzen

| Datei | Wenn du… |
|---|---|
| [`01-versand-pipeline.md`](.claude/references/01-versand-pipeline.md) | den Versand änderst, Fortschritt/Log verstehen musst, einen Cronjob oder Console-Command baust |
| [`02-datenmodell-und-install.md`](.claude/references/02-datenmodell-und-install.md) | Felder ergänzt, das Tableset anfasst, Install/Update anpasst, Rechte verstehst |
| [`03-rex-vars-und-abmeldung.md`](.claude/references/03-rex-vars-und-abmeldung.md) | eine REX_VAR ergänzt, den Abmeldelink, die Verschlüsselung oder den `ynewsletter_auth`-Validator änderst |
| [`04-bekannte-baustellen.md`](.claude/references/04-bekannte-baustellen.md) | wissen willst, welche Bugs und Feature-Wünsche offen sind, bevor du etwas „nebenbei" mitfixt |
