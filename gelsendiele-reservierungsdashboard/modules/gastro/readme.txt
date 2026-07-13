=== Gelsensystem Gastro ===
Contributors: Andreas Schwarz / Gelsendiele
Tags: gastronomie, bestellung, küche, schank, tische, reservierung
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 2.13.1

Service-, Küchen-, Schank- und Zahlungsmodul des Gelsensystems.

== Funktionen ==

* Gemeinsame WordPress-Datenbank für Tische, Speisekarte, Bestellungen und Zahlungen
* Responsive Serviceansicht für Smartphone und Tablet
* Tischstatus frei/belegt
* Bestellung mit Küchen- oder Schankzuordnung
* Sonderwünsche je Position
* Küchenmonitor: Neu -> In Zubereitung -> Fertig
* Schankmonitor mit identischem Ablauf
* Service kann fertige Positionen als serviert markieren
* Gesamtrechnung oder getrennte Bezahlung einzelner Mengen
* Bar- und manuelle Kartenzahlung
* Tisch wird nach vollständiger Bezahlung automatisch freigegeben
* Heller und dunkler Modus
* Einklappbare Desktop-Navigation in allen Arbeitsbereichen
* Öffentliche dynamische Speisekarte per [gelsensystem_speisekarte]
* Mitarbeiterrollen: Service, Küche und Schank
* Reservierungs-ID kann mit einer Bestellung verknüpft werden

== Installation ==

1. ZIP in WordPress unter Plugins > Installieren > Plugin hochladen installieren.
2. Plugin aktivieren.
3. Unter Gelsensystem > Tischplan die tatsächlichen Tische einrichten.
4. Unter Gelsensystem > Speisekarte die Artikel einrichten.
5. Die automatisch erstellten Seiten Service, Küche, Schank und Kasse öffnen.
6. Mitarbeiter als WordPress-Benutzer anlegen und die passende Rolle zuweisen.

Die Zentrale liegt unter /gelsensystem/. Service, Küche, Schank und Kasse werden als Arbeitsseiten darunter angelegt. Alte Links unter /reservierungsverwaltung/ werden weitergeleitet.

== Reservierungs-Integration ==

Eine Bestellung kann programmatisch mit einer vorhandenen Reservierung geöffnet werden:

    gelsendiele_gastro_open_order_from_reservation( $reservation_id, $table_id, $guest_name );

Oder über die Service-Seite:

    /service/?reservation_id=123&table_id=6&guest_name=Maier&guest_count=4

Für den Five-Star-Custom-Post-Type rtb-booking wird im WordPress-Backend eine Zeilenaktion „Bestellung öffnen“ ergänzt. Die genaue Schaltfläche im bereits vorhandenen Gelsendiele-Reservierungsdashboard wird nach Einbau in dessen Quellcode ergänzt.

== Kartenterminal und Registrierkasse ==

Version 0.1 speichert Bar- und Kartenzahlungen als interne Arbeitsdaten. Kartenzahlungen werden am vorhandenen Terminal manuell durchgeführt und anschließend bestätigt.

Die Version ersetzt noch keine österreichische Registrierkasse, erstellt keinen RKSV-Beleg und überträgt noch keinen Betrag automatisch an ein Terminal. Diese Funktionen sind als separates Integrationsmodul vorgesehen.

== Datenschutz und Betrieb ==

Die Arbeitsseiten sind nur für angemeldete WordPress-Benutzer mit passender Rolle sichtbar und werden mit No-Cache- sowie Noindex-Headern ausgeliefert.

== Changelog ==

= 2.3.3 =
* Öffentliches Reservierungsformular kompakter gestaltet und Hinweisleiste durch schlichten Text ersetzt.
* Automatische Folgeupdates aus den offiziellen Gelsensystem-GitHub-Releases ergänzt.

= 2.3.2 =
* Öffentlicher Reservierungsformularstil im Hellmodus gegen Theme-Überschreibungen abgesichert.
* Zweites Logo innerhalb des öffentlichen Formulars entfernt.

= 2.3.1 =
* Alle Arbeitsbereiche werden vollflächig ohne WordPress-Theme-Chrom ausgeliefert.
* Konsistenter, barriereärmerer Hell-/Dunkelmodus mit gemeinsam gespeicherter Auswahl.
* Kontrast- und Formularfarben sowie der dauerhaft sichtbare Lade-Layer wurden korrigiert.

= 2.3.0 =
* Gemeinsamer Versionsstand mit den neuen E-Mail- und Formulareinstellungen des Gelsensystems.

= 2.2.0 =
* Produktname auf Gelsensystem vereinheitlicht.
* Bestehende Arbeitsseiten mit typografisch verfälschten Shortcodes werden automatisch repariert.

= 2.1.0 =
* Integration in das zentrale Gelsendiele-Menü und die versionierte Migration.
* Keine automatischen Beispielgerichte in Produktivinstallationen.
* Flexible Marke und engere REST-Berechtigungen.

= 0.1.0 =
* Erstes funktionsfähiges Grundmodul.
