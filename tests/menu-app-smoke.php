<?php

$root  = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/';
$entry = file_get_contents( $root . 'gelsendiele-reservierungsdashboard.php' );
$admin = file_get_contents( $root . 'modules/gastro/includes/class-gdg-admin.php' );
$gastro = file_get_contents( $root . 'modules/gastro/gelsendiele-gastro-system.php' );
$css   = file_get_contents( $root . 'assets/dashboard.css' );

function menu_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Speisekarten-App-Test fehlgeschlagen: {$message}\n" );
		exit( 1 );
	}
}

menu_expect( false !== strpos( $entry, "array( 'reservations', 'settings', 'users', 'menu' )" ), 'Speisekarte ist keine zentrale App-Sektion' );
menu_expect( false !== strpos( $entry, "GDG_Admin::render_app_menu" ), 'App-Renderer wird nicht aufgerufen' );
menu_expect( false !== strpos( $entry, "add_query_arg( 'gd-section', 'menu', \$dashboard )" ), 'Navigation führt nicht in die zentrale App' );
menu_expect( false !== strpos( $admin, 'public static function render_app_menu' ), 'Speisekarten-Renderer fehlt' );
menu_expect( false !== strpos( $admin, 'name="gdg_context" value="app"' ), 'Frontend-Kontext für sichere Weiterleitung fehlt' );
menu_expect( false !== strpos( $admin, "check_admin_referer( 'gdg_admin_action', 'gdg_nonce' )" ), 'Nonce-Prüfung fehlt' );
menu_expect( false !== strpos( $admin, "current_user_can( 'gdg_manage' )" ), 'Berechtigungsprüfung fehlt' );
menu_expect( false !== strpos( $gastro, "add_action( 'template_redirect', array( 'GDG_Admin', 'handle_actions' ), 1 )" ), 'Frontend-Speicherung wird nicht verarbeitet' );
menu_expect( false !== strpos( $css, '.gelsensystem-menu-editor-grid' ), 'Desktop-Layout fehlt' );
menu_expect( false !== strpos( $css, '@media (max-width:620px)' ), 'Smartphone-Layout fehlt' );
menu_expect( false !== strpos( $css, 'html[data-gd-theme="dark"] .gelsensystem-menu-form' ), 'Dark-Mode-Stile fehlen' );

echo "Speisekarten-App-Tests erfolgreich.\n";
