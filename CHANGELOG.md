Changelog
=========

Unveröffentlicht
----------------

* Neue Extension Points `YNEWSLETTER_MAIL_BEFORE_SEND` und `YNEWSLETTER_MAIL_SENT` (#39)
* Preheader-Feld im Newsletter; wird unsichtbar nach dem `<body>`-Tag eingefügt (#41)
* Sprog-Platzhalter werden beim Versand in Betreff, Preheader, HTML- und Textfassung ersetzt (#38, #58)
* Beim Versand ist die Sprache des Newsletters die aktuelle Sprache, Templates mit `rex_clang::getCurrent()` liefern damit die richtige Sprache (#58)
* `rex_ynewsletter::isSending()` für Templates, um Platzhalter beim normalen Seitenaufruf durch einen Standardtext zu ersetzen (#44)
* `REX_YNEWSLETTER_UNSUBSCRIBE` akzeptiert `redirectTo` mit einer absoluten URL (#32)
* Datenseiten zeigen einen Hinweis statt einer leeren Seite, wenn das YForm-Tabellenrecht fehlt (#48)
* Ausschlussliste ist im YForm-Menü versteckt wie die anderen Tabellen (#34)
* Testversand: ID-Feld ist ein Zahlenfeld mit korrektem Label
* Anhänge werden über den Dateisystempfad (`rex_path::media`) angehängt statt über den URL-Pfad; damit funktioniert der Versand mit Anhängen auch aus Cronjobs und Konsole
* Englische Sprachdatei vervollständigt
* YForm 4/5: Rechteabfrage der Datenseiten angepasst; Abmeldelink mit ungültigem Parameter erzeugt keinen Eintrag mehr (#52); E-Mail-Abgleich der Ausschlussliste ignoriert Groß-/Kleinschreibung (#60)
* README erweitert: Voraussetzungen, Gruppenfilter, Anhänge, Ablauf von Versand und Log, Rechte; Cronjob-Beispiel korrigiert (#26)
* CLAUDE.md und Referenzdokumentation für die Weiterentwicklung ergänzt

Version 1.5.1 – 15.03.2022
--------------------------

* YForm 4 Anpassungen

Version 1.5 – 19.01.2021
--------------------------

* Ausschlussliste ergänzt. Man kann nun User aus dem Newsletter einer Gruppe ausschliessen. Z.B. weil sie sich abgemeldet haben
* Abmeldesystem integriert. Durch REX_YNEWSLETTER_UNSUBSCRIBE[redirectToID=3 output=url].  
* Attachments können nun an einen Newsletter gehängt werden

Version 1.4 – 01.11.2020
--------------------------

* Bug: Wenn Artikel keinen Inhalt hatte, wurde der HTML Body zum Textbody (Norbert tyrant88)
* Versand: Verzögerug ergänzt. Man kann Versand-Pakete mit Verzögerung verschicken.
* Sprache des Artikel beim Versand nun auswählbar -> auch mehrsprachige Newsletter möglich durch User mit Sprachgruppen und entsprechendem Newsletter (Norbert tyrant88)
* Testversand nun ohne Umweg möglich - an einen User der Gruppe durch die ID.
* README ins Backend aufgenommen
* YNewsletter nicht mehr als Block in der REDAXO Navigation. 

Version 1.3 – 30.09.2020
--------------------------

* Gruppierungfehler behoben
* CS
* Docs ergänzt mit Info zum Cronjobversand
* AltBody wieder auf Artikelcontent beschränkt - keine Template mehr
* Navigation umgebaut. Kein eigener Block sondern als Reiter.

Version 1.2 – 29.04.2020
--------------------------

* Versandname wurde falsch übernommen, Danke @tyrant88
* Der AltBody (Text) nutzt nun auch das Template
* Notices bei falschen REX_VARS entfernt
* Rechte ergänzte. ynewsletter[]
* In Subjects kann man nun auch REX_YNEWSLETTER_DATA verwenden.

Version 1.1 – 24.04.2019
--------------------------

* REX_VARS der User Daten über REX_YNEWSLETTER_DATA[field="email"] verwendbar
* Anzeige der User an die verschickt wird optimiert
* Neueste Einträge default oben
* Plaintextausgabe verbessert.
* Tableset aktualisiert. Läuft mit YForm 3.x
* Mehrfachauswahl bei Gruppen angepasst
* Versandreload angepasst
* Braucht REDAXO 5.7 und YForm 3

Version 1.0 – 26.04.2017
--------------------------

* Erste Version mit reinen Basisfunktionen.
