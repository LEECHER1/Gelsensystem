<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDG_Reservation_Bridge {
	/**
	 * Ergänzt im WordPress-Backend bei Five-Star-Reservierungen einen Link zum Service.
	 * Die genaue Frontend-Integration in das vorhandene Gelsendiele-Dashboard erfolgt,
	 * sobald dessen Plugin-Dateien vorliegen.
	 */
	public static function add_booking_row_action( array $actions, WP_Post $post ): array {
		if ( 'rtb-booking' !== $post->post_type || ! current_user_can( 'gdg_use_service' ) ) {
			return $actions;
		}
		$service_page = (int) get_option( 'gdg_page_service', 0 );
		if ( ! $service_page ) {
			return $actions;
		}
		$guest = self::extract_guest_name( $post );
		$url = add_query_arg(
			array(
				'reservation_id' => $post->ID,
				'guest_name' => $guest,
			),
			get_permalink( $service_page )
		);
		$actions['gdg_open_order'] = '<a href="' . esc_url( $url ) . '">Bestellung öffnen</a>';
		return $actions;
	}

	private static function extract_guest_name( WP_Post $post ): string {
		$possible = array( 'name', '_name', 'customer_name', '_customer_name', 'rtb_name', '_rtb_name' );
		foreach ( $possible as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return sanitize_text_field( $value );
			}
		}
		return 'Reservierung #' . $post->ID;
	}
}
