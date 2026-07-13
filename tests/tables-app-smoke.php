<?php

$root   = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/';
$entry  = file_get_contents( $root . 'gelsendiele-reservierungsdashboard.php' );
$admin  = file_get_contents( $root . 'modules/gastro/includes/class-gdg-admin.php' );
$css    = file_get_contents( $root . 'assets/dashboard.css' );

function tables_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Tisch-App-Test fehlgeschlagen: {$message}\n" );
		exit( 1 );
	}
}

tables_expect( false !== strpos( $entry, "array( 'reservations', 'settings', 'users', 'menu', 'tables', 'events' )" ), 'Tische sind keine zentrale App-Sektion' );
tables_expect( false !== strpos( $entry, 'GDG_Admin::render_app_tables' ), 'App-Renderer wird nicht aufgerufen' );
tables_expect( false !== strpos( $entry, "add_query_arg( 'gd-section', 'tables', \$dashboard )" ), 'Navigation führt nicht in die zentrale App' );
tables_expect( false !== strpos( $admin, 'public static function render_app_tables' ), 'Tisch-Renderer fehlt' );
tables_expect( false !== strpos( $admin, 'name="gdg_section" value="tables"' ), 'Sichere App-Weiterleitung fehlt' );
tables_expect( false !== strpos( $admin, "GDG_DB::get_open_orders()" ), 'Live-Belegungsstatus fehlt' );
tables_expect( false !== strpos( $admin, "check_admin_referer( 'gdg_admin_action', 'gdg_nonce' )" ), 'Nonce-Prüfung fehlt' );
tables_expect( false !== strpos( $admin, "current_user_can( 'gdg_manage' )" ), 'Berechtigungsprüfung fehlt' );
tables_expect( false !== strpos( $css, '.gelsensystem-table-layout' ), 'Desktop-Layout fehlt' );
tables_expect( false !== strpos( $css, 'html[data-gd-theme="dark"] .gelsensystem-table-form' ), 'Dark-Mode-Stile fehlen' );
tables_expect( false !== strpos( $css, '.gelsensystem-table-summary { grid-template-columns:repeat(2' ), 'Smartphone-Zusammenfassung fehlt' );

echo "Tisch-App-Tests erfolgreich.\n";
