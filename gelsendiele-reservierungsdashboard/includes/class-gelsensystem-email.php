<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Eigene, vorlagenbasierte Benachrichtigungen ohne Five-Star-Abhängigkeit. */
final class Gelsensystem_Email {
	const REMINDER_HOOK = 'gelsensystem_send_booking_reminder';

	public static function bootstrap() {
		add_action( 'gd_reservierungsdashboard_manual_booking_updated', array( __CLASS__, 'booking_changed' ), 10, 2 );
		add_action( self::REMINDER_HOOK, array( __CLASS__, 'send_reminder' ), 10, 1 );
	}

	public static function send_new_booking( $booking_id ) {
		$booking_id = absint( $booking_id );
		if ( ! $booking_id ) {
			return false;
		}

		$internal = self::send_template( 'internal_new', $booking_id );
		$guest_slug = 'confirmed' === get_post_status( $booking_id ) ? 'guest_confirmed' : 'guest_received';
		$guest = self::send_template( $guest_slug, $booking_id );
		self::schedule_reminder( $booking_id );
		return $internal || $guest;
	}

	public static function send_status( $booking_id, $status ) {
		$map = array(
			'confirmed' => 'guest_confirmed',
			'rejected'  => 'guest_rejected',
			'cancelled' => 'guest_cancelled',
		);
		$status = sanitize_key( $status );
		if ( ! isset( $map[ $status ] ) ) {
			return false;
		}
		if ( in_array( $status, array( 'cancelled', 'rejected' ), true ) ) {
			self::unschedule_reminder( $booking_id );
		}
		if ( 'confirmed' === $status ) {
			self::schedule_reminder( $booking_id );
		}
		return self::send_template( $map[ $status ], $booking_id );
	}

	public static function booking_changed( $booking_id, $changed ) {
		// Solange Five Star aktiv ist, besitzt dessen Update-Hook die Status- und
		// Änderungsbenachrichtigungen. Dadurch entstehen keine doppelten E-Mails.
		if ( defined( 'RTB_PLUGIN_DIR' ) || empty( $changed ) || in_array( 'Status', (array) $changed, true ) ) {
			return;
		}
		self::send_template( 'guest_changed', $booking_id );
		self::schedule_reminder( $booking_id );
	}

	public static function send_reminder( $booking_id ) {
		$booking_id = absint( $booking_id );
		if ( defined( 'RTB_PLUGIN_DIR' ) || ! $booking_id || 'confirmed' !== get_post_status( $booking_id ) || get_post_meta( $booking_id, '_gelsensystem_reminder_sent', true ) ) {
			return;
		}
		if ( self::send_template( 'guest_reminder', $booking_id ) ) {
			update_post_meta( $booking_id, '_gelsensystem_reminder_sent', current_time( 'mysql' ) );
		}
	}

	public static function schedule_existing_reminders() {
		$ids = get_posts( array(
			'post_type'      => defined( 'RTB_BOOKING_POST_TYPE' ) ? RTB_BOOKING_POST_TYPE : 'rtb-booking',
			'post_status'    => 'confirmed',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => array( array( 'after' => current_time( 'mysql' ), 'inclusive' => true ) ),
		) );
		foreach ( $ids as $booking_id ) {
			self::schedule_reminder( $booking_id );
		}
	}

	public static function schedule_reminder( $booking_id ) {
		$booking_id = absint( $booking_id );
		self::unschedule_reminder( $booking_id );
		$template = self::template( 'guest_reminder' );
		if ( defined( 'RTB_PLUGIN_DIR' ) || ! $booking_id || empty( $template['enabled'] ) || 'confirmed' !== get_post_status( $booking_id ) ) {
			return;
		}
		$post = get_post( $booking_id );
		if ( ! $post ) {
			return;
		}
		try {
			$booking_time = new DateTimeImmutable( $post->post_date, wp_timezone() );
		} catch ( Exception $error ) {
			return;
		}
		$hours = absint( Gelsendiele_Settings::get( 'emails', 'reminder_hours', 24 ) );
		$send_at = $booking_time->modify( '-' . max( 1, $hours ) . ' hours' )->getTimestamp();
		if ( $send_at <= time() + 300 ) {
			return;
		}
		delete_post_meta( $booking_id, '_gelsensystem_reminder_sent' );
		wp_schedule_single_event( $send_at, self::REMINDER_HOOK, array( $booking_id ) );
	}

	public static function send_test( $slug, $recipient ) {
		$recipient = sanitize_email( $recipient );
		if ( ! is_email( $recipient ) ) {
			return new WP_Error( 'gelsensystem_test_recipient', 'Bitte eine gültige Test-E-Mail-Adresse eintragen.' );
		}
		$template = self::template( $slug );
		if ( ! $template ) {
			return new WP_Error( 'gelsensystem_test_template', 'Die E-Mail-Vorlage wurde nicht gefunden.' );
		}
		$context = array(
			'guest_name' => 'Max Mustermann', 'date' => wp_date( 'd.m.Y', time() + DAY_IN_SECONDS ), 'time' => '18:30', 'party' => '4',
			'table' => 'Tisch 4', 'area' => 'Gastraum', 'phone' => '+43 660 1234567', 'email' => $recipient,
			'message' => 'Dies ist eine Testnachricht.', 'allergies' => 'Keine', 'highchair' => 'Ja', 'dog' => 'Nein', 'booking_id' => 'TEST-1001',
			'business_name' => Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' ), 'cancellation_link' => '',
		);
		return self::deliver_template( $slug, $template, $context, $recipient, 0 );
	}

	public static function send_template( $slug, $booking_id, $override_recipient = '' ) {
		$template = self::template( $slug );
		if ( ! $template || empty( $template['enabled'] ) ) {
			return false;
		}
		$context = self::booking_context( $booking_id );
		if ( ! $context ) {
			return false;
		}
		$recipient = self::recipient( $template, $context, $override_recipient );
		if ( ! is_email( $recipient ) ) {
			return false;
		}
		return self::deliver_template( $slug, $template, $context, $recipient, $booking_id );
	}

	private static function template( $slug ) {
		$templates = Gelsendiele_Settings::get( 'emails', 'templates', array() );
		return isset( $templates[ $slug ] ) && is_array( $templates[ $slug ] ) ? $templates[ $slug ] : null;
	}

	private static function recipient( $template, $context, $override ) {
		$override = sanitize_email( $override );
		if ( is_email( $override ) ) {
			return $override;
		}
		if ( 'guest' === $template['recipient'] ) {
			return sanitize_email( $context['email'] );
		}
		if ( 'custom' === $template['recipient'] ) {
			return sanitize_email( $template['custom_recipient'] );
		}
		return sanitize_email( Gelsendiele_Settings::get( 'general', 'internal_email', get_option( 'admin_email' ) ) );
	}

	private static function booking_context( $booking_id ) {
		$post = get_post( absint( $booking_id ) );
		if ( ! $post || RTB_BOOKING_POST_TYPE !== $post->post_type ) {
			return array();
		}
		$meta    = (array) get_post_meta( $post->ID, 'rtb', true );
		$details = (array) get_post_meta( $post->ID, '_gelsensystem_form_details', true );
		try {
			$date = new DateTimeImmutable( $post->post_date, wp_timezone() );
		} catch ( Exception $error ) {
			return array();
		}
		$date_format = Gelsendiele_Settings::get( 'general', 'date_format', 'd.m.Y' );
		$time_format = Gelsendiele_Settings::get( 'general', 'time_format', 'H:i' );
		$table = (string) get_post_meta( $post->ID, '_gd_table_number', true );
		if ( '' === $table && ! empty( $details['table'] ) ) {
			$table = $details['table'];
		}
		return array(
			'guest_name'       => $post->post_title,
			'date'             => wp_date( $date_format, $date->getTimestamp(), wp_timezone() ),
			'time'             => wp_date( $time_format, $date->getTimestamp(), wp_timezone() ),
			'party'            => (string) absint( isset( $meta['party'] ) ? $meta['party'] : 0 ),
			'table'            => $table,
			'area'             => isset( $details['area'] ) ? $details['area'] : '',
			'phone'            => isset( $meta['phone'] ) ? $meta['phone'] : '',
			'email'            => isset( $meta['email'] ) ? $meta['email'] : '',
			'message'          => $post->post_content,
			'allergies'        => isset( $details['allergies'] ) ? $details['allergies'] : '',
			'highchair'        => ! empty( $details['highchair'] ) ? 'Ja' : 'Nein',
			'dog'              => ! empty( $details['dog'] ) ? 'Ja' : 'Nein',
			'booking_id'       => (string) $post->ID,
			'business_name'    => Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' ),
			'cancellation_link'=> (string) apply_filters( 'gelsensystem_cancellation_link', '', $post->ID ),
		);
	}

	private static function deliver_template( $slug, $template, $context, $recipient, $booking_id ) {
		$html_context = array();
		foreach ( $context as $key => $value ) {
			$html_context[ $key ] = 'cancellation_link' === $key ? esc_url( $value ) : esc_html( $value );
		}
		$values  = 'html' === $template['format'] ? $html_context : $context;
		$subject = sanitize_text_field( self::replace( $template['subject'], $context ) );
		$body    = self::replace( $template['body'], $values );
		$headers = array();
		$sender_email = sanitize_email( Gelsendiele_Settings::get( 'general', 'sender_email', get_option( 'admin_email' ) ) );
		$sender_name  = sanitize_text_field( Gelsendiele_Settings::get( 'general', 'sender_name', Gelsendiele_Settings::get( 'general', 'business_name', 'Gelsensystem' ) ) );
		if ( is_email( $sender_email ) ) {
			$headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
		}
		if ( 'html' === $template['format'] ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$body = wpautop( $body );
		}
		$sent = wp_mail( $recipient, $subject, $body, $headers );
		if ( ! $sent ) {
			error_log( '[Gelsensystem] E-Mail-Vorlage ' . sanitize_key( $slug ) . ' für Reservierung ' . absint( $booking_id ) . ' konnte nicht versendet werden.' );
		}
		do_action( 'gelsensystem_email_sent', $slug, absint( $booking_id ), $sent );
		return (bool) $sent;
	}

	private static function replace( $template, $context ) {
		$replace = array();
		foreach ( $context as $key => $value ) {
			$replace[ '{' . $key . '}' ] = (string) $value;
		}
		return strtr( (string) $template, $replace );
	}

	private static function unschedule_reminder( $booking_id ) {
		$booking_id = absint( $booking_id );
		$timestamp  = wp_next_scheduled( self::REMINDER_HOOK, array( $booking_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::REMINDER_HOOK, array( $booking_id ) );
		}
	}
}
