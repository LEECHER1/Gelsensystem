<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$manifest_url = add_query_arg( 'gd-pwa-manifest', '1', home_url( '/' ) );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="theme-color" id="gd-theme-color" content="#08110b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Gelsensystem">
    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
    <script>(function(){try{var t=localStorage.getItem('gd-dashboard-theme');if(t!=='dark'&&t!=='light'){t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.dataset.gdTheme=t;}catch(e){}}());</script>
    <link rel="manifest" href="<?php echo esc_url( $manifest_url ); ?>">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo esc_url( add_query_arg( 'ver', Gelsendiele_Reservierungsdashboard::VERSION, plugins_url( '../assets/gelsendiele-app-icon-192.png', __FILE__ ) ) ); ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url( add_query_arg( 'ver', Gelsendiele_Reservierungsdashboard::VERSION, plugins_url( '../assets/gelsendiele-app-icon-192.png', __FILE__ ) ) ); ?>">
    <title><?php echo esc_html( 'Gelsensystem – ' . get_bloginfo( 'name' ) ); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="gd-app-root" class="gd-app-root">
    <?php echo do_shortcode( '[gelsendiele_reservierungen]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php wp_footer(); ?>
<?php
$gd_app_section = isset( $_GET['gd-section'] ) ? sanitize_key( wp_unslash( $_GET['gd-section'] ) ) : 'reservations';
if ( in_array( $gd_app_section, array( 'settings', 'users' ), true ) ) :
	?><script src="<?php echo esc_url( GELSENDIELE_URL . 'admin/assets/settings.js?ver=' . GELSENDIELE_VERSION ); ?>"></script>
	<script src="<?php echo esc_url( GELSENDIELE_URL . 'assets/dashboard.js?ver=' . GELSENDIELE_VERSION ); ?>"></script><?php
endif;
?>
</body>
</html>
