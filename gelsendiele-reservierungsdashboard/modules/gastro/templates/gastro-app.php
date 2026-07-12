<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$view = GDG_App::current_view();
if ( '' === $view ) {
	return;
}

$labels = array(
	'service'  => 'Service',
	'kitchen'  => 'Küche',
	'bar'      => 'Schank',
	'checkout' => 'Kasse',
);
$business_name = Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' );
$theme_mode   = Gelsendiele_Settings::get( 'branding', 'theme_mode', 'auto' );
if ( ! in_array( $theme_mode, array( 'auto', 'light', 'dark' ), true ) ) {
	$theme_mode = 'auto';
}
$dark_surface = Gelsendiele_Settings::get( 'branding', 'dark_surface_color', '#08110b' );
$brand_style  = Gelsendiele_Settings::css_variables();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
	<meta name="theme-color" id="gdg-theme-color" content="<?php echo esc_attr( $dark_surface ); ?>">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
	<title><?php echo esc_html( $labels[ $view ] . ' – ' . $business_name ); ?></title>
	<script>
	(function () {
		var mode = <?php echo wp_json_encode( $theme_mode ); ?>;
		try {
			var saved = window.localStorage.getItem('gd-dashboard-theme') || window.localStorage.getItem('gdg-theme');
			if (saved === 'light' || saved === 'dark') mode = saved;
		} catch (error) {}
		if (mode !== 'light' && mode !== 'dark') {
			mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
		}
		document.documentElement.setAttribute('data-gdg-theme', mode);
	}());
	</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> style="<?php echo esc_attr( $brand_style ); ?>">
<?php wp_body_open(); ?>
<main id="gdg-app-root" class="gdg-app-root">
	<?php echo do_shortcode( '[gelsendiele_gastro view="' . esc_attr( $view ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
