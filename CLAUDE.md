# CLAUDE.md — YNewsletter

Einstieg für die Arbeit **am AddOn selbst**. Fachliche Bedienung steht in `README.md`
(wird im Backend unter „ReadMe" gerendert), Versionshistorie in `CHANGELOG.md`.

## Repo-Situation

- Dieses Verzeichnis ist ein **eigenes Git-Repo** (`yakamara/ynewsletter`, Branch `master`),
  eingehängt in einen REDAXO-Core-Workspace, der `redaxo/src/addons/*` per `.gitignore` ausblendet.
  Commits, Tags und Releases laufen **hier**, nicht im Core-Repo.
- Die Befehle aus der übergeordneten `redaxo/CLAUDE.md` (`composer check`, `phpstan`, `cs`) gelten
  **nicht** für dieses AddOn: die Core-Konfiguration listet nur System-AddOns. Das AddOn hat kein
  eigenes `composer.json`, keine Tests, keine CS-Konfiguration und keine CI.
- Lokale Instanz im Workspace: REDAXO 5.21, YForm 5.0.2, YRewrite 2.12.1, Sprog 1.4. Das AddOn
  ist dort **nicht installiert**; zum manuellen Testen im Backend installieren, eine Gruppe auf eine
  vorhandene YForm-Tabelle zeigen lassen und den Testversand nutzen.

## Abhängigkeiten (Ist-Zustand, nicht nur package.yml)

| Paket | Deklariert | Tatsächlich |
|---|---|---|
| yform | **nein** | Pflicht: alle vier Tabellen, Modelklassen, `data_edit.php`, Validator |
| yrewrite `^2` | ja | nur für `rex_yrewrite::getCurrentDomain()` im Abmeldelink |
| sprog | nein | optional: `{{ ynewsletter.unsubscribe }}` und README-Beispiele setzen es voraus |
| phpmailer | (Core) | Versand über `rex_mailer` |

Wer `requires` anfasst, sollte `yform` ergänzen; das fehlende Require ist historisch, kein Wunsch.

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
  Es gibt keinen serverseitigen Scheduler; der Cronjob-Weg ist ein Snippet in der README.
- **Abmeldung**: `REX_YNEWSLETTER_UNSUBSCRIBE` baut eine URL mit AES-verschlüsseltem Payload
  (E-Mail, Gruppen, Redirect-Artikel). `initExclude()` läuft auf `PACKAGES_INCLUDED`, schreibt in die
  Ausschlussliste und leitet weiter.

Details: `.claude/references/01-versand-pipeline.md`, `02-datenmodell-und-install.md`,
`03-rex-vars-und-abmeldung.md`.

## Stolperfallen

- **Schema nur über das Tableset-JSON ändern**, nie per SQL oder im YForm-Backend der Instanz.
  `update.php` ist ein `require` von `install.php`; die löscht alle `select`-Felder der
  `rex_ynewsletter%`-Tabellen (YForm-3-Altlast) und importiert das Tableset neu. Alles darin muss
  also idempotent bleiben.
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
- `pages/main.php` ist tot: keine Subpage verweist darauf. Einstieg ist `pages/index.php`.
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

## Release

1. `version` in `package.yml` hochsetzen, `CHANGELOG.md` oben mit Datum ergänzen (deutsch,
   externe Beiträge namentlich nennen, wie bisher).
2. Tag **ohne** `v`-Präfix (`1.5.1`), GitHub-Release mit dem Changelog-Block als Text.
3. **Kein Release-Workflow im Repo.** Die Version muss auf redaxo.org von Hand hochgeladen werden;
   dort liegt Stand 2026-09 die 1.5.1 online. Seit diesem Tag sind unveröffentlichte Fixes im
   `master`, gesammelt im Block „Unveröffentlicht" der `CHANGELOG.md`.

## Referenzen

| Datei | Wenn du… |
|---|---|
| [`01-versand-pipeline.md`](.claude/references/01-versand-pipeline.md) | den Versand änderst, Fortschritt/Log verstehen musst, einen Cronjob oder Console-Command baust |
| [`02-datenmodell-und-install.md`](.claude/references/02-datenmodell-und-install.md) | Felder ergänzt, das Tableset anfasst, Install/Update anpasst, Rechte verstehst |
| [`03-rex-vars-und-abmeldung.md`](.claude/references/03-rex-vars-und-abmeldung.md) | eine REX_VAR ergänzt, den Abmeldelink, die Verschlüsselung oder den `ynewsletter_auth`-Validator änderst |
| [`04-bekannte-baustellen.md`](.claude/references/04-bekannte-baustellen.md) | wissen willst, welche Bugs und Feature-Wünsche offen sind, bevor du etwas „nebenbei" mitfixt |
