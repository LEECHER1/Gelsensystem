<?php

$root       = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/';
$entry      = file_get_contents( $root . 'gelsendiele-reservierungsdashboard.php' );
$events     = file_get_contents( $root . 'includes/class-gelsensystem-events.php' );
$admin_css  = file_get_contents( $root . 'assets/dashboard.css' );
$public_css = file_get_contents( $root . 'assets/public-events.css' );
$admin_js   = file_get_contents( $root . 'assets/dashboard.js' );
$public_js  = file_get_contents( $root . 'assets/public-events.js' );
$wp_admin   = file_get_contents( $root . 'includes/class-gelsendiele-admin.php' );

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
events_expect( false !== strpos( $events, "media_handle_upload( 'gse_event_image'" ), 'sicherer WordPress-Bildupload fehlt' );
events_expect( false !== strpos( $events, 'enctype="multipart/form-data"' ), 'Eventformular unterstützt keine Bilddateien' );
events_expect( false !== strpos( $events, 'name="event_images[]"' ) && false !== strpos( $events, 'multiple' ), 'Mehrfach-Bildupload fehlt' );
events_expect( false !== strpos( $events, 'self::META_DETAILS' ) && false !== strpos( $events, 'gse-event-card__details' ), 'aufklappbare Zusatzinformationen fehlen' );
events_expect( false !== strpos( $events, 'self::META_POPUP' ) && false !== strpos( $events, 'data-gse-popup' ), 'Startseiten-Popup fehlt' );
events_expect( false !== strpos( $events, "render_homepage_popup' ), 5" ), 'Popup muss vor den Footer-Skripten ausgegeben werden' );
events_expect( false !== strpos( $events, 'self::META_POPUP_START' ) && false !== strpos( $events, 'self::META_POPUP_END' ), 'Popup-Zeitraum fehlt' );
events_expect( false !== strpos( $events, "default_popup_start_date" ) && false !== strpos( $events, "modify( '-1 day' )" ), 'automatischer Popup-Start fehlt' );
events_expect( false !== strpos( $events, "self::normalize_url" ) && false !== strpos( $events, "'https://'" ), 'vereinfachte URL-Normalisierung fehlt' );
events_expect( false !== strpos( $events, 'inputmode="url"' ) && false === strpos( $events, 'type="url" name="link"' ), 'vereinfachtes Linkfeld fehlt' );
events_expect( false !== strpos( $events, '>Mehr Infos <' ) && false !== strpos( $events, '>Zur Webseite <' ), 'öffentliche Eventaktionen fehlen' );
events_expect( false !== strpos( $events, 'self::META_COLOR' ) && false !== strpos( $events, 'type="color"' ), 'Event-Farbauswahl fehlt' );
events_expect( false !== strpos( $events, 'self::META_SUBMISSION' ) && false !== strpos( $events, 'submission_exists' ), 'serverseitiger Duplikatschutz fehlt' );
events_expect( false !== strpos( $events, 'data-gse-filters' ) && false !== strpos( $events, 'data-gse-date' ), 'Status- und Kalenderfilter fehlen' );
events_expect( false !== strpos( $events, 'gse-event-card__image' ), 'Eventfoto fehlt in der öffentlichen Ausgabe' );
events_expect( false !== strpos( $events, "wp_enqueue_style( 'gelsensystem-public-events'" ), 'öffentliche Stile werden nicht bedarfsgerecht geladen' );
events_expect( false !== strpos( $wp_admin, "'gelsendiele-events'" ), 'WordPress-Untermenü für Events fehlt' );
events_expect( false !== strpos( $admin_css, '.gelsensystem-events-editor-grid' ), 'responsives Verwaltungs-Layout fehlt' );
events_expect( false !== strpos( $admin_css, '.gelsensystem-events-save-progress' ), 'sichtbarer Speicherfortschritt fehlt' );
events_expect( false !== strpos( $admin_js, "form.dataset.submitting === '1'" ), 'clientseitiger Mehrfachklickschutz fehlt' );
events_expect( false !== strpos( $admin_js, 'data-gse-popup-start' ) && false !== strpos( $admin_js, 'previousDay' ), 'Popup-Datumsautomatik fehlt' );
events_expect( false !== strpos( $events, "current_datetime()->format( 'Y-m-d' )" ), 'Startdatum wird bei neuen Events nicht vorbelegt' );
events_expect( false !== strpos( $events, '$end_date_value = $start_date_value' ) && false !== strpos( $admin_js, 'syncEventDates' ), 'Enddatum folgt dem Startdatum nicht automatisch' );
events_expect( false !== strpos( $events, 'placeholder="www.*"' ) && false === strpos( $events, 'Domain genügt' ), 'vereinfachter Webseitenhinweis fehlt' );
events_expect( false !== strpos( $admin_js, 'popupSchedule.hidden = !enabled' ) && false !== strpos( $admin_css, '.gelsensystem-events-popup-schedule[hidden]' ), 'Popup-Zeitraum wird nicht bedingt ein- und ausgeblendet' );
events_expect( false !== strpos( $admin_css, 'html[data-gd-theme="dark"] .gelsensystem-events-form' ), 'Dark-Mode-Stile fehlen' );
events_expect( false !== strpos( $public_css, '.gse-event-card' ), 'öffentliche Eventkarten fehlen' );
events_expect( false !== strpos( $public_css, '.gse-event-card__image' ), 'responsive Eventfoto-Stile fehlen' );
events_expect( false !== strpos( $public_css, '.gse-event-popup' ), 'responsive Popup-Stile fehlen' );
events_expect( false !== strpos( $public_js, 'applyFilters' ) && false !== strpos( $public_js, 'sessionStorage' ), 'Filter- oder Popup-Interaktion fehlt' );
events_expect( false !== strpos( $public_js, 'data-gse-details-toggle' ) && false !== strpos( $public_css, '.gse-event-card__actions' ), 'neue Mehr-Infos-Bedienung fehlt' );
events_expect( false !== strpos( $public_js, 'DOMContentLoaded' ) && false !== strpos( $public_js, 'initializePopup' ), 'robuste Popup-Initialisierung fehlt' );
events_expect( false !== strpos( $public_css, '@media (max-width:700px)' ), 'Smartphone-Layout fehlt' );

echo "Event-App-Tests erfolgreich.\n";
