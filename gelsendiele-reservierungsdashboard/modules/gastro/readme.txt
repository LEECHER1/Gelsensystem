=== Gelsendiele Gastro System ===
Contributors: Andreas Schwarz / Gelsendiele
Tags: gastronomie, bestellung, küche, schank, tische, reservierung
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 2.1.0

Service-, Küchen-, Schank- und Zahlungsmodul für die Gelsendiele.

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
* Mitarbeiterrollen: Service, Küche und Schank
* Reservierungs-ID kann mit einer Bestellung verknüpft werden

== Installation ==

1. ZIP in WordPress unter Plugins > Installieren > Plugin hochladen installieren.
2. Plugin aktivieren.
3. Unter Gelsendiele > Tischplan die tatsächlichen Tische einrichten.
4. Unter Gelsendiele > Speisekarte die Artikel einrichten.
5. Die automatisch erstellten Seiten Service, Küche, Schank und Kasse öffnen.
6. Mitarbeiter als WordPress-Benutzer anlegen und die passende Rolle zuweisen.

Wenn die Seite /reservierungsverwaltung/ vorhanden ist, werden die Arbeitsseiten darunter angelegt. Ansonsten wird automatisch die Seite /gelsendiele-gastro/ erstellt.

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

= 2.1.0 =
* Integration in das zentrale Gelsendiele-Menü und die versionierte Migration.
* Keine automatischen Beispielgerichte in Produktivinstallationen.
* Flexible Marke und engere REST-Berechtigungen.

= 0.1.0 =
* Erstes funktionsfähiges Grundmodul.
