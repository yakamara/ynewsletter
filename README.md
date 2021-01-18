# Newsletter für REDAXO 5.x

## Installation

* Ins Backend einloggen und mit dem Installer installieren

### Ablauf

* Eine Gruppe definieren indem man die Tabelle und das E-Mail Feld bestimmt
* Einen Newsletter definieren, indem man Subject, Absendeadresse, Artikel und Versandgruppe bestimmt
* Über Versand den entsprechenden Newsletter im Intervall oder als Paket verschicken

### Rechte

* Da die Tabellen über die YForm verwaltet werden, muss man hierrüber an die User Tabellenrechte geben.
* Nur ein Admin kann die Gruppen anlegen


### Ausschlussliste

* Diese Liste kann mit E-Mails befüllt werden, welche beim Versand explicit ausgenommen werden
* Wenn ein User keiner Versandgruppe zugordnet ist, wird der Versand an diese E-Mail immer unterbunden.


### Integrierte Abmeldung (Eintrag in die Ausschlussliste)

Die Ausschlussliste ermöglich ein Abmelden eines Empfänger ohne in der Originaltabelle Eintragungen zu machen. Dabei wird über REX_VARs ein Abmeldenlink erstellt und die ArtikelID angegeben, welchen nach der Abmeldung aufgerufen wird. ("Danke" für die Abmeldung).

Folgender REX_VAR wird dafür verwenden, welcher einfach in das Newsletter Template oder Modul eingesetzt wird

#### Beispiel 1
```
REX_YNEWSLETTER_UNSUBSCRIBE[groups="" redirectToID=3 output=url]
``` 
Es wird ein individueller Link erstellt welcher für Abmeldung des aktuellen Users sorge und auf die ArtikelID 3 verweist. Es wird ausschliesslich die URL ausgegeben.


#### Beispiel 2

```
REX_YNEWSLETTER_UNSUBSCRIBE[groups=1,3,2 redirectToID=4 output=html]
``` 

Es wird der komplette A Tag erstellt mit dem Link, welcher den User aus den Gruppen 1,2 und 3 abmelden und anschliessend auf den Artikel mit der ID weiterleitet.  


#### Beispiel 3 (normalerweise diesen hier nutzen)

```
REX_YNEWSLETTER_UNSUBSCRIBE[redirectToID=3 output=url]
``` 
Es wird ein Abmeldelink erstellt mit dem aktuellen User und der aktuellen Gruppe.

Sofern der Newsletter im Browser aufgerufen wird, verschwinden die REX_VARS.


### Platzhalter für die Verwendung in Templates, Modulen, Subject etc.

Hier ein Beispiel für die Verwendung von Ansprachen. Bitte beachten, dass bei Modulen die REX_VALUES mit output="html" verwendet werden müssen, da sonst unerwünschte Quotes auftauchen könnten oder Feldnamen nicht erkannt werden könnten.
```
REX_YNEWSLETTER_DATA[field="name" prefix="Sehr geehrte/r Herr/Frau "]
REX_YNEWSLETTER_DATA[field="name" ifempty="Sehr geehrte Damen und Herren"]
```







### Anmeldung erstellen

Platzhalter die angepasst werden müssen

| Platzhalter | Beschreibung |
| --- | --- |
| `%TABLE%` | Tabelle in der die Anmeldungen gespeichert werden |
| `%EMAIL_TEMPLATE_KEY%` | Key des YForm E-Mail-Templates |
| `%ARTICLE_ID_CONFIRM%` | Id zum Bestätigungsartikel (wird aus der E-Mail heraus aufgerufen) |

Andere Platzhalter die mit `{{ ... }}` umschlossen sind, werden via Sprog ersetzt.

#### YForm Tabelle `%TABLE%` erstellen um die Anmeldungen zu speichern

| Feld | Name | Typ | Sonstiges
| --- | --- | --- | --- |
| email | E-Mail-Adresse | text | |
| status | Status | choice | inaktiv=0, aktiv=1 |
| newsletter | Newsletter | checkbox | |
| activation_key | Aktivierungsschlüssel | text | |

#### Anmeldung

diesen Code via YForm-Builder im Anmeldeartikel notieren

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

#### Anmeldung per E-Mail versenden

```
Bitte bestätigen Sie Ihre Registrierung:
<?php
$url = rex::getServer().rex_getUrl(%ARTICLE_ID_CONFIRM%,'',[ 'activation_key' => REX_YFORM_DATA[field="activation_key"], 'email' => REX_YFORM_DATA[field="email"] ], '&');
$url = str_replace(['./','//','https:/'],['','/','https://'],$url);
echo $url;
?>
```

#### Anmeldung bestätigen

separaten Artikel zur Bestätigung erstellen und nachfolgenden Code via YForm-Builder platzieren <br />
(Diese Artikel-Id ist `%ARTICLE_ID_CONFIRM%`)

```
hidden|status|1
hidden|newsletter|1
objparams|submit_btn_show|0
objparams|send|1
objparams|csrf_protection|0

validate|ynewsletter_auth|%TABLE%|activation_key=activation_key,email=email|status=0|{{ form.newsletter.error.confirmation.validate }}|

action|db|%TABLE%|main_where
```

### Versand über Cronjob

Will man einen Versand über einen Cronjob immer automatisch starten, so kann man folgenden PHP Code verwenden:

`Achtung - Jeder erstellte Newsletter wird dann direkt verschickt`

```
<?php

$nllog = '';
$open_newsletters = self::query()->where('status', 0)->orderBy('id', 'desc')->find();

if (0 == count($open_newsletters)) {
    echo 'keine Newsletter zu verschicken';
} else {
    foreach ($open_newsletters as $obj) {
        $newsletter = self::get($obj->id);
        $newsletter->sendPackage(500);
        echo $newsletter->ynewsletter_user_count.'verschickt an '.$newsletter->subject.' [id='.$newsletter->id.']'."\n";
    }
}

?>
```
