<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Führt idempotente Updates auch bei bereits aktivem Plugin aus. */
final class Gelsendiele_Migrator {
	const VERSION_OPTION = 'gelsendiele_migration_version';
	const TARGET_VERSION = '2.3.1';
	const ERROR_OPTION   = 'gelsendiele_last_migration_error';

	public static function bootstrap() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_migrate' ), 120 );
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

			if ( class_exists( 'GD_Reservation_Engine' ) ) {
				GD_Reservation_Engine::instance()->ensure_roles();
			}
			if ( class_exists( 'GDG_DB' ) ) {
				GDG_DB::activate( false );
				GDG_DB::migrate_legacy_tables();
			}

			self::ensure_professional_roles();
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
			$manager = add_role( 'gelsendiele_manager', 'Gelsendiele Betriebsleitung', $manager_caps );
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
			$reservation = add_role( 'gelsendiele_reservation_staff', 'Gelsendiele Reservierungsmitarbeiter', $reservation_caps );
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
}
