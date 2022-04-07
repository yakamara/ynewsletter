# Newsletter für REDAXO 5.x

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
In YNewsletter > Gruppe einen Datensatz anlegen, z.B. Name `Empfänger`, Tabelle `rex_ynewsletter_verteiler` und E-Mail Feld `email`


### 4. in YNewsletter Newsletter erstellen
In YNewsletter > Newsletter einen Datensatz anlegen, z.B. Subject `Mein ersters Newsletter`, Absender eingeben (beispielsweise `meine_email@domain.de`), Absendername (beispielsweise `Maxima Musterfrau`), Article den Artikel angeben, der den Newsletter Inhalt abbildet, zuvor erstelle Gruppe angeben `Empfänger` und ggf. Sprache wählen (aus Redaxo System > Sprachen).


### 5. in Struktur Artikel erstellen
in Struktur Artikel für `Anmeldung` und `Anmeldung Bestätigen` erstellen, Bezeichnung frei wählbar


### 6. Artikel `Anmeldung Bestätigen` bearbeiten
Block `YForm Formbuilder` hinzufügen. In der Eingabemaske folgenden Code einfügen und Platzhalter `%TABLE%` mit Tabellennamen `rex_ynewsletter_verteiler` ersetzen. Die Artikel ID dieses Artikels notieren. Falls der Block `YForm Formbuilder` fehlt, in YForm Übersicht über den enstprechenden Button nachinstallieren.
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
zurück in Struktur im Artikel `Anmeldung` Block `YForm Formbuilder` hinzufügen und folgenden Code einfügen. Die Platzhalter `%TABLE%` und `%EMAIL_TEMPLATE_KEY%` mit Tabellennamen `rex_ynewsletter_verteiler` und E-Mail-Template-Key `email_tmpl_ynewsletter_anmeldung`ersetzen.
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
Nun kann man unter YNewsletter > Testversand oder unter YNewsletter > Versand ein Testversand respektive ein echter Versand ausgelöst werden. 

***


### (Optional) 10. Versand über Cronjob
Will man einen Versand über einen Cronjob automatisch starten, so kann man folgenden PHP Code verwenden:

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



## Gut zu wissen

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




