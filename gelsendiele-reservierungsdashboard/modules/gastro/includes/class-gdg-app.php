<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDG_App {
	private const VIEWS = array( 'service', 'kitchen', 'bar', 'checkout' );

	public static function register_shortcode(): void {
		add_shortcode( 'gelsendiele_gastro', array( __CLASS__, 'shortcode' ) );
	}

	public static function shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'view' => 'service',
			),
			$atts,
			'gelsendiele_gastro'
		);
		$view = sanitize_key( $atts['view'] );
		if ( ! in_array( $view, self::VIEWS, true ) ) {
			$view = 'service';
		}

		if ( ! is_user_logged_in() ) {
			ob_start();
			echo '<div class="gdg-login-wrap"><h2>Anmeldung erforderlich</h2><p>Dieser Bereich ist nur für berechtigte Gelsensystem-Benutzer zugänglich.</p>';
			wp_login_form( array( 'redirect' => get_permalink() ) );
			echo '</div>';
			return (string) ob_get_clean();
		}

		if ( ! self::can_view( $view ) ) {
			return '<div class="gdg-access-denied"><strong>Keine Berechtigung:</strong> Dein Benutzerkonto darf diesen Arbeitsbereich nicht öffnen.</div>';
		}

		self::enqueue_assets( $view );
		$labels = array(
			'service' => 'Service',
			'kitchen' => 'Küche',
			'bar' => 'Schank',
			'checkout' => 'Kasse',
		);
		$urls = self::get_app_urls();
		$dashboard_page_id = (int) get_option( 'gd_reservierungsdashboard_page_id', 0 );
		$dashboard_url     = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/reservierungsverwaltung/' );
		$business_name = Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' );
		$branding      = Gelsendiele_Settings::get( 'branding', null, array() );
		$logo_url      = $branding['logo_url'];
		if ( ! $logo_url && ! empty( $branding['logo_attachment_id'] ) ) {
			$logo_url = wp_get_attachment_image_url( absint( $branding['logo_attachment_id'] ), 'thumbnail' );
		}
		$brand_style = Gelsendiele_Settings::css_variables();

		ob_start();
		?>
		<div class="gdg-app" data-view="<?php echo esc_attr( $view ); ?>" style="<?php echo esc_attr( $brand_style ); ?>">
			<header class="gdg-topbar">
				<div class="gdg-brand">
					<span class="gdg-brand-mark"><?php if ( $logo_url ) : ?><img src="<?php echo esc_url( $logo_url ); ?>" alt=""><?php else : ?>G<?php endif; ?></span>
					<div><strong>Gelsensystem</strong><span><?php echo esc_html( $business_name . ' · ' . $labels[ $view ] ); ?></span></div>
				</div>
				<nav class="gdg-nav" aria-label="Arbeitsbereiche">
					<?php if ( current_user_can( 'manage_bookings' ) ) : ?><a href="<?php echo esc_url( add_query_arg( 'gd-section', 'reservations', $dashboard_url ) ); ?>">Reservierungen</a><?php endif; ?>
					<?php foreach ( $labels as $nav_view => $label ) : ?>
						<?php if ( self::can_view( $nav_view ) && ! empty( $urls[ $nav_view ] ) ) : ?>
							<a class="<?php echo $nav_view === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( $urls[ $nav_view ] ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
					<?php if ( current_user_can( 'gelsendiele_manage_settings' ) ) : ?><a href="<?php echo esc_url( add_query_arg( 'gd-section', 'settings', $dashboard_url ) ); ?>">Einstellungen</a><?php endif; ?>
				</nav>
				<div class="gdg-top-actions">
					<span class="gdg-connection" title="Verbindungsstatus"><i></i><span>Online</span></span>
					<button type="button" class="gdg-icon-button gdg-theme-button" data-gdg-theme-toggle aria-label="Darstellung wechseln" aria-pressed="false" title="Hell-/Dunkelmodus">
						<svg class="gdg-theme-icon gdg-theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8z"/></svg>
						<svg class="gdg-theme-icon gdg-theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
					</button>
				</div>
			</header>
			<main class="gdg-main">
				<div class="gdg-loading"><span class="gdg-spinner"></span><p>Daten werden geladen …</p></div>
				<div class="gdg-screen" hidden></div>
			</main>
			<div class="gdg-toast-region" aria-live="polite" aria-atomic="true"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function enqueue_assets( string $view ): void {
		wp_enqueue_style( 'gdg-app', GDG_URL . 'assets/app.css', array(), GDG_VERSION );
		wp_enqueue_script( 'gdg-app', GDG_URL . 'assets/app.js', array(), GDG_VERSION, true );

		$config = array(
			'restUrl' => esc_url_raw( rest_url( 'gelsendiele-gastro/v1/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'view' => $view,
			'pollInterval' => max( 3, min( 30, (int) get_option( 'gdg_poll_interval', 5 ) ) ) * 1000,
			'themeMode' => Gelsendiele_Settings::get( 'branding', 'theme_mode', 'auto' ),
			'themeColors' => array(
				'light' => '#f3f5f7',
				'dark'  => Gelsendiele_Settings::get( 'branding', 'dark_surface_color', '#08110b' ),
			),
			'locale' => get_locale(),
			'prefill' => array(
				'reservationId' => isset( $_GET['reservation_id'] ) ? absint( $_GET['reservation_id'] ) : 0,
				'tableId' => isset( $_GET['table_id'] ) ? absint( $_GET['table_id'] ) : 0,
				'guestName' => isset( $_GET['guest_name'] ) ? sanitize_text_field( wp_unslash( $_GET['guest_name'] ) ) : '',
				'guestCount' => isset( $_GET['guest_count'] ) ? absint( $_GET['guest_count'] ) : 0,
			),
			'labels' => array(
				'error' => 'Es ist ein Fehler aufgetreten.',
				'confirmCancel' => 'Position wirklich stornieren?',
				'cardManual' => 'Betrag bitte am Kartenterminal kassieren und danach bestätigen.',
			),
		);
		wp_add_inline_script( 'gdg-app', 'window.GDG_CONFIG = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	public static function get_app_urls(): array {
		$urls = array();
		foreach ( self::VIEWS as $view ) {
			$page_id = (int) get_option( 'gdg_page_' . $view, 0 );
			$urls[ $view ] = $page_id ? get_permalink( $page_id ) : '';
		}
		return $urls;
	}

	public static function can_view( string $view ): bool {
		if ( current_user_can( 'gdg_manage' ) ) {
			return true;
		}
		switch ( $view ) {
			case 'kitchen':
				return current_user_can( 'gdg_use_kitchen' ) || current_user_can( 'gdg_use_service' );
			case 'bar':
				return current_user_can( 'gdg_use_bar' ) || current_user_can( 'gdg_use_service' );
			case 'checkout':
				return current_user_can( 'gdg_use_checkout' );
			case 'service':
			default:
				return current_user_can( 'gdg_use_service' );
		}
	}

	public static function disable_cache_on_app_pages(): void {
		if ( ! self::is_app_page() ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}

	/** Erkennt ausschließlich die automatisch angelegten Gastro-Arbeitsseiten. */
	public static function is_app_page(): bool {
		if ( is_admin() || ! is_singular( 'page' ) ) {
			return false;
		}
		return '' !== self::current_view();
	}

	/** Liefert die validierte Ansicht der aktuell abgefragten Arbeitsseite. */
	public static function current_view(): string {
		$post_id = get_queried_object_id();
		$view    = $post_id ? sanitize_key( (string) get_post_meta( $post_id, '_gdg_view', true ) ) : '';
		return in_array( $view, self::VIEWS, true ) ? $view : '';
	}

	public static function hide_admin_bar_on_app_pages( $show ) {
		return self::is_app_page() ? false : $show;
	}

	/**
	 * Entfernt Theme-Header, Theme-Footer und deren Breitenbegrenzungen. Die
	 * Arbeitsbereiche werden dadurch wie das Reservierungsdashboard als eigene
	 * App ausgeliefert.
	 */
	public static function use_standalone_template( $template ) {
		$view = self::current_view();
		if ( '' === $view ) {
			return $template;
		}

		// template_include läuft vor wp_head(); die Assets sind dadurch bereits
		// für den Kopfbereich registriert und erscheinen ohne ungestylten Aufbau.
		self::enqueue_assets( $view );
		$app_template = GDG_DIR . 'templates/gastro-app.php';
		return file_exists( $app_template ) ? $app_template : $template;
	}

	public static function add_standalone_body_class( array $classes ): array {
		$view = self::current_view();
		if ( '' !== $view ) {
			$classes[] = 'gdg-standalone-app';
			$classes[] = 'gdg-view-' . $view;
		}
		return $classes;
	}
}
