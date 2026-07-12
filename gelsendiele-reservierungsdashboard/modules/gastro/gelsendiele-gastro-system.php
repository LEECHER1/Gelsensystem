<?php
/**
 * Plugin Name: Gelsensystem Gastro
 * Description: Service-, Küchen-, Schank- und Zahlungsmodul des Gelsensystems.
 * Version: 2.7.1
 * Author: Andreas Schwarz / Gelsensystem
 * Text Domain: gelsendiele-gastro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Kompatibilität mit der früheren eigenständigen Gastro-Erweiterung.
 * Ist sie noch aktiv, sind ihre Klassen und Funktionen bereits geladen.
 * Das integrierte Modul darf sie dann nicht ein zweites Mal deklarieren.
 */
// GDG_Plugin wird weiter unten in derselben Datei deklariert und kann je nach
// PHP-Ausführungsmodell bereits bei dieser Prüfung als vorhanden gelten. Als
// belastbares Signal für eine separat geladene Altversion dient deshalb nur
// die dort zwingend vorab eingebundene Datenbankklasse.
if ( class_exists( 'GDG_DB', false ) ) {
	return;
}

defined( 'GDG_VERSION' ) || define( 'GDG_VERSION', defined( 'GELSENDIELE_VERSION' ) ? GELSENDIELE_VERSION : '2.7.1' );
defined( 'GDG_FILE' ) || define( 'GDG_FILE', __FILE__ );
defined( 'GDG_DIR' ) || define( 'GDG_DIR', plugin_dir_path( __FILE__ ) );
defined( 'GDG_URL' ) || define( 'GDG_URL', plugin_dir_url( __FILE__ ) );

require_once GDG_DIR . 'includes/class-gdg-db.php';
require_once GDG_DIR . 'includes/class-gdg-rest.php';
require_once GDG_DIR . 'includes/class-gdg-admin.php';
require_once GDG_DIR . 'includes/class-gdg-app.php';
require_once GDG_DIR . 'includes/class-gdg-reservation-bridge.php';

register_activation_hook( __FILE__, array( 'GDG_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GDG_DB', 'deactivate' ) );

final class GDG_Plugin {
	private static ?GDG_Plugin $instance = null;

	public static function instance(): GDG_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( 'GDG_REST', 'register_routes' ) );
		add_action( 'admin_menu', array( 'GDG_Admin', 'register_menu' ) );
		add_action( 'admin_init', array( 'GDG_Admin', 'handle_actions' ) );
		add_action( 'template_redirect', array( 'GDG_Admin', 'handle_actions' ), 1 );
		add_filter( 'post_row_actions', array( 'GDG_Reservation_Bridge', 'add_booking_row_action' ), 10, 2 );
		add_action( 'template_redirect', array( 'GDG_App', 'disable_cache_on_app_pages' ) );
		add_filter( 'show_admin_bar', array( 'GDG_App', 'hide_admin_bar_on_app_pages' ) );
		add_filter( 'template_include', array( 'GDG_App', 'use_standalone_template' ), 999 );
		add_filter( 'body_class', array( 'GDG_App', 'add_standalone_body_class' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'gelsendiele-gastro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public function init(): void {
		GDG_App::register_shortcode();
	}
}

GDG_Plugin::instance();

/**
 * Öffnet oder lädt eine Bestellung zu einer Reservierung.
 * Kann vom vorhandenen Reservierungsdashboard direkt verwendet werden.
 *
 * @return int|WP_Error Bestell-ID oder Fehler.
 */
function gelsendiele_gastro_open_order_from_reservation( int $reservation_id, int $table_id, string $guest_name = '' ) {
	return GDG_DB::open_order( $table_id, $reservation_id, $guest_name );
}
