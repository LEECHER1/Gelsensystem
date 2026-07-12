<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDG_REST {
	private const NS = 'gelsendiele-gastro/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NS,
			'/bootstrap',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'bootstrap' ),
				'permission_callback' => array( __CLASS__, 'can_use_any_app' ),
			)
		);

		register_rest_route(
			self::NS,
			'/orders',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( __CLASS__, 'orders' ),
					'permission_callback' => array( __CLASS__, 'can_use_any_app' ),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( __CLASS__, 'create_order' ),
					'permission_callback' => array( __CLASS__, 'can_service' ),
					'args' => array(
						'table_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
						'reservation_id' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
						'guest_name' => array( 'type' => 'string', 'default' => '' ),
						'guest_count' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/orders/(?P<id>\d+)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_order' ),
				'permission_callback' => array( __CLASS__, 'can_use_any_app' ),
				'args' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/orders/(?P<id>\d+)/items',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'add_item' ),
				'permission_callback' => array( __CLASS__, 'can_service' ),
				'args' => array(
					'id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'menu_item_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
					'quantity' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 99, 'default' => 1 ),
					'note' => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/items/(?P<id>\d+)',
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( __CLASS__, 'update_item' ),
				'permission_callback' => array( __CLASS__, 'can_update_item' ),
				'args' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/queue/(?P<station>kitchen|bar)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'queue' ),
				'permission_callback' => array( __CLASS__, 'can_queue' ),
			)
		);

		register_rest_route(
			self::NS,
			'/orders/(?P<id>\d+)/checkout',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'checkout' ),
				'permission_callback' => array( __CLASS__, 'can_checkout' ),
				'args' => array(
					'id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'items' => array( 'required' => true, 'type' => 'array' ),
					'method' => array( 'type' => 'string', 'enum' => array( 'cash', 'card', 'other' ), 'default' => 'cash' ),
					'terminal_reference' => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
	}

	public static function can_use_any_app(): bool {
		return is_user_logged_in() && (
			current_user_can( 'gdg_use_service' ) ||
			current_user_can( 'gdg_use_kitchen' ) ||
			current_user_can( 'gdg_use_bar' ) ||
			current_user_can( 'gdg_use_checkout' ) ||
			current_user_can( 'gdg_manage' )
		);
	}

	public static function can_service(): bool {
		return is_user_logged_in() && ( current_user_can( 'gdg_use_service' ) || current_user_can( 'gdg_manage' ) );
	}

	public static function can_checkout(): bool {
		return is_user_logged_in() && ( current_user_can( 'gdg_use_checkout' ) || current_user_can( 'gdg_manage' ) );
	}

	public static function can_update_item(): bool {
		return self::can_use_any_app();
	}

	public static function can_queue( WP_REST_Request $request ): bool {
		$station = (string) $request['station'];
		if ( 'bar' === $station ) {
			return is_user_logged_in() && ( current_user_can( 'gdg_use_bar' ) || current_user_can( 'gdg_use_service' ) || current_user_can( 'gdg_manage' ) );
		}
		return is_user_logged_in() && ( current_user_can( 'gdg_use_kitchen' ) || current_user_can( 'gdg_use_service' ) || current_user_can( 'gdg_manage' ) );
	}

	public static function bootstrap( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			array(
				'tables' => GDG_DB::get_tables(),
				'categories' => GDG_DB::get_categories(),
				'menu_items' => GDG_DB::get_menu_items(),
				'orders' => GDG_DB::get_open_orders(),
				'pages' => GDG_App::get_app_urls(),
				'currency' => 'EUR',
				'terminal_mode' => get_option( 'gdg_terminal_mode', 'manual' ),
			)
		);
	}

	public static function orders( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( GDG_DB::get_open_orders() );
	}

	public static function get_order( WP_REST_Request $request ) {
		$order = GDG_DB::get_order( (int) $request['id'] );
		if ( ! $order ) {
			return new WP_Error( 'gdg_order_not_found', 'Bestellung nicht gefunden.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $order );
	}

	public static function create_order( WP_REST_Request $request ) {
		$order_id = GDG_DB::open_order(
			(int) $request->get_param( 'table_id' ),
			(int) $request->get_param( 'reservation_id' ),
			sanitize_text_field( (string) $request->get_param( 'guest_name' ) ),
			(int) $request->get_param( 'guest_count' )
		);
		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}
		return rest_ensure_response( GDG_DB::get_order( (int) $order_id ) );
	}

	public static function add_item( WP_REST_Request $request ) {
		$item_id = GDG_DB::add_order_item(
			(int) $request['id'],
			(int) $request->get_param( 'menu_item_id' ),
			(int) $request->get_param( 'quantity' ),
			sanitize_textarea_field( (string) $request->get_param( 'note' ) )
		);
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		return rest_ensure_response( GDG_DB::get_order( (int) $request['id'] ) );
	}

	public static function update_item( WP_REST_Request $request ) {
		$changes = array();
		foreach ( array( 'quantity', 'note', 'status' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$changes[ $field ] = $request->get_param( $field );
			}
		}
		$result = GDG_DB::update_item( (int) $request['id'], $changes );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function queue( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( GDG_DB::get_queue( (string) $request['station'] ) );
	}

	public static function checkout( WP_REST_Request $request ) {
		$result = GDG_DB::checkout(
			(int) $request['id'],
			(array) $request->get_param( 'items' ),
			sanitize_key( (string) $request->get_param( 'method' ) ),
			sanitize_text_field( (string) $request->get_param( 'terminal_reference' ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}
}
