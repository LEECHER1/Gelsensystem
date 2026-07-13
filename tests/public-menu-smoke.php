<?php

$root = dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/modules/gastro/';
$app  = file_get_contents( $root . 'includes/class-gdg-app.php' );
$css  = file_get_contents( $root . 'assets/public-menu.css' );

function public_menu_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Öffentliche-Speisekarten-Test fehlgeschlagen: {$message}\n" );
		exit( 1 );
	}
}

public_menu_expect( false !== strpos( $app, "add_shortcode( 'gelsensystem_speisekarte'" ), 'Enfold-Shortcode fehlt' );
public_menu_expect( false !== strpos( $app, 'GDG_DB::get_categories( true )' ), 'aktive Kategorien werden nicht geladen' );
public_menu_expect( false !== strpos( $app, 'GDG_DB::get_menu_items( true )' ), 'aktive Gerichte werden nicht geladen' );
public_menu_expect( false !== strpos( $app, "wp_enqueue_style( 'gdg-public-menu'" ), 'Speisekarten-Stile werden nicht bedarfsgerecht geladen' );
public_menu_expect( false !== strpos( $app, 'number_format_i18n' ), 'Preise werden nicht lokalisiert' );
public_menu_expect( false !== strpos( $app, "'columns'      => '1'" ), 'Einspaltige Standardausgabe fehlt' );
public_menu_expect( false !== strpos( $css, '.gdg-public-menu__categories' ), 'responsives Kategorien-Layout fehlt' );
public_menu_expect( false !== strpos( $css, '.gdg-public-menu__categories { display: grid; grid-template-columns: 1fr;' ), 'Kategorien stehen auf der Webseite nicht untereinander' );
public_menu_expect( false !== strpos( $css, '@media (max-width: 860px)' ), 'Tablet-Layout fehlt' );
public_menu_expect( false !== strpos( $css, '@media (max-width: 520px)' ), 'Smartphone-Layout fehlt' );

echo "Öffentliche Speisekarten-Tests erfolgreich.\n";
