# Newsletter für REDAXO 5.x

Versendet REDAXO-Artikel als HTML-Newsletter an Empfänger aus beliebigen YForm-Tabellen. Der Versand wird protokolliert, Empfänger können sich über einen Link in eine Ausschlussliste eintragen.

## Voraussetzungen

* REDAXO 5.7 oder neuer
* YForm 4 oder 5 (alle Tabellen des AddOns werden über YForm verwaltet)
* YRewrite 2 (für die Domain im Abmeldelink)
* optional Sprog für `{{ platzhalter }}` in Newsletter und Abmeldelink
* optional Cronjob-AddOn für den terminierten Versand ohne System-Cron (siehe Schritt 10); ohne das AddOn läuft der terminierte Versand ausschließlich über `bin/console ynewsletter:send`
* Mailversand über das PHPMailer-AddOn (System > PHPMailer) muss eingerichtet sein

## Installation

### Ablauf

1. Addon installieren
2. YForm Tabelle erstellen
3. Eine Gruppe definieren indem man die Tabelle und das E-Mail Feld bestimmt
4. Einen Newsletter definieren, indem man Subject, Absendeadresse, Artikel und Versandgruppe bestimmt
5. Artikel für `Anmeldung` und `Anmeldung Bestätigen` erstellen
6. Artikel `Anmeldung Bestätigen` befüllen
7. YForm E-Mail Template erstellen
8. Artikel `Anmeldung` befüllen
9. Über Versand den entsprechenden Newsletter im Intervall oder als Paket verschicken
10. (optional) Versand über Cronjob steuern



## Schritt für Schritt Anleitung

### 1. Addon YNewsletter installieren
Ins Backend einloggen und mit dem Installer das Addon installieren


### 2. YForm Tabelle erstellen
In YForm Table Manager eine Tabelle für Newsletter-Empfänger erstellen, zBsp. `rex_ynewsletter_verteiler`. 
Folgende Felder erstellen:

| Feld | Name | Typ | Sonstiges
| --- | --- | --- | --- |
| email | E-Mail-Adresse | email | |
| status | Status | choice | inaktiv=0, aktiv=1 |
| newsletter | Newsletter | checkbox | |
| activation_key | Aktivierungsschlüssel | text | |


### 3. in YNewsletter Gruppe erstellen
In YNewsletter > Gruppe einen Datensatz anlegen, z.B. Name `Empfänger`, Tabelle `rex_ynewsletter_verteiler` und E-Mail Feld `email`.

Im Feld `Filter` kann pro Zeile eine SQL-Bedingung stehen, die Zeilen werden mit `AND` verknüpft, z.B.

```
status = 1
newsletter = 1
```

Die Bedingungen landen unverändert in der Abfrage; deshalb können nur Administratoren Gruppen anlegen und bearbeiten.


### 4. in YNewsletter Newsletter erstellen
In YNewsletter > Newsletter einen Datensatz anlegen, z.B. Subject `Mein erster Newsletter`, Absender eingeben (beispielsweise `meine_email@domain.de`), Absendername (beispielsweise `Maxima Musterfrau`), Article den Artikel angeben, der den Newsletter Inhalt abbildet, zuvor erstellte Gruppe angeben `Empfänger` und ggf. Sprache wählen (aus Redaxo System > Sprachen).

Optional: ein `Preheader` (Kurztext für die Posteingangsvorschau, siehe unten) und `Anhänge` aus dem Medienpool, die jeder Mail beigefügt werden.


### 5. in Struktur Artikel erstellen
in Struktur Artikel für `Anmeldung` und `Anmeldung Bestätigen` erstellen, Bezeichnung frei wählbar


### 6. Artikel `Anmeldung Bestätigen` bearbeiten
Block `YForm Formbuilder` hinzufügen. In der Eingabemaske folgenden Code einfügen und Platzhalter `%TABLE%` mit Tabellennamen `rex_ynewsletter_verteiler` ersetzen. Die Artikel ID dieses Artikels notieren. Falls der Block `YForm Formbuilder` fehlt, in YForm Übersicht über den entsprechenden Button nachinstallieren.
```
hidden|status|1
hidden|newsletter|1
objparams|submit_btn_show|0
objparams|send|1
objparams|csrf_protection|0

validate|ynewsletter_auth|%TABLE%|activation_key=activation_key,email=email|status=0|{{ form.newsletter.error.confirmation.validate }}|

action|db|%TABLE%|main_where
```


### 7. YForm E-Mail Template erstellen
In YForm > E-Mail Templates einen neuen Datensatz anlegen folgenden Code einfügen. Den Platzhalter %ARTICLE_ID_CONFIRM% mit der zuvor notierten Artikel ID ersetzen. Im Feld “Key” einen eindeutigen Key eingeben, zBsp. `email_tmpl_ynewsletter_anmeldung`
```
Bitte bestätigen Sie Ihre Registrierung:
<?php
$url = rex::getServer().rex_getUrl(%ARTICLE_ID_CONFIRM%,'',[ 'activation_key' => REX_YFORM_DATA[field="activation_key"], 'email' => REX_YFORM_DATA[field="email"] ], '&');
$url = str_replace(['./','//','https:/'],['','/','https://'],$url);
echo $url;
?>
```


### 8. Artikel `Anmeldung` bearbeiten
zurück in Struktur im Artikel `Anmeldung` Block `YForm Formbuilder` hinzufügen und folgenden Code einfügen. Die Platzhalter `%TABLE%` und `%EMAIL_TEMPLATE_KEY%` mit Tabellennamen `rex_ynewsletter_verteiler` und E-Mail-Template-Key `email_tmpl_ynewsletter_anmeldung` ersetzen.
```
generate_key|activation_key
hidden|status|0
hidden|newsletter|1

text|email|{{ form.email }}*
validate|unique|email|{{ form.email.error.unique }}|%TABLE%
validate|type|email|email|{{ form.email.error.type }}
captcha|{{ form.captcha  }}|{{ form.captcha.error }}

checkbox|privacy|{{ form.newsletter.privacy }}|0|no_db
validate|empty|privacy|{{ form.privacy.error.empty }}

submit|send|{{ form.newsletter.submit }}|no_db

action|db|%TABLE%
action|tpl2email|%EMAIL_TEMPLATE_KEY%|email
```


### 9. Fertig!
Nun kann unter YNewsletter > Testversand oder unter YNewsletter > Versand ein Testversand respektive ein echter Versand ausgelöst werden. Wie der Versand im Detail abläuft, steht unten unter „Versand und Log".

***


### (Optional) 10. Versandtermin und automatischer Versand

Im Newsletter kann ein **Versandtermin** (Datum und Uhrzeit) eingetragen werden. Ein Newsletter mit Termin wird ab diesem Zeitpunkt automatisch verschickt und ist auf der Versandseite gesperrt; er erscheint dort in der Liste „Geplante und laufende Versände". Ohne Termin bleibt es beim manuellen Versand über die Versandseite.

Für den automatischen Versand gibt es zwei Wege, die dieselbe Logik nutzen:

**Konsole** (System-Cron, z.B. jede Minute):

```
php redaxo/bin/console ynewsletter:send
php redaxo/bin/console ynewsletter:send --package-size=100 --delay=2
```

**Cronjob-AddOn**: Cronjob vom Typ „YNewsletter: geplante Newsletter versenden" anlegen, Intervall nach Bedarf. Der Cronjob läuft dann im gewählten Umfeld des Cronjob-AddOns (Backend, Frontend oder Skript).

Ein Lauf verschickt jeden fälligen Newsletter komplett, paketweise mit optionaler Pause zwischen den Paketen, und setzt den Status auf „versendet". Während des Laufs ist der Newsletter gesperrt (`sending_started_at`), ein überlappender zweiter Lauf überspringt ihn. Bricht ein Lauf ab, bleibt die Sperre stehen; sie kann auf der Versandseite über „Sperre aufheben" entfernt werden, der nächste Lauf setzt den Versand dann fort, weil das Log den Fortschritt kennt.

Ein Versandtermin in der Vergangenheit ist erlaubt: Der Newsletter geht beim nächsten Lauf sofort raus.



## Gut zu wissen

### Versand und Log

* **Manuell oder terminiert**: Ohne Versandtermin geht der Newsletter über die Versandseite im Browser raus. Mit Versandtermin übernimmt die Konsole oder der Cronjob (siehe Schritt 10), und die Versandseite verweigert den manuellen Versand, bis der Termin entfernt ist. Der Testversand bleibt in beiden Fällen möglich.

* **HTML-Fassung** ist der Artikel samt Template (`getArticleTemplate`), **Textfassung** der Artikelinhalt ohne Template und ohne HTML-Tags. Für beide werden die Platzhalter pro Empfänger ersetzt.
* **Empfänger** sind alle Zeilen der Gruppentabelle, die den Filter erfüllen, abzüglich der Ausschlussliste und abzüglich der Empfänger, die für diesen Newsletter schon im **Log** stehen. Das Log ist damit der Versandfortschritt: Ein Empfänger bekommt den Newsletter genau einmal, auch wenn der Versand unterbrochen und später fortgesetzt wird.
* **Paketversand**: Unter Versand kann man alle Mails auf einmal oder in Paketen von 10, 50 oder 100 Empfängern verschicken. Zwischen den Paketen lädt die Seite nach der eingestellten Verzögerung automatisch neu. Nach dem letzten Paket ist ein weiterer Durchlauf nötig, der keine Empfänger mehr findet und den Status auf „versendet" setzt.
* **Fehlgeschlagene Mails** stehen mit Status `failed` im Log und werden nicht automatisch wiederholt. Für einen erneuten Versuch den Log-Eintrag löschen.
* **Erneut versenden**: Ein Newsletter mit Status „versendet" erscheint nicht mehr unter Versand. Wer ihn erneut verschicken will, setzt den Status auf „offen" **und** löscht die Log-Einträge des Newsletters; sonst werden alle Empfänger als bereits versorgt übersprungen.
* **Testversand** schickt den Newsletter an eine Zeile der Gruppentabelle (ID eingeben) und löscht den Log-Eintrag danach wieder, damit der Empfänger beim echten Versand nicht fehlt.

### Platzhalter die angepasst werden müssen

| Platzhalter | Beschreibung |
| --- | --- |
| `%TABLE%` | Tabelle in der die Anmeldungen gespeichert werden |
| `%EMAIL_TEMPLATE_KEY%` | Key des YForm E-Mail-Templates |
| `%ARTICLE_ID_CONFIRM%` | Id zum Bestätigungsartikel (wird aus der E-Mail heraus aufgerufen) |

Andere Platzhalter die mit `{{ ... }}` umschlossen sind, werden via Sprog ersetzt.

__Platzhalter für die Verwendung in Templates, Modulen, Subject etc.__
Hier ein Beispiel für die Verwendung von Ansprachen. Bitte beachten, dass bei Modulen die REX_VALUES mit output="html" verwendet werden müssen, da sonst unerwünschte Quotes auftauchen könnten oder Feldnamen nicht erkannt werden könnten.
```
REX_YNEWSLETTER_DATA[field="name" prefix="Sehr geehrte/r Herr/Frau "]
REX_YNEWSLETTER_DATA[field="name" ifempty="Sehr geehrte Damen und Herren"]
```

### Rechte

* Das Recht `ynewsletter[]` schaltet das AddOn im Backend frei.
* Da die Tabellen über YForm verwaltet werden, brauchen Nicht-Admins zusätzlich die YForm-Tabellenrechte für `rex_ynewsletter`, `rex_ynewsletter_exclusionlist` und `rex_ynewsletter_log`. Fehlt ein Recht, zeigt die Seite einen entsprechenden Hinweis.
* Nur ein Admin kann Gruppen anlegen und bearbeiten, weil dort SQL-Bedingungen eingetragen werden.


### Ausschlussliste

* Diese Liste kann mit E-Mail-Adressen befüllt werden, die beim Versand ausgenommen werden. Der Abgleich ignoriert Groß- und Kleinschreibung.
* Ein Eintrag mit Gruppe gilt nur für diese Gruppe. Ein Eintrag **ohne** Gruppe gilt für alle Gruppen.
* Die Adressen bleiben stehen, damit nachvollziehbar ist, wer sich wann abgemeldet hat. Soll der Datensatz in der Empfängertabelle gelöscht werden, muss das projektseitig geschehen, z.B. über den Extension Point `YNEWSLETTER_MAIL_SENT` oder einen Cronjob.


### Integrierte Abmeldung (Eintrag in die Ausschlussliste)

Die Ausschlussliste ermöglicht das Abmelden eines Empfängers, ohne in der Originaltabelle etwas zu ändern. Dabei wird über eine REX_VAR ein Abmeldelink erstellt und die Artikel-ID angegeben, die nach der Abmeldung aufgerufen wird („Danke" für die Abmeldung). Der Link enthält E-Mail und Gruppen verschlüsselt; wer den Link hat, kann die Adresse abmelden.

Folgende REX_VAR wird dafür verwendet, sie wird einfach in das Newsletter-Template oder Modul eingesetzt:

#### Beispiel 1
```
REX_YNEWSLETTER_UNSUBSCRIBE[groups="" redirectToID=3 output=url]
``` 
Es wird ein individueller Link erstellt, der den aktuellen Empfänger aus **allen** Gruppen abmeldet (leere Gruppenangabe) und auf den Artikel mit der ID 3 verweist. Es wird ausschließlich die URL ausgegeben.


#### Beispiel 2

```
REX_YNEWSLETTER_UNSUBSCRIBE[groups=1,3,2 redirectToID=4 output=html]
``` 

Es wird der komplette `<a>`-Tag erstellt mit dem Link, der den Empfänger aus den Gruppen 1, 2 und 3 abmeldet und anschließend auf den Artikel mit der ID 4 weiterleitet.


#### Beispiel 3 (normalerweise diesen hier nutzen)

```
REX_YNEWSLETTER_UNSUBSCRIBE[redirectToID=3 output=url]
``` 
Es wird ein Abmeldelink für den aktuellen Empfänger und die Gruppe des gerade versendeten Newsletters erstellt.

#### Beispiel 4 (Weiterleitung auf eine beliebige URL)

```
REX_YNEWSLETTER_UNSUBSCRIBE[redirectTo="https://www.example.org/abgemeldet" output=url]
```
Statt einer Artikel-ID kann mit `redirectTo` eine absolute URL angegeben werden, auf die nach der Abmeldung weitergeleitet wird. Ist beides angegeben, gewinnt `redirectTo`.

Der Linktext bei `output=html` ist der Sprog-Platzhalter `{{ ynewsletter.unsubscribe }}`; er wird beim Versand ersetzt, wenn Sprog installiert ist und den Platzhalter kennt. Ohne Sprog `output=url` verwenden und den Link selbst bauen.


### Platzhalter auf der Webseite

Die `REX_YNEWSLETTER_*`-Platzhalter werden erst beim Versand ersetzt. Wird der Newsletter-Artikel im Browser aufgerufen, stehen sie deshalb wörtlich im Text. Templates und Module können das abfangen:

```php
<?php if (rex_ynewsletter::isSending()): ?>
    REX_YNEWSLETTER_DATA[field="name" prefix="Sehr geehrte/r Herr/Frau "]
<?php else: ?>
    Sehr geehrte Damen und Herren
<?php endif; ?>
```

`rex_ynewsletter::getCurrentSending()` liefert währenddessen den Newsletter-Datensatz (z.B. für Betreff oder Gruppe).


### Sprache

Beim Versand ist die im Newsletter gewählte Sprache die aktuelle Sprache (`rex_clang::getCurrent()`), auch für Templates und Sprog-Platzhalter. Ohne Auswahl wird die Sprache des angemeldeten Backend-Users verwendet.


### Sprog

Ist Sprog installiert, werden `{{ platzhalter }}` in Betreff, Preheader, HTML- und Textfassung beim Versand in der Sprache des Newsletters ersetzt.


### Preheader

Im Newsletter kann ein Preheader hinterlegt werden: ein Kurztext, den viele E-Mail-Programme in der Posteingangsvorschau nach dem Betreff anzeigen. Er wird unsichtbar direkt nach dem `<body>`-Tag eingefügt und darf `REX_YNEWSLETTER_DATA`- und Sprog-Platzhalter enthalten.


### Extension Points

| Extension Point | Subject | Params | Zweck |
| --- | --- | --- | --- |
| `YNEWSLETTER_MAIL_BEFORE_SEND` | `rex_mailer` | `newsletter`, `group`, `user`, `email` | Mail vor dem Versand verändern (Header, Tracking, eigene Platzhalter). Gibt der EP kein `rex_mailer`-Objekt zurück, wird die Mail nicht verschickt, aber als fehlgeschlagen geloggt. |
| `YNEWSLETTER_MAIL_SENT` | Status (`1` ok, `0` fehlgeschlagen) | `newsletter`, `group`, `user`, `email`, `mail` | Nach dem Versandversuch, z.B. für eigene Protokolle. |

```php
rex_extension::register('YNEWSLETTER_MAIL_BEFORE_SEND', function (rex_extension_point $ep) {
    /** @var rex_mailer $mail */
    $mail = $ep->getSubject();
    $mail->addCustomHeader('List-Unsubscribe', '<mailto:abmelden@example.org>');
    return $mail;
});
```




