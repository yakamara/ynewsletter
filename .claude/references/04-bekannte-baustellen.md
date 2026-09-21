# 04 — Bekannte Baustellen

Stand 2026-09-21. Quelle: Code-Durchsicht plus offene GitHub-Issues
(`gh issue list -R yakamara/ynewsletter`). Vor einem Fix das Issue lesen, danach dort
referenzieren.

## Aus dem Code

| Stelle | Befund |
|---|---|
| `package.yml` `requires` | `yform` fehlt, obwohl Pflicht. Installer meldet deshalb keine fehlende Abhängigkeit. |
| `rex_ynewsletter::send()` | Fehlgeschlagene Mails (`status = 0`) werden geloggt und nie erneut versucht. |
| `rex_ynewsletter_exclusionlist::initExclude()` | Läuft in jedem Request (auch Backend/Konsole); keine Bestätigung, kein Ablauf. |
| `pages/main.php` | Nicht referenziert, kann entfallen. |
| `pages/send_test.php` | Eingabefeld hat `type="test"` statt `type="text"` bzw. `number`; Label-`for` zeigt auf nicht vorhandene ID. |
| Kein `uninstall.php` | Tabellen bleiben nach Deinstallation stehen (siehe Referenz 02). |
| Kein Tooling | Weder php-cs-fixer noch phpstan noch Tests; `declare(strict_types=1)` nur in `pages/data_edit.php`. |

## Offene Issues, geclustert

**Versand-Logik**
- #57 Versendete Newsletter nicht erneut nutzbar (Status/Log zurücksetzen fehlt)
- #59 User in mehreren Gruppen bekommt Mehrfachzustellung
- #58 Mehrsprachigkeit nur über getrennte Newsletter
- #37 Versandplaner / zeitgesteuerter Versand
- #39 Extension Point vor dem Versand
- #36 Mailjet-Anbindung
- #41 Preheader

**Backend-Bedienung**
- #40 Testversand: Testuser per Widget statt ID-Eingabe
- #35 Vorschau mit Userdaten
- #48 Fehlerbild ohne YForm-Tabellenrecht
- #34 Ausschlussliste erscheint im YForm-Hauptmenü
- #33 „Tabelle wurde nicht gefunden" bei Ausschlussliste (Install-/Cache-Thema)

**REX_VARs und Abmeldung**
- #43 / #44 `REX_YNEWSLETTER_DATA` verhält sich anders als `REX_YFORM_DATA` (Array statt Dataset, Kontext)
- #38 `{{ ynewsletter.unsubscribe }}` wird ohne Sprog nicht ersetzt
- #32 Abmeldung über echte, lesbare URL
- #53 Ausschlussliste und DSGVO (Klartext-E-Mail wird gespeichert)

**Doku und Release**
- #26, #45 README ausbauen (Schritt-für-Schritt ist inzwischen drin)
- #55 Aktuelle Version nicht im Installer (Release-Upload auf redaxo.org ist manuell)
- #49 Fehler mit neuer YForm-Version (prüfen, ob durch YForm-4/5-Anpassungen erledigt)

## Nicht anfassen ohne Entscheidung

- Verschlüsselungsverfahren des Abmeldelinks (macht verschickte Links ungültig).
- Semantik des Logs als Fortschrittszustand (Cronjobs im Feld verlassen sich darauf).
- Rohes SQL in `group.table`/`group.filter` (Kernfeature, nur Admin).
