<?php

$root       = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/';
$dashboard  = file_get_contents( $root . 'gelsendiele-reservierungsdashboard.php' );
$dashboard_css = file_get_contents( $root . 'assets/dashboard.css' );
$dashboard_js  = file_get_contents( $root . 'assets/dashboard.js' );
$gastro     = file_get_contents( $root . 'modules/gastro/includes/class-gdg-app.php' );
$gastro_css = file_get_contents( $root . 'modules/gastro/assets/app.css' );
$gastro_js  = file_get_contents( $root . 'modules/gastro/assets/app.js' );

function nav_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Navigationstest fehlgeschlagen: {$message}\n" );
		exit( 1 );
	}
}

nav_expect( false !== strpos( $dashboard, 'data-sidebar-connection' ) && false !== strpos( $dashboard, '>Online</span>' ), 'Verbindungsstatus fehlt in der zentralen Navigation' );
nav_expect( false !== strpos( $dashboard, 'gelsensystem-sidebar-theme' ) && false !== strpos( $dashboard, "wp_logout_url( \$dashboard )" ), 'Theme- oder Abmeldebereich fehlt in der zentralen Navigation' );
nav_expect( false !== strpos( $gastro, 'gdg-connection' ) && false !== strpos( $gastro, 'gdg-theme-button' ) && false !== strpos( $gastro, "wp_logout_url( \$dashboard_url )" ), 'Theme-, Online- oder Abmeldebereich fehlt in der Gastro-Navigation' );
nav_expect( false !== strpos( $gastro, "esc_html( \$business_name )" ) && false === strpos( $gastro, "\$business_name . ' · ' . \$labels[ \$view ]" ), 'Gastro-Unterzeile unterscheidet sich von der zentralen Navigation' );
nav_expect( false !== strpos( $dashboard_css, '.gelsensystem-sidebar nav { display:grid;flex:1 1 auto;min-height:0' ) && false !== strpos( $dashboard_css, 'overflow-y:auto' ), 'zentrale Navigation bleibt bei kleinen Desktop-Höhen nicht bedienbar' );
nav_expect( false !== strpos( $gastro_css, '.gdg-nav { display: grid; flex: 1 1 auto; min-height: 0' ) && false !== strpos( $gastro_css, 'overflow-y: auto' ), 'Gastro-Navigation bleibt bei kleinen Desktop-Höhen nicht bedienbar' );
nav_expect( false !== strpos( $dashboard_js, "window.addEventListener('online', updateSidebarConnection)" ) && false !== strpos( $dashboard_js, "window.addEventListener('offline', updateSidebarConnection)" ), 'zentraler Online-Status reagiert nicht auf Verbindungswechsel' );
nav_expect( false !== strpos( $dashboard_js, "window.localStorage.setItem('gd-dashboard-theme'" ) && false !== strpos( $gastro_js, "window.localStorage.setItem('gd-dashboard-theme'" ), 'Darkmode wird nicht bereichsübergreifend gespeichert' );
nav_expect( false !== strpos( $dashboard, 'class="gd-login-submit"' ) && false !== strpos( $gastro, 'wp_login_form(' ), 'Anmeldung fehlt in einem geschützten Bereich' );

echo "Navigationstests erfolgreich.\n";
