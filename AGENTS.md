# Projektregeln für BlueIris

Diese Datei gilt für die gesamte BlueIris-Bibliothek. Lies vor Änderungen die [zentralen Arbeitsregeln](../SymconDevelopment/AGENTS.md), die [Architektur der Entwicklungsbasis](../SymconDevelopment/ARCHITECTURE.md), die [Engineering-Prinzipien](../SymconDevelopment/principles/ENGINEERING.md), die [Regeln zur Zusammenarbeit](../SymconDevelopment/principles/AI_COLLABORATION.md) und die [aufgabenrelevanten Standards](../SymconDevelopment/standards/README.md).

Nutze für Symcon-API-Fragen den [offiziellen Dokumentationseinstieg](https://www.symcon.de/de/llms.txt). Suche PHP-, Kern- und Modulfunktionen zuerst im [Funktionsindex](https://www.symcon.de/de/llms/function-index.md) und lade dann die passende Detailseite.

## Aufbau und Schnittstellen

Beginne mit [library.json](library.json), der [Bibliotheks-README](README.md) sowie `module.json`, `module.php`, `form.json`, `locale.json` und der README der betroffenen Module.

| Modul | Aufgabe |
| --- | --- |
| [SymconBlueIrisGateway](SymconBlueIrisGateway/README.md) | Parent der drei übrigen Module. Kommuniziert mit der Blue-Iris-JSON-API, verwaltet die Anmeldung und verteilt Kamera- und Systemdaten. |
| [SymconBlueIrisConfigurator](SymconBlueIrisConfigurator/README.md) | Zeigt entdeckte Kameras an und bietet die Anlage von Kamera- und Systeminstanzen an. |
| [SymconBlueIrisCam](SymconBlueIrisCam/README.md) | Zeigt Daten einer einzelnen Kamera an, steuert sie und kann ein Medienobjekt für den Kamerastream anlegen. |
| [SymconBlueIrisSystem](SymconBlueIrisSystem/README.md) | Zeigt Serverstatus an und ruft je nach Konfiguration Clips, Alarme und Protokolle ab. |

- Erhalte veröffentlichte Bibliotheks- und Modul-GUIDs, Datenfluss-GUIDs, PHP-Präfixe (insbesondere `BlueIrisGateway`), öffentliche Methoden, Property-Namen und -Typen, Variablen-Idents, Profile, Actions und Statuscodes. Prüfe bei Änderungen an der Parent-/Child-Kommunikation beide Seiten des bestehenden JSON-Vertrags mit `DataID`, `Buffer`, `cmd` und `payload`.
- Die Kamera-Zuordnung verwendet den Blue-Iris-Kurznamen aus `ShortName`. Der Anzeigename einer Kamera dient der Beschriftung. Diese Zuordnung betrifft einen externen Dienst. Für konfigurierte IP-Symcon-Quellvariablen gilt der [zentrale Standard zur Variablen-ID](../SymconDevelopment/standards/SOURCE_VARIABLE_IDENTITY.md).
- Nutze [BlueIrisApi.html](SymconBlueIrisGateway/BlueIrisApi.html) als Referenz für den Bestandsstand. Prüfe geänderte API-Aufrufe gegen die im Auftrag betroffene Blue-Iris-Version.

## Bestandsstand und Entwicklung

- Die vorhandenen READMEs nennen IP-Symcon ab 5.3. Die Bestandsmodule verwenden `IPSModule`. Das zentrale Entwicklungsziel ist IP-Symcon 9 mit PHP 8.5. Prüfe die Kompatibilität für die jeweilige Änderung. Änderungen an Mindestversion, Basisklasse oder persistenten Verträgen benötigen eine ausdrückliche Entscheidung und gegebenenfalls eine [Migration](../SymconDevelopment/standards/MIGRATIONS.md).
- BlueIris hat derzeit kein eingebundenes Helper-Bundle. Prüfe vor fachlichen Änderungen passende Verträge in [Symcon_ModuleHelper](../Symcon_ModuleHelper/README.md). Gleiche bei einer Einbindung insbesondere den vorhandenen Datenfluss und die Basisklasse mit dem Helper-Vertrag ab.
- Der lokale Bestandsstand hat keine projektspezifischen Tests, StylePHP-Konfiguration oder GitHub-Actions-Workflows. Für eine CI-Anbindung gilt der [zentrale CI-Standard](../SymconDevelopment/standards/CI.md) mit [Symcon_ModuleCI](../Symcon_ModuleCI/README.md). Dokumentiere bei jeder Änderung die tatsächlich ausgeführten und die ausstehenden relevanten Prüfungen.
- Die Bibliothek hat im vorliegenden Stand keine `CHANGELOG.md`. Dokumentiere künftige sichtbare Änderungen nach dem [zentralen Changelog-Standard](../SymconDevelopment/standards/CHANGELOG.md).
- Arbeite für alle Änderungen an BlueIris ausschließlich direkt auf dem dauerhaften Branch `dev`. Diese Projektregel hat Vorrang vor der Vorgabe für kurzlebige Aufgabenbranches im [zentralen Git-Workflow](../SymconDevelopment/standards/GIT_WORKFLOW.md). Commit und Push erledigt der Benutzer über GitHub Desktop.

## Systemzugriff und Bildschirmaufnahmen

- Kläre vor Zugriffen auf laufende Symcon- und Blue-Iris-Systeme Rolle, Ziel und erlaubten Umfang. Prüfe Änderungen am Datenfluss möglichst mit synthetischen Daten. Stimme Laufzeittests mit Seiteneffekten wie PTZ-Bewegungen, Aufnahme-Triggern oder Änderungen an Profilen vorher für das betroffene System ab. Maßgeblich sind die [Regeln zum Systemzugriff](../SymconDevelopment/standards/SYMCON_ACCESS.md).
- Halte Blue-Iris-Zugangsdaten, Sitzungskennungen, Stream-URLs mit Zugangsdaten, private Kamerabilder und vollständige reale API-Antworten aus Repository, Beispielen und Debug-Ausgaben heraus. Beachte den [Datenschutzstandard](../SymconDevelopment/standards/PRIVATE_DATA.md).
- Jede Bildschirmaufnahme, Erfassung von Desktop oder Fenstern und screenshotbasierte Computersteuerung erfordert vorab die ausdrückliche Zustimmung des Benutzers für die aktuelle Aufgabe. Das gilt für alle Werkzeuge und Aufnahmewege, auch bei Diagnoseaufträgen und allgemeinem Vollzugriff. Ohne Zustimmung dürfen keine Bildschirmbilder erzeugt, gespeichert oder an Modelle oder Dienste übertragen werden. Vom Benutzer angehängte Bilder dürfen im Rahmen seiner Anfrage analysiert werden.
- `computer-use` und `unified-computer-use` bleiben deaktiviert, bis der Benutzer ihre Wiederaktivierung ausdrücklich verlangt. Kläre bei genehmigter Erfassung vorab Ziel, Zweck und Umfang und beende die Erfassung unmittelbar danach. Der Zustimmungsvorbehalt gilt auch für die im zentralen Standard beschriebene Prüfung von MCP-Kachelvorschauen.
