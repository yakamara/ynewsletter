# 01 — Versand-Pipeline

Alles in `lib/ynewsletter.php` (`rex_ynewsletter`) und `lib/ynewsletter_group.php`.

## Einstiegspunkte

| Aufrufer | Methode | Bemerkung |
|---|---|---|
| `pages/send.php` | `sendPackage($package_size)` | `0` = alles auf einmal (`sendAll()`) |
| `pages/send_test.php` | `send([$id => $row])` + `deleteUserFromLog($row)` | ein User, danach Log-Eintrag wieder entfernt |
| README-Cronjob | `sendPackage(500)` in Schleife über `status = 0` | kein Code im AddOn, nur Doku |

## Ablauf `sendPackage()`

```
sendPackage(size)
 └─ getUserOffset()
     ├─ getUsers()            = group->getAllUsers()      SELECT * FROM <table> [WHERE (<filter1>) AND (<filter2>) …]
     ├─ group->filterExclusions()                          Ausschlussliste: Einträge der Gruppe ODER ohne Gruppe, E-Mail lowercase-Vergleich
     │    → setzt ynewsletter_user_count
     ├─ Log-Einträge für diesen Newsletter laden          → ynewsletter_log_count, ynewsletter_sent_count
     └─ alle user_id aus dem Log aus der Liste entfernen
 ├─ array_splice(users, 0, size)
 └─ send(users)
     ├─ leer?  → status = 1, save(), return true         ← „fertig"
     ├─ Body   = rex_article_content(article_id, clang_id)->getArticleTemplate()
     ├─ AltBody= getArticle() → strip_tags → html_entity_decode → optimizeTextBody()
     ├─ Anhänge aus `attachments` (kommagetrennte Medien-Dateinamen)
     └─ pro User:
          rex_var::parse(Subject|AltBody|Body, ENV_OUTPUT, 'ynewsletter_template', ['user'=>row,'group'=>group])
          → rex_stream('ynewsletter/plain_content', …) → rex_file::getOutput()   (führt eingebettetes PHP aus)
          rex_mailer: From, FromName, AddAddress, Subject, Body, AltBody, Attachments
          Log-Eintrag: user_id, newsletter, email, status (1 ok / 0 failed)
          ++ynewsletter_sent_count
     return false                                          ← „Paket raus, weiter reloaden"
```

## Was daraus folgt

- **Idempotenz über das Log.** Ein Empfänger bekommt den Newsletter genau dann noch, wenn für
  `(newsletter, user_id)` kein Log-Eintrag existiert. Auch fehlgeschlagene Mails (`status = 0`)
  werden geloggt und damit **nicht** wiederholt. Wer Retry will, muss Log-Einträge mit `status = 0`
  löschen.
- **Der Abgleich läuft über `user_id`, nicht über E-Mail.** Zwei Zeilen mit gleicher Adresse in der
  Gruppentabelle bekommen zwei Mails. Die Ausschlussliste dagegen vergleicht Adressen.
- **`status = 1` wird erst gesetzt, wenn ein Aufruf keine Empfänger mehr findet.** Nach dem letzten
  Paket ist also noch ein weiterer Reload nötig; der Backend-Reload erledigt das automatisch, ein
  Cronjob braucht einen weiteren Lauf.
- **Ein „versendeter" Newsletter ist nicht wiederverwendbar** (Issue #57): `send.php` und
  `send_test.php` listen nur `status = 0`. Zurücksetzen heißt Status auf 0 und Log für den
  Newsletter leeren.
- **Fortschrittsanzeige** in `send.php` nutzt `ynewsletter_user_count` (Gesamt nach Ausschluss)
  und `ynewsletter_sent_count` (Logeinträge vor dem Paket plus im Paket verschickte).
- **Mehrsprachigkeit** ist nur pro Newsletter über `clang_id` gelöst. Ein Newsletter geht in
  einer Sprache an eine Gruppe; für mehrere Sprachen braucht es mehrere Newsletter mit Gruppen-
  Filtern (Issue #58).
- **Verzögerung** (`send_delay`) ist reines JavaScript im Backend (`setTimeout` vor
  `location.reload()`). Serverseitig wird nicht gewartet; `sleep()` gehört nicht in `send()`.
- **Kein Extension Point** vor oder nach dem Mailversand (Issue #39). Wer Preheader, Tracking oder
  ESP-Anbindung (Issue #36) will, braucht einen EP um `rex_mailer->Send()`.

## Gruppen-Query

`getAllUsers()` setzt `table` und jede Zeile von `filter` unescaped in die Query. Zeilen werden
mit `AND` verbunden und einzeln geklammert. Ergebnis ist ein Array `id => row`, die Tabelle muss
also eine Spalte `id` haben. Das E-Mail-Feld ist frei benennbar (`group.email`, Default `email`).

## Cronjob oder Console-Command bauen

Das README-Snippet ist ein Cronjob-Skript mit `self::query()`, das so nur innerhalb der Klasse
funktioniert; im Cronjob-AddOn muss dort `rex_ynewsletter::query()` stehen. Beim Bau eines
echten `rex_console_command` beachten:

- `getArticleTemplate()` braucht eine Frontend-Umgebung (Template-Include, `rex_article`-Kontext).
  Im Backend funktioniert es, in der Konsole ist `rex::isFrontend()` false und einige Templates
  greifen auf `rex_article::getCurrent()` zu.
- Der Abmeldelink braucht `rex_yrewrite::getCurrentDomain()`, das in der Konsole die Default-
  Domain liefert.
