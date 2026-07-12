<?php

define( 'ABSPATH', __DIR__ . '/' );

function wp_timezone() {
	return new DateTimeZone( 'Europe/Vienna' );
}

function absint( $value ) {
	return abs( (int) $value );
}

final class Gelsendiele_Settings {
	public static $rules = array();
	public static function get( $section, $key = null, $default = null ) {
		return 'availability' === $section && 'rules' === $key ? self::$rules : $default;
	}
}

require dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/includes/class-gelsendiele-availability.php';

function gelsensystem_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Verfügbarkeitstest fehlgeschlagen: {$message}\n" );
		exit( 1 );
	}
}

function gelsensystem_rule( $type, $start_date, $end_date, $overrides = array() ) {
	return array_merge(
		array(
			'id' => uniqid( 'test-', true ), 'enabled' => 1, 'type' => $type,
			'start_date' => $start_date, 'end_date' => $end_date,
			'start_time' => '', 'end_time' => '', 'max_bookings' => 0, 'max_people' => 0,
			'areas' => array(), 'comment' => '', 'public_message' => '',
		),
		$overrides
	);
}

$date = '2026-07-20';
$regular = array( array( 'start' => '10:00', 'end' => '18:00' ) );

Gelsendiele_Settings::$rules = array( gelsensystem_rule( 'closed', $date, $date ) );
gelsensystem_expect( array() === Gelsendiele_Availability::apply_to_ranges( $date, $regular ), 'ganztägige Schließung' );

Gelsendiele_Settings::$rules = array(
	gelsensystem_rule( 'special_open', $date, $date, array( 'start_time' => '11:00', 'end_time' => '14:00' ) ),
);
gelsensystem_expect(
	array( array( 'start' => '11:00', 'end' => '14:00' ) ) === Gelsendiele_Availability::apply_to_ranges( $date, $regular ),
	'Sonderöffnung ersetzt Wochenplan'
);

Gelsendiele_Settings::$rules = array(
	gelsensystem_rule( 'blocked_time', $date, $date, array( 'start_time' => '12:00', 'end_time' => '14:00' ) ),
);
gelsensystem_expect(
	array( array( 'start' => '10:00', 'end' => '12:00' ), array( 'start' => '14:00', 'end' => '18:00' ) ) === Gelsendiele_Availability::apply_to_ranges( $date, $regular ),
	'zeitliche Sperre teilt Öffnungsblock'
);

Gelsendiele_Settings::$rules = array(
	gelsensystem_rule( 'capacity', $date, $date, array( 'max_bookings' => 2, 'max_people' => 18, 'public_message' => 'Heute eingeschränkter Betrieb.' ) ),
);
$limits = Gelsendiele_Availability::capacity_limits( new DateTimeImmutable( $date . ' 12:00', wp_timezone() ) );
gelsensystem_expect( 2 === $limits['max_bookings'] && 18 === $limits['max_people'], 'reduzierte Tageskapazität' );
gelsensystem_expect( 'Heute eingeschränkter Betrieb.' === Gelsendiele_Availability::public_notice( $date ), 'öffentlicher Hinweis' );

$previous = '2026-07-19';
Gelsendiele_Settings::$rules = array(
	gelsensystem_rule( 'special_open', $previous, $previous, array( 'start_time' => '22:00', 'end_time' => '02:00' ) ),
);
gelsensystem_expect(
	array( array( 'start' => '00:00', 'end' => '02:00' ) ) === Gelsendiele_Availability::apply_to_ranges( $date, array() ),
	'Sonderöffnung über Mitternacht'
);

echo "Verfügbarkeitstests erfolgreich.\n";
