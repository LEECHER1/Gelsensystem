<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Führt idempotente Updates auch bei bereits aktivem Plugin aus. */
final class Gelsendiele_Migrator {
	const VERSION_OPTION = 'gelsendiele_migration_version';
	const TARGET_VERSION = '2.15.0';
	const ERROR_OPTION   = 'gelsendiele_last_migration_error';

	public static function bootstrap() {
		// Seiten- und Rollenfunktionen sind erst ab init vollständig verfügbar.
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 1 );
	}

	public static function activate() {
		self::maybe_migrate( true );
	}

	public static function maybe_migrate( $force = false ) {
		$current = (string) get_option( self::VERSION_OPTION, '0.0.0' );
		if ( ! $force && version_compare( $current, self::TARGET_VERSION, '>=' ) ) {
			return true;
		}

		try {
			Gelsendiele_Settings::maybe_initialize();
			if ( class_exists( 'Gelsendiele_Reservierungsdashboard' ) ) {
				Gelsendiele_Reservierungsdashboard::ensure_central_page();
			}

			if ( class_exists( 'GD_Reservation_Engine' ) ) {
				GD_Reservation_Engine::instance()->ensure_roles();
			}
			if ( class_exists( 'GDG_DB' ) ) {
				GDG_DB::activate( false );
				GDG_DB::migrate_legacy_tables();
			}

			self::ensure_professional_roles();
			self::rename_backend_entities();
			update_option( self::VERSION_OPTION, self::TARGET_VERSION, false );
			delete_option( self::ERROR_OPTION );
			return true;
		} catch ( Throwable $error ) {
			$message = sprintf( '[Gelsendiele Migration %s] %s', self::TARGET_VERSION, $error->getMessage() );
			update_option( self::ERROR_OPTION, sanitize_text_field( $message ), false );
			error_log( $message );
			return false;
		}
	}

	private static function ensure_professional_roles() {
		$manager_caps = array(
			'read'                         => true,
			'manage_bookings'              => true,
			'edit_booking'                 => true,
			'read_booking'                 => true,
			'delete_booking'               => true,
			'gelsendiele_manage_settings'  => true,
			'gelsendiele_view_system'      => true,
			'gdg_manage'                   => true,
			'gdg_use_service'              => true,
			'gdg_use_kitchen'              => true,
			'gdg_use_bar'                  => true,
			'gdg_use_checkout'             => true,
		);

		$manager = get_role( 'gelsendiele_manager' );
		if ( ! $manager ) {
			$manager = add_role( 'gelsendiele_manager', 'Gelsensystem Betriebsleitung', $manager_caps );
		}
		if ( $manager ) {
			foreach ( $manager_caps as $capability => $grant ) {
				$manager->add_cap( $capability, $grant );
			}
		}

		$reservation_caps = array(
			'read'            => true,
			'manage_bookings' => true,
			'edit_booking'    => true,
			'read_booking'    => true,
			'delete_booking'  => true,
		);
		$reservation = get_role( 'gelsendiele_reservation_staff' );
		if ( ! $reservation ) {
			$reservation = add_role( 'gelsendiele_reservation_staff', 'Gelsensystem Reservierungen', $reservation_caps );
		}
		if ( $reservation ) {
			foreach ( $reservation_caps as $capability => $grant ) {
				$reservation->add_cap( $capability, $grant );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( array_keys( $manager_caps ) as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
	}

	/** Ändert ausschließlich sichtbare Backend-Bezeichnungen; technische Schlüssel bleiben kompatibel. */
	private static function rename_backend_entities() {
		$page_id = (int) get_option( 'gd_reservierungsdashboard_page_id', 0 );
		if ( $page_id && 'Gelsensystem' !== get_the_title( $page_id ) ) {
			wp_update_post( array( 'ID' => $page_id, 'post_title' => 'Gelsensystem' ) );
		}

		$labels = array(
			'gelsendiele_manager'           => 'Gelsensystem Betriebsleitung',
			'gelsendiele_reservation_staff' => 'Gelsensystem Reservierungen',
			'gd_reservation_manager'        => 'Gelsensystem Reservierungen',
			'gdg_service'                   => 'Gelsensystem Service',
			'gdg_kitchen'                   => 'Gelsensystem Küche',
			'gdg_bar'                       => 'Gelsensystem Schank',
		);
		$wp_roles = wp_roles();
		$changed  = false;
		foreach ( $labels as $slug => $label ) {
			if ( isset( $wp_roles->roles[ $slug ] ) && $wp_roles->roles[ $slug ]['name'] !== $label ) {
				$wp_roles->roles[ $slug ]['name'] = $label;
				$wp_roles->role_names[ $slug ]    = $label;
				$changed = true;
			}
		}
		if ( $changed && $wp_roles->use_db ) {
			update_option( $wp_roles->role_key, $wp_roles->roles );
		}
	}
}
