CourseImport
============

CourseImport Plugin, finanziert durch Hochschule Bremerhaven. Entwickelt durch studer + raimann ag. Das Plugin unterliegt der GNU/GPL. 

**HINWEIS**: Dieses Plugin wird open source durch die studer + raimann ag der ILIAS Community zur Verüfgung gestellt. Das Plugin hat noch keinen Pluginpaten. Das heisst, dass die studer + raimann ag etwaige Fehler, Support und Release-Pflege für die Kunden der studer + raimann ag mit einem entsprechenden Hosting/Wartungsvertrag leistet. Wir veröffentlichen unsere Plugins, weil wir diese sehr gerne auch anderen Community-Mitglieder verfügbar machen möchten. Falls Sie nicht zu unseren Hosting-Kunden gehören, bitten wir Sie um Verständnis, dass wir leider weder kostenlosen Support noch die Release-Pflege für Sie garantieren können.
Sind Sie interessiert an einer Plugin-Patenschaft (https://studer-raimann.ch/produkte/ilias-plugins/plugin-patenschaften/ ) Rufen Sie uns an oder senden Sie uns eine E-Mail.

###Beschreibung
Das CourseImport Plugin ist ein UIHook Plugin für die E-Learning Plattform ILIAS. Es ermöglicht das Importieren
mehrerer Kurse durch Hochladen eines Excel- oder eines XML-Files. Die XML-Files weisen eine bestimmte Struktur vor, die zuerst durch
ein XSD-File und zusätzlich durch das Plugin validiert wird, bevor die Kurse in ILIAS erzeugt werden.

Beim erstellen eines Excel-Kursimports ist zu beachten, dass **der Zeitrahmen für die Kurseinschreibung ignoriert wird,
falls keine direkte Registration ausgewählt wurde**.

###Documentation
https://github.com/studer-raimann/CourseImport/raw/master/doc/Documentation_1_0_0.pdf

###Installation
Beginnend im ILIAS-root-Verzeichnis:
```bash
mkdir -p Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/
cd Customizing/global/plugins/Services/UIComponent/UserInterfaceHook
git clone https://github.com/studer-raimann/CourseImport.git
```
Als ILIAS Administrator, installieren und aktivieren Sie das Plugin unter "Administration->Plugins".

###Contact
studer + raimann ag
Waldeggstrasse 72
3097 Liebefeld
Switzerland

info@studer-raimann.ch
www.studer-raimann.ch
