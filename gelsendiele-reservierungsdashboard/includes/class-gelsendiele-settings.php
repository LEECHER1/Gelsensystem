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
	const SCHEMA_VERSION = 3;

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
				'rules'        => array(),
			),
			'emails'         => array(
				'reminder_hours' => 24,
				'templates'      => self::email_template_defaults(),
			),
			'form'           => array(
				'privacy_text' => 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung der Reservierung zu.',
				'success_text' => 'Vielen Dank! Ihre Reservierungsanfrage wurde übermittelt.',
				'error_text'   => 'Bitte prüfen Sie Ihre Angaben und versuchen Sie es erneut.',
				'button_text'  => 'Reservierung anfragen',
				'headline'     => 'Tisch reservieren',
				'intro'        => 'Wir freuen uns auf Ihren Besuch.',
				'width'        => 860,
				'theme_mode'   => 'inherit',
				'primary_color'=> '',
				'surface_color'=> '',
				'text_color'   => '',
				'fields'       => self::form_field_defaults(),
			),
		);
	}

	public static function email_template_defaults() {
		return array(
			'internal_new' => array(
				'label' => 'Neue Reservierungsanfrage an den Betrieb', 'enabled' => 1, 'recipient' => 'internal', 'custom_recipient' => '', 'format' => 'text',
				'subject' => 'Neue Reservierung: {guest_name} am {date}',
				'body' => "Neue Reservierung\n\nName: {guest_name}\nDatum: {date}\nUhrzeit: {time}\nPersonen: {party}\nTelefon: {phone}\nE-Mail: {email}\nTisch: {table}\nBereich: {area}\nKinderstuhl: {highchair}\nHund: {dog}\nAllergien: {allergies}\nNachricht: {message}\nBuchungsnummer: {booking_id}",
			),
			'guest_received' => array(
				'label' => 'Eingangsbestätigung an den Gast', 'enabled' => 1, 'recipient' => 'guest', 'custom_recipient' => '', 'format' => 'text',
				'subject' => 'Ihre Reservierungsanfrage bei {business_name}',
				'body' => "Hallo {guest_name},\n\nvielen Dank für Ihre Anfrage. Wir melden uns mit der Bestätigung.\n\nTermin: {date} um {time} Uhr\nPersonen: {party}\n\n{business_name}",
			),
			'guest_confirmed' => array(
				'label' => 'Reservierung bestätigt', 'enabled' => 1, 'recipient' => 'guest', 'custom_recipient' => '', 'format' => 'text',
				'subject' => 'Ihre Reservierung bei {business_name} ist bestätigt',
				'body' => "Hallo {guest_name},\n\nIhre Reservierung ist bestätigt.\n\nTermin: {date} um {time} Uhr\nPersonen: {party}\nTisch: {table}\n\n{business_name}",
			),
			'guest_rejected' => array(
				'label' => 'Reservierung abgelehnt', 'enabled' => 1, 'recipient' => 'guest', 'custom_recipient' => '', 'format' => 'text',
				'subject' => 'Ihre Reservierungsanfrage bei {business_name}',
				'body' => "Hallo {guest_name},\n\nleider können wir Ihre Reservierungsanfrage für {date} um {time} Uhr nicht annehmen.\n\n{business_name}",
			),
			'guest_changed' => array(
				'label' => 'Reservierung geändert', 'enabled' => 1, 'recipient' => 'guest', 'custom_recipient' => '', 'format' => 'text',
				'subject' => 'Ihre Reservierung bei {business_name} wurde geändert',
				'body' => "Hallo {guest_name},\n\nIhre Reservierung wurde aktualisiert.\n\nTermin: {date} um {time} Uhr\nPersonen: {party}\nTisch: {table}\n\n{business_name}",
			),
			'guest_cancelled' => array(
				'label' => 'Reservierung storniert', 'enabled' => 1, 'recipient' => 'guest', 'custom_recipient' => '', 'format' => 'text',
				'subject' => 'Ihre Reservierung bei {business_name} wurde storniert',
				'body' => "Hallo {guest_name},\n\nIhre Reservierung für {date} um {time} Uhr wurde storniert.\n\n{business_name}",
			),
			'guest_reminder' => array(
				'label' => 'Erinnerung vor dem Termin', 'enabled' => 0, 'recipient' => 'guest', 'custom_recipient' => '', 'format' => 'text',
				'subject' => 'Erinnerung an Ihre Reservierung bei {business_name}',
				'body' => "Hallo {guest_name},\n\nwir erinnern Sie an Ihre Reservierung am {date} um {time} Uhr für {party} Personen.\n\n{business_name}",
			),
		);
	}

	public static function form_field_defaults() {
		return array(
			'date' => array( 'label' => 'Datum', 'enabled' => 1, 'required' => 1, 'locked' => 1 ),
			'time' => array( 'label' => 'Uhrzeit', 'enabled' => 1, 'required' => 1, 'locked' => 1 ),
			'party' => array( 'label' => 'Personen', 'enabled' => 1, 'required' => 1, 'locked' => 1 ),
			'name' => array( 'label' => 'Name', 'enabled' => 1, 'required' => 1 ),
			'email' => array( 'label' => 'E-Mail', 'enabled' => 1, 'required' => 1 ),
			'phone' => array( 'label' => 'Telefon', 'enabled' => 1, 'required' => 1 ),
			'message' => array( 'label' => 'Nachricht', 'enabled' => 1, 'required' => 0 ),
			'area' => array( 'label' => 'Bereichswunsch', 'enabled' => 0, 'required' => 0 ),
			'table' => array( 'label' => 'Tischwunsch', 'enabled' => 0, 'required' => 0 ),
			'highchair' => array( 'label' => 'Kinderstuhl benötigt', 'enabled' => 0, 'required' => 0 ),
			'dog' => array( 'label' => 'Hund kommt mit', 'enabled' => 0, 'required' => 0 ),
			'allergies' => array( 'label' => 'Allergien und Unverträglichkeiten', 'enabled' => 0, 'required' => 0 ),
			'privacy' => array( 'label' => 'Datenschutz', 'enabled' => 1, 'required' => 1 ),
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
			self::maybe_upgrade();
			return;
		}

		$settings = self::sanitize( self::import_legacy_settings( self::defaults() ) );
		add_option( self::OPTION, $settings, '', false );
		self::synchronize_wordpress_runtime( $settings );
		self::$cache = null;
	}

	/** Aktualisiert ausschließlich die versionierte Einstellungsstruktur. */
	public static function maybe_upgrade() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$version = isset( $stored['schema_version'] ) ? absint( $stored['schema_version'] ) : 0;
		if ( $version >= self::SCHEMA_VERSION ) {
			return;
		}

		if ( $version < 2 ) {
			$availability = isset( $stored['availability'] ) && is_array( $stored['availability'] ) ? $stored['availability'] : array();
			$rules        = isset( $availability['rules'] ) && is_array( $availability['rules'] ) ? $availability['rules'] : array();
			foreach ( (array) ( isset( $availability['closed_dates'] ) ? $availability['closed_dates'] : array() ) as $date ) {
				if ( self::valid_date_value( $date ) ) {
					$rules[] = array(
						'id'         => 'legacy-' . sanitize_key( $date ),
						'enabled'    => 1,
						'type'       => 'closed',
						'start_date' => $date,
						'end_date'   => $date,
						'comment'    => 'Aus bisherigen geschlossenen Tagen übernommen',
					);
				}
			}
			$availability['rules']        = $rules;
			$availability['closed_dates'] = array();
			$stored['availability']       = $availability;
		}

		$stored['schema_version'] = self::SCHEMA_VERSION;
		$stored                   = self::sanitize( $stored );
		update_option( self::OPTION, $stored, false );
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
			'availability_rules' => $settings['availability']['rules'],
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

		$start = self::normalize_time( isset( $value['start'] ) ? $value['start'] : ( isset( $value['from'] ) ? $value['from'] : '' ) );
		$end   = self::normalize_time( isset( $value['end'] ) ? $value['end'] : ( isset( $value['to'] ) ? $value['to'] : '' ) );
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
			if ( self::valid_date_value( $date ) ) {
				$closed_dates[] = $date;
			}
		}
		$rules = array();
		foreach ( (array) $settings['availability']['rules'] as $rule ) {
			$sanitized_rule = self::sanitize_availability_rule( $rule );
			if ( $sanitized_rule ) {
				$rules[] = $sanitized_rule;
			}
		}
		usort( $rules, array( __CLASS__, 'sort_availability_rules' ) );
		$settings['availability'] = array(
			'closed_dates' => array_values( array_unique( $closed_dates ) ),
			'rules'        => array_slice( $rules, 0, 500 ),
		);

		$email_defaults     = self::email_template_defaults();
		$email_source       = isset( $settings['emails'] ) && is_array( $settings['emails'] ) ? $settings['emails'] : array();
		$email_templates    = isset( $email_source['templates'] ) && is_array( $email_source['templates'] ) ? $email_source['templates'] : array();
		$sanitized_templates = array();
		foreach ( $email_defaults as $slug => $template_default ) {
			$template = isset( $email_templates[ $slug ] ) && is_array( $email_templates[ $slug ] ) ? self::merge_recursive( $template_default, $email_templates[ $slug ] ) : $template_default;
			$format   = in_array( $template['format'], array( 'text', 'html' ), true ) ? $template['format'] : 'text';
			$recipient = in_array( $template['recipient'], array( 'internal', 'guest', 'custom' ), true ) ? $template['recipient'] : $template_default['recipient'];
			$sanitized_templates[ $slug ] = array(
				'label'            => $template_default['label'],
				'enabled'          => ! empty( $template['enabled'] ) ? 1 : 0,
				'recipient'        => $recipient,
				'custom_recipient' => sanitize_email( $template['custom_recipient'] ),
				'format'           => $format,
				'subject'          => sanitize_text_field( $template['subject'] ),
				'body'             => 'html' === $format ? wp_kses_post( $template['body'] ) : sanitize_textarea_field( $template['body'] ),
			);
		}
		$settings['emails'] = array(
			'reminder_hours' => max( 1, min( 336, absint( isset( $email_source['reminder_hours'] ) ? $email_source['reminder_hours'] : 24 ) ) ),
			'templates'      => $sanitized_templates,
		);

		$form = $settings['form'];
		$field_defaults = self::form_field_defaults();
		$field_source   = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
		$fields         = array();
		foreach ( $field_defaults as $slug => $field_default ) {
			$field = isset( $field_source[ $slug ] ) && is_array( $field_source[ $slug ] ) ? self::merge_recursive( $field_default, $field_source[ $slug ] ) : $field_default;
			$locked = ! empty( $field_default['locked'] );
			$label  = sanitize_text_field( $field['label'] );
			$fields[ $slug ] = array(
				'label'    => '' !== $label ? $label : $field_default['label'],
				'enabled'  => ( $locked || ! empty( $field['enabled'] ) ) ? 1 : 0,
				'required' => ( $locked || ! empty( $field['required'] ) ) ? 1 : 0,
			);
			if ( $locked ) {
				$fields[ $slug ]['locked'] = 1;
			}
			if ( empty( $fields[ $slug ]['enabled'] ) ) {
				$fields[ $slug ]['required'] = 0;
			}
		}
		$settings['form'] = array(
			'privacy_text' => sanitize_textarea_field( $form['privacy_text'] ),
			'success_text' => sanitize_textarea_field( $form['success_text'] ),
			'error_text'   => sanitize_textarea_field( $form['error_text'] ),
			'button_text'  => sanitize_text_field( $form['button_text'] ),
			'headline'     => sanitize_text_field( $form['headline'] ),
			'intro'        => sanitize_textarea_field( $form['intro'] ),
			'width'        => max( 320, min( 1400, absint( $form['width'] ) ) ),
			'theme_mode'   => in_array( $form['theme_mode'], array( 'inherit', 'light', 'dark' ), true ) ? $form['theme_mode'] : 'inherit',
			'primary_color'=> self::optional_color( $form['primary_color'] ),
			'surface_color'=> self::optional_color( $form['surface_color'] ),
			'text_color'   => self::optional_color( $form['text_color'] ),
			'fields'       => $fields,
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

	/** Five Star speichert Zeiten je nach Format z. B. als "4:00 PM". */
	private static function normalize_time( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( self::valid_time( $value ) ) {
			return $value;
		}
		if ( '' === $value || 'undefined' === strtolower( $value ) ) {
			return '';
		}
		try {
			return ( new DateTimeImmutable( $value, wp_timezone() ) )->format( 'H:i' );
		} catch ( Exception $error ) {
			return '';
		}
	}

	private static function valid_date_value( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	private static function sanitize_availability_rule( $rule ) {
		if ( ! is_array( $rule ) ) {
			return null;
		}
		$type = isset( $rule['type'] ) ? sanitize_key( $rule['type'] ) : '';
		if ( ! in_array( $type, array( 'closed', 'vacation', 'special_open', 'blocked_time', 'capacity' ), true ) ) {
			return null;
		}

		$start_date = isset( $rule['start_date'] ) ? sanitize_text_field( $rule['start_date'] ) : '';
		$end_date   = isset( $rule['end_date'] ) ? sanitize_text_field( $rule['end_date'] ) : $start_date;
		if ( ! self::valid_date_value( $start_date ) ) {
			return null;
		}
		if ( ! self::valid_date_value( $end_date ) || $end_date < $start_date ) {
			$end_date = $start_date;
		}

		$start_time = isset( $rule['start_time'] ) ? sanitize_text_field( $rule['start_time'] ) : '';
		$end_time   = isset( $rule['end_time'] ) ? sanitize_text_field( $rule['end_time'] ) : '';
		$needs_time = in_array( $type, array( 'special_open', 'blocked_time' ), true );
		if ( $needs_time && ( ! self::valid_time( $start_time ) || ! self::valid_time( $end_time ) || $start_time === $end_time ) ) {
			return null;
		}
		if ( 'capacity' === $type && ( ! self::valid_time( $start_time ) || ! self::valid_time( $end_time ) || $start_time === $end_time ) ) {
			$start_time = '';
			$end_time   = '';
		}
		if ( ! $needs_time && 'capacity' !== $type ) {
			$start_time = '';
			$end_time   = '';
		}

		$max_bookings = isset( $rule['max_bookings'] ) ? max( 0, min( 1000, absint( $rule['max_bookings'] ) ) ) : 0;
		$max_people   = isset( $rule['max_people'] ) ? max( 0, min( 10000, absint( $rule['max_people'] ) ) ) : 0;
		if ( 'capacity' === $type && 0 === $max_bookings && 0 === $max_people ) {
			return null;
		}

		$areas = array();
		$raw_areas = isset( $rule['areas'] ) ? $rule['areas'] : array();
		if ( is_string( $raw_areas ) ) {
			$raw_areas = preg_split( '/\s*,\s*/', $raw_areas, -1, PREG_SPLIT_NO_EMPTY );
		}
		foreach ( (array) $raw_areas as $area ) {
			$area = sanitize_text_field( $area );
			if ( '' !== $area ) {
				$areas[] = $area;
			}
		}

		$id = isset( $rule['id'] ) ? sanitize_key( $rule['id'] ) : '';
		if ( '' === $id ) {
			$id = 'rule-' . str_replace( '-', '', wp_generate_uuid4() );
		}

		return array(
			'id'             => $id,
			'enabled'        => ! empty( $rule['enabled'] ) ? 1 : 0,
			'type'           => $type,
			'start_date'     => $start_date,
			'end_date'       => $end_date,
			'start_time'     => $start_time,
			'end_time'       => $end_time,
			'max_bookings'   => $max_bookings,
			'max_people'     => $max_people,
			'areas'          => array_values( array_unique( $areas ) ),
			'comment'        => isset( $rule['comment'] ) ? sanitize_textarea_field( $rule['comment'] ) : '',
			'public_message' => isset( $rule['public_message'] ) ? sanitize_textarea_field( $rule['public_message'] ) : '',
		);
	}

	private static function sort_availability_rules( $left, $right ) {
		$by_date = strcmp( $left['start_date'], $right['start_date'] );
		return 0 !== $by_date ? $by_date : strcmp( $left['id'], $right['id'] );
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

	private static function optional_color( $value ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : '';
	}
}
