# Changelog

## 2.1.0
- Zentrale, versionierte Einstellungsarchitektur als gemeinsame Quelle für Formular und Dashboard.
- Einheitliches Gelsendiele-Menü mit vorbereiteten Gastro-Modulen.
- Allgemeine Einstellungen, Markenfarben, Logo, Öffnungszeiten und Reservierungsregeln im Backend.
- Idempotente Update-Migration für Datenbanktabellen, Arbeitsseiten, Rollen und Capabilities.
- Übernahme des bisherigen nummerischen Tischmodells ohne Verlust vorhandener Zuordnungen.
- Five-Star-Kompatibilitätsklassen werden erst nach dem Laden aller Plugins registriert.
- Keine doppelte Post-Type-Registrierung und kein eigener Statusmail-Hook bei aktivem Five Star.
- Öffentliches Formular und manuelle Reservierungen verwenden dieselbe zentrale Verfügbarkeit.
- Mehrere Öffnungsblöcke, Pufferzeiten und Öffnungen über Mitternacht.
- Flexible Marke in Reservierungsformular, Dashboard und Gastro-Arbeitsansichten.
- Stationsbezogene REST-Berechtigungen für Küche und Schank.
- Original-ZIP 2.0.3 als unveränderliche Referenz im Repository archiviert.

## 2.0.3
- Reservierungen werden unabhängig von der Five-Star-Klasse gespeichert.
- Saubere JSON-Erfolgsmeldung auch bei parallel aktivem Five Star.
- Eigener Kalender mit ausgegrauten geschlossenen, vergangenen und nicht verfügbaren Tagen.
- Uhrzeiten werden abhängig von Datum, Personenzahl, Öffnungszeiten und Kapazität geladen.
