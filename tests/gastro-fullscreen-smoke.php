<?php

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['gdg_test_page_id'] = 3555;
$GLOBALS['gdg_test_view']    = 'service';

function is_admin() { return false; }
function is_singular( $type = '' ) { return 'page' === $type; }
function get_queried_object_id() { return $GLOBALS['gdg_test_page_id']; }
function get_post_meta( $post_id, $key, $single = false ) {
	return '_gdg_view' === $key && $post_id === $GLOBALS['gdg_test_page_id'] ? $GLOBALS['gdg_test_view'] : '';
}
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }

require dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/modules/gastro/includes/class-gdg-app.php';

function gdg_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Gastro-Oberflächentest fehlgeschlagen: {$message}\n" );
		exit( 1 );
	}
}

gdg_expect( GDG_App::is_app_page(), 'Arbeitsseite wird nicht erkannt' );
gdg_expect( 'service' === GDG_App::current_view(), 'Ansicht wird nicht aus Seitenmetadaten gelesen' );
gdg_expect( false === GDG_App::hide_admin_bar_on_app_pages( true ), 'Adminleiste bleibt auf Arbeitsseite aktiv' );

$classes = GDG_App::add_standalone_body_class( array( 'page' ) );
gdg_expect( in_array( 'gdg-standalone-app', $classes, true ), 'Vollbild-Klasse fehlt' );
gdg_expect( in_array( 'gdg-view-service', $classes, true ), 'Ansichtsklasse fehlt' );

$GLOBALS['gdg_test_view'] = 'invalid';
gdg_expect( '' === GDG_App::current_view(), 'Ungültige Ansicht wird nicht verworfen' );
gdg_expect( true === GDG_App::hide_admin_bar_on_app_pages( true ), 'Adminleiste wird außerhalb der App verändert' );

$root     = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/modules/gastro/';
$template = file_get_contents( $root . 'templates/gastro-app.php' );
$css      = file_get_contents( $root . 'assets/app.css' );
$js       = file_get_contents( $root . 'assets/app.js' );

gdg_expect( false !== strpos( $template, 'gdg-app-root' ), 'eigenständiges App-Template fehlt' );
gdg_expect( false !== strpos( $css, 'body.gdg-standalone-app .gdg-app' ), 'Vollbild-CSS fehlt' );
gdg_expect( false !== strpos( $css, 'html[data-gdg-theme]' ), 'Theme-Mindestbreite wird nicht überschrieben' );
gdg_expect( false !== strpos( $css, 'min-width: 0 !important' ), 'Theme-Mindestbreite bleibt aktiv' );
gdg_expect( false !== strpos( $css, '.gdg-loading[hidden]' ), 'Lade-Layer respektiert hidden nicht' );
gdg_expect( false !== strpos( $js, "localStorage.setItem('gd-dashboard-theme'" ), 'Theme-Auswahl wird nicht systemweit geteilt' );
gdg_expect( false !== strpos( $js, 'document.documentElement.dataset.gdgTheme' ), 'Dokument-Theme wird nicht aktualisiert' );
gdg_expect( false !== strpos( $css, '.gdg-app.is-nav-collapsed' ), 'einklappbarer Fokusmodus fehlt' );
gdg_expect( false !== strpos( $css, '@media (min-width: 791px)' ), 'Tablet- und Desktop-Seitenleiste fehlt' );
gdg_expect( false !== strpos( $js, "localStorage.getItem('gd-sidebar-collapsed')" ), 'Menüzustand wird nicht geteilt' );
gdg_expect( false !== strpos( $js, "localStorage.setItem('gd-sidebar-collapsed'" ), 'Menüzustand wird nicht gespeichert' );

echo "Gastro-Vollbild- und Theme-Tests erfolgreich.\n";
