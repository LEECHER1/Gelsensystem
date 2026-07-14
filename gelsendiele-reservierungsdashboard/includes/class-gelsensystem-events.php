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
	const PAGE_OPTION      = 'gse_events_page_id';
	const PAGE_SLUG        = 'events';
	const PREVIOUS_CONTENT = '_gse_previous_event_page_content';
	const ROUTE_REFRESH    = 'gse_events_refresh_routes';
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
	const META_POPUP_START = '_gse_popup_start';
	const META_POPUP_END   = '_gse_popup_end';
	const META_COLOR       = '_gse_color';
	const META_SUBMISSION  = '_gse_submission_token';

	private static $popup_event_loaded = false;
	private static $popup_event        = null;

	public static function bootstrap() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_refresh_public_routes' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'render_public_route' ), 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_public_route_assets' ), 19 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_homepage_popup_assets' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'render_homepage_popup' ), 5 );
		add_action( 'wp_ajax_gse_media_library', array( __CLASS__, 'ajax_media_library' ) );
		add_action( 'wp_ajax_gse_media_upload', array( __CLASS__, 'ajax_media_upload' ) );
	}

	private static function media_payload( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		return array(
			'id'        => $attachment_id,
			'url'       => (string) wp_get_attachment_url( $attachment_id ),
			'thumbnail' => (string) wp_get_attachment_image_url( $attachment_id, 'medium' ),
			'alt'       => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'title'     => (string) get_the_title( $attachment_id ),
		);
	}

	public static function ajax_media_library() {
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'gdg_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
		}
		check_ajax_referer( 'gse_event_media', 'nonce' );
		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$images = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 120,
				'orderby'        => 'date',
				'order'          => 'DESC',
				's'              => $search,
				'fields'         => 'ids',
			)
		);
		wp_send_json_success( array_map( array( __CLASS__, 'media_payload' ), $images ) );
	}

	public static function ajax_media_upload() {
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'gdg_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
		}
		check_ajax_referer( 'gse_event_media', 'nonce' );
		if ( empty( $_FILES['event_images'] ) ) {
			wp_send_json_error( array( 'message' => 'Bitte Bilder auswählen.' ), 400 );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$files    = $_FILES['event_images'];
		$uploaded = array();
		$count    = min( 12, is_array( $files['name'] ?? null ) ? count( $files['name'] ) : 0 );
		for ( $index = 0; $index < $count; $index++ ) {
			$_FILES['gse_event_image'] = array(
				'name'     => $files['name'][ $index ],
				'type'     => $files['type'][ $index ],
				'tmp_name' => $files['tmp_name'][ $index ],
				'error'    => $files['error'][ $index ],
				'size'     => $files['size'][ $index ],
			);
			$attachment_id = media_handle_upload( 'gse_event_image', 0 );
			if ( ! is_wp_error( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
				$uploaded[] = self::media_payload( $attachment_id );
			}
		}
		unset( $_FILES['gse_event_image'] );
		if ( empty( $uploaded ) ) {
			wp_send_json_error( array( 'message' => 'Die Bilder konnten nicht hochgeladen werden.' ), 400 );
		}
		wp_send_json_success( $uploaded );
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
	}

	/**
	 * Legt eine echte WordPress-Seite für die Events an. Vorhandene Inhalte
	 * (beispielsweise ein EventON-Shortcode) werden einmalig als Metadatum
	 * gesichert, bevor die Seite auf die Gelsensystem-Ausgabe umgestellt wird.
	 */
	public static function ensure_public_page() {
		$page_id = (int) get_option( self::PAGE_OPTION, 0 );
		$page    = $page_id ? get_post( $page_id ) : null;
		if ( ! $page || 'page' !== $page->post_type ) {
			$page    = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
			$page_id = $page ? (int) $page->ID : 0;
		}

		$shortcode = '[' . self::SHORTCODE . ']';
		if ( $page_id ) {
			$content = (string) $page->post_content;
			if ( ! has_shortcode( $content, self::SHORTCODE ) && '' !== trim( $content ) && ! metadata_exists( 'post', $page_id, self::PREVIOUS_CONTENT ) ) {
				add_post_meta( $page_id, self::PREVIOUS_CONTENT, $content, true );
			}

			$update = array( 'ID' => $page_id );
			if ( 'Events' !== $page->post_title ) {
				$update['post_title'] = 'Events';
			}
			if ( self::PAGE_SLUG !== $page->post_name ) {
				$update['post_name'] = self::PAGE_SLUG;
			}
			if ( 'publish' !== $page->post_status ) {
				$update['post_status'] = 'publish';
			}
			if ( ! has_shortcode( $content, self::SHORTCODE ) || trim( $content ) !== $shortcode ) {
				$update['post_content'] = $shortcode;
			}
			if ( count( $update ) > 1 ) {
				wp_update_post( $update );
				self::schedule_route_refresh();
			}
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'   => 'Events',
					'post_name'    => self::PAGE_SLUG,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => $shortcode,
				),
				true
			);
			if ( is_wp_error( $page_id ) ) {
				return 0;
			}
			self::schedule_route_refresh();
		}

		update_option( self::PAGE_OPTION, (int) $page_id, false );
		return (int) $page_id;
	}

	public static function schedule_route_refresh() {
		update_option( self::ROUTE_REFRESH, 1, false );
	}

	public static function maybe_refresh_public_routes() {
		if ( ! get_option( self::ROUTE_REFRESH, 0 ) ) {
			return;
		}
		flush_rewrite_rules( false );
		delete_option( self::ROUTE_REFRESH );
		delete_option( 'gse_route_version' );
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

		$popup       = empty( $_POST['popup'] ) ? 0 : 1;
		$popup_start = self::sanitize_date( $_POST['popup_start_date'] ?? '' );
		$popup_end   = self::sanitize_date( $_POST['popup_end_date'] ?? '' );
		if ( ! $popup_start ) {
			$popup_start = self::default_popup_start_date( $start );
		}
		if ( ! $popup_end ) {
			$popup_end = self::date_part( $end );
		}
		if ( $popup && ( ! $popup_start || ! $popup_end || $popup_end < $popup_start ) ) {
			self::redirect( 'invalid_popup' );
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
		update_post_meta( $event_id, self::META_LINK, self::normalize_url( $_POST['link'] ?? '' ) );
		update_post_meta( $event_id, self::META_ALL_DAY, $all_day );
		update_post_meta( $event_id, self::META_ACTIVE, empty( $_POST['active'] ) ? 0 : 1 );
		update_post_meta( $event_id, self::META_DETAILS, $details );
		update_post_meta( $event_id, self::META_POPUP, $popup );
		update_post_meta( $event_id, self::META_POPUP_START, $popup_start . ' 00:00:00' );
		update_post_meta( $event_id, self::META_POPUP_END, $popup_end . ' 23:59:59' );
		update_post_meta( $event_id, self::META_COLOR, self::sanitize_color( $_POST['color'] ?? '' ) );
		if ( $submission_token ) {
			update_post_meta( $event_id, self::META_SUBMISSION, $submission_token );
		}

		if ( isset( $_POST['event_image_ids'] ) ) {
			$image_ids = self::sanitize_selected_image_ids( $_POST['event_image_ids'] );
		} else {
			$image_ids = self::get_image_ids( $event_id );
			$remove_ids = array_map( 'absint', (array) ( $_POST['remove_images'] ?? array() ) );
			$image_ids = array_values( array_diff( $image_ids, $remove_ids ) );
		}
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

	private static function normalize_url( $value ) {
		$value = is_string( $value ) ? trim( wp_unslash( $value ) ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( 0 === strpos( $value, '//' ) ) {
			$value = 'https:' . $value;
		} elseif ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $value ) ) {
			$value = 'https://' . ltrim( $value, '/' );
		}
		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	private static function default_popup_start_date( $event_start ) {
		try {
			$date = new DateTimeImmutable( $event_start, wp_timezone() );
			return $date->modify( '-1 day' )->format( 'Y-m-d' );
		} catch ( Exception $exception ) {
			return '';
		}
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

	private static function sanitize_selected_image_ids( $value ) {
		$ids = is_array( $value ) ? $value : explode( ',', sanitize_text_field( wp_unslash( $value ) ) );
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, 12 );
		return array_values(
			array_filter(
				$ids,
				static function ( $image_id ) {
					return 'attachment' === get_post_type( $image_id ) && wp_attachment_is_image( $image_id );
				}
			)
		);
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
		$start     = (string) get_post_meta( $post->ID, self::META_START, true );
		$end       = (string) get_post_meta( $post->ID, self::META_END, true );
		$popup_start = (string) get_post_meta( $post->ID, self::META_POPUP_START, true );
		$popup_end   = (string) get_post_meta( $post->ID, self::META_POPUP_END, true );
		if ( ! $popup_start && $start ) {
			$popup_start = self::default_popup_start_date( $start ) . ' 00:00:00';
		}
		if ( ! $popup_end && $end ) {
			$popup_end = self::date_part( $end ) . ' 23:59:59';
		}
		return array(
			'id'          => (int) $post->ID,
			'title'       => (string) $post->post_title,
			'description' => (string) $post->post_content,
			'start'       => $start,
			'end'         => $end,
			'location'    => (string) get_post_meta( $post->ID, self::META_LOCATION, true ),
			'link'        => (string) get_post_meta( $post->ID, self::META_LINK, true ),
			'all_day'     => (bool) get_post_meta( $post->ID, self::META_ALL_DAY, true ),
			'active'      => (bool) get_post_meta( $post->ID, self::META_ACTIVE, true ),
			'details'     => (string) get_post_meta( $post->ID, self::META_DETAILS, true ),
			'popup'       => (bool) get_post_meta( $post->ID, self::META_POPUP, true ),
			'popup_start' => $popup_start,
			'popup_end'   => $popup_end,
			'popup_start_custom' => metadata_exists( 'post', $post->ID, self::META_POPUP_START ),
			'popup_end_custom'   => metadata_exists( 'post', $post->ID, self::META_POPUP_END ),
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

		$posts  = get_posts( $args );
		$events = array_map( array( __CLASS__, 'event_data' ), $posts );
		usort(
			$events,
			static function ( $left, $right ) {
				$by_start = strcmp( (string) $left['start'], (string) $right['start'] );
				return 0 !== $by_start ? $by_start : (int) $left['id'] <=> (int) $right['id'];
			}
		);
		return $events;
	}

	private static function get_linkable_pages() {
		$excluded_ids = array(
			(int) get_option( 'gd_reservierungsdashboard_page_id', 0 ),
			(int) get_option( 'gdg_parent_page', 0 ),
			(int) get_option( 'gdg_page_service', 0 ),
			(int) get_option( 'gdg_page_kitchen', 0 ),
			(int) get_option( 'gdg_page_bar', 0 ),
			(int) get_option( 'gdg_page_checkout', 0 ),
		);
		$excluded_ids = array_values( array_unique( array_filter( $excluded_ids ) ) );
		$pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);
		return array_values(
			array_filter(
				$pages,
				static function ( $page ) use ( $excluded_ids ) {
					return ! in_array( (int) $page->ID, $excluded_ids, true ) && '' === (string) get_post_meta( $page->ID, '_gdg_view', true );
				}
			)
		);
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
		$start_date_value = self::date_part( $edit['start'] ?? '' );
		if ( ! $start_date_value ) {
			$start_date_value = current_datetime()->format( 'Y-m-d' );
		}
		$end_date_value = self::date_part( $edit['end'] ?? '' );
		if ( ! $end_date_value ) {
			$end_date_value = $start_date_value;
		}
		$end_date_is_automatic = ! $edit || $end_date_value === $start_date_value;
		$selected_image_ids = ! empty( $edit['image_ids'] ) ? array_map( 'absint', $edit['image_ids'] ) : array();
		$linkable_pages = self::get_linkable_pages();
		?>
		<div class="gelsensystem-events-manager">
			<header class="gelsensystem-events-heading">
				<div><span>Gelsensystem</span><h1>Events</h1><p>Veranstaltungen zentral eintragen und automatisch auf der Webseite anzeigen.</p></div>
				<a class="button" href="<?php echo esc_url( home_url( '/events/' ) ); ?>" target="_blank" rel="noopener">Webseite ansehen</a>
			</header>
			<?php if ( $notice ) : ?>
				<div class="notice <?php echo in_array( $notice, array( 'invalid', 'invalid_popup', 'image_error', 'error' ), true ) ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( self::notice_text( $notice ) ); ?></p></div>
			<?php endif; ?>
			<div class="gelsensystem-events-summary">
				<div><strong><?php echo esc_html( (string) $future ); ?></strong><span>Kommend</span></div>
				<div><strong><?php echo esc_html( (string) $active ); ?></strong><span>Aktiv</span></div>
				<div><strong><?php echo esc_html( (string) count( $events ) ); ?></strong><span>Gesamt</span></div>
			</div>
			<div class="gelsensystem-events-editor-grid">
				<form method="post" class="gelsensystem-events-form">
					<header><div><span>Event</span><h2><?php echo $edit ? 'Event bearbeiten' : 'Neues Event'; ?></h2></div><?php if ( $edit ) : ?><a href="<?php echo esc_url( $app_url ); ?>">Abbrechen</a><?php endif; ?></header>
					<?php wp_nonce_field( 'gse_event_action', 'gse_nonce' ); ?>
					<input type="hidden" name="gse_action" value="save_event">
					<input type="hidden" name="event_id" value="<?php echo esc_attr( $edit['id'] ?? 0 ); ?>">
					<input type="hidden" name="submission_token" value="<?php echo esc_attr( $submission_token ); ?>">
					<label class="gelsensystem-events-wide"><span>Titel *</span><input name="title" required value="<?php echo esc_attr( $edit['title'] ?? '' ); ?>" placeholder="z. B. Sommerfest"></label>
					<div class="gelsensystem-events-field-grid">
						<label><span>Startdatum *</span><input type="date" name="start_date" data-gse-event-start required value="<?php echo esc_attr( $start_date_value ); ?>"></label>
						<label><span>Startzeit</span><input type="time" name="start_time" data-gse-event-time value="<?php echo esc_attr( self::time_part( $edit['start'] ?? '', '18:00' ) ); ?>"></label>
						<div class="gelsensystem-events-end-date-group"><label><span>Enddatum</span><input type="date" name="end_date" data-gse-event-end data-auto="<?php echo $end_date_is_automatic ? '1' : '0'; ?>" value="<?php echo esc_attr( $end_date_value ); ?>"></label><label class="gelsensystem-events-all-day"><input type="checkbox" name="all_day" value="1" data-gse-all-day <?php checked( ! empty( $edit['all_day'] ) ); ?>> <span>Ganztägig</span></label></div>
						<label><span>Endzeit</span><input type="time" name="end_time" data-gse-event-time value="<?php echo esc_attr( self::time_part( $edit['end'] ?? '', '22:00' ) ); ?>"></label>
						<label class="gelsensystem-events-wide"><span>Ort</span><input name="location" value="<?php echo esc_attr( $edit['location'] ?? 'Die Gelsendiele' ); ?>" placeholder="Die Gelsendiele"></label>
						<label class="gelsensystem-events-wide"><span>Kurzbeschreibung</span><textarea name="description" rows="4" placeholder="Das Wichtigste auf einen Blick"><?php echo esc_textarea( $edit['description'] ?? '' ); ?></textarea></label>
						<label class="gelsensystem-events-wide"><span>Weitere Informationen (aufklappbar)</span><textarea name="details" rows="6" placeholder="Programm, Eintritt, Reservierung, Bandinfos …"><?php echo esc_textarea( $edit['details'] ?? '' ); ?></textarea><small>Dieser Text erscheint erst nach „Mehr anzeigen“.</small></label>
						<div class="gelsensystem-events-wide gelsensystem-events-image-field">
							<span>Eventfotos</span>
							<input type="hidden" name="event_image_ids" data-gse-image-ids value="<?php echo esc_attr( implode( ',', $selected_image_ids ) ); ?>">
							<div class="gelsensystem-events-media-actions">
								<?php if ( current_user_can( 'upload_files' ) ) : ?><button type="button" class="button" data-gse-media-open>Mediathek öffnen</button><?php endif; ?>
								<small>Bis zu 12 Bilder aus der WordPress-Mediathek. Das erste Bild ist das Titelbild.</small>
							</div>
							<div class="gelsensystem-events-image-preview" data-gse-image-preview <?php echo $selected_image_ids ? '' : 'hidden'; ?>>
								<?php foreach ( $selected_image_ids as $index => $image_id ) : ?>
									<article data-gse-image-id="<?php echo esc_attr( $image_id ); ?>"><?php echo wp_get_attachment_image( $image_id, 'medium', false, array( 'alt' => $edit['title'] ?? '' ) ); ?><div><span><?php echo 0 === $index ? 'Titelbild' : 'Eventfoto'; ?></span><button type="button" data-gse-image-remove>Entfernen</button></div></article>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="gelsensystem-events-wide gelsensystem-events-link-field">
							<span>Optionale Webseite</span>
							<div><input type="text" inputmode="url" name="link" data-gse-link-input value="<?php echo esc_attr( $edit['link'] ?? '' ); ?>" placeholder="www.*"><button type="button" class="button" data-gse-page-picker-toggle <?php disabled( empty( $linkable_pages ) ); ?>>WordPress-Seiten</button></div>
							<?php if ( $linkable_pages ) : ?><div class="gelsensystem-events-page-picker" data-gse-page-picker hidden><strong>Veröffentlichte Seite auswählen</strong><?php foreach ( $linkable_pages as $page ) : ?><button type="button" data-gse-page-url="<?php echo esc_url( get_permalink( $page ) ); ?>"><?php echo esc_html( get_the_title( $page ) ); ?><span><?php echo esc_html( wp_make_link_relative( get_permalink( $page ) ) ); ?></span></button><?php endforeach; ?></div><?php endif; ?>
						</div>
					</div>
					<div class="gelsensystem-events-checks">
						<label><input type="checkbox" name="active" value="1" <?php checked( ! $edit || ! empty( $edit['active'] ) ); ?>> Auf Webseite anzeigen</label>
						<label><input type="checkbox" name="popup" value="1" data-gse-popup-enabled <?php checked( ! empty( $edit['popup'] ) ); ?>> Als Popup auf der Startseite anzeigen</label>
						<div class="gelsensystem-events-popup-schedule" data-gse-popup-schedule <?php echo empty( $edit['popup'] ) ? 'hidden' : ''; ?>>
							<div><strong>Popup-Zeitraum</strong><small>Standard: ein Tag vor dem Event bis zum Event-Enddatum.</small></div>
							<label><span>Popup anzeigen ab *</span><input type="date" name="popup_start_date" data-gse-popup-start data-auto="<?php echo ! $edit || empty( $edit['popup_start_custom'] ) ? '1' : '0'; ?>" value="<?php echo esc_attr( self::date_part( $edit['popup_start'] ?? '' ) ); ?>"></label>
							<label><span>Popup anzeigen bis *</span><input type="date" name="popup_end_date" data-gse-popup-end data-auto="<?php echo ! $edit || empty( $edit['popup_end_custom'] ) ? '1' : '0'; ?>" value="<?php echo esc_attr( self::date_part( $edit['popup_end'] ?? '' ) ); ?>"></label>
						</div>
					</div>
					<label class="gelsensystem-events-color-field"><span>Eventfarbe</span><input type="color" name="color" value="<?php echo esc_attr( $edit['color'] ?? '#149447' ); ?>"><small>Farbe für Datum, Akzente und Button.</small></label>
					<button type="submit" class="button button-primary" data-gse-submit><?php echo $edit ? 'Event speichern' : 'Event anlegen'; ?></button>
					<div class="gelsensystem-events-save-progress" data-gse-progress hidden aria-live="polite"><span>Event wird gespeichert und Bilder werden verarbeitet …</span><div><i></i></div></div>
				</form>
				<section class="gelsensystem-events-inventory">
					<header><div><span>Übersicht</span><h2>Alle Events</h2></div><p>Vergangene Events bleiben erhalten und sind auf der Webseite über den Filter erreichbar.</p></header>
					<div class="gelsensystem-events-list">
						<?php if ( ! $events ) : ?><div class="gelsensystem-events-empty"><strong>Noch keine Events</strong><span>Lege links das erste Event an.</span></div><?php endif; ?>
						<?php foreach ( $events as $event ) : ?>
							<?php
							$description_summary = trim( wp_strip_all_tags( $event['description'] ) );
							$details_summary = trim( wp_strip_all_tags( $event['details'] ) );
							$link_label = $event['link'] ? ( wp_parse_url( $event['link'], PHP_URL_HOST ) ?: $event['link'] ) : 'Nicht gesetzt';
							$popup_label = $event['popup'] ? self::format_date( $event['popup_start'], 'd.m.Y' ) . '–' . self::format_date( $event['popup_end'], 'd.m.Y' ) : 'Deaktiviert';
							?>
							<article class="<?php echo $event['active'] ? '' : 'is-inactive'; ?>" style="--gse-admin-accent:<?php echo esc_attr( $event['color'] ); ?>">
								<div class="gelsensystem-events-date"><strong><?php echo esc_html( self::format_date( $event['start'], 'd' ) ); ?></strong><span><?php echo esc_html( self::format_date( $event['start'], 'M' ) ); ?></span></div>
								<div class="gelsensystem-events-main"><span><?php echo esc_html( self::format_event_time( $event ) ); ?></span><strong><?php echo esc_html( $event['title'] ); ?></strong><small><?php echo esc_html( $event['location'] ?: 'Kein Ort angegeben' ); ?></small></div>
								<div class="gelsensystem-events-actions"><span class="<?php echo $event['active'] ? 'is-on' : 'is-off'; ?>"><?php echo $event['active'] ? 'Aktiv' : 'Ausgeblendet'; ?></span><a class="button" href="<?php echo esc_url( add_query_arg( array( 'gd-section' => 'events', 'edit_event' => $event['id'] ), $dashboard_url ) ); ?>">Bearbeiten</a><form method="post" onsubmit="return confirm('Event wirklich löschen?');"><?php wp_nonce_field( 'gse_event_action', 'gse_nonce' ); ?><input type="hidden" name="gse_action" value="delete_event"><input type="hidden" name="event_id" value="<?php echo esc_attr( $event['id'] ); ?>"><button type="submit" class="button button-link-delete">Löschen</button></form></div>
								<div class="gelsensystem-events-overview">
									<div class="is-wide"><span>Kurzbeschreibung</span><p><?php echo esc_html( $description_summary ?: 'Nicht eingetragen' ); ?></p></div>
									<div class="is-wide"><span>Weitere Informationen</span><p><?php echo esc_html( $details_summary ?: 'Nicht eingetragen' ); ?></p></div>
									<div><span>Bilder</span><strong><?php echo esc_html( $event['image_ids'] ? count( $event['image_ids'] ) . ( 1 === count( $event['image_ids'] ) ? ' Bild' : ' Bilder' ) : 'Keine' ); ?></strong></div>
									<div><span>Webseite</span><?php if ( $event['link'] ) : ?><a href="<?php echo esc_url( $event['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link_label ); ?></a><?php else : ?><strong><?php echo esc_html( $link_label ); ?></strong><?php endif; ?></div>
									<div><span>Startseiten-Popup</span><strong><?php echo esc_html( $popup_label ); ?></strong></div>
									<div><span>Eventfarbe</span><strong class="gelsensystem-events-color-value"><i></i><?php echo esc_html( strtoupper( $event['color'] ) ); ?></strong></div>
								</div>
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
			'invalid_popup' => 'Bitte für das Popup ein gültiges Start- und Enddatum auswählen.',
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
		$end_date = self::format_date( $event['end'], 'D, d. F Y' );
		$is_multi_day = self::date_part( $event['start'] ) !== self::date_part( $event['end'] );
		if ( $event['all_day'] ) {
			return $date . ( $is_multi_day ? '–' . $end_date : '' ) . ' · ganztägig';
		}
		$start_time = self::format_date( $event['start'], 'H:i' );
		$end_time   = self::format_date( $event['end'], 'H:i' );
		if ( $is_multi_day ) {
			return $date . ' · ' . $start_time . ' Uhr–' . $end_date . ' · ' . $end_time . ' Uhr';
		}
		return $date . ' · ' . $start_time . ( $end_time ? '–' . $end_time . ' Uhr' : ' Uhr' );
	}

	private static function enqueue_public_assets() {
		wp_enqueue_style( 'gelsensystem-public-events', GELSENDIELE_URL . 'assets/public-events.css', array(), GELSENDIELE_VERSION );
		wp_enqueue_script( 'gelsensystem-public-events', GELSENDIELE_URL . 'assets/public-events.js', array(), GELSENDIELE_VERSION, true );
	}

	public static function render_public_events( $attributes = array() ) {
		$attributes = shortcode_atts( array( 'limit' => 100, 'show_past' => 'filter' ), $attributes, self::SHORTCODE );
		$mode       = strtolower( (string) $attributes['show_past'] );
		$filterable = 'filter' === $mode;
		$show_past  = $filterable || 'yes' === $mode;
		$events     = self::get_events( true, absint( $attributes['limit'] ), $show_past );
		$now        = current_datetime()->format( 'Y-m-d H:i:s' );
		$today      = self::date_part( $now );
		$upcoming   = count( array_filter( $events, static function ( $event ) use ( $now ) { return $event['end'] >= $now; } ) );
		$past       = count( $events ) - $upcoming;
		self::enqueue_public_assets();

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
					<label><span>Datum auswählen</span><input type="date" data-gse-date data-default-date="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $today ); ?>"><button type="button" data-gse-date-clear aria-label="Datumsfilter auf heute zurücksetzen">×</button></label>
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
								<?php $has_details = $event['details'] || count( $event['image_ids'] ) > 1; ?>
								<?php if ( $has_details || $event['link'] ) : ?>
									<div class="gse-event-card__actions">
										<?php if ( $has_details ) : ?><button type="button" class="gse-event-card__info-button" data-gse-details-toggle aria-expanded="false" aria-controls="gse-event-details-<?php echo esc_attr( $event['id'] ); ?>">Mehr Infos <span aria-hidden="true">↓</span></button><?php endif; ?>
										<?php if ( $event['link'] ) : ?><a class="gse-event-card__link" href="<?php echo esc_url( $event['link'] ); ?>" target="_blank" rel="noopener">Zur Webseite <span aria-hidden="true">↗</span></a><?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( $has_details ) : ?>
									<div class="gse-event-card__details" id="gse-event-details-<?php echo esc_attr( $event['id'] ); ?>" data-gse-details-panel hidden>
										<?php if ( $event['details'] ) : ?><div class="gse-event-card__details-text"><?php echo wp_kses_post( wpautop( $event['details'] ) ); ?></div><?php endif; ?>
										<?php if ( count( $event['image_ids'] ) > 1 ) : ?><div class="gse-event-card__gallery"><?php foreach ( array_slice( $event['image_ids'], 1 ) as $image_id ) { echo wp_get_attachment_image( $image_id, 'large', false, array( 'alt' => $event['title'], 'loading' => 'lazy' ) ); } ?></div><?php endif; ?>
									</div>
								<?php endif; ?>
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
		if ( ! self::is_homepage_request() || is_admin() ) {
			return null;
		}
		$now = current_datetime()->format( 'Y-m-d H:i:s' );
		foreach ( self::get_events( true, 100, false ) as $event ) {
			if ( $event['popup'] && $event['popup_start'] <= $now && $event['popup_end'] >= $now ) {
				self::$popup_event = $event;
				break;
			}
		}
		return self::$popup_event;
	}

	private static function is_homepage_request() {
		if ( is_front_page() || is_home() ) {
			return true;
		}
		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		$home_path    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return untrailingslashit( $request_path ) === untrailingslashit( $home_path );
	}

	private static function is_public_events_request() {
		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		$events_path  = (string) wp_parse_url( home_url( '/' . self::PAGE_SLUG . '/' ), PHP_URL_PATH );
		return untrailingslashit( $request_path ) === untrailingslashit( $events_path );
	}

	public static function enqueue_public_route_assets() {
		$page_id = (int) get_option( self::PAGE_OPTION, 0 );
		if ( self::is_public_events_request() || ( $page_id && is_page( $page_id ) ) ) {
			self::enqueue_public_assets();
		}
	}

	public static function enqueue_homepage_popup_assets() {
		if ( ! self::get_homepage_popup_event() ) {
			return;
		}
		self::enqueue_public_assets();
	}

	public static function render_homepage_popup() {
		$event = self::get_homepage_popup_event();
		if ( ! $event ) {
			return;
		}
		?>
		<div class="gse-event-popup" data-gse-popup data-event-id="<?php echo esc_attr( $event['id'] ); ?>" data-popup-version="<?php echo esc_attr( md5( $event['popup_start'] . '|' . $event['popup_end'] ) ); ?>" style="--gse-accent:<?php echo esc_attr( $event['color'] ); ?>" hidden>
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
		if ( is_admin() || ! self::is_public_events_request() ) {
			return;
		}

		$page_id = (int) get_option( self::PAGE_OPTION, 0 );
		if ( $page_id && is_page( $page_id ) ) {
			return;
		}

		// Fallback: Selbst wenn Permalinks oder ein fremdes Archiv die Adresse
		// abfangen, liefert /events/ weiterhin ausschließlich Gelsensystem-Events.
		status_header( 200 );
		nocache_headers();
		get_header();
		echo '<main id="main" class="gelsensystem-events-page"><div class="container">' . do_shortcode( '[' . self::SHORTCODE . ']' ) . '</div></main>';
		get_footer();
		exit;
	}
}
