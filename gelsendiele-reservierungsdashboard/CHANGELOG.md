# Changelog

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
