<?php

$root     = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/';
$entry    = file_get_contents( $root . 'gelsendiele-reservierungsdashboard.php' );
$migrator = file_get_contents( $root . 'includes/class-gelsendiele-migrator.php' );
$db       = file_get_contents( $root . 'modules/gastro/includes/class-gdg-db.php' );
$css      = file_get_contents( $root . 'assets/dashboard.css' );
$js       = file_get_contents( $root . 'assets/dashboard.js' );

function central_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Zentrale-URL-/Responsive-Test fehlgeschlagen: {$message}\n" );
		exit( 1 );
	}
}

central_expect( false !== strpos( $entry, 'public static function ensure_central_page' ), 'zentrale Seitenmigration fehlt' );
central_expect( false !== strpos( $entry, "'post_name'    => \$target_slug" ), 'kanonischer Slug wird nicht gespeichert' );
central_expect( false !== strpos( $entry, "\$target_slug = 'gelsensystem'" ), 'kanonische URL /gelsensystem/ fehlt' );
central_expect( false !== strpos( $entry, 'public function redirect_legacy_app_urls' ), 'Weiterleitung alter URLs fehlt' );
central_expect( false !== strpos( $entry, "home_url( '/reservierungsverwaltung/' )" ), 'alte URL wird nicht erkannt' );
central_expect( false !== strpos( $migrator, 'Gelsendiele_Reservierungsdashboard::ensure_central_page()' ), 'URL-Migration läuft bei Updates nicht' );
central_expect( false !== strpos( $migrator, "add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 1 )" ), 'URL-Migration läuft zu früh' );
central_expect( false === strpos( $migrator, "add_action( 'plugins_loaded', array( __CLASS__, 'maybe_migrate' )" ), 'unsichere plugins_loaded-Migration ist noch aktiv' );
central_expect( false !== strpos( $db, "get_option( 'gd_reservierungsdashboard_page_id'" ), 'Arbeitsseiten verwenden nicht die neue Zentrale' );
central_expect( false !== strpos( $entry, 'class="gelsensystem-mobile-nav"' ), 'Touch-Navigation fehlt' );
central_expect( false !== strpos( $entry, 'render_more_app_links' ), 'Arbeitsbereiche fehlen im mobilen Mehr-Menü' );
central_expect( false !== strpos( $css, '@media (min-width:700px) and (max-width:1024px)' ), 'Tablet-Breakpoint fehlt' );
central_expect( false !== strpos( $css, '@media (max-width:699px)' ), 'Smartphone-Breakpoint fehlt' );
central_expect( false !== strpos( $css, '.gelsensystem-mobile-nav a { display:flex' ), 'Touch-Ziele der Navigation fehlen' );
central_expect( false !== strpos( $css, 'html[data-gd-theme="dark"] .gelsensystem-mobile-nav' ), 'Dark Mode der mobilen Navigation fehlt' );
central_expect( 1 === substr_count( $entry, 'data-export-csv' ), 'CSV-Export ist außerhalb des Werkzeugmenüs noch doppelt sichtbar' );
central_expect( 1 === substr_count( $entry, 'data-export-xlsx' ), 'Excel-Export ist außerhalb des Werkzeugmenüs noch doppelt sichtbar' );
central_expect( false !== strpos( $entry, 'class="gd-desktop-settings-button"' ), 'Desktop-Einstellungsmenü fehlt' );
central_expect( false !== strpos( $entry, 'Einstellungen &amp; Werkzeuge' ), 'Werkzeugmenü ist nicht eindeutig benannt' );
central_expect( false !== strpos( $css, '.gd-desktop-tabs { display:flex !important' ), 'Tablet-Statusleiste mit allen Zählern fehlt' );
central_expect( false !== strpos( $css, '.gd-tab:not(.is-active) span[data-count]' ), 'Dark-Mode-Kontrast für inaktive Zähler fehlt' );
central_expect( false !== strpos( $css, '@media (min-width:1025px)' ) && false !== strpos( $css, '.gd-sheet-layer.is-open' ), 'Desktop-Seitenmenü fehlt' );
central_expect( false !== strpos( $js, "scope: GDReservations.pwaScope || '/gelsensystem/'" ), 'PWA-Fallback nutzt noch die alte URL' );

echo "Zentrale-URL- und Responsive-Tests erfolgreich.\n";
