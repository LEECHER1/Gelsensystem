<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDG_Admin {
	public static function register_menu(): void {
		add_menu_page(
			'Gelsendiele Gastro',
			'Gelsendiele Gastro',
			'gdg_manage',
			'gdg-gastro',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-food',
			26
		);
		add_submenu_page( 'gdg-gastro', 'Übersicht', 'Übersicht', 'gdg_manage', 'gdg-gastro', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'gdg-gastro', 'Tische', 'Tische', 'gdg_manage', 'gdg-tables', array( __CLASS__, 'render_tables' ) );
		add_submenu_page( 'gdg-gastro', 'Speisekarte', 'Speisekarte', 'gdg_manage', 'gdg-menu', array( __CLASS__, 'render_menu' ) );
		add_submenu_page( 'gdg-gastro', 'Einstellungen', 'Einstellungen', 'gdg_manage', 'gdg-settings', array( __CLASS__, 'render_settings' ) );
	}

	public static function handle_actions(): void {
		if ( empty( $_POST['gdg_action'] ) || ! current_user_can( 'gdg_manage' ) ) {
			return;
		}
		check_admin_referer( 'gdg_admin_action', 'gdg_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['gdg_action'] ) );
		switch ( $action ) {
			case 'save_table':
				self::save_table();
				break;
			case 'save_category':
				self::save_category();
				break;
			case 'save_menu_item':
				self::save_menu_item();
				break;
			case 'save_settings':
				self::save_settings();
				break;
		}
	}

	private static function redirect( string $page, string $notice = 'saved' ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => $page, 'gdg_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function save_table(): void {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'seats' => max( 1, absint( $_POST['seats'] ?? 4 ) ),
			'area' => sanitize_text_field( wp_unslash( $_POST['area'] ?? '' ) ),
			'sort_order' => intval( $_POST['sort_order'] ?? 0 ),
			'active' => empty( $_POST['active'] ) ? 0 : 1,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( '' === $data['name'] ) {
			self::redirect( 'gdg-tables', 'missing_name' );
		}
		if ( $id ) {
			$wpdb->update( GDG_DB::table( 'tables' ), $data, array( 'id' => $id ), array( '%s', '%d', '%s', '%d', '%d', '%s' ), array( '%d' ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( GDG_DB::table( 'tables' ), $data, array( '%s', '%d', '%s', '%d', '%d', '%s', '%s' ) );
		}
		self::redirect( 'gdg-tables' );
	}

	private static function save_category(): void {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'sort_order' => intval( $_POST['sort_order'] ?? 0 ),
			'active' => empty( $_POST['active'] ) ? 0 : 1,
		);
		if ( '' === $data['name'] ) {
			self::redirect( 'gdg-menu', 'missing_name' );
		}
		if ( $id ) {
			$wpdb->update( GDG_DB::table( 'menu_categories' ), $data, array( 'id' => $id ), array( '%s', '%d', '%d' ), array( '%d' ) );
		} else {
			$wpdb->insert( GDG_DB::table( 'menu_categories' ), $data, array( '%s', '%d', '%d' ) );
		}
		self::redirect( 'gdg-menu' );
	}

	private static function save_menu_item(): void {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$station = sanitize_key( wp_unslash( $_POST['station'] ?? 'kitchen' ) );
		if ( ! in_array( $station, array( 'kitchen', 'bar' ), true ) ) {
			$station = 'kitchen';
		}
		$data = array(
			'category_id' => absint( $_POST['category_id'] ?? 0 ),
			'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'price' => max( 0, (float) str_replace( ',', '.', wp_unslash( $_POST['price'] ?? '0' ) ) ),
			'station' => $station,
			'sort_order' => intval( $_POST['sort_order'] ?? 0 ),
			'active' => empty( $_POST['active'] ) ? 0 : 1,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( ! $data['category_id'] || '' === $data['name'] ) {
			self::redirect( 'gdg-menu', 'missing_name' );
		}
		if ( $id ) {
			$wpdb->update( GDG_DB::table( 'menu_items' ), $data, array( 'id' => $id ), array( '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%s' ), array( '%d' ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( GDG_DB::table( 'menu_items' ), $data, array( '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s' ) );
		}
		self::redirect( 'gdg-menu' );
	}

	private static function save_settings(): void {
		$mode = sanitize_key( wp_unslash( $_POST['terminal_mode'] ?? 'manual' ) );
		if ( ! in_array( $mode, array( 'manual', 'sumup', 'custom' ), true ) ) {
			$mode = 'manual';
		}
		update_option( 'gdg_terminal_mode', $mode );
		update_option( 'gdg_poll_interval', max( 3, min( 30, absint( $_POST['poll_interval'] ?? 5 ) ) ) );
		self::redirect( 'gdg-settings' );
	}

	private static function notice(): void {
		if ( empty( $_GET['gdg_notice'] ) ) {
			return;
		}
		$notice = sanitize_key( wp_unslash( $_GET['gdg_notice'] ) );
		$message = 'Änderungen gespeichert.';
		$type = 'success';
		if ( 'missing_name' === $notice ) {
			$message = 'Bitte alle Pflichtfelder ausfüllen.';
			$type = 'error';
		}
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	public static function render_dashboard(): void {
		self::guard();
		$urls = GDG_App::get_app_urls();
		$orders = GDG_DB::get_open_orders();
		$queue_kitchen = GDG_DB::get_queue( 'kitchen' );
		$queue_bar = GDG_DB::get_queue( 'bar' );
		?>
		<div class="wrap">
			<h1>Gelsendiele Gastro</h1>
			<?php self::notice(); ?>
			<p>Gemeinsames System für Reservierung, Tischbelegung, Bestellungen, Küche, Schank und Abrechnung.</p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;max-width:1000px;margin:24px 0;">
				<?php self::stat_card( 'Offene Tische', count( $orders ) ); ?>
				<?php self::stat_card( 'Küchenbons', count( $queue_kitchen ) ); ?>
				<?php self::stat_card( 'Schankbons', count( $queue_bar ) ); ?>
			</div>
			<h2>Arbeitsbereiche</h2>
			<p>
				<?php foreach ( array( 'service' => 'Service öffnen', 'kitchen' => 'Küche öffnen', 'bar' => 'Schank öffnen', 'checkout' => 'Kasse öffnen' ) as $view => $label ) : ?>
					<?php if ( ! empty( $urls[ $view ] ) ) : ?>
						<a class="button button-primary" style="margin:0 8px 8px 0" href="<?php echo esc_url( $urls[ $view ] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</p>
			<div class="notice notice-warning inline" style="max-width:900px;margin-top:24px;"><p><strong>Wichtig:</strong> Die Zahlungsansicht dokumentiert in Version 0.1 Zahlungen intern. Sie ersetzt noch keine österreichische Registrierkasse und erstellt keinen RKSV-Beleg.</p></div>
		</div>
		<?php
	}

	private static function stat_card( string $label, int $number ): void {
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.04)"><div style="font-size:30px;font-weight:700">' . esc_html( (string) $number ) . '</div><div>' . esc_html( $label ) . '</div></div>';
	}

	public static function render_tables(): void {
		self::guard();
		global $wpdb;
		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$edit = $edit_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDG_DB::table( 'tables' ) . ' WHERE id = %d', $edit_id ), ARRAY_A ) : null;
		$tables = GDG_DB::get_tables( false );
		?>
		<div class="wrap">
			<h1>Tische</h1>
			<?php self::notice(); ?>
			<div style="display:grid;grid-template-columns:minmax(320px,480px) 1fr;gap:28px;align-items:start;">
				<form method="post" style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:8px;">
					<h2><?php echo $edit ? 'Tisch bearbeiten' : 'Tisch hinzufügen'; ?></h2>
					<?php wp_nonce_field( 'gdg_admin_action', 'gdg_nonce' ); ?>
					<input type="hidden" name="gdg_action" value="save_table">
					<input type="hidden" name="id" value="<?php echo esc_attr( $edit['id'] ?? 0 ); ?>">
					<table class="form-table"><tbody>
					<tr><th><label for="gdg-table-name">Name</label></th><td><input class="regular-text" id="gdg-table-name" name="name" required value="<?php echo esc_attr( $edit['name'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="gdg-table-seats">Sitzplätze</label></th><td><input type="number" min="1" id="gdg-table-seats" name="seats" value="<?php echo esc_attr( $edit['seats'] ?? 4 ); ?>"></td></tr>
					<tr><th><label for="gdg-table-area">Bereich</label></th><td><input class="regular-text" id="gdg-table-area" name="area" value="<?php echo esc_attr( $edit['area'] ?? 'Gaststube' ); ?>"></td></tr>
					<tr><th><label for="gdg-table-sort">Reihenfolge</label></th><td><input type="number" id="gdg-table-sort" name="sort_order" value="<?php echo esc_attr( $edit['sort_order'] ?? 0 ); ?>"></td></tr>
					<tr><th>Aktiv</th><td><label><input type="checkbox" name="active" value="1" <?php checked( ! isset( $edit['active'] ) || (int) $edit['active'] === 1 ); ?>> Tisch anzeigen</label></td></tr>
					</tbody></table>
					<?php submit_button( $edit ? 'Tisch speichern' : 'Tisch hinzufügen' ); ?>
				</form>
				<div>
					<table class="widefat striped"><thead><tr><th>Name</th><th>Bereich</th><th>Sitze</th><th>Status</th><th></th></tr></thead><tbody>
					<?php foreach ( $tables as $table ) : ?>
					<tr><td><strong><?php echo esc_html( $table['name'] ); ?></strong></td><td><?php echo esc_html( $table['area'] ); ?></td><td><?php echo esc_html( $table['seats'] ); ?></td><td><?php echo (int) $table['active'] ? 'Aktiv' : 'Deaktiviert'; ?></td><td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'gdg-tables', 'edit' => $table['id'] ), admin_url( 'admin.php' ) ) ); ?>">Bearbeiten</a></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_menu(): void {
		self::guard();
		global $wpdb;
		$categories = GDG_DB::get_categories( false );
		$items = GDG_DB::get_menu_items( false );
		$edit_cat_id = isset( $_GET['edit_cat'] ) ? absint( $_GET['edit_cat'] ) : 0;
		$edit_item_id = isset( $_GET['edit_item'] ) ? absint( $_GET['edit_item'] ) : 0;
		$edit_cat = $edit_cat_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDG_DB::table( 'menu_categories' ) . ' WHERE id = %d', $edit_cat_id ), ARRAY_A ) : null;
		$edit_item = $edit_item_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDG_DB::table( 'menu_items' ) . ' WHERE id = %d', $edit_item_id ), ARRAY_A ) : null;
		?>
		<div class="wrap">
			<h1>Speisekarte</h1>
			<?php self::notice(); ?>
			<div style="display:grid;grid-template-columns:minmax(280px,380px) minmax(360px,520px);gap:24px;align-items:start;margin-bottom:28px;">
				<form method="post" style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:8px;">
					<h2><?php echo $edit_cat ? 'Kategorie bearbeiten' : 'Kategorie hinzufügen'; ?></h2>
					<?php wp_nonce_field( 'gdg_admin_action', 'gdg_nonce' ); ?><input type="hidden" name="gdg_action" value="save_category"><input type="hidden" name="id" value="<?php echo esc_attr( $edit_cat['id'] ?? 0 ); ?>">
					<p><label>Name<br><input class="regular-text" name="name" required value="<?php echo esc_attr( $edit_cat['name'] ?? '' ); ?>"></label></p>
					<p><label>Reihenfolge<br><input type="number" name="sort_order" value="<?php echo esc_attr( $edit_cat['sort_order'] ?? 0 ); ?>"></label></p>
					<p><label><input type="checkbox" name="active" value="1" <?php checked( ! isset( $edit_cat['active'] ) || (int) $edit_cat['active'] === 1 ); ?>> Aktiv</label></p>
					<?php submit_button( $edit_cat ? 'Kategorie speichern' : 'Kategorie hinzufügen' ); ?>
				</form>
				<form method="post" style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:8px;">
					<h2><?php echo $edit_item ? 'Gericht/Getränk bearbeiten' : 'Gericht/Getränk hinzufügen'; ?></h2>
					<?php wp_nonce_field( 'gdg_admin_action', 'gdg_nonce' ); ?><input type="hidden" name="gdg_action" value="save_menu_item"><input type="hidden" name="id" value="<?php echo esc_attr( $edit_item['id'] ?? 0 ); ?>">
					<p><label>Kategorie<br><select name="category_id" required><option value="">Bitte wählen</option><?php foreach ( $categories as $category ) : ?><option value="<?php echo esc_attr( $category['id'] ); ?>" <?php selected( $edit_item['category_id'] ?? 0, $category['id'] ); ?>><?php echo esc_html( $category['name'] ); ?></option><?php endforeach; ?></select></label></p>
					<p><label>Name<br><input class="regular-text" name="name" required value="<?php echo esc_attr( $edit_item['name'] ?? '' ); ?>"></label></p>
					<p><label>Beschreibung<br><textarea class="large-text" rows="3" name="description"><?php echo esc_textarea( $edit_item['description'] ?? '' ); ?></textarea></label></p>
					<p><label>Preis (€)<br><input type="number" step="0.01" min="0" name="price" value="<?php echo esc_attr( $edit_item['price'] ?? '0.00' ); ?>"></label></p>
					<p><label>Ausgabe<br><select name="station"><option value="kitchen" <?php selected( $edit_item['station'] ?? 'kitchen', 'kitchen' ); ?>>Küche</option><option value="bar" <?php selected( $edit_item['station'] ?? '', 'bar' ); ?>>Schank</option></select></label></p>
					<p><label>Reihenfolge<br><input type="number" name="sort_order" value="<?php echo esc_attr( $edit_item['sort_order'] ?? 0 ); ?>"></label></p>
					<p><label><input type="checkbox" name="active" value="1" <?php checked( ! isset( $edit_item['active'] ) || (int) $edit_item['active'] === 1 ); ?>> Aktiv</label></p>
					<?php submit_button( $edit_item ? 'Eintrag speichern' : 'Eintrag hinzufügen' ); ?>
				</form>
			</div>
			<h2>Vorhandene Einträge</h2>
			<table class="widefat striped"><thead><tr><th>Kategorie</th><th>Name</th><th>Preis</th><th>Ausgabe</th><th>Status</th><th></th></tr></thead><tbody>
			<?php foreach ( $items as $item ) : ?>
			<tr><td><?php echo esc_html( $item['category_name'] ?: '–' ); ?></td><td><strong><?php echo esc_html( $item['name'] ); ?></strong></td><td><?php echo esc_html( number_format_i18n( (float) $item['price'], 2 ) ); ?> €</td><td><?php echo 'bar' === $item['station'] ? 'Schank' : 'Küche'; ?></td><td><?php echo (int) $item['active'] ? 'Aktiv' : 'Deaktiviert'; ?></td><td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'gdg-menu', 'edit_item' => $item['id'] ), admin_url( 'admin.php' ) ) ); ?>">Bearbeiten</a></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<h2 style="margin-top:28px">Kategorien</h2>
			<p><?php foreach ( $categories as $category ) : ?><a class="button" style="margin:0 6px 6px 0" href="<?php echo esc_url( add_query_arg( array( 'page' => 'gdg-menu', 'edit_cat' => $category['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $category['name'] ); ?></a><?php endforeach; ?></p>
		</div>
		<?php
	}

	public static function render_settings(): void {
		self::guard();
		?>
		<div class="wrap"><h1>Einstellungen</h1><?php self::notice(); ?>
		<form method="post" style="max-width:700px;background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:8px;">
			<?php wp_nonce_field( 'gdg_admin_action', 'gdg_nonce' ); ?><input type="hidden" name="gdg_action" value="save_settings">
			<table class="form-table"><tbody>
			<tr><th><label for="gdg-terminal">Kartenterminal</label></th><td><select id="gdg-terminal" name="terminal_mode"><option value="manual" <?php selected( get_option( 'gdg_terminal_mode', 'manual' ), 'manual' ); ?>>Manuell – Betrag am Terminal eingeben</option><option value="sumup" <?php selected( get_option( 'gdg_terminal_mode', 'manual' ), 'sumup' ); ?>>SumUp – Schnittstelle vorbereiten</option><option value="custom" <?php selected( get_option( 'gdg_terminal_mode', 'manual' ), 'custom' ); ?>>Andere Schnittstelle</option></select><p class="description">In Version 0.1 wird Kartenzahlung nur intern markiert. Eine echte Terminal-API wird im nächsten Schritt ergänzt.</p></td></tr>
			<tr><th><label for="gdg-poll">Aktualisierung</label></th><td><input id="gdg-poll" type="number" min="3" max="30" name="poll_interval" value="<?php echo esc_attr( get_option( 'gdg_poll_interval', 5 ) ); ?>"> Sekunden</td></tr>
			</tbody></table><?php submit_button(); ?>
		</form></div>
		<?php
	}

	private static function guard(): void {
		if ( ! current_user_can( 'gdg_manage' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'gelsendiele-gastro' ) );
		}
	}
}
