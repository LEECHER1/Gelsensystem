=== Gelsensystem ===
Contributors: Andreas Schwarz / OpenAI
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.4.0

Zentrales Reservierungs-, Service-, Küchen-, Schank- und Zahlungsgrundsystem für Gastronomiebetriebe.

== Enthalten ==
* Zentrale Gelsensystem-App für Reservierung und Gastro-Module
* App-interne Einstellungen sowie Benutzer- und Rechteverwaltung
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
* Eigene Text-/HTML-E-Mail-Vorlagen mit Platzhaltern, Testversand und optionalen Erinnerungen
* Konfigurierbare Formularfelder, Beschriftungen, Pflichtangaben, Breite, Farben und Darstellung
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

= 2.4.0 =
* Backend-Produktname vollständig auf Gelsensystem umgestellt; der Betriebsname bleibt Kundendaten.
* Neue zentrale Desktop-Navigation bei unverändert kompakter Smartphone-Bedienung.
* WordPress-Einstellungen sind direkt in der App erreichbar und speicherbar.
* Benutzerrollen können innerhalb der App verwaltet werden.
* GitHub-Updater auf das Repository LEECHER1/Gelsensystem umgestellt.

= 2.3.3 =
* Das Reservierungsformular ist auf Desktop kompakter und benötigt deutlich weniger Bildschirmhöhe.
* Der Verfügbarkeitshinweis ist schlichter Text mit Sternchen; die leere gelbe Hinweisleiste entfällt.
* Neue offizielle GitHub-Releases werden über den nativen WordPress-Updater automatisch erkannt und eingespielt.

= 2.3.2 =
* Das öffentliche Reservierungsformular besitzt keinen zweiten Logoblock mehr; das Navigationslogo bleibt unverändert.
* Der helle Formularmodus überschreibt Theme-Button- und Feldfarben zuverlässig.

= 2.3.1 =
* Service, Küche, Schank und Kasse verwenden ein eigenständiges Vollbild-Template ohne Theme-Header, Footer oder Breitenbegrenzung.
* Hell- und Dunkelmodus gelten für die vollständige Arbeitsoberfläche und bleiben beim Wechsel zwischen Reservierungen und Gastro-Modulen erhalten.
* Farben von Überschriften, Tischen, Formularfeldern und Statusanzeigen sind in beiden Darstellungen kontraststark.
* Der Ladebildschirm wird nach erfolgreichem Datenabruf zuverlässig ausgeblendet.

= 2.3.0 =
* Sieben eigene E-Mail-Vorlagen für Betrieb, Gast, Statusänderungen und Erinnerung.
* Text/HTML, Empfängerwahl, Platzhalter und kontrollierter Testversand.
* Zeitgesteuerte Erinnerungen für bestätigte Reservierungen.
* Formularfelder und Pflichtangaben zentral konfigurierbar; zusätzliche Wünsche werden strukturiert gespeichert.
* Eigene Formulartexte, Breite, Farben und Hell-/Dunkelmodus.

= 2.2.0 =
* Produktname in Gelsensystem geändert, ohne datenrelevante Slugs oder Präfixe umzubenennen.
* Vollständige Verwaltung für Sondertage, Betriebsurlaub, Sonderöffnungen, Zeitsperren und Kapazitätsgrenzen.
* Sonderregeln wirken unmittelbar auf öffentlichen Kalender und Zeitslots.
* Fehlerhafte typografische Anführungszeichen auf bestehenden Gastro-Arbeitsseiten werden automatisch repariert.
* Five-Star-Zeitangaben im 12-Stunden-Format werden beim einmaligen Import korrekt normalisiert.
* Atomare Kurzzeitsperren verhindern doppelte oder konkurrierende Reservierungsübermittlungen.
