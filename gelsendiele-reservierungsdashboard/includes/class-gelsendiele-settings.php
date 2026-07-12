<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zentrale, versionierte Konfiguration des gesamten Gastro-Systems.
 *
 * Bestehende gd_* Optionen bleiben während der Migration lesbar. Neue Module
 * greifen ausschließlich über diese Klasse auf gemeinsame Einstellungen zu.
 */
final class Gelsendiele_Settings {
	const OPTION         = 'gelsendiele_settings';
	const SCHEMA_VERSION = 1;

	private static $cache = null;

	public static function defaults() {
		$admin_email = sanitize_email( get_option( 'admin_email', '' ) );

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'general'        => array(
				'business_name'        => 'Die Gelsendiele',
				'sender_name'          => 'Die Gelsendiele',
				'sender_email'         => $admin_email,
				'internal_email'       => $admin_email,
				'phone'                => '',
				'timezone'             => 'Europe/Vienna',
				'date_format'          => 'd.m.Y',
				'time_format'          => 'H:i',
				'language'             => 'de_AT',
				'currency'             => 'EUR',
				'confirmation_mode'    => 'manual',
			),
			'branding'       => array(
				'logo_attachment_id' => 0,
				'logo_url'           => '',
				'primary_color'      => '#179b57',
				'secondary_color'    => '#0b5d38',
				'accent_color'       => '#d9a441',
				'surface_color'      => '#ffffff',
				'dark_surface_color' => '#08110b',
				'border_radius'      => 18,
				'theme_mode'         => 'auto',
			),
			'opening_hours'  => self::closed_week(),
			'reservations'   => array(
				'min_party'        => 1,
				'max_party'        => 20,
				'lead_minutes'     => 60,
				'advance_days'     => 120,
				'time_interval'    => 30,
				'booking_duration' => 120,
				'buffer_minutes'   => 0,
				'max_bookings'     => 30,
				'max_people'       => 120,
			),
			'availability'   => array(
				'closed_dates' => array(),
			),
			'form'           => array(
				'privacy_text' => 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung der Reservierung zu.',
				'success_text' => 'Vielen Dank! Ihre Reservierungsanfrage wurde übermittelt.',
				'button_text'  => 'Reservierung anfragen',
				'headline'     => 'Tisch reservieren',
				'intro'        => 'Wir freuen uns auf Ihren Besuch.',
			),
		);
	}

	public static function get_all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored      = get_option( self::OPTION, array() );
		self::$cache = self::merge_recursive( self::defaults(), is_array( $stored ) ? $stored : array() );
		return self::$cache;
	}

	public static function get( $section, $key = null, $default = null ) {
		$settings = self::get_all();
		if ( ! isset( $settings[ $section ] ) || ! is_array( $settings[ $section ] ) ) {
			return $default;
		}
		if ( null === $key ) {
			return $settings[ $section ];
		}
		return array_key_exists( $key, $settings[ $section ] ) ? $settings[ $section ][ $key ] : $default;
	}

	public static function maybe_initialize() {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}

		$settings = self::sanitize( self::import_legacy_settings( self::defaults() ) );
		add_option( self::OPTION, $settings, '', false );
		self::synchronize_wordpress_runtime( $settings );
		self::$cache = null;
	}

	public static function save_sections( $sections ) {
		$current = self::get_all();
		foreach ( (array) $sections as $section => $value ) {
			if ( array_key_exists( $section, $current ) && is_array( $value ) ) {
				$current[ $section ] = $value;
			}
		}

		$sanitized = self::sanitize( $current );
		$updated   = update_option( self::OPTION, $sanitized, false );
		self::$cache = $sanitized;
		self::synchronize_wordpress_runtime( $sanitized );
		return $updated;
	}

	/**
	 * Liefert die bisherige Engine-Struktur, damit bestehende Formulare während
	 * der schrittweisen Migration denselben zentralen Datenbestand verwenden.
	 */
	public static function reservation_engine_settings() {
		$settings     = self::get_all();
		$general      = $settings['general'];
		$reservations = $settings['reservations'];
		$form         = $settings['form'];
		$hours        = array();

		foreach ( self::day_keys() as $day ) {
			$config        = isset( $settings['opening_hours'][ $day ] ) ? $settings['opening_hours'][ $day ] : array();
			$hours[ $day ] = ! empty( $config['enabled'] ) ? (array) $config['blocks'] : array();
		}

		return array(
			'time_interval'    => $reservations['time_interval'],
			'booking_duration' => $reservations['booking_duration'],
			'min_party'        => $reservations['min_party'],
			'max_party'        => $reservations['max_party'],
			'max_tables'       => $reservations['max_bookings'],
			'max_people'       => $reservations['max_people'],
			'advance_days'     => $reservations['advance_days'],
			'lead_minutes'     => $reservations['lead_minutes'],
			'buffer_minutes'   => $reservations['buffer_minutes'],
			'admin_email'      => $general['internal_email'],
			'opening_hours'    => $hours,
			'closed_dates'     => $settings['availability']['closed_dates'],
			'privacy_text'     => $form['privacy_text'],
			'success_text'     => $form['success_text'],
		);
	}

	public static function css_variables() {
		$branding = self::get( 'branding', null, array() );
		return sprintf(
			'--gelsendiele-primary:%1$s;--gelsendiele-secondary:%2$s;--gelsendiele-accent:%3$s;--gelsendiele-surface:%4$s;--gelsendiele-dark-surface:%5$s;--gelsendiele-radius:%6$dpx;',
			esc_attr( $branding['primary_color'] ),
			esc_attr( $branding['secondary_color'] ),
			esc_attr( $branding['accent_color'] ),
			esc_attr( $branding['surface_color'] ),
			esc_attr( $branding['dark_surface_color'] ),
			absint( $branding['border_radius'] )
		);
	}

	private static function import_legacy_settings( $settings ) {
		$legacy = get_option( 'gd_reservation_engine_settings', array() );
		if ( is_array( $legacy ) ) {
			if ( ! empty( $legacy ) ) {
				// Bewahrt das bisherige Erscheinungsbild der konkreten Gelsendiele-
				// Installation. White-Label-Neuinstallationen starten ohne externes Logo.
				$settings['branding']['logo_url'] = 'https://www.gelsendiele.at/wp-content/uploads/2026/07/Logo-Gelsendiele_klein-1-300x247.png';
			}
			$map = array(
				'min_party'        => 'min_party',
				'max_party'        => 'max_party',
				'lead_minutes'     => 'lead_minutes',
				'advance_days'     => 'advance_days',
				'time_interval'    => 'time_interval',
				'booking_duration' => 'booking_duration',
				'max_tables'       => 'max_bookings',
				'max_people'       => 'max_people',
			);
			foreach ( $map as $old_key => $new_key ) {
				if ( isset( $legacy[ $old_key ] ) ) {
					$settings['reservations'][ $new_key ] = $legacy[ $old_key ];
				}
			}
			if ( ! empty( $legacy['admin_email'] ) ) {
				$settings['general']['internal_email'] = $legacy['admin_email'];
			}
			if ( ! empty( $legacy['privacy_text'] ) ) {
				$settings['form']['privacy_text'] = $legacy['privacy_text'];
			}
			if ( ! empty( $legacy['success_text'] ) ) {
				$settings['form']['success_text'] = $legacy['success_text'];
			}
			if ( ! empty( $legacy['closed_dates'] ) && is_array( $legacy['closed_dates'] ) ) {
				$settings['availability']['closed_dates'] = $legacy['closed_dates'];
			}
			if ( ! empty( $legacy['opening_hours'] ) && is_array( $legacy['opening_hours'] ) ) {
				foreach ( self::day_keys() as $day ) {
					$blocks = isset( $legacy['opening_hours'][ $day ] ) && is_array( $legacy['opening_hours'][ $day ] ) ? $legacy['opening_hours'][ $day ] : array();
					$settings['opening_hours'][ $day ] = array(
						'enabled' => ! empty( $blocks ),
						'blocks'  => $blocks,
					);
				}
			}
		}

		$five_star_hours = self::read_five_star_opening_hours();
		if ( ! empty( $five_star_hours ) ) {
			$settings['opening_hours'] = $five_star_hours;
		}

		return $settings;
	}

	private static function read_five_star_opening_hours() {
		global $rtb_controller;
		if ( ! isset( $rtb_controller->settings ) || ! is_object( $rtb_controller->settings ) || ! method_exists( $rtb_controller->settings, 'get_setting' ) ) {
			return array();
		}

		$rules = $rtb_controller->settings->get_setting( 'schedule-open' );
		if ( ! is_array( $rules ) ) {
			return array();
		}

		$week = self::closed_week();
		$days = array(
			'monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed', 'thursday' => 'thu',
			'friday' => 'fri', 'saturday' => 'sat', 'sunday' => 'sun',
		);
		$found = false;
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['weekdays'] ) ) {
				continue;
			}
			$blocks = self::extract_time_blocks( isset( $rule['time'] ) ? $rule['time'] : array() );
			if ( empty( $blocks ) ) {
				continue;
			}
			foreach ( $days as $long => $short ) {
				if ( ! empty( $rule['weekdays'][ $long ] ) ) {
					$week[ $short ]['enabled'] = true;
					$week[ $short ]['blocks']  = array_merge( $week[ $short ]['blocks'], $blocks );
					$found = true;
				}
			}
		}

		return $found ? $week : array();
	}

	private static function extract_time_blocks( $value ) {
		$blocks = array();
		if ( ! is_array( $value ) ) {
			return $blocks;
		}

		$start = isset( $value['start'] ) ? $value['start'] : ( isset( $value['from'] ) ? $value['from'] : '' );
		$end   = isset( $value['end'] ) ? $value['end'] : ( isset( $value['to'] ) ? $value['to'] : '' );
		if ( self::valid_time( $start ) && self::valid_time( $end ) ) {
			$blocks[] = array( 'start' => $start, 'end' => $end );
		}
		foreach ( $value as $child ) {
			if ( is_array( $child ) ) {
				$blocks = array_merge( $blocks, self::extract_time_blocks( $child ) );
			}
		}
		return self::unique_blocks( $blocks );
	}

	private static function sanitize( $settings ) {
		$defaults = self::defaults();
		$settings = self::merge_recursive( $defaults, is_array( $settings ) ? $settings : array() );
		$general  = $settings['general'];

		$timezones = timezone_identifiers_list();
		$timezone  = sanitize_text_field( $general['timezone'] );
		if ( ! in_array( $timezone, $timezones, true ) ) {
			$timezone = 'Europe/Vienna';
		}

		$confirmation = in_array( $general['confirmation_mode'], array( 'manual', 'automatic' ), true ) ? $general['confirmation_mode'] : 'manual';
		$currency     = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $general['currency'] ) );
		$settings['general'] = array(
			'business_name'     => sanitize_text_field( $general['business_name'] ),
			'sender_name'       => sanitize_text_field( $general['sender_name'] ),
			'sender_email'      => sanitize_email( $general['sender_email'] ),
			'internal_email'    => sanitize_email( $general['internal_email'] ),
			'phone'             => sanitize_text_field( $general['phone'] ),
			'timezone'          => $timezone,
			'date_format'       => sanitize_text_field( $general['date_format'] ),
			'time_format'       => sanitize_text_field( $general['time_format'] ),
			'language'          => sanitize_text_field( $general['language'] ),
			'currency'          => 3 === strlen( $currency ) ? $currency : 'EUR',
			'confirmation_mode' => $confirmation,
		);

		$branding = $settings['branding'];
		$settings['branding'] = array(
			'logo_attachment_id' => absint( $branding['logo_attachment_id'] ),
			'logo_url'           => esc_url_raw( $branding['logo_url'] ),
			'primary_color'      => self::color( $branding['primary_color'], $defaults['branding']['primary_color'] ),
			'secondary_color'    => self::color( $branding['secondary_color'], $defaults['branding']['secondary_color'] ),
			'accent_color'       => self::color( $branding['accent_color'], $defaults['branding']['accent_color'] ),
			'surface_color'      => self::color( $branding['surface_color'], $defaults['branding']['surface_color'] ),
			'dark_surface_color' => self::color( $branding['dark_surface_color'], $defaults['branding']['dark_surface_color'] ),
			'border_radius'      => max( 0, min( 40, absint( $branding['border_radius'] ) ) ),
			'theme_mode'         => in_array( $branding['theme_mode'], array( 'auto', 'light', 'dark' ), true ) ? $branding['theme_mode'] : 'auto',
		);

		$hours = self::closed_week();
		foreach ( self::day_keys() as $day ) {
			$source = isset( $settings['opening_hours'][ $day ] ) && is_array( $settings['opening_hours'][ $day ] ) ? $settings['opening_hours'][ $day ] : array();
			$blocks = array();
			foreach ( isset( $source['blocks'] ) && is_array( $source['blocks'] ) ? $source['blocks'] : array() as $block ) {
				$start = isset( $block['start'] ) ? sanitize_text_field( $block['start'] ) : '';
				$end   = isset( $block['end'] ) ? sanitize_text_field( $block['end'] ) : '';
				if ( self::valid_time( $start ) && self::valid_time( $end ) && $start !== $end ) {
					$blocks[] = array( 'start' => $start, 'end' => $end );
				}
			}
			$blocks = array_slice( self::unique_blocks( $blocks ), 0, 10 );
			$hours[ $day ] = array(
				'enabled' => ! empty( $source['enabled'] ) && ! empty( $blocks ),
				'blocks'  => $blocks,
			);
		}
		$settings['opening_hours'] = $hours;

		$res = $settings['reservations'];
		$settings['reservations'] = array(
			'min_party'        => max( 1, min( 100, absint( $res['min_party'] ) ) ),
			'max_party'        => max( 1, min( 500, absint( $res['max_party'] ) ) ),
			'lead_minutes'     => max( 0, min( 10080, absint( $res['lead_minutes'] ) ) ),
			'advance_days'     => max( 1, min( 730, absint( $res['advance_days'] ) ) ),
			'time_interval'    => max( 5, min( 180, absint( $res['time_interval'] ) ) ),
			'booking_duration' => max( 15, min( 1440, absint( $res['booking_duration'] ) ) ),
			'buffer_minutes'   => max( 0, min( 240, absint( $res['buffer_minutes'] ) ) ),
			'max_bookings'     => max( 0, min( 1000, absint( $res['max_bookings'] ) ) ),
			'max_people'       => max( 0, min( 10000, absint( $res['max_people'] ) ) ),
		);
		if ( $settings['reservations']['max_party'] < $settings['reservations']['min_party'] ) {
			$settings['reservations']['max_party'] = $settings['reservations']['min_party'];
		}

		$closed_dates = array();
		foreach ( (array) $settings['availability']['closed_dates'] as $date ) {
			$date = sanitize_text_field( $date );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$closed_dates[] = $date;
			}
		}
		$settings['availability']['closed_dates'] = array_values( array_unique( $closed_dates ) );

		$form = $settings['form'];
		$settings['form'] = array(
			'privacy_text' => sanitize_textarea_field( $form['privacy_text'] ),
			'success_text' => sanitize_textarea_field( $form['success_text'] ),
			'button_text'  => sanitize_text_field( $form['button_text'] ),
			'headline'     => sanitize_text_field( $form['headline'] ),
			'intro'        => sanitize_textarea_field( $form['intro'] ),
		);
		$settings['schema_version'] = self::SCHEMA_VERSION;
		return $settings;
	}

	private static function synchronize_wordpress_runtime( $settings ) {
		$general = $settings['general'];
		update_option( 'timezone_string', $general['timezone'] );
		update_option( 'date_format', $general['date_format'] );
		update_option( 'time_format', $general['time_format'] );
	}

	private static function merge_recursive( $defaults, $stored ) {
		foreach ( $stored as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && is_array( $value ) ) {
				$defaults[ $key ] = self::merge_recursive( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}
		return $defaults;
	}

	private static function closed_week() {
		$week = array();
		foreach ( self::day_keys() as $day ) {
			$week[ $day ] = array( 'enabled' => false, 'blocks' => array() );
		}
		return $week;
	}

	public static function day_keys() {
		return array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
	}

	private static function valid_time( $value ) {
		return is_string( $value ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value );
	}

	private static function unique_blocks( $blocks ) {
		$unique = array();
		foreach ( $blocks as $block ) {
			$key = $block['start'] . '-' . $block['end'];
			$unique[ $key ] = $block;
		}
		return array_values( $unique );
	}

	private static function color( $value, $fallback ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : $fallback;
	}
}
