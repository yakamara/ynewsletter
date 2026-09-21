# 02 — Datenmodell, Install, Rechte

## Tabellen (Quelle: `install/tablesets/ynewsletter_tables.json`)

### `rex_ynewsletter` → `rex_ynewsletter`
| Feld | Typ | Bemerkung |
|---|---|---|
| status | choice | `0` offen, `1` versendet (Labels über `translate:`-Keys) |
| subject | text | darf REX_YNEWSLETTER_DATA enthalten |
| email_from, email_from_name | email, text | Absender |
| article_id | be_link | Artikel, der den Newsletter bildet |
| group | be_manager_relation → `rex_ynewsletter_group.name` | Empfängergruppe |
| clang_id | choice `SELECT id,name FROM rex_clang` | Sprache für Artikel-Rendering |
| attachments | be_media (multiple) | kommagetrennte Mediapool-Dateinamen |

Validierungen: `empty` auf subject, article_id, email_from, group; `email` auf email_from.

### `rex_ynewsletter_group` → `rex_ynewsletter_group`
| Feld | Typ | Bemerkung |
|---|---|---|
| name | text | |
| table | text | Tabellenname, **roh in SQL** |
| email | text, Default `email` | Name der E-Mail-Spalte in `table` |
| filter | textarea | WHERE-Bedingungen, eine je Zeile, **roh in SQL** |

### `rex_ynewsletter_exclusionlist` → `rex_ynewsletter_exclusionlist`
| Feld | Typ | Bemerkung |
|---|---|---|
| email | text | |
| type | text | bisher nur `unsubscribe` (aus `excludeEMail`) oder leer (manuell) |
| create_datetime | datestamp | |
| group | be_manager_relation → group (empty_option) | leer = gilt für **alle** Gruppen |

`getByGroupId()` liefert Einträge mit `group = <id>` **oder** `group = ''`, gruppiert nach E-Mail.

### `rex_ynewsletter_log` → `rex_ynewsletter_log`
| Feld | Typ | Bemerkung |
|---|---|---|
| newsletter | be_manager_relation → rex_ynewsletter | |
| email | text | |
| user_id | **text** | ID aus der Gruppentabelle |
| status | choice | `1` ok, `0` failed |
| send_datetime | datestamp | |

Die Modelklasse `rex_ynewsletter_log` ist leer; alle Logik liegt in `rex_ynewsletter`.

## Weitere Persistenz

- `rex_config('ynewsletter', 'encryption_key')`: 128 Hex-Zeichen, wird bei erster Nutzung in
  `rex_ynewsletter::getEncryptionKey()` erzeugt. Kein Install-Schritt legt ihn an.

## Install / Update

```php
// install.php
DELETE FROM rex_yform_field WHERE table_name LIKE "rex_ynewsletter%" AND type_name = "select";
rex_yform_manager_table_api::importTablesets(<json>);
// update.php
require __DIR__ . '/install.php';
```

- Der DELETE räumt Felder aus der YForm-3-Zeit weg, die durch `choice` ersetzt wurden. Er bleibt,
  bis niemand mehr von < 1.5.1 aktualisiert.
- `importTablesets()` legt Tabellen und Felder an bzw. gleicht sie ab. Spalten, die im JSON fehlen,
  werden nicht gelöscht. Eine Umbenennung braucht also eigene Migration (Daten kopieren, alte
  Spalte per `rex_sql_table` entfernen), sonst bleibt die Altspalte stehen.
- Es gibt keine `uninstall.php`; die Tabellen bleiben bei Deinstallation erhalten. Das ist bei
  Newsletter-Logs vermutlich gewollt, sollte aber bei einem `uninstall.php` bewusst entschieden
  werden.
- Nach jeder Tableset-Änderung: AddOn im Backend reinstallieren, dann prüfen, ob die YForm-
  Tabellendefinition (YForm → Table Manager) wirklich dem JSON entspricht. Das JSON exportiert man
  am sauberste über den Table-Manager (Tableset-Export) und ersetzt die Datei komplett.

## Backend-Seiten und Rechte

- `perm: ynewsletter[]` schaltet das ganze Modul frei; die Gruppen-Seite verlangt zusätzlich
  `admin[]`, weil dort SQL eingetragen wird.
- Datenseiten (`newsletter`, `group`, `exclusionlist`, `log`) sind Subpages mit `yformTable`;
  `pages/data_edit.php` liest diese Property und rendert `rex_yform_manager`. Es prüft zusätzlich
  YForm-Tabellenrechte (`rex_yform_manager_table_authorization::onAttribute('EDIT', …)`) oder
  Admin. **Ohne YForm-Tabellenrecht sieht ein Nicht-Admin eine leere Seite ohne Hinweis**
  (Issue #48 meldet ein „Oops" für ältere Stände).
- Die Tabellen erscheinen zusätzlich im YForm-Table-Manager, falls sie dort nicht auf „hidden"
  stehen (Issue #34).
- `pjax: false` in `package.yml`, weil `send.php` per `location.reload()` arbeitet.
