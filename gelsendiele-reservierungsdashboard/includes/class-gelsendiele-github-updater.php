<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies WordPress with trusted release metadata from the public GitHub repository.
 */
final class Gelsendiele_GitHub_Updater {
	const SLUG       = 'gelsendiele-reservierungsdashboard';
	const REPOSITORY = 'LEECHER1/Gelsendiele';
	const API_URL    = 'https://api.github.com/repos/LEECHER1/Gelsendiele/releases/latest';
	const CACHE_KEY  = 'gelsendiele_github_release';

	/**
	 * Register native WordPress update hooks.
	 */
	public static function bootstrap() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'enable_auto_update' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 10, 2 );
	}

	/**
	 * Add a newer GitHub release to WordPress' regular plugin update response.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::latest_release();
		if ( ! $release || ! version_compare( $release['version'], GELSENDIELE_VERSION, '>' ) ) {
			return $transient;
		}

		$plugin = plugin_basename( GELSENDIELE_FILE );
		$update = (object) array(
			'id'           => 'github.com/' . self::REPOSITORY,
			'slug'         => self::SLUG,
			'plugin'       => $plugin,
			'new_version'  => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $release['package'],
			'tested'       => get_bloginfo( 'version' ),
			'requires_php' => '7.4',
		);

		$transient->response[ $plugin ] = $update;
		return $transient;
	}

	/**
	 * Populate the WordPress plugin-information dialog for GitHub updates.
	 *
	 * @param mixed  $result Existing result.
	 * @param string $action API action.
	 * @param object $args   API arguments.
	 * @return mixed
	 */
	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Gelsensystem',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/LEECHER1">Andreas Schwarz / Gelsendiele</a>',
			'homepage'      => $release['html_url'],
			'download_link' => $release['package'],
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'sections'      => array(
				'description' => 'Eigenständiges Reservierungs-, Service-, Küchen- und Kassensystem für die Gelsendiele.',
				'changelog'   => nl2br( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * Always allow background updates for this plugin only.
	 *
	 * @param bool|null $update Whether to update.
	 * @param object    $item   Update item.
	 * @return bool|null
	 */
	public static function enable_auto_update( $update, $item ) {
		$plugin = isset( $item->plugin ) ? (string) $item->plugin : '';
		$slug   = isset( $item->slug ) ? (string) $item->slug : '';

		if ( plugin_basename( GELSENDIELE_FILE ) === $plugin || self::SLUG === $slug ) {
			return true;
		}

		return $update;
	}

	/**
	 * Clear cached release information after this plugin is upgraded.
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $options  Upgrade details.
	 */
	public static function clear_cache_after_upgrade( $upgrader, $options ) {
		unset( $upgrader );
		if ( 'update' !== ( $options['action'] ?? '' ) || 'plugin' !== ( $options['type'] ?? '' ) ) {
			return;
		}

		$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array();
		if ( in_array( plugin_basename( GELSENDIELE_FILE ), $plugins, true ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Fetch and validate the latest public GitHub release.
	 *
	 * @return array|false
	 */
	private static function latest_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return ! empty( $cached['unavailable'] ) ? false : $cached;
		}

		$response = wp_remote_get(
			self::API_URL,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Gelsensystem/' . GELSENDIELE_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::CACHE_KEY, array( 'unavailable' => true ), 10 * MINUTE_IN_SECONDS );
			return false;
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = isset( $data['tag_name'] ) ? ltrim( sanitize_text_field( $data['tag_name'] ), 'vV' ) : '';
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			set_site_transient( self::CACHE_KEY, array( 'unavailable' => true ), 10 * MINUTE_IN_SECONDS );
			return false;
		}

		$expected_name = 'gelsensystem-v' . $version . '.zip';
		$package       = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( $expected_name === ( $asset['name'] ?? '' ) ) {
				$package = esc_url_raw( $asset['browser_download_url'] ?? '' );
				break;
			}
		}

		$package_host = wp_parse_url( $package, PHP_URL_HOST );
		$package_path = wp_parse_url( $package, PHP_URL_PATH );
		$trusted_path = '/LEECHER1/Gelsendiele/releases/download/';
		if ( 'github.com' !== $package_host || 0 !== strpos( (string) $package_path, $trusted_path ) ) {
			set_site_transient( self::CACHE_KEY, array( 'unavailable' => true ), 10 * MINUTE_IN_SECONDS );
			return false;
		}

		$release = array(
			'version'  => $version,
			'package'  => $package,
			'html_url' => esc_url_raw( $data['html_url'] ?? 'https://github.com/' . self::REPOSITORY . '/releases' ),
			'notes'    => sanitize_textarea_field( $data['body'] ?? '' ),
		);
		set_site_transient( self::CACHE_KEY, $release, 30 * MINUTE_IN_SECONDS );

		return $release;
	}
}
