<?php

$root = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/';
$dashboard_css = file_get_contents( $root . 'assets/dashboard.css' );
$gastro_css = file_get_contents( $root . 'modules/gastro/assets/app.css' );
$events_css = file_get_contents( $root . 'assets/public-events.css' );
$events_php = file_get_contents( $root . 'includes/class-gelsensystem-events.php' );
$form_css = file_get_contents( $root . 'assets/reservation-form.css' );
$form_php = file_get_contents( $root . 'includes/class-gd-reservation-engine.php' );

function branding_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

branding_expect( false !== strpos( $events_php, 'class="gse-public-events" style="<?php echo esc_attr( Gelsendiele_Settings::css_variables() ); ?>"' ), 'Events erhalten die Branding-Variablen nicht.' );
branding_expect( false !== strpos( $events_css, '--gse-green:var(--gelsendiele-primary' ), 'Der globale Events-Akzent folgt nicht der Primärfarbe.' );
branding_expect( false !== strpos( $events_css, '--gse-secondary:var(--gelsendiele-secondary' ), 'Die Events-Sekundärfarbe fehlt.' );
branding_expect( false !== strpos( $events_css, 'background:var(--gse-surface)' ), 'Die helle Events-Fläche folgt nicht der Branding-Fläche.' );

branding_expect( false !== strpos( $dashboard_css, 'background:linear-gradient(135deg,var(--gd-green),var(--gd-green-dark))' ), 'Die zentrale aktive Navigation verwendet die Branding-Farben nicht.' );
branding_expect( false === strpos( $dashboard_css, 'background:linear-gradient(135deg,#315b2d,#234b24)' ), 'Die alte grüne Zentralnavigation ist weiterhin fest codiert.' );
branding_expect( false === strpos( $dashboard_css, 'background:#315b2d!important' ), 'Der Darkmode überschreibt die mobile Primärfarbe weiterhin.' );

branding_expect( false !== strpos( $gastro_css, 'background: linear-gradient(135deg,var(--gd-primary),var(--gd-primary-2))' ), 'Die Gastro-Seitenleiste folgt Primär- und Sekundärfarbe nicht.' );
branding_expect( false === strpos( $gastro_css, 'rgba(8,124,255' ) && false === strpos( $gastro_css, 'rgba(8, 124, 255' ), 'Gastro-Fokusringe enthalten weiterhin eine fest codierte Markenfarbe.' );

branding_expect( false !== strpos( $form_css, '--gd-secondary: var(--gelsendiele-secondary' ), 'Das Reservierungsformular erhält die Sekundärfarbe nicht.' );
branding_expect( false !== strpos( $form_php, 'Gelsendiele_Settings::css_variables()' ), 'Das Reservierungsformular erhält nicht alle Branding-Variablen.' );

echo "Branding-Konsistenz geprüft.\n";
