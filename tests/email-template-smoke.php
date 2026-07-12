<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

function add_action() {}
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function get_option( $key, $default = '' ) { return $default; }
function esc_url( $value ) { return filter_var( $value, FILTER_SANITIZE_URL ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wpautop( $value ) { return '<p>' . $value . '</p>'; }
function do_action() {}
function wp_mail( $recipient, $subject, $body, $headers = array() ) {
	$GLOBALS['gelsensystem_mail'] = compact( 'recipient', 'subject', 'body', 'headers' );
	return true;
}

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

final class Gelsendiele_Settings {
	public static function get( $section, $key = null, $default = null ) {
		if ( 'general' === $section ) {
			$values = array( 'business_name' => 'Testgasthaus', 'sender_name' => 'Testgasthaus', 'sender_email' => 'office@example.at' );
			return isset( $values[ $key ] ) ? $values[ $key ] : $default;
		}
		if ( 'emails' === $section && 'templates' === $key ) {
			return array(
				'guest_received' => array(
					'enabled' => 1, 'recipient' => 'guest', 'custom_recipient' => '', 'format' => 'text',
					'subject' => 'Hallo {guest_name} bei {business_name}',
					'body' => 'Termin {date} um {time} für {party} Personen.',
				),
			);
		}
		return $default;
	}
}

require dirname( __DIR__ ) . '/gelsendiele-reservierungsdashboard/includes/class-gelsensystem-email.php';

$sent = Gelsensystem_Email::send_test( 'guest_received', 'gast@example.at' );
if ( true !== $sent ) {
	fwrite( STDERR, "E-Mail-Test konnte nicht versendet werden.\n" );
	exit( 1 );
}
$mail = $GLOBALS['gelsensystem_mail'];
if ( 'gast@example.at' !== $mail['recipient'] || false !== strpos( $mail['subject'], '{' ) || false !== strpos( $mail['body'], '{' ) || false === strpos( $mail['subject'], 'Testgasthaus' ) ) {
	fwrite( STDERR, "E-Mail-Platzhalter wurden nicht korrekt ersetzt.\n" );
	exit( 1 );
}

echo "E-Mail-Vorlagentest erfolgreich.\n";
