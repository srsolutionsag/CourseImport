CourseImport
============
CourseImport Plugin, finanziert durch Hochschule Bremerhaven. Entwickelt durch studer + raimann ag. Das Plugin unterliegt der GNU/GPL. 
Die studer + raimann ag garantiert bei diesem Plugin keine zeitnahen Release-Updates. 
Falls Sie interessiert an einer Pluginpatenschaft mit garantierten Release-Updates sind, 
so informieren Sie sich unter https://studer-raimann.ch/produkte/ilias-plugins/

###Beschreibung
Das CourseImport Plugin ist ein UIHook Plugin für die E-Learning Plattform ILIAS. Es ermöglicht das Importieren
mehrerer Kurse durch Hochladen eines Excel- oder eines XML-Files. Die XML-Files weisen eine bestimmte Struktur vor, die zuerst durch
ein XSD-File und zusätzlich durch das Plugin validiert wird, bevor die Kurse in ILIAS erzeugt werden.

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