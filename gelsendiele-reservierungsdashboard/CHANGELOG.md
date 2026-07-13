# Changelog

## 2.13.2

- Startseiten-Popup vor die Footer-Skripte verschoben, damit die Initialisierung das Markup zuverlässig findet.
- Zusätzliche DOM-Ladeinitialisierung schützt vor abweichenden Theme-Ausgabereihenfolgen.
- Popup-Zeitraum direkt unter die Popup-Checkbox verschoben und bei deaktivierter Option vollständig ausgeblendet.

## 2.13.1

- Linkeingabe akzeptiert Domains ohne `http://`, `https://` oder `www.` und ergänzt automatisch HTTPS.
- Öffentliche Eventkarten zeigen „Mehr Infos“ und „Zur Webseite“ als gemeinsame Buttonzeile.
- Popup-Anzeigezeitraum mit Start- und Enddatum ergänzt; Standard ist ein Tag vor dem Event bis zum Event-Enddatum.
- Homepage-Erkennung und Popup-Sitzungsschlüssel stabilisiert.

## 2.13.0

- Events als eigenen Menüpunkt im WordPress-Backend ergänzt.
- Bis zu zwölf Eventbilder mit Titelbild und öffentlicher Galerie ergänzt.
- Aufklappbare Zusatzinformationen, frei wählbare Eventfarbe und optionales Startseiten-Popup ergänzt.
- Sichtbarer Speicherfortschritt, deaktivierter Speicherbutton und serverseitiges Einmal-Token verhindern doppelte Events.
- Öffentliche Filter für kommende, vergangene und alle Events sowie ein Kalenderfilter ergänzt.

## 2.12.1

- Bild-Upload für Events mit WordPress-Medienverarbeitung ergänzt.
- Vorhandene Eventfotos können ersetzt oder aus dem Event entfernt werden.
- Öffentliche Eventkarten zeigen Bilder responsiv und mit semantischer Bildauszeichnung.

## 2.12.0

- Schlanke Eventverwaltung direkt in die zentrale Gelsensystem-App integriert.
- Titel, Datum, Uhrzeit, Ort, Beschreibung, Link und öffentliche Sichtbarkeit lassen sich ohne EventON pflegen.
- Responsive öffentliche Eventliste über `[gelsensystem_events]` ergänzt.
- Bestehende Enfold-Menüadresse `/events/` zeigt automatisch die Gelsensystem-Events statt der fehlerhaften EventON-Archivseite.
- Smartphone-, Tablet-, Desktop- und Dark-Mode-Layouts für die Eventpflege ergänzt.

## 2.11.0

- Einheitlichen App-Drawer für den schnellen Bereichswechsel auf Smartphone und Tablet ergänzt.
- Zentrale, Einstellungen und alle Gastro-Arbeitsbereiche verwenden denselben Bereichswechsler.
- Service mobil in Tische, Speisekarte und Bestellung gegliedert, damit keine langen Gesamtseiten mehr nötig sind.
- Küchen- und Schanktickets an die Kartenoptik und großen Touch-Aktionen von Bestellung/Kasse angeglichen.

## 2.10.1

- Fokusmodus auf Tablet-Queransichten erweitert und schmale Arbeitsflächen einspaltig optimiert.

## 2.10.0

- Gemeinsame einklappbare Desktop-Seitenleiste auf Service, Küche, Schank und Kasse ergänzt.
- Fokuszustand wird beim Wechsel zwischen Zentrale und Arbeitsbereichen beibehalten.
- Öffentliche, responsive Speisekarte per `[gelsensystem_speisekarte]` für Enfold ergänzt.
- Aktive Kategorien, Gerichte, Beschreibungen und Preise werden direkt aus der zentralen Speisekartenpflege ausgegeben.

## 2.9.0

- Desktop-Seitenleiste lässt sich zu einer kompakten Icon-Leiste einklappen; die Auswahl bleibt lokal gespeichert.
- Reservierungskarten sind auf Tablet und Desktop standardmäßig geschlossen und per Kopfzeile ein- und ausklappbar.
- Aktualisierung als Icon in die obere rechte Aktionsleiste verschoben.
- Wischaktualisierung auf Touch-Tablets und Touch-Desktops erweitert.
- Hell-/Dunkelmodus-Schalter im Desktop-Kopf zentriert und ohne störenden dunklen Kasten gestaltet.

## 2.8.0

- Tablet-Reservierungen zeigen eine eigene Statusleiste mit den Zählern für Offen, Heute, Kommend und Bestätigt.
- Desktop-Hauptansicht auf Suche, neue Reservierung, Aktualisierung und Statusfilter konzentriert.
- Automatisierung, Tischoptionen, WhatsApp-Vorlage sowie CSV-/Excel-Export in ein gemeinsames Einstellungs- und Werkzeugmenü verschoben.
- Neues seitliches Einstellungsmenü für Desktop und klareres Zahnrad-Menü auf Tablets ergänzt.
- Kontrast der inaktiven Statuszähler im Dark Mode korrigiert.

## 2.7.2

- Theme-Mindestbreiten werden in der eigenständigen App bei Smartphone- und Tablet-Breiten überschrieben.
- Horizontale Überbreite bleibt auf die vorgesehene Touch-Navigation begrenzt.

## 2.7.1

- URL- und Seitenmigration läuft erst in der sicheren WordPress-`init`-Phase.
- Behebt `Undefined constant WP_POST_REVISIONS` unter WordPress 7.0.1.
- Unnötige Seitenaktualisierungen werden durch einen Feldvergleich vermieden.

## 2.7.0

- Eigene kanonische URL `/gelsensystem/` für die zentrale App eingeführt.
- Bestehende Zentrale und Gastro-Arbeitsseiten werden idempotent auf die neue URL-Struktur migriert.
- Alte Links unter `/reservierungsverwaltung/` bleiben durch permanente Weiterleitungen kompatibel.
- Horizontale Touch-Navigation für alle berechtigten Arbeitsbereiche auf Smartphone und Tablet ergänzt.
- Reservierungs-Menü um Service, Küche, Schank, Kasse, Tische und Speisekarte erweitert.
- Touch-Ziele, Formularhöhen, Abstände und Tablet-Aufteilungen optimiert.
- Direkter Hell-/Dunkelmodus-Schalter auf zentralen Verwaltungsseiten ergänzt.

## 2.6.0

- Tisch- und Bereichsverwaltung in die zentrale Gelsensystem-App übernommen.
- Tischname, Sitzplätze, Bereich, Reihenfolge und Sichtbarkeit lassen sich direkt pflegen.
- Bereiche werden aus den Tischzuordnungen gebildet und mit Sitzplatzsummen dargestellt.
- Aktuell belegte Tische sind in der Verwaltung sichtbar.
- Responsive Desktop-/Mobile-Oberfläche und Dark-Mode-Stile ergänzt.

## 2.5.0

- Speisekartenverwaltung in die zentrale Gelsensystem-App übernommen.
- Kategorien und Artikel lassen sich mit Preis, Ausgabestation, Reihenfolge und Sichtbarkeit direkt pflegen.
- Eigenständige, responsive Desktop- und Mobile-Oberfläche mit Light-/Dark-Mode ergänzt.
- Bestehende Gastro-Tabellen bleiben die gemeinsame Datenquelle für Service, Küche und Schank.

## 2.4.5
- Kontextabhängiger Speichern-Button verhindert den Frontend-Aufruf der ausschließlich im WordPress-Admin verfügbaren Funktion `submit_button()`.
- Alle sechs app-internen Einstellungsformulare werden dadurch vollständig und ohne PHP-Fehler gerendert.

## 2.4.4
- Zentrale App-Skripte werden in Einstellungs- und Benutzerbereichen registriert, mit `defer` versehen und im Dokumentkopf ausgegeben.
- Direkte Script-Tags entfallen, damit Sicherheits- und Optimierungsfilter des aktiven Themes die Initialisierung nicht entfernen.

## 2.4.3
- App-Skripte werden vor `wp_footer()` ausgegeben und funktionieren damit auch bei Footer-Hooks, die die nachfolgende Template-Ausgabe beenden.
- Build-Prüfung schützt die für das aktive Website-Theme notwendige Ausgabereihenfolge.

## 2.4.2
- Explizite, themeunabhängige Script-Ausgabe in app-internen Einstellungs- und Benutzerbereichen.
- Idempotente Initialisierung verhindert doppelte Event-Handler, falls ein Theme registrierte Skripte doch ausgibt.

## 2.4.1
- Robuste Script-Ausgabe für app-interne Einstellungen bei Themes mit veränderter Footer-Ausgabe.
- Frühzeitige Theme-Initialisierung verhindert falsche Farben und erhält die Hell-/Dunkelwahl über alle App-Bereiche.
- Der Mediathek-Button wird außerhalb des WordPress-Backends nur angezeigt, wenn die Medienbibliothek tatsächlich verfügbar ist.

## 2.4.0
- Backend-Marke, Rollenbezeichnungen, PWA-Name und Arbeitsbereiche heißen nun Gelsensystem; der Betriebsname bleibt als Kundendatensatz erhalten.
- Die Reservierungs-App besitzt auf Desktop eine zentrale Navigation zu allen freigegebenen Modulen.
- Alle vorhandenen WordPress-Einstellungen sind auch innerhalb der eigenständigen App erreichbar.
- Administratoren können Benutzerrollen und Bereichszugriffe direkt in der App verwalten.
- Bestehende technische Slugs, Optionen und Tabellen bleiben zur verlustfreien Datenmigration kompatibel.
- Der automatische Updater verwendet das umbenannte GitHub-Repository `LEECHER1/Gelsensystem`.

## 2.3.3
- Das öffentliche Reservierungsformular benötigt durch kleinere Abstände und Feldhöhen deutlich weniger Bildschirmhöhe.
- Der Verfügbarkeitshinweis wird als schlichter Sternchen-Text ausgegeben; die leere gelbe Hinweisleiste wurde entfernt.
- Ein integrierter, auf das öffentliche Projekt und exakt benannte Release-ZIPs begrenzter GitHub-Updater ermöglicht automatische Folgeupdates über WordPress.

## 2.3.2
- Das öffentliche Reservierungsformular zeigt kein zusätzliches Logo mehr; das Navigationslogo bleibt erhalten.
- Der helle Formularmodus überschreibt Theme-Stile für Datumsschaltfläche, Felder, Kalender und Auswahloptionen zuverlässig.

## 2.3.1
- Service, Küche, Schank und Kasse verwenden dasselbe eigenständige Vollbildprinzip wie die Reservierungsverwaltung.
- Theme-Header, Theme-Footer, Adminleiste und Inhaltsbreiten des WordPress-Themes werden auf den internen Arbeitsseiten entfernt.
- Hell-/Dunkelmodus setzt Dokument, App-Fläche, Browser-Themefarbe und native Formulare konsistent.
- Die Darstellungswahl wird mit dem Reservierungsdashboard geteilt und über alle Gastro-Seiten beibehalten.
- Überschriften, Karten, Formularfelder, Statusanzeigen und Platzhalter besitzen in beiden Modi belastbare Kontraste.
- Versteckte Lade- und Inhaltsbereiche respektieren das `hidden`-Attribut wieder zuverlässig.

## 2.3.0
- Sieben vollständig eigene E-Mail-Vorlagen für neue Anfragen, Eingang, Bestätigung, Ablehnung, Änderung, Stornierung und Erinnerung.
- Vorlagen unterstützen Text oder HTML, Empfängersteuerung, Absender aus den Betriebseinstellungen und sichere Platzhalter.
- Test-E-Mails können nach expliziter Benutzeraktion direkt aus der jeweiligen Vorlage versendet werden.
- Bestätigte Reservierungen können über WordPress-Cron automatisch vor dem Termin erinnert werden.
- Formularfelder für Kontakt, Nachricht, Bereichs-/Tischwunsch, Kinderstuhl, Hund, Allergien und Datenschutz sind aktivierbar und als Pflichtfeld konfigurierbar.
- Beschriftungen, Einleitung, Erfolgs-/Fehlermeldungen, Buttontext, Breite, Farben und Hell-/Dunkelmodus sind zentral einstellbar.
- Zusätzliche Formularangaben werden strukturiert an der Reservierung gespeichert und stehen E-Mail-Vorlagen zur Verfügung.
- Datum, Uhrzeit und Personenzahl bleiben als technisch notwendige Kernfelder geschützt.

## 2.2.0
- Sichtbarer Produkt- und Pluginname auf **Gelsensystem** geändert; technische Slugs und Datenpräfixe bleiben updatekompatibel.
- Funktionale Verwaltung für einzelne Schließtage, Zeiträume, Betriebsurlaub, Sonderöffnungen, Uhrzeitsperren und reduzierte Kapazitäten.
- Sonderregeln werden unmittelbar im öffentlichen Kalender und bei der Zeitslot-Ermittlung berücksichtigt.
- Optionale öffentliche Tageshinweise werden ausschließlich nach Datumsauswahl angezeigt; interne Kommentare bleiben intern.
- Bestehende Gastro-Arbeitsseiten mit typografischen Anführungszeichen im Shortcode werden idempotent repariert.
- Five-Star-Zeitwerte wie `4:00 PM` werden beim einmaligen Import korrekt nach `16:00` normalisiert.
- Der Formularbutton behält nach dem Absenden seinen frei konfigurierbaren Text.
- Kurzzeitige, atomare Sperren verhindern doppelte Formularübermittlungen und Kapazitätsüberschreitungen durch parallele Anfragen.

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
