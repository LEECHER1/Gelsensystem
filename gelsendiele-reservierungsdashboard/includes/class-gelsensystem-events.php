<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schlanke Eventverwaltung und öffentliche Eventliste des Gelsensystems.
 *
 * Die Daten liegen als eigener, nicht öffentlicher Beitragstyp in WordPress.
 * Dadurch bleiben sie unabhängig von Theme und EventON und können später
 * problemlos um Bilder, Kategorien oder eine App-API erweitert werden.
 */
final class Gelsensystem_Events {
	const POST_TYPE        = 'gelsensystem_event';
	const SHORTCODE        = 'gelsensystem_events';
	const ROUTE_QUERY_VAR  = 'gelsensystem_events_page';
	const ROUTE_VERSION    = '1';
	const META_START       = '_gse_start';
	const META_END         = '_gse_end';
	const META_LOCATION    = '_gse_location';
	const META_LINK        = '_gse_link';
	const META_ALL_DAY     = '_gse_all_day';
	const META_ACTIVE      = '_gse_active';
	const META_IMAGE_ID    = '_gse_image_id';
	const META_IMAGE_IDS   = '_gse_image_ids';
	const META_DETAILS     = '_gse_details';
	const META_POPUP       = '_gse_popup';
	const META_COLOR       = '_gse_color';
	const META_SUBMISSION  = '_gse_submission_token';

	private static $popup_event_loaded = false;
	private static $popup_event        = null;

	public static function bootstrap() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'render_public_route' ), 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_homepage_popup_assets' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'render_homepage_popup' ), 20 );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => 'Gelsensystem Events',
					'singular_name' => 'Event',
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor' ),
			)
		);

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_public_events' ) );
		add_rewrite_rule( '^events/?$', 'index.php?' . self::ROUTE_QUERY_VAR . '=1', 'top' );

		if ( self::ROUTE_VERSION !== (string) get_option( 'gse_route_version', '' ) ) {
			flush_rewrite_rules( false );
			update_option( 'gse_route_version', self::ROUTE_VERSION, false );
		}
	}

	public static function register_query_var( $query_vars ) {
		$query_vars[] = self::ROUTE_QUERY_VAR;
		return $query_vars;
	}

	public static function handle_actions() {
		if ( empty( $_POST['gse_action'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'gdg_manage' ) ) {
			wp_die(
				esc_html__( 'Keine Berechtigung für die Eventverwaltung.', 'gelsendiele-dashboard' ),
				esc_html__( 'Kein Zugriff', 'gelsendiele-dashboard' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'gse_event_action', 'gse_nonce' );
		$action = sanitize_key( wp_unslash( $_POST['gse_action'] ) );

		if ( 'delete_event' === $action ) {
			$event_id = absint( $_POST['event_id'] ?? 0 );
			if ( $event_id && self::POST_TYPE === get_post_type( $event_id ) ) {
				wp_trash_post( $event_id );
			}
			self::redirect( 'deleted' );
		}

		if ( 'save_event' !== $action ) {
			return;
		}

		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
		$details     = wp_kses_post( wp_unslash( $_POST['details'] ?? '' ) );
		$start_date = self::sanitize_date( $_POST['start_date'] ?? '' );
		$end_date   = self::sanitize_date( $_POST['end_date'] ?? '' );
		$all_day    = empty( $_POST['all_day'] ) ? 0 : 1;
		$start_time = $all_day ? '00:00' : self::sanitize_time( $_POST['start_time'] ?? '' );
		$end_time   = $all_day ? '23:59' : self::sanitize_time( $_POST['end_time'] ?? '' );

		if ( ! $end_date ) {
			$end_date = $start_date;
		}
		if ( ! $end_time ) {
			$end_time = $start_time;
		}

		$start = self::make_datetime( $start_date, $start_time );
		$end   = self::make_datetime( $end_date, $end_time );
		if ( '' === $title || ! $start || ! $end || $end < $start ) {
			self::redirect( 'invalid' );
		}

		$event_id = absint( $_POST['event_id'] ?? 0 );
		$submission_token = sanitize_text_field( wp_unslash( $_POST['submission_token'] ?? '' ) );
		if ( ! $event_id && $submission_token && self::submission_exists( $submission_token ) ) {
			self::redirect( 'duplicate' );
		}
		$post_data = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $description,
		);
		if ( $event_id && self::POST_TYPE === get_post_type( $event_id ) ) {
			$post_data['ID'] = $event_id;
			$result = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			self::redirect( 'error' );
		}

		$event_id = (int) $result;
		update_post_meta( $event_id, self::META_START, $start );
		update_post_meta( $event_id, self::META_END, $end );
		update_post_meta( $event_id, self::META_LOCATION, sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ) );
		update_post_meta( $event_id, self::META_LINK, esc_url_raw( wp_unslash( $_POST['link'] ?? '' ) ) );
		update_post_meta( $event_id, self::META_ALL_DAY, $all_day );
		update_post_meta( $event_id, self::META_ACTIVE, empty( $_POST['active'] ) ? 0 : 1 );
		update_post_meta( $event_id, self::META_DETAILS, $details );
		update_post_meta( $event_id, self::META_POPUP, empty( $_POST['popup'] ) ? 0 : 1 );
		update_post_meta( $event_id, self::META_COLOR, self::sanitize_color( $_POST['color'] ?? '' ) );
		if ( $submission_token ) {
			update_post_meta( $event_id, self::META_SUBMISSION, $submission_token );
		}

		$image_ids = self::get_image_ids( $event_id );
		$remove_ids = array_map( 'absint', (array) ( $_POST['remove_images'] ?? array() ) );
		$image_ids = array_values( array_diff( $image_ids, $remove_ids ) );
		$upload = self::handle_image_uploads( $event_id );
		$image_ids = array_slice( array_values( array_unique( array_merge( $image_ids, $upload['ids'] ) ) ), 0, 12 );
		update_post_meta( $event_id, self::META_IMAGE_IDS, $image_ids );
		if ( $image_ids ) {
			update_post_meta( $event_id, self::META_IMAGE_ID, $image_ids[0] );
		} else {
			delete_post_meta( $event_id, self::META_IMAGE_ID );
		}
		if ( $upload['error'] ) {
			self::redirect( 'image_error' );
		}
		self::redirect( 'saved' );
	}

	private static function submission_exists( $token ) {
		return (bool) get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_SUBMISSION,
				'meta_value'     => $token,
				'no_found_rows'  => true,
			)
		);
	}

	private static function sanitize_color( $value ) {
		$color = sanitize_hex_color( wp_unslash( $value ) );
		return $color ?: '#149447';
	}

	private static function get_image_ids( $event_id ) {
		$ids = get_post_meta( $event_id, self::META_IMAGE_IDS, true );
		$ids = is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
		$legacy_id = absint( get_post_meta( $event_id, self::META_IMAGE_ID, true ) );
		if ( $legacy_id && ! in_array( $legacy_id, $ids, true ) ) {
			array_unshift( $ids, $legacy_id );
		}
		return array_slice( array_values( array_unique( $ids ) ), 0, 12 );
	}

	private static function handle_image_uploads( $event_id ) {
		$result = array( 'ids' => array(), 'error' => false );
		if ( empty( $_FILES['event_images']['name'] ) || ! is_array( $_FILES['event_images']['name'] ) ) {
			return $result;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$files = $_FILES['event_images'];
		foreach ( array_slice( array_keys( $files['name'] ), 0, 12 ) as $index ) {
			if ( empty( $files['name'][ $index ] ) ) {
				continue;
			}
			$file_check = wp_check_filetype_and_ext(
				$files['tmp_name'][ $index ] ?? '',
				$files['name'][ $index ],
				array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
				)
			);
			if ( empty( $file_check['type'] ) ) {
				$result['error'] = true;
				continue;
			}
			$_FILES['gse_event_image'] = array(
				'name'     => $files['name'][ $index ],
				'type'     => $files['type'][ $index ] ?? '',
				'tmp_name' => $files['tmp_name'][ $index ] ?? '',
				'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $files['size'][ $index ] ?? 0,
			);
			$image_id = media_handle_upload( 'gse_event_image', $event_id );
			if ( is_wp_error( $image_id ) ) {
				$result['error'] = true;
			} else {
				$result['ids'][] = (int) $image_id;
			}
		}
		unset( $_FILES['gse_event_image'] );
		return $result;
	}

	private static function sanitize_date( $value ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	private static function sanitize_time( $value ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	private static function make_datetime( $date, $time ) {
		if ( ! $date || ! $time ) {
			return '';
		}
		$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		$errors   = DateTimeImmutable::getLastErrors();
		if ( ! $datetime || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
			return '';
		}
		return $datetime->format( 'Y-m-d H:i:s' );
	}

	private static function redirect( $notice ) {
		$page_id = (int) get_option( 'gd_reservierungsdashboard_page_id', 0 );
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/gelsensystem/' );
		wp_safe_redirect( add_query_arg( array( 'gd-section' => 'events', 'gse_notice' => sanitize_key( $notice ) ), $url ) );
		exit;
	}

	private static function get_event( $event_id ) {
		$post = get_post( $event_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		return self::event_data( $post );
	}

	private static function event_data( $post ) {
		$image_ids = self::get_image_ids( $post->ID );
		$image_id  = $image_ids ? $image_ids[0] : 0;
		return array(
			'id'          => (int) $post->ID,
			'title'       => (string) $post->post_title,
			'description' => (string) $post->post_content,
			'start'       => (string) get_post_meta( $post->ID, self::META_START, true ),
			'end'         => (string) get_post_meta( $post->ID, self::META_END, true ),
			'location'    => (string) get_post_meta( $post->ID, self::META_LOCATION, true ),
			'link'        => (string) get_post_meta( $post->ID, self::META_LINK, true ),
			'all_day'     => (bool) get_post_meta( $post->ID, self::META_ALL_DAY, true ),
			'active'      => (bool) get_post_meta( $post->ID, self::META_ACTIVE, true ),
			'details'     => (string) get_post_meta( $post->ID, self::META_DETAILS, true ),
			'popup'       => (bool) get_post_meta( $post->ID, self::META_POPUP, true ),
			'color'       => self::sanitize_color( get_post_meta( $post->ID, self::META_COLOR, true ) ),
			'image_ids'   => $image_ids,
			'image_id'    => $image_id,
			'image_url'   => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'large' ) : '',
			'submission_token' => (string) get_post_meta( $post->ID, self::META_SUBMISSION, true ),
		);
	}

	private static function get_events( $public_only = false, $limit = 100, $show_past = true ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 250, (int) $limit ) ),
			'meta_key'       => self::META_START,
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		$meta_query = array();
		if ( $public_only ) {
			$meta_query[] = array( 'key' => self::META_ACTIVE, 'value' => '1' );
		}
		if ( ! $show_past ) {
			$now = current_datetime()->format( 'Y-m-d H:i:s' );
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => self::META_END, 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME' ),
				array( 'key' => self::META_START, 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME' ),
			);
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query;
		}

		$posts = get_posts( $args );
		return array_map( array( __CLASS__, 'event_data' ), $posts );
	}

	public static function render_app( $dashboard_url ) {
		if ( ! current_user_can( 'gdg_manage' ) ) {
			echo '<div class="gd-notice gd-notice-error">Kein Zugriff auf die Eventverwaltung.</div>';
			return;
		}

		$edit_id = absint( $_GET['edit_event'] ?? 0 );
		$edit    = $edit_id ? self::get_event( $edit_id ) : null;
		$events  = self::get_events( false, 100, true );
		$now     = current_datetime()->format( 'Y-m-d H:i:s' );
		$active  = count( array_filter( $events, static function ( $event ) { return $event['active']; } ) );
		$future  = count( array_filter( $events, static function ( $event ) use ( $now ) { return $event['end'] >= $now; } ) );
		$app_url = add_query_arg( 'gd-section', 'events', $dashboard_url );
		$notice  = sanitize_key( wp_unslash( $_GET['gse_notice'] ?? '' ) );
		$submission_token = $edit && ! empty( $edit['submission_token'] ) ? $edit['submission_token'] : wp_generate_uuid4();
		?>
		<div class="gelsensystem-events-manager">
			<header class="gelsensystem-events-heading">
				<div><span>Gelsensystem</span><h1>Events</h1><p>Veranstaltungen zentral eintragen und automatisch auf der Webseite anzeigen.</p></div>
				<a class="button" href="<?php echo esc_url( home_url( '/events/' ) ); ?>" target="_blank" rel="noopener">Webseite ansehen</a>
			</header>
			<?php if ( $notice ) : ?>
				<div class="notice <?php echo in_array( $notice, array( 'invalid', 'image_error', 'error' ), true ) ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( self::notice_text( $notice ) ); ?></p></div>
			<?php endif; ?>
			<div class="gelsensystem-events-summary">
				<div><strong><?php echo esc_html( (string) $future ); ?></strong><span>Kommend</span></div>
				<div><strong><?php echo esc_html( (string) $active ); ?></strong><span>Aktiv</span></div>
				<div><strong><?php echo esc_html( (string) count( $events ) ); ?></strong><span>Gesamt</span></div>
			</div>
			<div class="gelsensystem-events-editor-grid">
				<form method="post" enctype="multipart/form-data" class="gelsensystem-events-form">
					<header><div><span>Event</span><h2><?php echo $edit ? 'Event bearbeiten' : 'Neues Event'; ?></h2></div><?php if ( $edit ) : ?><a href="<?php echo esc_url( $app_url ); ?>">Abbrechen</a><?php endif; ?></header>
					<?php wp_nonce_field( 'gse_event_action', 'gse_nonce' ); ?>
					<input type="hidden" name="gse_action" value="save_event">
					<input type="hidden" name="event_id" value="<?php echo esc_attr( $edit['id'] ?? 0 ); ?>">
					<input type="hidden" name="submission_token" value="<?php echo esc_attr( $submission_token ); ?>">
					<label class="gelsensystem-events-wide"><span>Titel *</span><input name="title" required value="<?php echo esc_attr( $edit['title'] ?? '' ); ?>" placeholder="z. B. Sommerfest"></label>
					<div class="gelsensystem-events-field-grid">
						<label><span>Startdatum *</span><input type="date" name="start_date" required value="<?php echo esc_attr( self::date_part( $edit['start'] ?? '' ) ); ?>"></label>
						<label><span>Startzeit</span><input type="time" name="start_time" value="<?php echo esc_attr( self::time_part( $edit['start'] ?? '', '18:00' ) ); ?>"></label>
						<label><span>Enddatum</span><input type="date" name="end_date" value="<?php echo esc_attr( self::date_part( $edit['end'] ?? '' ) ); ?>"></label>
						<label><span>Endzeit</span><input type="time" name="end_time" value="<?php echo esc_attr( self::time_part( $edit['end'] ?? '', '22:00' ) ); ?>"></label>
						<label class="gelsensystem-events-wide"><span>Ort</span><input name="location" value="<?php echo esc_attr( $edit['location'] ?? 'Die Gelsendiele' ); ?>" placeholder="Die Gelsendiele"></label>
						<label class="gelsensystem-events-wide"><span>Kurzbeschreibung</span><textarea name="description" rows="4" placeholder="Das Wichtigste auf einen Blick"><?php echo esc_textarea( $edit['description'] ?? '' ); ?></textarea></label>
						<label class="gelsensystem-events-wide"><span>Weitere Informationen (aufklappbar)</span><textarea name="details" rows="6" placeholder="Programm, Eintritt, Reservierung, Bandinfos …"><?php echo esc_textarea( $edit['details'] ?? '' ); ?></textarea><small>Dieser Text erscheint erst nach „Mehr anzeigen“.</small></label>
						<label class="gelsensystem-events-wide gelsensystem-events-image-field"><span>Eventfotos</span><input type="file" name="event_images[]" accept="image/jpeg,image/png,image/webp" multiple><small>Bis zu 12 Bilder als JPG, PNG oder WebP. Das erste Bild ist das Titelbild.</small></label>
						<?php if ( ! empty( $edit['image_ids'] ) ) : ?>
							<div class="gelsensystem-events-image-preview gelsensystem-events-wide">
								<?php foreach ( $edit['image_ids'] as $index => $image_id ) : ?>
									<label class="gelsensystem-events-image-preview__item"><?php echo wp_get_attachment_image( $image_id, 'medium', false, array( 'alt' => $edit['title'] ) ); ?><span><input type="checkbox" name="remove_images[]" value="<?php echo esc_attr( $image_id ); ?>"> Bild entfernen<?php echo 0 === $index ? ' (Titelbild)' : ''; ?></span></label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<label class="gelsensystem-events-wide"><span>Optionaler Link</span><input type="url" name="link" value="<?php echo esc_attr( $edit['link'] ?? '' ); ?>" placeholder="https://…"></label>
						<label><span>Eventfarbe</span><input type="color" name="color" value="<?php echo esc_attr( $edit['color'] ?? '#149447' ); ?>"><small>Farbe für Datum, Akzente und Button.</small></label>
					</div>
					<div class="gelsensystem-events-checks">
						<label><input type="checkbox" name="all_day" value="1" <?php checked( ! empty( $edit['all_day'] ) ); ?>> Ganztägig</label>
						<label><input type="checkbox" name="active" value="1" <?php checked( ! $edit || ! empty( $edit['active'] ) ); ?>> Auf Webseite anzeigen</label>
						<label><input type="checkbox" name="popup" value="1" <?php checked( ! empty( $edit['popup'] ) ); ?>> Als Popup auf der Startseite anzeigen</label>
					</div>
					<button type="submit" class="button button-primary" data-gse-submit><?php echo $edit ? 'Event speichern' : 'Event anlegen'; ?></button>
					<div class="gelsensystem-events-save-progress" data-gse-progress hidden aria-live="polite"><span>Event wird gespeichert und Bilder werden verarbeitet …</span><div><i></i></div></div>
				</form>
				<section class="gelsensystem-events-inventory">
					<header><div><span>Übersicht</span><h2>Alle Events</h2></div><p>Vergangene Events bleiben erhalten und sind auf der Webseite über den Filter erreichbar.</p></header>
					<div class="gelsensystem-events-list">
						<?php if ( ! $events ) : ?><div class="gelsensystem-events-empty"><strong>Noch keine Events</strong><span>Lege links das erste Event an.</span></div><?php endif; ?>
						<?php foreach ( $events as $event ) : ?>
							<article class="<?php echo $event['active'] ? '' : 'is-inactive'; ?>">
								<div class="gelsensystem-events-date"><strong><?php echo esc_html( self::format_date( $event['start'], 'd' ) ); ?></strong><span><?php echo esc_html( self::format_date( $event['start'], 'M' ) ); ?></span></div>
								<div class="gelsensystem-events-main"><span><?php echo esc_html( self::format_event_time( $event ) ); ?></span><strong><?php echo esc_html( $event['title'] ); ?></strong><small><?php echo esc_html( $event['location'] ?: 'Kein Ort angegeben' ); ?></small></div>
								<div class="gelsensystem-events-actions"><span class="<?php echo $event['active'] ? 'is-on' : 'is-off'; ?>"><?php echo $event['active'] ? 'Aktiv' : 'Ausgeblendet'; ?></span><a class="button" href="<?php echo esc_url( add_query_arg( array( 'gd-section' => 'events', 'edit_event' => $event['id'] ), $dashboard_url ) ); ?>">Bearbeiten</a><form method="post" onsubmit="return confirm('Event wirklich löschen?');"><?php wp_nonce_field( 'gse_event_action', 'gse_nonce' ); ?><input type="hidden" name="gse_action" value="delete_event"><input type="hidden" name="event_id" value="<?php echo esc_attr( $event['id'] ); ?>"><button type="submit" class="button button-link-delete">Löschen</button></form></div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	private static function notice_text( $notice ) {
		$messages = array(
			'saved'   => 'Event wurde gespeichert.',
			'duplicate' => 'Das Event wurde bereits gespeichert. Ein doppelter Eintrag wurde verhindert.',
			'deleted' => 'Event wurde gelöscht.',
			'invalid' => 'Bitte Titel, Datum und Uhrzeit vollständig und korrekt ausfüllen.',
			'image_error' => 'Das Event wurde gespeichert, aber das Foto konnte nicht hochgeladen werden. Bitte JPG, PNG oder WebP verwenden.',
			'error'   => 'Das Event konnte nicht gespeichert werden.',
		);
		return $messages[ $notice ] ?? 'Änderung abgeschlossen.';
	}

	private static function date_part( $value ) {
		return $value ? substr( $value, 0, 10 ) : '';
	}

	private static function time_part( $value, $fallback = '' ) {
		return $value ? substr( $value, 11, 5 ) : $fallback;
	}

	private static function format_date( $value, $format ) {
		try {
			$date = new DateTimeImmutable( $value, wp_timezone() );
			return wp_date( $format, $date->getTimestamp(), wp_timezone() );
		} catch ( Exception $exception ) {
			return '';
		}
	}

	private static function format_event_time( $event ) {
		$date = self::format_date( $event['start'], 'D, d. F Y' );
		if ( $event['all_day'] ) {
			return $date . ' · ganztägig';
		}
		$start_time = self::format_date( $event['start'], 'H:i' );
		$end_time   = self::format_date( $event['end'], 'H:i' );
		return $date . ' · ' . $start_time . ( $end_time ? '–' . $end_time . ' Uhr' : ' Uhr' );
	}

	public static function render_public_events( $attributes = array() ) {
		$attributes = shortcode_atts( array( 'limit' => 100, 'show_past' => 'filter' ), $attributes, self::SHORTCODE );
		$mode       = strtolower( (string) $attributes['show_past'] );
		$filterable = 'filter' === $mode;
		$show_past  = $filterable || 'yes' === $mode;
		$events     = self::get_events( true, absint( $attributes['limit'] ), $show_past );
		$now        = current_datetime()->format( 'Y-m-d H:i:s' );
		$upcoming   = count( array_filter( $events, static function ( $event ) use ( $now ) { return $event['end'] >= $now; } ) );
		$past       = count( $events ) - $upcoming;
		wp_enqueue_style( 'gelsensystem-public-events', GELSENDIELE_URL . 'assets/public-events.css', array(), GELSENDIELE_VERSION );
		wp_enqueue_script( 'gelsensystem-public-events', GELSENDIELE_URL . 'assets/public-events.js', array(), GELSENDIELE_VERSION, true );

		ob_start();
		?>
		<section class="gse-public-events" aria-labelledby="gse-events-title">
			<header class="gse-public-events__header"><span>Die Gelsendiele</span><h1 id="gse-events-title">Events</h1><p>Was bei uns als Nächstes los ist.</p></header>
			<?php if ( $filterable && $events ) : ?>
				<div class="gse-public-events__filters" data-gse-filters>
					<div class="gse-public-events__filter-buttons" role="group" aria-label="Events filtern">
						<button type="button" class="is-active" data-gse-status="upcoming">Kommend <span><?php echo esc_html( (string) $upcoming ); ?></span></button>
						<button type="button" data-gse-status="past">Vergangen <span><?php echo esc_html( (string) $past ); ?></span></button>
						<button type="button" data-gse-status="all">Alle <span><?php echo esc_html( (string) count( $events ) ); ?></span></button>
					</div>
					<label><span>Datum auswählen</span><input type="date" data-gse-date><button type="button" data-gse-date-clear aria-label="Datumsfilter zurücksetzen">×</button></label>
				</div>
			<?php endif; ?>
			<?php if ( ! $events ) : ?>
				<div class="gse-public-events__empty"><strong>Aktuell sind keine kommenden Events eingetragen.</strong><span>Schau bald wieder vorbei.</span></div>
			<?php else : ?>
				<div class="gse-public-events__list" data-gse-event-list>
					<?php foreach ( $events as $event ) : ?>
						<?php $event_status = $event['end'] >= $now ? 'upcoming' : 'past'; ?>
						<article class="gse-event-card<?php echo $event['image_id'] ? ' has-image' : ''; ?>" data-gse-event data-status="<?php echo esc_attr( $event_status ); ?>" data-date="<?php echo esc_attr( self::date_part( $event['start'] ) ); ?>" style="--gse-accent:<?php echo esc_attr( $event['color'] ); ?>" <?php echo $filterable && 'past' === $event_status ? 'hidden' : ''; ?> itemscope itemtype="https://schema.org/Event">
							<time class="gse-event-card__date" datetime="<?php echo esc_attr( str_replace( ' ', 'T', $event['start'] ) ); ?>" itemprop="startDate"><strong><?php echo esc_html( self::format_date( $event['start'], 'd' ) ); ?></strong><span><?php echo esc_html( self::format_date( $event['start'], 'M' ) ); ?></span></time>
							<?php if ( $event['image_id'] ) : ?><figure class="gse-event-card__image"><?php echo wp_get_attachment_image( $event['image_id'], 'large', false, array( 'alt' => $event['title'], 'itemprop' => 'image', 'loading' => 'lazy' ) ); ?></figure><?php endif; ?>
							<div class="gse-event-card__content">
								<span class="gse-event-card__time"><?php echo esc_html( self::format_event_time( $event ) ); ?></span>
								<h2 itemprop="name"><?php echo esc_html( $event['title'] ); ?></h2>
								<?php if ( $event['location'] ) : ?><p class="gse-event-card__location" itemprop="location">⌖ <?php echo esc_html( $event['location'] ); ?></p><?php endif; ?>
								<?php if ( $event['description'] ) : ?><div class="gse-event-card__description" itemprop="description"><?php echo wp_kses_post( wpautop( $event['description'] ) ); ?></div><?php endif; ?>
								<?php if ( $event['details'] || count( $event['image_ids'] ) > 1 ) : ?>
									<details class="gse-event-card__details">
										<summary>Mehr anzeigen <span aria-hidden="true">⌄</span></summary>
										<?php if ( $event['details'] ) : ?><div class="gse-event-card__details-text"><?php echo wp_kses_post( wpautop( $event['details'] ) ); ?></div><?php endif; ?>
										<?php if ( count( $event['image_ids'] ) > 1 ) : ?><div class="gse-event-card__gallery"><?php foreach ( array_slice( $event['image_ids'], 1 ) as $image_id ) { echo wp_get_attachment_image( $image_id, 'large', false, array( 'alt' => $event['title'], 'loading' => 'lazy' ) ); } ?></div><?php endif; ?>
									</details>
								<?php endif; ?>
								<?php if ( $event['link'] ) : ?><a class="gse-event-card__link" href="<?php echo esc_url( $event['link'] ); ?>">Mehr erfahren <span aria-hidden="true">→</span></a><?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
				<div class="gse-public-events__empty" data-gse-filter-empty hidden><strong>Für diesen Filter wurden keine Events gefunden.</strong><span>Wähle einen anderen Zeitraum oder lösche den Datumsfilter.</span></div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function get_homepage_popup_event() {
		if ( self::$popup_event_loaded ) {
			return self::$popup_event;
		}
		self::$popup_event_loaded = true;
		if ( ! is_front_page() || is_admin() ) {
			return null;
		}
		foreach ( self::get_events( true, 100, false ) as $event ) {
			if ( $event['popup'] ) {
				self::$popup_event = $event;
				break;
			}
		}
		return self::$popup_event;
	}

	public static function enqueue_homepage_popup_assets() {
		if ( ! self::get_homepage_popup_event() ) {
			return;
		}
		wp_enqueue_style( 'gelsensystem-public-events', GELSENDIELE_URL . 'assets/public-events.css', array(), GELSENDIELE_VERSION );
		wp_enqueue_script( 'gelsensystem-public-events', GELSENDIELE_URL . 'assets/public-events.js', array(), GELSENDIELE_VERSION, true );
	}

	public static function render_homepage_popup() {
		$event = self::get_homepage_popup_event();
		if ( ! $event ) {
			return;
		}
		?>
		<div class="gse-event-popup" data-gse-popup data-event-id="<?php echo esc_attr( $event['id'] ); ?>" style="--gse-accent:<?php echo esc_attr( $event['color'] ); ?>" hidden>
			<button type="button" class="gse-event-popup__backdrop" data-gse-popup-close aria-label="Popup schließen"></button>
			<section class="gse-event-popup__dialog<?php echo $event['image_id'] ? ' has-image' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="gse-popup-title-<?php echo esc_attr( $event['id'] ); ?>">
				<button type="button" class="gse-event-popup__close" data-gse-popup-close aria-label="Popup schließen">×</button>
				<?php if ( $event['image_id'] ) : ?><figure><?php echo wp_get_attachment_image( $event['image_id'], 'large', false, array( 'alt' => $event['title'] ) ); ?></figure><?php endif; ?>
				<div class="gse-event-popup__content"><span>Nächstes Event · <?php echo esc_html( self::format_event_time( $event ) ); ?></span><h2 id="gse-popup-title-<?php echo esc_attr( $event['id'] ); ?>"><?php echo esc_html( $event['title'] ); ?></h2><?php if ( $event['description'] ) : ?><div><?php echo wp_kses_post( wpautop( $event['description'] ) ); ?></div><?php endif; ?><?php if ( $event['link'] ) : ?><a href="<?php echo esc_url( $event['link'] ); ?>">Mehr erfahren</a><?php else : ?><a href="<?php echo esc_url( home_url( '/events/' ) ); ?>">Alle Informationen</a><?php endif; ?></div>
			</section>
		</div>
		<?php
	}

	public static function render_public_route() {
		if ( is_admin() ) {
			return;
		}
		$is_route = (bool) get_query_var( self::ROUTE_QUERY_VAR );
		$is_eventon_archive = function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'ajde_events' );
		if ( ! $is_route && ! $is_eventon_archive ) {
			return;
		}

		status_header( 200 );
		nocache_headers();
		get_header();
		echo '<main id="main" class="gelsensystem-events-page"><div class="container">' . do_shortcode( '[' . self::SHORTCODE . ']' ) . '</div></main>';
		get_footer();
		exit;
	}
}
