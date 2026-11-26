# Unveröffentlicht

## Neu

- Gateway und MJPEG-Stream können über HTTPS mit Zertifikatsprüfung verbunden werden. Vorhandene Stream-Medien lassen sich über ihre bisherige Medien-ID auf die neue Adresse umstellen.
- Das Gateway kann ein öffentliches PEM-Zertifikat für die HTTPS-API und die Prüfung von MJPEG-Streams verwenden.
- Das Gateway importiert öffentliche PEM-Zertifikatdateien über die Symcon-Konsole. Der manuelle PEM-Editor ist standardmäßig eingeklappt.

## Fixes

- Behebt veraltete PHP-Aufrufe und Parametersignaturen bei Verwendung von PHP 8.5.
- Gateway und System halten beim Start das eingestellte Abfrageintervall ein. Das Gateway beendet hängende HTTP-Anfragen und erneuert abgelaufene Sitzungen.
- Der Konfigurator ordnet Kameras und Systeminstanzen ihrem jeweiligen Blue-Iris-Gateway zu. Bereits eingerichtete Kameras bleiben auch ohne aktuelle Erkennung sichtbar.
- Kamerastatus wird nur an die Instanz mit dem passenden Kurznamen weitergegeben. Sonderzeichen in neu angelegten Stream-URLs werden korrekt kodiert.
- Kamera- und Systembefehle geben die dokumentierten PTZ- und Systemoptionen korrekt an Blue Iris weiter.
