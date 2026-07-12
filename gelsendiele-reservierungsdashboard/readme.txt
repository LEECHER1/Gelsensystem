=== Gelsensystem ===
Contributors: Andreas Schwarz / OpenAI
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.2.0

Eigenständiges Reservierungs-, Service-, Küchen-, Schank- und Zahlungsgrundsystem für die Gelsendiele.

== Enthalten ==
* Zentrales Gelsendiele-Menü für Reservierung und Gastro-Module
* Versionierte, wiederholbare Migrationen bei Installation und Update
* Flexible Betriebs-, Logo-, Farb- und Darstellungsoptionen
* Mehrere Öffnungsblöcke pro Wochentag und Öffnungen über Mitternacht
* Öffentliches Reservierungsformular: [gelsendiele_reservierungsformular]
* Kompatibler Ersatz für den bisherigen Shortcode [booking-form], sobald Five Star deaktiviert ist
* Bestehendes Reservierungsdashboard mit manueller Anlage, Bearbeitung, Tischzuordnung, Export und PWA
* Eigener Reservierungs-Datentyp und eigene Statusverwaltung
* Öffnungszeiten, Zeitslots, Kapazitätskontrolle und geschlossene Tage
* Sondertage, Betriebsurlaub, Sonderöffnungen, Uhrzeitsperren und reduzierte Tageskapazitäten
* E-Mail an Betrieb und Gast
* Service-, Küchen-, Schank- und Zahlungsmodul
* Bestehende Five-Star-Reservierungen werden weiterverwendet, da Datentyp und Metadaten kompatibel bleiben

== Umstieg von Five Star ==
1. Vorher vollständiges WordPress-Backup erstellen.
2. Dieses Plugin als Update installieren und aktivieren.
3. Reservierungsverwaltung und öffentliches Formular testen.
4. Danach Five Star Restaurant Reservations deaktivieren, aber zunächst nicht löschen.
5. Das öffentliche Formular verwendet [gelsendiele_reservierungsformular]. Der bisherige Shortcode [booking-form] wird ebenfalls übernommen.

== Wichtiger Hinweis ==
Das Zahlungsmodul dokumentiert Bar- und Kartenzahlungen, ist aber noch keine RKSV-Registrierkasse und besitzt noch keine direkte Terminalanbindung.

== Aktualisierung ==
Installierbare ZIP-Dateien werden aus einem geprüften Git-Tag erstellt. Vor jedem Produktivupdate sind WordPress-Dateien und Datenbank vollständig zu sichern.

== Changelog ==

= 2.2.0 =
* Produktname in Gelsensystem geändert, ohne datenrelevante Slugs oder Präfixe umzubenennen.
* Vollständige Verwaltung für Sondertage, Betriebsurlaub, Sonderöffnungen, Zeitsperren und Kapazitätsgrenzen.
* Sonderregeln wirken unmittelbar auf öffentlichen Kalender und Zeitslots.
* Fehlerhafte typografische Anführungszeichen auf bestehenden Gastro-Arbeitsseiten werden automatisch repariert.
* Five-Star-Zeitangaben im 12-Stunden-Format werden beim einmaligen Import korrekt normalisiert.
* Atomare Kurzzeitsperren verhindern doppelte oder konkurrierende Reservierungsübermittlungen.
