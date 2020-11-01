Changelog
=========


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
