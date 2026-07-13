<?php

$root       = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/';
$entry      = file_get_contents( $root . 'gelsendiele-reservierungsdashboard.php' );
$events     = file_get_contents( $root . 'includes/class-gelsensystem-events.php' );
$admin_css  = file_get_contents( $root . 'assets/dashboard.css' );
$public_css = file_get_contents( $root . 'assets/public-events.css' );

function events_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Event-App-Test fehlgeschlagen: {$message}\n" );
		exit( 1 );
	}
}

events_expect( false !== strpos( $entry, "'events'       => 'gdg_manage'" ), 'Berechtigung für zentrale Eventsektion fehlt' );
events_expect( false !== strpos( $entry, 'Gelsensystem_Events::render_app' ), 'Event-App-Renderer wird nicht aufgerufen' );
events_expect( false !== strpos( $entry, "add_query_arg( 'gd-section', 'events', \$dashboard )" ), 'Event-Navigation fehlt' );
events_expect( false !== strpos( $events, "add_shortcode( self::SHORTCODE" ), 'öffentlicher Shortcode fehlt' );
events_expect( false !== strpos( $events, "add_rewrite_rule( '^events/?$'" ), 'bestehende Enfold-Event-URL wird nicht übernommen' );
events_expect( false !== strpos( $events, "current_user_can( 'gdg_manage' )" ), 'Berechtigungsprüfung fehlt' );
events_expect( false !== strpos( $events, "check_admin_referer( 'gse_event_action', 'gse_nonce' )" ), 'Nonce-Prüfung fehlt' );
events_expect( false !== strpos( $events, "wp_trash_post" ), 'sicheres Löschen in den Papierkorb fehlt' );
events_expect( false !== strpos( $events, "self::META_ACTIVE" ), 'öffentliche Sichtbarkeitssteuerung fehlt' );
events_expect( false !== strpos( $events, "self::META_IMAGE_ID" ), 'Eventfoto wird nicht dauerhaft gespeichert' );
events_expect( false !== strpos( $events, "media_handle_upload( 'event_image'" ), 'sicherer WordPress-Bildupload fehlt' );
events_expect( false !== strpos( $events, 'enctype="multipart/form-data"' ), 'Eventformular unterstützt keine Bilddateien' );
events_expect( false !== strpos( $events, 'gse-event-card__image' ), 'Eventfoto fehlt in der öffentlichen Ausgabe' );
events_expect( false !== strpos( $events, "wp_enqueue_style( 'gelsensystem-public-events'" ), 'öffentliche Stile werden nicht bedarfsgerecht geladen' );
events_expect( false !== strpos( $admin_css, '.gelsensystem-events-editor-grid' ), 'responsives Verwaltungs-Layout fehlt' );
events_expect( false !== strpos( $admin_css, 'html[data-gd-theme="dark"] .gelsensystem-events-form' ), 'Dark-Mode-Stile fehlen' );
events_expect( false !== strpos( $public_css, '.gse-event-card' ), 'öffentliche Eventkarten fehlen' );
events_expect( false !== strpos( $public_css, '.gse-event-card__image' ), 'responsive Eventfoto-Stile fehlen' );
events_expect( false !== strpos( $public_css, '@media (max-width:700px)' ), 'Smartphone-Layout fehlt' );

echo "Event-App-Tests erfolgreich.\n";
