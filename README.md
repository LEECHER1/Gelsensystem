# Gelsendiele Gastro System

Ein modulares WordPress-Gastronomiesystem für Reservierungen, Tischverwaltung, Service, Küche, Schank und Abrechnung. Das Plugin wird schrittweise von der historischen Five-Star-Kompatibilität zu einem vollständig eigenständigen, professionell releasebaren Produkt migriert.

## Repository- und Release-Modell

- `main` enthält ausschließlich geprüfte, freigegebene Versionen.
- Entwicklungsänderungen erfolgen auf Branches und werden über Pull Requests geprüft.
- Jede Plugin-Version erhöht die Versionsnummer und erhält einen Git-Tag.
- GitHub Actions prüft PHP- und JavaScript-Syntax und erzeugt aus dem Tag eine installierbare ZIP.
- Die ZIP wird als GitHub-Release-Artefakt veröffentlicht und anschließend kontrolliert in WordPress installiert.
- Die produktive Website wird nicht direkt mit jedem GitHub-Commit synchronisiert.

Das vermeidet, dass ein unvollständiger Commit unmittelbar den Gastronomiebetrieb beeinflusst. Ein späterer signierter Update-Client kann Releases aus einem privaten oder öffentlichen Update-Kanal beziehen, bleibt aber ein separates Modul.

## Verzeichnisstruktur

```text
gelsendiele-reservierungsdashboard/  WordPress-Plugin
releases/legacy/                     unveränderliche historische Ausgangsarchive
scripts/                             Prüf- und Buildskripte
.github/workflows/                   CI- und Release-Automatisierung
```

## Lokaler Build

```bash
./scripts/verify-plugin.sh
./scripts/build-plugin.sh
```

Das Buildskript erzeugt `dist/gelsendiele-system-v<version>.zip` mit genau einem Plugin-Stammordner.

## Sicherheit

Vor jedem produktiven Update sind Dateisystem und Datenbank zu sichern. Reservierungen bleiben vorerst im kompatiblen WordPress-Post-Type `rtb-booking`; Migrationen sind versioniert, wiederholbar und verändern bestehende Buchungen nicht.
