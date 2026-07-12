<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDG_DB {
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'gdg_' . $name;
	}

	public static function activate( $seed_demo_data = false ): void {
		self::create_tables();
		self::create_roles();
		if ( $seed_demo_data ) {
			self::seed_defaults();
		}
		self::create_app_pages();
		update_option( 'gdg_db_version', GDG_VERSION );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/** Übernimmt das bisherige nummerische Tischmodell ohne Reservierungen zu verändern. */
	public static function migrate_legacy_tables(): void {
		global $wpdb;
		if ( 0 < (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( 'tables' ) ) ) {
			return;
		}
		$count      = max( 0, min( 300, absint( get_option( 'gd_table_count', 0 ) ) ) );
		$default    = max( 1, min( 50, absint( get_option( 'gd_table_default_capacity', 5 ) ) ) );
		$overrides  = get_option( 'gd_table_capacity_overrides', array() );
		$overrides  = is_array( $overrides ) ? $overrides : array();
		$now        = current_time( 'mysql' );
		for ( $number = 1; $number <= $count; $number++ ) {
			$seats = isset( $overrides[ $number ] ) ? absint( $overrides[ $number ] ) : $default;
			$wpdb->insert(
				self::table( 'tables' ),
				array(
					'name'       => 'Tisch ' . $number,
					'seats'      => max( 1, min( 50, $seats ) ),
					'area'       => 'Gastraum',
					'sort_order' => $number,
					'active'     => 1,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
			);
		}
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$tables = self::table( 'tables' );
		$categories = self::table( 'menu_categories' );
		$menu_items = self::table( 'menu_items' );
		$orders = self::table( 'orders' );
		$order_items = self::table( 'order_items' );
		$payments = self::table( 'payments' );

		$sql = "
		CREATE TABLE {$tables} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			seats smallint(5) unsigned NOT NULL DEFAULT 4,
			area varchar(100) NOT NULL DEFAULT '',
			sort_order int(11) NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY active_sort (active, sort_order)
		) {$charset};

		CREATE TABLE {$categories} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(120) NOT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY active_sort (active, sort_order)
		) {$charset};

		CREATE TABLE {$menu_items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			category_id bigint(20) unsigned NOT NULL,
			name varchar(180) NOT NULL,
			description text NULL,
			price decimal(10,2) NOT NULL DEFAULT 0.00,
			station varchar(20) NOT NULL DEFAULT 'kitchen',
			sort_order int(11) NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY category_active (category_id, active),
			KEY station_active (station, active)
		) {$charset};

		CREATE TABLE {$orders} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			table_id bigint(20) unsigned NOT NULL,
			reservation_id bigint(20) unsigned NULL,
			guest_name varchar(180) NOT NULL DEFAULT '',
			guest_count smallint(5) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'open',
			note text NULL,
			opened_by bigint(20) unsigned NOT NULL DEFAULT 0,
			opened_at datetime NOT NULL,
			closed_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY table_status (table_id, status),
			KEY reservation_id (reservation_id),
			KEY status_updated (status, updated_at)
		) {$charset};

		CREATE TABLE {$order_items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			menu_item_id bigint(20) unsigned NULL,
			item_name varchar(180) NOT NULL,
			unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
			quantity smallint(5) unsigned NOT NULL DEFAULT 1,
			paid_quantity smallint(5) unsigned NOT NULL DEFAULT 0,
			note text NULL,
			station varchar(20) NOT NULL DEFAULT 'kitchen',
			status varchar(30) NOT NULL DEFAULT 'new',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_status (order_id, status),
			KEY station_status (station, status),
			KEY created_at (created_at)
		) {$charset};

		CREATE TABLE {$payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			method varchar(30) NOT NULL DEFAULT 'cash',
			terminal_reference varchar(190) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY created_at (created_at)
		) {$charset};
		";

		dbDelta( $sql );
	}

	private static function create_roles(): void {
		$roles = array(
			'gdg_service' => array(
				'label' => 'Gelsendiele Service',
				'caps'  => array(
					'read' => true,
					'gdg_use_service' => true,
					'gdg_use_checkout' => true,
				),
			),
			'gdg_kitchen' => array(
				'label' => 'Gelsendiele Küche',
				'caps'  => array(
					'read' => true,
					'gdg_use_kitchen' => true,
				),
			),
			'gdg_bar' => array(
				'label' => 'Gelsendiele Schank',
				'caps'  => array(
					'read' => true,
					'gdg_use_bar' => true,
				),
			),
		);

		foreach ( $roles as $key => $data ) {
			$role = get_role( $key );
			if ( ! $role ) {
				$role = add_role( $key, $data['label'], $data['caps'] );
			}
			if ( $role ) {
				foreach ( $data['caps'] as $cap => $grant ) {
					$role->add_cap( $cap, $grant );
				}
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( 'gdg_manage', 'gdg_use_service', 'gdg_use_kitchen', 'gdg_use_bar', 'gdg_use_checkout' ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	private static function seed_defaults(): void {
		global $wpdb;
		$now = current_time( 'mysql' );

		if ( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( 'tables' ) ) ) {
			for ( $i = 1; $i <= 12; $i++ ) {
				$wpdb->insert(
					self::table( 'tables' ),
					array(
						'name' => 'Tisch ' . $i,
						'seats' => 4,
						'area' => $i <= 6 ? 'Gaststube' : 'Nebenraum',
						'sort_order' => $i,
						'active' => 1,
						'created_at' => $now,
						'updated_at' => $now,
					),
					array( '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
				);
			}
		}

		if ( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( 'menu_categories' ) ) ) {
			$wpdb->insert( self::table( 'menu_categories' ), array( 'name' => 'Speisen', 'sort_order' => 10, 'active' => 1 ), array( '%s', '%d', '%d' ) );
			$food_cat = (int) $wpdb->insert_id;
			$wpdb->insert( self::table( 'menu_categories' ), array( 'name' => 'Getränke', 'sort_order' => 20, 'active' => 1 ), array( '%s', '%d', '%d' ) );
			$drink_cat = (int) $wpdb->insert_id;

			$samples = array(
				array( $food_cat, 'Schnitzel', 'Beispielgericht – bitte in der Verwaltung anpassen.', 15.90, 'kitchen', 10 ),
				array( $food_cat, 'Cordon Bleu', 'Beispielgericht – bitte in der Verwaltung anpassen.', 17.90, 'kitchen', 20 ),
				array( $food_cat, 'Beilagensalat', '', 5.20, 'kitchen', 30 ),
				array( $drink_cat, 'Mineralwasser', '', 3.40, 'bar', 10 ),
				array( $drink_cat, 'Soda Zitrone', '', 3.80, 'bar', 20 ),
				array( $drink_cat, 'Bier', '', 4.40, 'bar', 30 ),
			);

			foreach ( $samples as $sample ) {
				$wpdb->insert(
					self::table( 'menu_items' ),
					array(
						'category_id' => $sample[0],
						'name' => $sample[1],
						'description' => $sample[2],
						'price' => $sample[3],
						'station' => $sample[4],
						'sort_order' => $sample[5],
						'active' => 1,
						'created_at' => $now,
						'updated_at' => $now,
					),
					array( '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s' )
				);
			}
		}
	}

	private static function create_app_pages(): void {
		$parent = get_page_by_path( 'reservierungsverwaltung' );
		$parent_id = $parent ? (int) $parent->ID : 0;
		if ( ! $parent_id ) {
			$parent_id = wp_insert_post(
				array(
					'post_title' => 'Gelsendiele Gastro',
					'post_name' => 'gelsendiele-gastro',
					'post_status' => 'publish',
					'post_type' => 'page',
					'post_content' => '<p>Bitte einen der Arbeitsbereiche auswählen.</p>',
				),
				true
			);
			if ( is_wp_error( $parent_id ) ) {
				$parent_id = 0;
			}
		}

		$views = array(
			'service' => 'Service',
			'kitchen' => 'Küche',
			'bar' => 'Schank',
			'checkout' => 'Kasse',
		);
		$page_ids = array();
		foreach ( $views as $view => $title ) {
			$existing_id = (int) get_option( 'gdg_page_' . $view, 0 );
			if ( $existing_id && get_post( $existing_id ) ) {
				$page_ids[ $view ] = $existing_id;
				continue;
			}
			$page_id = wp_insert_post(
				array(
					'post_title' => $title,
					'post_name' => sanitize_title( $title ),
					'post_status' => 'publish',
					'post_type' => 'page',
					'post_parent' => $parent_id,
					'post_content' => '[gelsendiele_gastro view="' . esc_attr( $view ) . '"]',
				),
				true
			);
			if ( ! is_wp_error( $page_id ) ) {
				update_post_meta( $page_id, '_gdg_view', $view );
				update_option( 'gdg_page_' . $view, (int) $page_id );
				$page_ids[ $view ] = (int) $page_id;
			}
		}
		update_option( 'gdg_parent_page', $parent_id );
		update_option( 'gdg_app_pages', $page_ids );
	}

	public static function get_tables( bool $active_only = true ): array {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'tables' );
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		$sql .= ' ORDER BY area ASC, sort_order ASC, name ASC';
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	public static function get_categories( bool $active_only = true ): array {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'menu_categories' );
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		$sql .= ' ORDER BY sort_order ASC, name ASC';
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	public static function get_menu_items( bool $active_only = true ): array {
		global $wpdb;
		$sql = 'SELECT mi.*, mc.name AS category_name FROM ' . self::table( 'menu_items' ) . ' mi LEFT JOIN ' . self::table( 'menu_categories' ) . ' mc ON mc.id = mi.category_id';
		if ( $active_only ) {
			$sql .= ' WHERE mi.active = 1 AND mc.active = 1';
		}
		$sql .= ' ORDER BY mc.sort_order ASC, mi.sort_order ASC, mi.name ASC';
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	public static function get_open_orders(): array {
		global $wpdb;
		$sql = 'SELECT o.*, t.name AS table_name, t.area AS table_area FROM ' . self::table( 'orders' ) . ' o LEFT JOIN ' . self::table( 'tables' ) . " t ON t.id = o.table_id WHERE o.status IN ('open','ready_for_payment') ORDER BY o.opened_at ASC";
		$orders = $wpdb->get_results( $sql, ARRAY_A ) ?: array();
		foreach ( $orders as &$order ) {
			$order['items'] = self::get_order_items( (int) $order['id'] );
			$order['total'] = self::calculate_order_total( $order['items'] );
			$order['paid'] = self::calculate_order_paid( $order['items'] );
			$order['open_amount'] = round( $order['total'] - $order['paid'], 2 );
		}
		return $orders;
	}

	public static function get_order( int $order_id ): ?array {
		global $wpdb;
		$order = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT o.*, t.name AS table_name, t.area AS table_area FROM ' . self::table( 'orders' ) . ' o LEFT JOIN ' . self::table( 'tables' ) . ' t ON t.id = o.table_id WHERE o.id = %d',
				$order_id
			),
			ARRAY_A
		);
		if ( ! $order ) {
			return null;
		}
		$order['items'] = self::get_order_items( $order_id );
		$order['payments'] = self::get_payments( $order_id );
		$order['total'] = self::calculate_order_total( $order['items'] );
		$order['paid'] = self::calculate_order_paid( $order['items'] );
		$order['open_amount'] = round( $order['total'] - $order['paid'], 2 );
		return $order;
	}

	public static function get_open_order_for_table( int $table_id ): ?array {
		global $wpdb;
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . self::table( 'orders' ) . " WHERE table_id = %d AND status IN ('open','ready_for_payment') ORDER BY id DESC LIMIT 1",
				$table_id
			)
		);
		return $id ? self::get_order( $id ) : null;
	}

	public static function open_order( int $table_id, int $reservation_id = 0, string $guest_name = '', int $guest_count = 0 ) {
		global $wpdb;
		$existing = self::get_open_order_for_table( $table_id );
		if ( $existing ) {
			if ( $reservation_id && empty( $existing['reservation_id'] ) ) {
				$wpdb->update(
					self::table( 'orders' ),
					array(
						'reservation_id' => $reservation_id,
						'guest_name' => $guest_name ?: $existing['guest_name'],
						'guest_count' => $guest_count ?: (int) $existing['guest_count'],
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $existing['id'] ),
					array( '%d', '%s', '%d', '%s' ),
					array( '%d' )
				);
			}
			return (int) $existing['id'];
		}

		$table_exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'tables' ) . ' WHERE id = %d AND active = 1', $table_id ) );
		if ( ! $table_exists ) {
			return new WP_Error( 'gdg_invalid_table', 'Der ausgewählte Tisch existiert nicht oder ist deaktiviert.', array( 'status' => 400 ) );
		}

		$now = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			self::table( 'orders' ),
			array(
				'table_id' => $table_id,
				'reservation_id' => $reservation_id ?: null,
				'guest_name' => $guest_name,
				'guest_count' => max( 0, $guest_count ),
				'status' => 'open',
				'opened_by' => get_current_user_id(),
				'opened_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'gdg_order_create_failed', 'Die Bestellung konnte nicht geöffnet werden.', array( 'status' => 500 ) );
		}
		$order_id = (int) $wpdb->insert_id;
		do_action( 'gelsendiele_gastro_order_opened', $order_id, $reservation_id, $table_id );
		return $order_id;
	}

	public static function add_order_item( int $order_id, int $menu_item_id, int $quantity = 1, string $note = '' ) {
		global $wpdb;
		$order = self::get_order( $order_id );
		if ( ! $order || ! in_array( $order['status'], array( 'open', 'ready_for_payment' ), true ) ) {
			return new WP_Error( 'gdg_invalid_order', 'Die Bestellung ist nicht mehr offen.', array( 'status' => 400 ) );
		}
		$item = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'menu_items' ) . ' WHERE id = %d AND active = 1', $menu_item_id ), ARRAY_A );
		if ( ! $item ) {
			return new WP_Error( 'gdg_invalid_menu_item', 'Der Menüeintrag wurde nicht gefunden.', array( 'status' => 404 ) );
		}
		$now = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			self::table( 'order_items' ),
			array(
				'order_id' => $order_id,
				'menu_item_id' => $menu_item_id,
				'item_name' => $item['name'],
				'unit_price' => $item['price'],
				'quantity' => max( 1, min( 99, $quantity ) ),
				'paid_quantity' => 0,
				'note' => $note,
				'station' => $item['station'],
				'status' => 'new',
				'created_by' => get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'gdg_item_create_failed', 'Die Position konnte nicht hinzugefügt werden.', array( 'status' => 500 ) );
		}
		$wpdb->update( self::table( 'orders' ), array( 'status' => 'open', 'updated_at' => $now ), array( 'id' => $order_id ), array( '%s', '%s' ), array( '%d' ) );
		$item_id = (int) $wpdb->insert_id;
		do_action( 'gelsendiele_gastro_item_added', $item_id, $order_id );
		return $item_id;
	}

	public static function get_order_items( int $order_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table( 'order_items' ) . ' WHERE order_id = %d ORDER BY created_at ASC, id ASC', $order_id ),
			ARRAY_A
		) ?: array();
	}

	public static function get_order_item( int $item_id ): ?array {
		global $wpdb;
		$item = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'order_items' ) . ' WHERE id = %d', $item_id ), ARRAY_A );
		return $item ?: null;
	}

	public static function get_queue( string $station ): array {
		global $wpdb;
		$station = in_array( $station, array( 'kitchen', 'bar' ), true ) ? $station : 'kitchen';
		$sql = $wpdb->prepare(
			'SELECT oi.*, o.table_id, o.guest_name, o.opened_at, t.name AS table_name FROM ' . self::table( 'order_items' ) . ' oi INNER JOIN ' . self::table( 'orders' ) . ' o ON o.id = oi.order_id LEFT JOIN ' . self::table( 'tables' ) . " t ON t.id = o.table_id WHERE oi.station = %s AND oi.status IN ('new','preparing','ready') AND o.status IN ('open','ready_for_payment') ORDER BY CASE oi.status WHEN 'new' THEN 1 WHEN 'preparing' THEN 2 ELSE 3 END, oi.created_at ASC",
			$station
		);
		$items = $wpdb->get_results( $sql, ARRAY_A ) ?: array();
		$orders = array();
		foreach ( $items as $item ) {
			$order_id = (int) $item['order_id'];
			if ( ! isset( $orders[ $order_id ] ) ) {
				$orders[ $order_id ] = array(
					'order_id' => $order_id,
					'table_id' => (int) $item['table_id'],
					'table_name' => $item['table_name'],
					'guest_name' => $item['guest_name'],
					'opened_at' => $item['opened_at'],
					'items' => array(),
				);
			}
			$orders[ $order_id ]['items'][] = $item;
		}
		return array_values( $orders );
	}

	public static function update_item( int $item_id, array $changes ) {
		global $wpdb;
		$item = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'order_items' ) . ' WHERE id = %d', $item_id ), ARRAY_A );
		if ( ! $item ) {
			return new WP_Error( 'gdg_item_not_found', 'Position nicht gefunden.', array( 'status' => 404 ) );
		}

		$update = array();
		$formats = array();
		if ( isset( $changes['quantity'] ) ) {
			$quantity = max( 1, min( 99, (int) $changes['quantity'] ) );
			if ( $quantity < (int) $item['paid_quantity'] ) {
				return new WP_Error( 'gdg_quantity_paid', 'Die Menge kann nicht kleiner als die bereits bezahlte Menge sein.', array( 'status' => 400 ) );
			}
			$update['quantity'] = $quantity;
			$formats[] = '%d';
		}
		if ( isset( $changes['note'] ) ) {
			$update['note'] = sanitize_textarea_field( $changes['note'] );
			$formats[] = '%s';
		}
		if ( isset( $changes['status'] ) ) {
			$allowed = array( 'new', 'preparing', 'ready', 'served', 'cancelled' );
			$status = sanitize_key( $changes['status'] );
			if ( ! in_array( $status, $allowed, true ) ) {
				return new WP_Error( 'gdg_invalid_status', 'Ungültiger Positionsstatus.', array( 'status' => 400 ) );
			}
			if ( (int) $item['paid_quantity'] > 0 && 'cancelled' === $status ) {
				return new WP_Error( 'gdg_item_paid', 'Eine bereits bezahlte Position kann nicht storniert werden.', array( 'status' => 400 ) );
			}
			$update['status'] = $status;
			$formats[] = '%s';
		}
		if ( ! $update ) {
			return true;
		}
		$update['updated_at'] = current_time( 'mysql' );
		$formats[] = '%s';
		$ok = $wpdb->update( self::table( 'order_items' ), $update, array( 'id' => $item_id ), $formats, array( '%d' ) );
		if ( false === $ok ) {
			return new WP_Error( 'gdg_item_update_failed', 'Die Position konnte nicht aktualisiert werden.', array( 'status' => 500 ) );
		}
		$wpdb->update( self::table( 'orders' ), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $item['order_id'] ), array( '%s' ), array( '%d' ) );
		do_action( 'gelsendiele_gastro_item_updated', $item_id, $update );
		return true;
	}

	public static function checkout( int $order_id, array $items, string $method, string $terminal_reference = '' ) {
		global $wpdb;
		$order = self::get_order( $order_id );
		if ( ! $order || ! in_array( $order['status'], array( 'open', 'ready_for_payment' ), true ) ) {
			return new WP_Error( 'gdg_invalid_order', 'Die Bestellung ist nicht mehr offen.', array( 'status' => 400 ) );
		}
		$method = in_array( $method, array( 'cash', 'card', 'other' ), true ) ? $method : 'cash';
		$by_id = array();
		foreach ( $order['items'] as $order_item ) {
			$by_id[ (int) $order_item['id'] ] = $order_item;
		}

		$normalized = array();
		$amount = 0.0;
		foreach ( $items as $pay_item ) {
			$item_id = isset( $pay_item['item_id'] ) ? (int) $pay_item['item_id'] : 0;
			$qty = isset( $pay_item['quantity'] ) ? (int) $pay_item['quantity'] : 0;
			if ( ! $item_id || $qty < 1 || ! isset( $by_id[ $item_id ] ) ) {
				continue;
			}
			$current = $by_id[ $item_id ];
			if ( 'cancelled' === $current['status'] ) {
				continue;
			}
			$remaining = (int) $current['quantity'] - (int) $current['paid_quantity'];
			if ( $qty > $remaining ) {
				return new WP_Error( 'gdg_invalid_payment_quantity', 'Die ausgewählte Zahlungsmenge ist höher als die offene Menge.', array( 'status' => 400 ) );
			}
			$normalized[] = array( 'item_id' => $item_id, 'quantity' => $qty );
			$amount += (float) $current['unit_price'] * $qty;
		}
		if ( ! $normalized || $amount <= 0 ) {
			return new WP_Error( 'gdg_empty_payment', 'Es wurden keine offenen Positionen ausgewählt.', array( 'status' => 400 ) );
		}

		$wpdb->query( 'START TRANSACTION' );
		try {
			foreach ( $normalized as $pay_item ) {
				$updated = $wpdb->query(
					$wpdb->prepare(
						'UPDATE ' . self::table( 'order_items' ) . ' SET paid_quantity = paid_quantity + %d, updated_at = %s WHERE id = %d AND order_id = %d AND paid_quantity + %d <= quantity',
						$pay_item['quantity'],
						current_time( 'mysql' ),
						$pay_item['item_id'],
						$order_id,
						$pay_item['quantity']
					)
				);
				if ( 1 !== $updated ) {
					throw new RuntimeException( 'Position konnte nicht bezahlt werden.' );
				}
			}

			$wpdb->insert(
				self::table( 'payments' ),
				array(
					'order_id' => $order_id,
					'amount' => round( $amount, 2 ),
					'method' => $method,
					'terminal_reference' => sanitize_text_field( $terminal_reference ),
					'created_by' => get_current_user_id(),
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%f', '%s', '%s', '%d', '%s' )
			);
			if ( ! $wpdb->insert_id ) {
				throw new RuntimeException( 'Zahlung konnte nicht gespeichert werden.' );
			}
			$payment_id = (int) $wpdb->insert_id;

			$fresh_items = self::get_order_items( $order_id );
			$all_paid = true;
			foreach ( $fresh_items as $fresh_item ) {
				if ( 'cancelled' !== $fresh_item['status'] && (int) $fresh_item['paid_quantity'] < (int) $fresh_item['quantity'] ) {
					$all_paid = false;
					break;
				}
			}
			$wpdb->update(
				self::table( 'orders' ),
				array(
					'status' => $all_paid ? 'closed' : 'ready_for_payment',
					'closed_at' => $all_paid ? current_time( 'mysql' ) : null,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $order_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdg_checkout_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		do_action( 'gelsendiele_gastro_payment_completed', $payment_id, $order_id, round( $amount, 2 ), $method, $all_paid );
		return array(
			'payment_id' => $payment_id,
			'amount' => round( $amount, 2 ),
			'order_closed' => $all_paid,
			'order' => self::get_order( $order_id ),
		);
	}

	public static function get_payments( int $order_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'payments' ) . ' WHERE order_id = %d ORDER BY created_at ASC', $order_id ), ARRAY_A ) ?: array();
	}

	private static function calculate_order_total( array $items ): float {
		$total = 0.0;
		foreach ( $items as $item ) {
			if ( 'cancelled' !== $item['status'] ) {
				$total += (float) $item['unit_price'] * (int) $item['quantity'];
			}
		}
		return round( $total, 2 );
	}

	private static function calculate_order_paid( array $items ): float {
		$total = 0.0;
		foreach ( $items as $item ) {
			if ( 'cancelled' !== $item['status'] ) {
				$total += (float) $item['unit_price'] * (int) $item['paid_quantity'];
			}
		}
		return round( $total, 2 );
	}
}
