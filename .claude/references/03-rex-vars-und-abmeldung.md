# 03 — REX_VARs, Abmeldelink, Validator

## REX_YNEWSLETTER_DATA (`lib/var/ynewsletter_data.php`)

```
REX_YNEWSLETTER_DATA[field="name" prefix="Hallo " suffix="," ifempty="Sehr geehrte Damen und Herren" output="html"]
REX_YNEWSLETTER_DATA[field="name" isset=1]          → 'true' | 'false'
```

- Gültig nur bei `getContext() === 'ynewsletter_template'`; sonst `return false`, und `rex_var`
  lässt den Platzhalter **unverändert im Text** stehen. Genau deshalb überleben die Vars das
  Artikel-Caching und werden erst in `send()` ersetzt.
- `getContextData()` liefert `['user' => <DB-Zeile als Array>, 'group' => rex_ynewsletter_group]`.
  `user` ist **kein** Dataset, sondern das Array aus `getAllUsers()`; es gibt also keine
  Relationen oder YForm-Feldlogik. Das ist der Unterschied zu `REX_YFORM_DATA` (Issue #43/#44).
- `output="html"` → `htmlspecialchars` + `nl2br`; `output="plain"` entschärft nur `<?`/`?>`;
  ohne `output` kommt der Rohwert. In Modulen deshalb `output="html"` verwenden (README).
- `prefix`, `suffix`, `ifempty` kommen aus `rex_var::getGlobalArgsOutput()`, nicht aus dieser Klasse.

## REX_YNEWSLETTER_UNSUBSCRIBE (`lib/var/ynewsletter_unsubscribe.php`)

```
REX_YNEWSLETTER_UNSUBSCRIBE[redirectToID=3 output=url]                 aktuelle Gruppe
REX_YNEWSLETTER_UNSUBSCRIBE[groups=1,3 redirectToID=4 output=html]     mehrere Gruppen, kompletter <a>
REX_YNEWSLETTER_UNSUBSCRIBE[groups="" redirectToID=3 output=url]       Gruppe leer = alle Gruppen
```

- `groups` fehlt → ID der aktuellen Gruppe. `groups=""` (Attribut vorhanden, aber leer) → leerer
  String, also Eintrag ohne Gruppe = globaler Ausschluss. Diese Unterscheidung läuft über
  `hasArg('groups')`.
- `redirectToID` fehlt → Startartikel der aktuellen YRewrite-Domain.
- `output=html` gibt `<a href="…">{{ ynewsletter.unsubscribe }}</a>` aus; der Linktext ist ein
  **Sprog-Platzhalter**. Ohne Sprog oder ohne den Sprog-Key steht er wörtlich in der Mail
  (Issue #38).

## Abmeldung serverseitig (`lib/ynewsletter_exclusionlist.php`)

```
getUnsubscribeUrl(email, groups, redirectToID)
  payload = serialize(['email'=>…, 'groups'=>…, 'redirectToID'=>…])
  token   = openssl_encrypt(payload, 'AES-128-ECB', encryption_key)     // Base64-Ausgabe
  url     = <YRewrite-Domain-URL>?rex_ynewsletter_unsubscribe=<urlencode(token)>

initExclude()   (boot.php, EP PACKAGES_INCLUDED, jeder Request)
  token vorhanden? → decryptString → unserialize
  ist Array? → excludeEMail(email, groups) → rex_response::sendRedirect(rex_getUrl(redirectToID))
```

- `excludeEMail()` splittet `groups` an Kommas und legt **je Gruppe einen Eintrag** an; bei
  leerem String genau einen Eintrag ohne Gruppe.
- Es gibt **keine Prüfung**, ob die E-Mail zu einer Gruppentabelle gehört, kein Ablaufdatum und
  keine Bestätigungsseite. Der Link ist ein Bearer-Token: Wer ihn hat, meldet die Adresse ab.
- `unserialize()` auf entschlüsselten Daten: solange nur `AES` mit geheimem Schlüssel davor
  sitzt, ist das vertretbar; bei einem Formatwechsel `json_encode`/`json_decode` verwenden und
  das alte Format weiter lesen können (verschickte Mails leben lange).
- ECB ohne IV ist deterministisch: gleiche Adresse plus gleiche Gruppen ergibt immer denselben
  Link. Nicht als Sicherheitsproblem behandeln, aber auch nicht darauf bauen, dass Links
  „einmalig" sind.
- Ausgabe-Encoding: `urlencode()` auf dem Base64-Token; `+`, `/`, `=` werden damit korrekt
  transportiert.

## Validator `ynewsletter_auth` (`lib/yform/validate/ynewsletter_auth.php`)

Pipe-Syntax aus der README:

```
validate|ynewsletter_auth|<table>|activation_key=activation_key,email=email|status=0|<fehlermeldung>|<felder-für-mail>
                          f1      f2 (label=requestparam,…)                f3 (spalte=wert)  f4              f5
```

- Baut `SELECT * FROM <table> WHERE spalte = :spalte AND …` aus Request-Werten (Prepared
  Statement, Werte sind sicher; **Spaltennamen und Tabelle** kommen aus der Formulardefinition).
- Genau **eine** Trefferzeile ist Erfolg; dann setzt er `params['main_where']`, `main_id`,
  `main_table`, sodass ein folgendes `action|db|<table>|main_where` die Zeile aktualisiert
  (Aktivierung: `hidden|status|1`).
- `f5` (Felder) kopiert Spaltenwerte in `value_pool['email']`, damit ein anschließendes
  `tpl2email` sie nutzen kann.
- `getDefinitions()` liefert die Tabellen als Choice aus `information_schema`; das ist für den
  YForm-Formbuilder im Backend, nicht für den Frontend-Pipe-Weg.
