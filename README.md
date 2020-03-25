# Newsletter für REDAXO 5.x

## Installation

* Ins Backend einloggen und mit dem Installer installieren

### Ablauf

* Eine Gruppe definieren indem man die Tabelle und das E-Mail Feld bestimmt
* Einen Newsletter definieren, indem man Subject, Absendeadresse, Artikel und Versandgruppe bestimmt
* Über Versand den entsprechenden Newsletter im Intervall oder als Paket verschicken



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
### Platzhalter für die Verwendung in Templates, Modulen etc.

```
REX_YNEWSLETTER_DATA[field="meinfeld"]
```
