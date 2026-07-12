=== Gelsensystem ===
Contributors: Andreas Schwarz / OpenAI
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.8.0

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

= 2.8.0 =
* Tablet-Statusleiste zeigt alle wichtigen Reservierungszähler gleichzeitig.
* Desktop-Hauptansicht auf Tagesarbeit, Suche und neue Reservierungen reduziert.
* CSV, Excel und selten benötigte Optionen in ein seitliches Einstellungs- und Werkzeugmenü verschoben.
* Dark-Mode-Kontrast der inaktiven Zähler korrigiert.

= 2.7.2 =
* Hebt die feste Mindestbreite des Website-Themes in der eigenständigen App auf Smartphones und Tablets sicher auf.
* Verhindert horizontales Scrollen außerhalb der vorgesehenen Touch-Navigation.

= 2.7.1 =
* URL-Migration in die sichere WordPress-init-Phase verschoben.
* Verhindert den Fehler "Undefined constant WP_POST_REVISIONS" unter WordPress 7.0.1.
* Zentrale Seite wird nur noch aktualisiert, wenn sich Titel, Slug, Status oder Shortcode tatsächlich ändern.

= 2.7.0 =
* Zentrale Gelsensystem-App auf die eigene kanonische URL /gelsensystem/ umgestellt.
* Alte Links unter /reservierungsverwaltung/ werden einschließlich Arbeitsbereichen sicher weitergeleitet.
* Neue Touch-Navigation für Smartphone und Tablet mit allen berechtigten Arbeitsbereichen.
* Größere Bedienelemente, kompaktere Smartphone-Layouts und optimierte Tablet-Aufteilung.
* Hell-/Dunkelmodus kann nun auch direkt in allen zentralen Verwaltungsseiten gewechselt werden.

= 2.6.0 =
* Tische und Bereiche vollständig in die zentrale Gelsensystem-App integriert.
* Tischname, Sitzplätze, Bereich, Reihenfolge und Sichtbarkeit können direkt gepflegt werden.
* Live-Belegungsstatus und Sitzplatzsummen pro Bereich ergänzt.
* Responsive Desktop- und Smartphone-Darstellung sowie Dark-Mode-Stile ergänzt.

= 2.5.0 =
* Speisekartenverwaltung vollständig in die zentrale Gelsensystem-App integriert.
* Kategorien, Gerichte, Getränke, Preise, Ausgabe, Reihenfolge und Sichtbarkeit können ohne Wechsel ins WordPress-Backend gepflegt werden.
* Responsive Desktop- und Smartphone-Darstellung sowie Dark-Mode-Stile ergänzt.
* Service, Küche und Schank verwenden weiterhin unmittelbar dieselben Speisekartendaten.

= 2.4.5 =
* Einstellungsformulare verwenden in App und WordPress-Backend jeweils einen kompatiblen Speichern-Button.

= 2.4.4 =
* App-Skripte werden WordPress-konform mit defer im Dokumentkopf geladen und dadurch nicht von Frontend-Optimierern entfernt.

= 2.4.3 =
* App-Skripte werden vor WordPress-Footer-Hooks ausgegeben, die auf einzelnen Themes die weitere Ausgabe beenden.

= 2.4.2 =
* App-Skripte werden unabhängig von fehlerhaften Theme-Markierungen zuverlässig geladen.
* Die Einstellungsinitialisierung ist gegen eine mögliche doppelte Skriptausgabe abgesichert.

= 2.4.1 =
* App-interne Einstellungen laden ihre dynamischen Skripte zuverlässig, auch wenn ein Theme die WordPress-Footer-Ausgabe verändert.
* Hell-/Dunkelmodus wird bereits vor dem Aufbau der zentralen App gesetzt und bleibt beim Bereichswechsel erhalten.

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
