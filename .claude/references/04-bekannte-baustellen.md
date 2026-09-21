# 04 — Bekannte Baustellen

Stand 2026-09-21. Quelle: Code-Durchsicht plus offene GitHub-Issues
(`gh issue list -R yakamara/ynewsletter`). Vor einem Fix das Issue lesen, danach dort
referenzieren.

## Aus dem Code

| Stelle | Befund |
|---|---|
| `rex_ynewsletter::send()` | Fehlgeschlagene Mails (`status = 0`) werden geloggt und nie erneut versucht. |
| `rex_ynewsletter_exclusionlist::initExclude()` | Läuft in jedem Request (auch Backend/Konsole); keine Bestätigung, kein Ablauf. |
| Kein `uninstall.php` | Tabellen bleiben nach Deinstallation stehen (siehe Referenz 02). |
| `declare(strict_types=1)` | Nur in `pages/data_edit.php` und den Tests; der Altbestand läuft ohne. |

## Mit 1.6 erledigt (Issue nach Release schließen)

#34 hidden-Flag, #38 Sprog beim Versand, #39 Extension Points, #41 Preheader, #44 `isSending()`,
#48 Rechte-Hinweis, #58 Sprache beim Versand, #32 `redirectTo`, #37 Versandplanung. #49 und #55 waren im Code seit
2022 gefixt und brauchen nur das Release. #45 (README) und #53 (DSGVO-Frage) sind beantwortet.

## Offene Issues, geclustert

**Versand-Logik**
- #57 Versendete Newsletter nicht erneut nutzbar (Status/Log zurücksetzen fehlt); Vorschlag:
  Hinweis mit Logzahl auf der Versandseite plus Button „Log leeren und erneut versenden"
- #59 User in mehreren Gruppen bekommt Mehrfachzustellung oder wird nicht gefunden; Rückfrage nötig
- #36 Mailjet-Anbindung (Bounces in die Ausschlussliste); über `YNEWSLETTER_MAIL_BEFORE_SEND`
  projektseitig machbar
- #33 „Tabelle wurde nicht gefunden" nach Update 1.4 → 1.5; seit `update.php` vermutlich erledigt,
  Bestätigung fehlt

**Backend-Bedienung**
- #40 Testversand: Testuser per Widget oder E-Mail statt ID-Eingabe
- #35 Vorschau mit Userdaten

**REX_VARs**
- #43 `REX_YNEWSLETTER_DATA` kennt kein `_LABELS`-Suffix (User ist Array, kein Dataset)

**Doku**
- #26 README um Text/HTML-Fassung und Paketversand erweitern

## Nicht anfassen ohne Entscheidung

- Verschlüsselungsverfahren des Abmeldelinks (macht verschickte Links ungültig).
- Semantik des Logs als Fortschrittszustand (Cronjobs im Feld verlassen sich darauf).
- Rohes SQL in `group.table`/`group.filter` (Kernfeature, nur Admin).
