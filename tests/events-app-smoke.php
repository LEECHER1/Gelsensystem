<?php

$root       = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/';
$entry      = file_get_contents( $root . 'gelsendiele-reservierungsdashboard.php' );
$events     = file_get_contents( $root . 'includes/class-gelsensystem-events.php' );
$admin_css  = file_get_contents( $root . 'assets/dashboard.css' );
$public_css = file_get_contents( $root . 'assets/public-events.css' );
$admin_js   = file_get_contents( $root . 'assets/dashboard.js' );
$public_js  = file_get_contents( $root . 'assets/public-events.js' );
$wp_admin   = file_get_contents( $root . 'includes/class-gelsendiele-admin.php' );
$migrator   = file_get_contents( $root . 'includes/class-gelsendiele-migrator.php' );

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
events_expect( false !== strpos( $events, 'ensure_public_page' ) && false !== strpos( $events, "'post_content' => \$shortcode" ), 'eigenständige WordPress-Event-Seite fehlt' );
events_expect( false !== strpos( $events, 'self::PREVIOUS_CONTENT' ) && false !== strpos( $events, 'add_post_meta' ), 'bestehender Event-Seiteninhalt wird vor der Migration nicht gesichert' );
events_expect( false !== strpos( $migrator, 'Gelsensystem_Events::ensure_public_page()' ) && false !== strpos( $migrator, 'Gelsensystem_Events::schedule_route_refresh()' ), 'Event-Seite wird beim Update nicht automatisch repariert' );
events_expect( false === strpos( $events, 'ajde_events' ) && false === strpos( $events, 'is_post_type_archive' ), 'öffentliche Event-Seite ist weiterhin von EventON abhängig' );
events_expect( false !== strpos( $events, 'is_public_events_request' ) && false !== strpos( $events, "home_url( '/' . self::PAGE_SLUG . '/' )" ), 'pfadbasierter Event-Fallback fehlt' );
events_expect( false !== strpos( $events, 'unabhängig von the_content()' ) && false === strpos( $events, "is_page( \$page_id ) ) {\n\t\t\treturn;" ), 'Enfold kann die direkte Event-Ausgabe weiterhin mit alten Layout-Daten übersteuern' );
events_expect( false !== strpos( $events, 'maybe_refresh_public_routes' ) && false !== strpos( $events, 'flush_rewrite_rules( false )' ), 'Permalink-Reparatur nach der EventON-Migration fehlt' );
events_expect( false !== strpos( $events, "current_user_can( 'gdg_manage' )" ), 'Berechtigungsprüfung fehlt' );
events_expect( false !== strpos( $events, "check_admin_referer( 'gse_event_action', 'gse_nonce' )" ), 'Nonce-Prüfung fehlt' );
events_expect( false !== strpos( $events, "wp_trash_post" ), 'sicheres Löschen in den Papierkorb fehlt' );
events_expect( false !== strpos( $events, "self::META_ACTIVE" ), 'öffentliche Sichtbarkeitssteuerung fehlt' );
events_expect( false !== strpos( $events, "self::META_IMAGE_ID" ), 'Eventfoto wird nicht dauerhaft gespeichert' );
events_expect( false !== strpos( $entry, "array( \$this, 'enqueue_assets' ), 999" ), 'Event-Assets werden nicht am Ende der Frontend-Ladephase gesichert' );
events_expect( false !== strpos( $entry, "'eventMediaNonce'" ), 'Nonce für den Event-Mediathek-Dialog fehlt' );
events_expect( false !== strpos( $events, 'name="event_image_ids"' ) && false !== strpos( $events, 'data-gse-media-open' ), 'Mehrfachauswahl aus der Mediathek fehlt' );
events_expect( false !== strpos( $events, 'sanitize_selected_image_ids' ) && false !== strpos( $events, 'wp_attachment_is_image' ), 'Mediathek-Auswahl wird serverseitig nicht validiert' );
events_expect( false !== strpos( $events, "wp_ajax_gse_media_library" ) && false !== strpos( $events, "wp_ajax_gse_media_upload" ), 'WordPress-Mediathek-AJAX-Endpunkte fehlen' );
events_expect( false !== strpos( $events, "current_user_can( 'upload_files' )" ) && false !== strpos( $events, "check_ajax_referer( 'gse_event_media'" ), 'Mediathek-Endpunkte sind nicht ausreichend geschützt' );
events_expect( false !== strpos( $events, 'media_handle_upload' ) && false !== strpos( $events, "'post_type'      => 'attachment'" ), 'echte WordPress-Anhänge werden nicht geladen oder erstellt' );
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
events_expect( false !== strpos( $events, 'data-default-date=' ) && false !== strpos( $public_js, 'let dateFilterActive = false' ), 'Kalenderfilter zeigt das aktuelle Datum nicht ohne anfängliche Filterung an' );
events_expect( false !== strpos( $events, 'gse-event-card__image' ), 'Eventfoto fehlt in der öffentlichen Ausgabe' );
events_expect( false !== strpos( $events, "wp_enqueue_style( 'gelsensystem-public-events'" ), 'öffentliche Stile werden nicht bedarfsgerecht geladen' );
events_expect( false !== strpos( $events, "enqueue_public_route_assets' ), 19" ), 'Event-Assets werden für die eigenständige Seite nicht vor dem Theme geladen' );
events_expect( false !== strpos( $wp_admin, "'gelsendiele-events'" ), 'WordPress-Untermenü für Events fehlt' );
events_expect( false !== strpos( $admin_css, '.gelsensystem-events-editor-grid' ), 'responsives Verwaltungs-Layout fehlt' );
events_expect( false !== strpos( $admin_css, '.gelsensystem-events-save-progress' ), 'sichtbarer Speicherfortschritt fehlt' );
events_expect( false !== strpos( $admin_js, "form.dataset.submitting === '1'" ), 'clientseitiger Mehrfachklickschutz fehlt' );
events_expect( false !== strpos( $admin_js, 'gse_media_library' ) && false !== strpos( $admin_js, 'gse_media_upload' ) && false !== strpos( $admin_js, 'Eventfotos auswählen' ), 'Mediathek-Interaktion fehlt' );
events_expect( false !== strpos( $admin_css, '.gse-media-dialog__grid' ) && false !== strpos( $admin_css, 'html[data-gd-theme="dark"] .gse-media-dialog__panel' ), 'responsiver Mediathek-Dialog oder Dark Mode fehlt' );
events_expect( false !== strpos( $admin_js, 'data-gse-popup-start' ) && false !== strpos( $admin_js, 'previousDay' ), 'Popup-Datumsautomatik fehlt' );
events_expect( false !== strpos( $events, "current_datetime()->format( 'Y-m-d' )" ), 'Startdatum wird bei neuen Events nicht vorbelegt' );
events_expect( false !== strpos( $events, '$end_date_value = $start_date_value' ) && false !== strpos( $admin_js, 'syncEventDates' ), 'Enddatum folgt dem Startdatum nicht automatisch' );
events_expect( false !== strpos( $events, 'placeholder="www.*"' ) && false === strpos( $events, 'Domain genügt' ), 'vereinfachter Webseitenhinweis fehlt' );
events_expect( false !== strpos( $events, 'data-gse-page-picker-toggle' ) && false !== strpos( $events, 'get_linkable_pages' ), 'WordPress-Seitenauswahl fehlt' );
events_expect( false !== strpos( $events, 'data-gse-all-day' ) && false !== strpos( $admin_js, 'applyAllDayState' ), 'Ganztägig-Schalter deaktiviert die Zeitfelder nicht' );
events_expect( false !== strpos( $events, 'gelsensystem-events-overview' ) && false !== strpos( $events, 'Startseiten-Popup' ) && false !== strpos( $events, 'Weitere Informationen' ), 'vollständige Event-Kurzübersicht fehlt' );
events_expect( strpos( $events, 'gelsensystem-events-color-field' ) > strpos( $events, 'gelsensystem-events-checks' ), 'Eventfarbe steht nicht am Ende des Formulars' );
events_expect( false !== strpos( $events, 'usort(' ) && false !== strpos( $events, '$by_start = strcmp' ), 'Events werden nach einer nachträglichen Datumsänderung nicht erneut chronologisch sortiert' );
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
