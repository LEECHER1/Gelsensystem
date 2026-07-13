=== Gelsensystem ===
Contributors: Andreas Schwarz / OpenAI
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.15.3

Zentrales Reservierungs-, Service-, Küchen-, Schank- und Zahlungsgrundsystem für Gastronomiebetriebe.

== Enthalten ==
* Zentrale Gelsensystem-App für Reservierung und Gastro-Module
* App-interne Einstellungen sowie Benutzer- und Rechteverwaltung
* Versionierte, wiederholbare Migrationen bei Installation und Update
* Flexible Betriebs-, Logo-, Farb- und Darstellungsoptionen
* Mehrere Öffnungsblöcke pro Wochentag und Öffnungen über Mitternacht
* Öffentliches Reservierungsformular: [gelsendiele_reservierungsformular]
* Öffentliche, dynamische Speisekarte für Enfold und andere Themes: [gelsensystem_speisekarte]
* Schlanke Eventverwaltung mit öffentlicher Enfold-Ausgabe: [gelsensystem_events]
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

= 2.15.3 =
* Eventbilder werden in der Zentrale über einen eigenen, zuverlässigen WordPress-Mediathek-Dialog ausgewählt, gesucht und hochgeladen.
* Die Bildauswahl bleibt auch dann funktionsfähig, wenn Theme oder Frontend-Erweiterungen WordPress-Medienskripte entfernen.

= 2.15.2 =
* Von Frontend-Erweiterungen entfernte WordPress-Mediathek-Assets werden für die Event-App am Ende der Ladephase wiederhergestellt.

= 2.15.1 =
* Die WordPress-Mediathek wird vor der Eventverwaltung geladen und öffnet dadurch zuverlässig in der Frontend-App.

= 2.15.0 =
* Eventbilder werden direkt über die WordPress-Mediathek ausgewählt, sortiert und entfernt.
* Veröffentlichte WordPress-Seiten lassen sich über einen Schnellwähler als Eventlink übernehmen.
* Eventübersicht zeigt Kurztext, Zusatzinfos, Bilder, Link, Popup-Zeitraum und Eventfarbe.
* Ganztägig steht beim Enddatum und deaktiviert die Uhrzeitfelder; Eventfarbe steht am Formularende.
* Speisekarten-Kategorien werden direkt im Artikelformular angelegt; Einträge und Kategorien können gelöscht werden.
* Die öffentliche Speisekarte ordnet alle Kategorien übersichtlich untereinander an.

= 2.14.1 =
* Events werden nach jeder Datums- oder Uhrzeitänderung zuverlässig neu chronologisch sortiert.
* Kalenderfilter zeigt standardmäßig das aktuelle Datum und filtert erst nach einer bewussten Auswahl.

= 2.14.0 =
* Neue Events erhalten sofort Start- und Enddatum; eintägige Events synchronisieren beide Datumsfelder automatisch.
* Webseitenfeld vereinfacht und den überflüssigen Protokollhinweis entfernt.
* Einheitliche Seitenleiste, vollständige Bereichsnavigation und funktionsfähiger Hell-/Dunkelmodus auf allen App-Seiten.

= 2.13.2 =
* Startseiten-Popup wird vor den Footer-Skripten ausgegeben und zuverlässig initialisiert.
* Popup-Zeitraum erscheint direkt unter der Popup-Option und nur bei gesetztem Häkchen.

= 2.13.1 =
* Event-Webseiten können ohne Protokoll eingegeben werden und werden automatisch als HTTPS-Link gespeichert.
* „Mehr Infos“ und „Zur Webseite“ erscheinen als gemeinsame Aktionsleiste.
* Start- und Enddatum für das Startseiten-Popup ergänzt und Homepage-Erkennung stabilisiert.

= 2.13.0 =
* Eventverwaltung im WordPress-Menü ergänzt.
* Mehrere Eventbilder, aufklappbare Zusatzinfos, Eventfarben und optionales Startseiten-Popup ergänzt.
* Ladeanzeige und Duplikatschutz verhindern mehrfaches Anlegen bei langsamen Bild-Uploads.
* Öffentliche Eventliste erhält kommende, vergangene und alle Events sowie einen Kalenderfilter.

= 2.12.1 =
* Eventfotos lassen sich direkt in der Eventverwaltung hochladen, ersetzen und entfernen.
* Öffentliche Eventkarten zeigen Fotos responsiv auf Smartphone, Tablet und Desktop.

= 2.12.0 =
* Neue Eventverwaltung als eigener Bereich der Gelsensystem-App.
* Events mit Titel, Datum, Uhrzeit, Ort, Beschreibung, Link und Sichtbarkeit pflegen.
* Responsive Website-Ausgabe über [gelsensystem_events] und automatische Übernahme der URL /events/.

= 2.11.0 =
* Einheitlicher Bereichswechsler als App-Drawer auf Smartphone und Tablet.
* Reservierungen, Service, Küche, Schank, Kasse und Einstellungen sind über denselben Schnellwechsler erreichbar.
* Service erhält eine kompakte Smartphone-Navigation für Tische, Speisekarte und Bestellung.
* Küche und Schank verwenden dieselbe Karten- und Aktionslogik wie Bestellung und Kasse.

= 2.10.1 =
* Der einklappbare Fokusmodus greift nun auch zuverlässig in der Tablet-Queransicht.
* Schmale Tablet-Arbeitsflächen wechseln automatisch auf ein einspaltiges Inhaltslayout.

= 2.10.0 =
* Service, Küche, Schank und Kasse erhalten dieselbe einklappbare Desktop-Seitenleiste wie die Zentrale.
* Der Fokusmodus bleibt beim Wechsel zwischen allen Arbeitsbereichen erhalten.
* Neuer responsiver Shortcode [gelsensystem_speisekarte] für eine dynamische Website-Speisekarte.
* Kategorien, Sichtbarkeit, Beschreibungen und Preise stammen immer direkt aus der Gelsensystem-Pflege.

= 2.9.0 =
* Einklappbare Desktop-Seitenleiste für eine breite Fokusansicht.
* Reservierungskarten lassen sich nun auch auf Tablet und Desktop schließen und öffnen.
* Aktualisierung als Icon oben rechts sowie Wischaktualisierung auf Touch-Geräten.
* Zentrierter, rahmenloser Hell-/Dunkelmodus-Schalter im Desktop-Kopf.

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
