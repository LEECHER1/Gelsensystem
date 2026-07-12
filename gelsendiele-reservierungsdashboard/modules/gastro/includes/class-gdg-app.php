<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDG_App {
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
		if ( ! in_array( $view, array( 'service', 'kitchen', 'bar', 'checkout' ), true ) ) {
			$view = 'service';
		}

		if ( ! is_user_logged_in() ) {
			ob_start();
			echo '<div class="gdg-login-wrap"><h2>Anmeldung erforderlich</h2><p>Dieser Bereich ist nur für Mitarbeiter der Gelsendiele zugänglich.</p>';
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
					<div><strong><?php echo esc_html( $business_name ); ?></strong><span><?php echo esc_html( $labels[ $view ] ); ?></span></div>
				</div>
				<nav class="gdg-nav" aria-label="Arbeitsbereiche">
					<?php foreach ( $labels as $nav_view => $label ) : ?>
						<?php if ( self::can_view( $nav_view ) && ! empty( $urls[ $nav_view ] ) ) : ?>
							<a class="<?php echo $nav_view === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( $urls[ $nav_view ] ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
				<div class="gdg-top-actions">
					<span class="gdg-connection" title="Verbindungsstatus"><i></i><span>Online</span></span>
					<button type="button" class="gdg-icon-button" data-gdg-theme-toggle aria-label="Darstellung wechseln">◐</button>
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
		foreach ( array( 'service', 'kitchen', 'bar', 'checkout' ) as $view ) {
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
		if ( is_admin() || ! is_singular( 'page' ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id || ! get_post_meta( $post_id, '_gdg_view', true ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
}
