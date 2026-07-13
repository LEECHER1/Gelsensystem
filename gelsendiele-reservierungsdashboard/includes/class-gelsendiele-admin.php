<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Einheitliche WordPress-Verwaltung für das gesamte Gastro-System. */
final class Gelsendiele_Admin {
	const MENU_SLUG     = 'gelsendiele';
	const SETTINGS_SLUG = 'gelsendiele-settings';

	public static function bootstrap() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_menu_page(
			'Gelsensystem',
			'Gelsensystem',
			'read',
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-store',
			25
		);
		add_submenu_page( self::MENU_SLUG, 'Dashboard', 'Dashboard', 'read', self::MENU_SLUG, array( __CLASS__, 'render_dashboard' ), 1 );
		add_submenu_page( self::MENU_SLUG, 'Reservierungen', 'Reservierungen', 'manage_bookings', 'gelsendiele-reservations', array( __CLASS__, 'render_reservations' ), 2 );
		add_submenu_page( self::MENU_SLUG, 'Service', 'Service', 'gdg_use_service', 'gelsendiele-service', array( __CLASS__, 'render_workspace' ), 4 );
		add_submenu_page( self::MENU_SLUG, 'Küche', 'Küche', 'gdg_use_kitchen', 'gelsendiele-kitchen', array( __CLASS__, 'render_workspace' ), 5 );
		add_submenu_page( self::MENU_SLUG, 'Schank', 'Schank', 'gdg_use_bar', 'gelsendiele-bar', array( __CLASS__, 'render_workspace' ), 6 );
		add_submenu_page( self::MENU_SLUG, 'Events', 'Events', 'gdg_manage', 'gelsendiele-events', array( __CLASS__, 'render_events' ), 7 );
		add_submenu_page( self::MENU_SLUG, 'Kunden', 'Kunden', 'manage_bookings', 'gelsendiele-customers', array( __CLASS__, 'render_module_placeholder' ), 8 );
		add_submenu_page( self::MENU_SLUG, 'Statistiken', 'Statistiken', 'manage_bookings', 'gelsendiele-statistics', array( __CLASS__, 'render_module_placeholder' ), 9 );
		add_submenu_page( self::MENU_SLUG, 'Einstellungen', 'Einstellungen', 'gelsendiele_manage_settings', self::SETTINGS_SLUG, array( __CLASS__, 'render_settings' ), 10 );
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'gelsendiele' ) && false === strpos( (string) $hook, 'gdg-' ) ) {
			return;
		}
		wp_enqueue_style( 'gelsendiele-admin', GELSENDIELE_URL . 'admin/assets/settings.css', array(), GELSENDIELE_VERSION );
		if ( false !== strpos( (string) $hook, self::SETTINGS_SLUG ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'gelsendiele-settings', GELSENDIELE_URL . 'admin/assets/settings.js', array(), GELSENDIELE_VERSION, true );
		}
	}

	public static function render_dashboard() {
		$settings      = Gelsendiele_Settings::get_all();
		$business_name = $settings['general']['business_name'];
		$cards         = array(
			array( 'Reservierungen', 'Anfragen, Bestätigungen und Tischzuweisungen verwalten.', 'manage_bookings', admin_url( 'admin.php?page=gelsendiele-reservations' ) ),
			array( 'Tische & Bereiche', 'Strukturierte Tisch- und Bereichsverwaltung.', 'gdg_manage', admin_url( 'admin.php?page=gdg-tables' ) ),
			array( 'Service', 'Tische öffnen und Bestellungen aufnehmen.', 'gdg_use_service', self::workspace_url( 'service' ) ),
			array( 'Küche', 'Live-Monitor für Speisen.', 'gdg_use_kitchen', self::workspace_url( 'kitchen' ) ),
			array( 'Schank', 'Live-Monitor für Getränke.', 'gdg_use_bar', self::workspace_url( 'bar' ) ),
			array( 'Speisekarte', 'Kategorien, Speisen, Getränke und Preise.', 'gdg_manage', admin_url( 'admin.php?page=gdg-menu' ) ),
			array( 'Events', 'Veranstaltungen, Bilder, Popup und Farben verwalten.', 'gdg_manage', admin_url( 'admin.php?page=gelsendiele-events' ) ),
			array( 'Einstellungen', 'Betrieb, Marke, Öffnungszeiten und System konfigurieren.', 'gelsendiele_manage_settings', admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ),
		);
		?>
		<div class="wrap gelsendiele-admin-wrap">
			<div class="gelsendiele-admin-hero" style="<?php echo esc_attr( Gelsendiele_Settings::css_variables() ); ?>">
				<span>Gelsensystem</span>
				<h1><?php echo esc_html( $business_name ); ?></h1>
				<p>Reservierung, Service, Küche, Schank und Abrechnung in einem modularen WordPress-Plugin.</p>
			</div>
			<div class="gelsendiele-module-grid">
				<?php foreach ( $cards as $card ) : ?>
					<?php if ( current_user_can( $card[2] ) ) : ?>
						<a class="gelsendiele-module-card" href="<?php echo esc_url( $card[3] ); ?>">
							<strong><?php echo esc_html( $card[0] ); ?></strong>
							<span><?php echo esc_html( $card[1] ); ?></span>
							<em>Öffnen →</em>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public static function render_reservations() {
		self::guard( 'manage_bookings' );
		$page_id       = (int) get_option( 'gd_reservierungsdashboard_page_id', 0 );
		$dashboard_url = $page_id ? get_permalink( $page_id ) : home_url( '/gelsensystem/' );
		?>
		<div class="wrap gelsendiele-admin-wrap"><h1>Reservierungen</h1>
			<div class="gelsendiele-action-panel">
				<p>Für die tägliche Arbeit steht das touchoptimierte Reservierungsdashboard bereit. Die WordPress-Liste bleibt für Administration und Kompatibilität erhalten.</p>
				<p><a class="button button-primary button-hero" href="<?php echo esc_url( $dashboard_url ); ?>">Reservierungsdashboard öffnen</a> <a class="button button-hero" href="<?php echo esc_url( admin_url( 'edit.php?post_type=rtb-booking' ) ); ?>">WordPress-Liste öffnen</a></p>
			</div>
		</div>
		<?php
	}

	public static function render_events() {
		self::guard( 'gdg_manage' );
		$page_id = (int) get_option( 'gd_reservierungsdashboard_page_id', 0 );
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/gelsensystem/' );
		$url     = add_query_arg( 'gd-section', 'events', $url );
		?>
		<div class="wrap gelsendiele-admin-wrap"><h1>Events</h1><div class="gelsendiele-action-panel"><p>Events werden in der zentralen Gelsensystem-App gepflegt und automatisch auf der Webseite ausgegeben.</p><p><a class="button button-primary button-hero" href="<?php echo esc_url( $url ); ?>">Eventverwaltung öffnen</a></p></div></div>
		<?php
	}

	public static function render_workspace() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$map  = array(
			'gelsendiele-service' => array( 'service', 'Service', 'gdg_use_service' ),
			'gelsendiele-kitchen' => array( 'kitchen', 'Küche', 'gdg_use_kitchen' ),
			'gelsendiele-bar'     => array( 'bar', 'Schank', 'gdg_use_bar' ),
		);
		if ( ! isset( $map[ $page ] ) ) {
			wp_die( esc_html__( 'Unbekannter Arbeitsbereich.', 'gelsendiele-dashboard' ) );
		}
		$view = $map[ $page ];
		self::guard( $view[2] );
		$url = self::workspace_url( $view[0] );
		?>
		<div class="wrap gelsendiele-admin-wrap"><h1><?php echo esc_html( $view[1] ); ?></h1>
			<div class="gelsendiele-action-panel">
				<?php if ( $url ) : ?>
					<p>Der Arbeitsbereich ist für Tablets, Smartphones und Monitore als eigenständige Oberfläche ausgeführt.</p>
					<p><a class="button button-primary button-hero" href="<?php echo esc_url( $url ); ?>"> <?php echo esc_html( $view[1] ); ?> öffnen</a></p>
				<?php else : ?>
					<div class="notice notice-error inline"><p>Die Arbeitsseite fehlt. Die automatische Migration muss erneut ausgeführt werden.</p></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function render_module_placeholder() {
		self::guard( 'manage_bookings' );
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$title = 'gelsendiele-statistics' === $page ? 'Statistiken' : 'Kunden';
		?>
		<div class="wrap gelsendiele-admin-wrap"><h1><?php echo esc_html( $title ); ?></h1>
			<div class="gelsendiele-action-panel"><p>Dieses Modul ist im zentralen Gastro-System vorgesehen. Datenmodell und Berechtigungen werden in einer der nächsten versionierten Entwicklungsstufen ergänzt.</p></div>
		</div>
		<?php
	}

	public static function render_settings() {
		self::guard( 'gelsendiele_manage_settings' );
		self::render_settings_interface( 'admin' );
	}

	/** Rendert dieselben Einstellungen innerhalb der eigenständigen Gelsensystem-App. */
	public static function render_app_settings( $dashboard_url ) {
		self::guard( 'gelsendiele_manage_settings' );
		self::render_settings_interface( 'app', $dashboard_url );
	}

	private static function render_settings_interface( $context = 'admin', $dashboard_url = '' ) {
		$tabs = array(
			'general'       => 'Allgemein',
			'opening-hours' => 'Öffnungszeiten',
			'reservations'  => 'Reservierungen',
			'availability'  => 'Verfügbarkeiten',
			'tables'        => 'Tische & Bereiche',
			'emails'        => 'E-Mails',
			'form'          => 'Formular',
			'dashboard'     => 'Dashboard',
			'roles'         => 'Benutzerrollen',
			'system-status' => 'Systemstatus',
		);
		$tab_key = 'app' === $context ? 'gd-settings-tab' : 'tab';
		$tab = isset( $_GET[ $tab_key ] ) ? sanitize_key( wp_unslash( $_GET[ $tab_key ] ) ) : 'general';
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}

		$saved          = false;
		$notice_type    = '';
		$notice_message = '';
		if ( isset( $_POST['gelsendiele_settings_action'] ) && 'save' === sanitize_key( wp_unslash( $_POST['gelsendiele_settings_action'] ) ) ) {
			check_admin_referer( 'gelsendiele_save_settings', 'gelsendiele_settings_nonce' );
			$saved = self::save_settings_tab( $tab );
			if ( $saved && 'emails' === $tab && isset( $_POST['gelsensystem_test_template'] ) ) {
				$slug      = sanitize_key( wp_unslash( $_POST['gelsensystem_test_template'] ) );
				$recipient = isset( $_POST['gelsensystem_test_recipient'] ) ? sanitize_email( wp_unslash( $_POST['gelsensystem_test_recipient'] ) ) : '';
				$result    = Gelsensystem_Email::send_test( $slug, $recipient );
				if ( is_wp_error( $result ) || ! $result ) {
					$notice_type    = 'error';
					$notice_message = is_wp_error( $result ) ? $result->get_error_message() : 'Die Test-E-Mail konnte nicht versendet werden. Bitte SMTP-Protokoll prüfen.';
				} else {
					$notice_type    = 'success';
					$notice_message = 'Einstellungen gespeichert und Test-E-Mail versendet.';
				}
			} elseif ( $saved ) {
				$notice_type    = 'success';
				$notice_message = 'Einstellungen wurden gespeichert.';
			}
		}

		$settings = Gelsendiele_Settings::get_all();
		?>
		<div class="<?php echo 'app' === $context ? 'gelsensystem-app-settings' : 'wrap gelsendiele-admin-wrap'; ?>">
			<h1>Gelsensystem Einstellungen</h1>
			<?php if ( $notice_message ) : ?><div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice_message ); ?></p></div><?php endif; ?>
			<nav class="nav-tab-wrapper gelsendiele-settings-tabs" aria-label="Einstellungsbereiche">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<?php $tab_url = 'app' === $context ? add_query_arg( array( 'gd-section' => 'settings', 'gd-settings-tab' => $slug ), $dashboard_url ) : admin_url( 'admin.php?page=' . self::SETTINGS_SLUG . '&tab=' . $slug ); ?>
					<a class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $tab_url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="gelsendiele-settings-panel">
				<?php
				switch ( $tab ) {
					case 'general': self::render_general_tab( $settings ); break;
					case 'opening-hours': self::render_opening_hours_tab( $settings ); break;
					case 'reservations': self::render_reservations_tab( $settings ); break;
					case 'availability': self::render_availability_tab( $settings ); break;
					case 'emails': self::render_emails_tab( $settings ); break;
					case 'form': self::render_form_tab( $settings ); break;
					case 'system-status': self::render_system_status(); break;
					case 'roles': self::render_roles_tab(); break;
					default: self::render_settings_placeholder( $tabs[ $tab ] ); break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/** Benutzer und ihre vordefinierten Gelsensystem-Rechte verwalten. */
	public static function render_app_users() {
		self::guard( 'manage_options' );
		$message = '';
		if ( isset( $_POST['gelsensystem_user_action'] ) && 'update_role' === sanitize_key( wp_unslash( $_POST['gelsensystem_user_action'] ) ) ) {
			check_admin_referer( 'gelsensystem_update_user_role', 'gelsensystem_user_nonce' );
			$user_id = isset( $_POST['gelsensystem_user_id'] ) ? absint( $_POST['gelsensystem_user_id'] ) : 0;
			$role    = isset( $_POST['gelsensystem_user_role'] ) ? sanitize_key( wp_unslash( $_POST['gelsensystem_user_role'] ) ) : '';
			$allowed = array( 'administrator', 'gelsendiele_manager', 'gelsendiele_reservation_staff', 'gdg_service', 'gdg_kitchen', 'gdg_bar', 'subscriber' );
			$target  = get_user_by( 'id', $user_id );
			if ( $target && in_array( $role, $allowed, true ) && ! ( get_current_user_id() === $user_id && 'administrator' !== $role ) ) {
				$target->set_role( $role );
				$message = 'Benutzerrechte wurden aktualisiert.';
			} else {
				$message = 'Die Benutzerrechte konnten nicht geändert werden. Das eigene Administratorkonto ist geschützt.';
			}
		}

		$roles = array(
			'administrator'                   => array( 'Administrator', 'Vollzugriff einschließlich Benutzerverwaltung' ),
			'gelsendiele_manager'             => array( 'Gelsensystem Betriebsleitung', 'Alle Betriebsbereiche und Einstellungen' ),
			'gelsendiele_reservation_staff'   => array( 'Gelsensystem Reservierungen', 'Reservierungen erstellen und bearbeiten' ),
			'gdg_service'                     => array( 'Gelsensystem Service', 'Service und Kasse' ),
			'gdg_kitchen'                     => array( 'Gelsensystem Küche', 'Küchenmonitor' ),
			'gdg_bar'                         => array( 'Gelsensystem Schank', 'Schankmonitor' ),
			'subscriber'                      => array( 'Kein Systemzugriff', 'Nur normales WordPress-Benutzerkonto' ),
		);
		?>
		<div class="gelsensystem-users">
			<div class="gelsensystem-section-heading"><div><span>Administration</span><h1>Benutzer &amp; Rechte</h1><p>Lege pro Benutzer fest, welche Bereiche des Gelsensystems sichtbar und bedienbar sind.</p></div></div>
			<?php if ( $message ) : ?><div class="gd-notice"><strong><?php echo esc_html( $message ); ?></strong></div><?php endif; ?>
			<div class="gelsensystem-user-list">
				<?php foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $account ) :
					$current_role = ! empty( $account->roles ) ? (string) reset( $account->roles ) : 'subscriber';
					if ( ! isset( $roles[ $current_role ] ) ) { $current_role = 'subscriber'; }
				?>
				<form method="post" class="gelsensystem-user-card">
					<?php wp_nonce_field( 'gelsensystem_update_user_role', 'gelsensystem_user_nonce' ); ?>
					<input type="hidden" name="gelsensystem_user_action" value="update_role"><input type="hidden" name="gelsensystem_user_id" value="<?php echo esc_attr( $account->ID ); ?>">
					<div class="gelsensystem-user-identity"><span><?php echo esc_html( strtoupper( mb_substr( $account->display_name, 0, 1 ) ) ); ?></span><div><strong><?php echo esc_html( $account->display_name ); ?></strong><small><?php echo esc_html( $account->user_email ); ?></small></div></div>
					<label><span>Zugriffsprofil</span><select name="gelsensystem_user_role" <?php disabled( get_current_user_id() === (int) $account->ID ); ?>><?php foreach ( $roles as $slug => $role_data ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_role, $slug ); ?>><?php echo esc_html( $role_data[0] ); ?></option><?php endforeach; ?></select><small><?php echo esc_html( $roles[ $current_role ][1] ); ?></small></label>
					<button type="submit" class="button button-primary" <?php disabled( get_current_user_id() === (int) $account->ID ); ?>>Rechte speichern</button>
				</form>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function save_settings_tab( $tab ) {
		$input = isset( $_POST['gelsendiele_settings'] ) && is_array( $_POST['gelsendiele_settings'] ) ? wp_unslash( $_POST['gelsendiele_settings'] ) : array();
		if ( 'general' === $tab ) {
			$result = Gelsendiele_Settings::save_sections( array(
				'general'  => isset( $input['general'] ) ? $input['general'] : array(),
				'branding' => isset( $input['branding'] ) ? $input['branding'] : array(),
			) );
			$mode = Gelsendiele_Settings::get( 'general', 'confirmation_mode', 'manual' );
			update_option( 'gd_auto_confirm_bookings', 'automatic' === $mode ? 1 : 0, false );
			return true === $result || false === $result;
		}
		if ( 'opening-hours' === $tab ) {
			Gelsendiele_Settings::save_sections( array( 'opening_hours' => isset( $input['opening_hours'] ) ? $input['opening_hours'] : array() ) );
			return true;
		}
		if ( 'reservations' === $tab ) {
			Gelsendiele_Settings::save_sections( array( 'reservations' => isset( $input['reservations'] ) ? $input['reservations'] : array() ) );
			return true;
		}
		if ( 'availability' === $tab ) {
			Gelsendiele_Settings::save_sections( array(
				'availability' => array(
					'closed_dates' => array(),
					'rules'        => isset( $input['availability']['rules'] ) && is_array( $input['availability']['rules'] ) ? $input['availability']['rules'] : array(),
				),
			) );
			return true;
		}
		if ( 'emails' === $tab ) {
			Gelsendiele_Settings::save_sections( array( 'emails' => isset( $input['emails'] ) ? $input['emails'] : array() ) );
			Gelsensystem_Email::schedule_existing_reminders();
			return true;
		}
		if ( 'form' === $tab ) {
			Gelsendiele_Settings::save_sections( array( 'form' => isset( $input['form'] ) ? $input['form'] : array() ) );
			return true;
		}
		return false;
	}

	private static function render_general_tab( $settings ) {
		$general  = $settings['general'];
		$branding = $settings['branding'];
		?>
		<form method="post" class="gelsendiele-settings-form">
			<?php wp_nonce_field( 'gelsendiele_save_settings', 'gelsendiele_settings_nonce' ); ?>
			<input type="hidden" name="gelsendiele_settings_action" value="save">
			<section class="gelsendiele-settings-card"><h2>Betrieb</h2><div class="gelsendiele-field-grid">
				<?php self::text_field( 'Betriebsname', 'business_name', $general['business_name'] ); ?>
				<?php self::text_field( 'Telefonnummer', 'phone', $general['phone'], 'tel' ); ?>
				<?php self::text_field( 'Absendername', 'sender_name', $general['sender_name'] ); ?>
				<?php self::text_field( 'Absender-E-Mail', 'sender_email', $general['sender_email'], 'email' ); ?>
				<?php self::text_field( 'Interne Empfänger-E-Mail', 'internal_email', $general['internal_email'], 'email' ); ?>
				<label><span>Zeitzone</span><select name="gelsendiele_settings[general][timezone]"><?php foreach ( timezone_identifiers_list() as $timezone ) : ?><option value="<?php echo esc_attr( $timezone ); ?>" <?php selected( $general['timezone'], $timezone ); ?>><?php echo esc_html( $timezone ); ?></option><?php endforeach; ?></select></label>
				<?php self::text_field( 'Datumsformat', 'date_format', $general['date_format'] ); ?>
				<?php self::text_field( 'Zeitformat', 'time_format', $general['time_format'] ); ?>
				<?php self::text_field( 'Sprache', 'language', $general['language'] ); ?>
				<?php self::text_field( 'Währung', 'currency', $general['currency'] ); ?>
				<label><span>Bestätigung</span><select name="gelsendiele_settings[general][confirmation_mode]"><option value="manual" <?php selected( $general['confirmation_mode'], 'manual' ); ?>>Manuell bestätigen</option><option value="automatic" <?php selected( $general['confirmation_mode'], 'automatic' ); ?>>Automatisch bestätigen</option></select></label>
			</div></section>

			<section class="gelsendiele-settings-card"><h2>Marke & Darstellung</h2><p>Diese Werte bilden die White-Label-Grundlage für einen späteren Verkauf als eigenständige Gastro-Software.</p>
				<div class="gelsendiele-logo-field">
					<input type="hidden" name="gelsendiele_settings[branding][logo_attachment_id]" value="<?php echo esc_attr( $branding['logo_attachment_id'] ); ?>" data-gelsendiele-logo-id>
					<label><span>Logo-URL</span><input type="url" name="gelsendiele_settings[branding][logo_url]" value="<?php echo esc_attr( $branding['logo_url'] ); ?>" data-gelsendiele-logo-url></label>
					<button type="button" class="button" data-gelsendiele-select-logo>Logo aus Mediathek wählen</button>
					<div class="gelsendiele-logo-preview" data-gelsendiele-logo-preview><?php if ( $branding['logo_url'] ) : ?><img src="<?php echo esc_url( $branding['logo_url'] ); ?>" alt="Logo-Vorschau"><?php endif; ?></div>
				</div>
				<div class="gelsendiele-color-grid">
					<?php self::color_field( 'Primärfarbe', 'primary_color', $branding['primary_color'] ); ?>
					<?php self::color_field( 'Sekundärfarbe', 'secondary_color', $branding['secondary_color'] ); ?>
					<?php self::color_field( 'Akzentfarbe', 'accent_color', $branding['accent_color'] ); ?>
					<?php self::color_field( 'Helle Fläche', 'surface_color', $branding['surface_color'] ); ?>
					<?php self::color_field( 'Dunkle Fläche', 'dark_surface_color', $branding['dark_surface_color'] ); ?>
					<label><span>Eckenradius</span><input type="number" min="0" max="40" name="gelsendiele_settings[branding][border_radius]" value="<?php echo esc_attr( $branding['border_radius'] ); ?>"></label>
					<label><span>Standarddarstellung</span><select name="gelsendiele_settings[branding][theme_mode]"><option value="auto" <?php selected( $branding['theme_mode'], 'auto' ); ?>>System</option><option value="light" <?php selected( $branding['theme_mode'], 'light' ); ?>>Hell</option><option value="dark" <?php selected( $branding['theme_mode'], 'dark' ); ?>>Dunkel</option></select></label>
				</div>
			</section>
			<?php self::render_submit_button( 'Allgemeine Einstellungen speichern' ); ?>
		</form>
		<?php
	}

	private static function render_opening_hours_tab( $settings ) {
		$labels = array( 'mon' => 'Montag', 'tue' => 'Dienstag', 'wed' => 'Mittwoch', 'thu' => 'Donnerstag', 'fri' => 'Freitag', 'sat' => 'Samstag', 'sun' => 'Sonntag' );
		?>
		<form method="post" class="gelsendiele-settings-form">
			<?php wp_nonce_field( 'gelsendiele_save_settings', 'gelsendiele_settings_nonce' ); ?><input type="hidden" name="gelsendiele_settings_action" value="save">
			<p>Mehrere Zeitblöcke pro Tag werden unterstützt. Ein Endwert vor dem Start gilt als Öffnung über Mitternacht.</p>
			<div class="gelsendiele-hours-list">
			<?php foreach ( $labels as $day => $label ) : $config = $settings['opening_hours'][ $day ]; $blocks = $config['blocks']; if ( empty( $blocks ) ) { $blocks = array( array( 'start' => '11:00', 'end' => '22:00' ) ); } ?>
				<section class="gelsendiele-day-card" data-gelsendiele-day="<?php echo esc_attr( $day ); ?>">
					<header><strong><?php echo esc_html( $label ); ?></strong><label><input type="hidden" name="gelsendiele_settings[opening_hours][<?php echo esc_attr( $day ); ?>][enabled]" value="0"><input type="checkbox" name="gelsendiele_settings[opening_hours][<?php echo esc_attr( $day ); ?>][enabled]" value="1" <?php checked( $config['enabled'] ); ?>> Geöffnet</label></header>
					<div data-gelsendiele-blocks>
					<?php foreach ( $blocks as $index => $block ) : ?>
						<div class="gelsendiele-time-block"><label>Von <input type="time" name="gelsendiele_settings[opening_hours][<?php echo esc_attr( $day ); ?>][blocks][<?php echo esc_attr( $index ); ?>][start]" value="<?php echo esc_attr( $block['start'] ); ?>"></label><label>Bis <input type="time" name="gelsendiele_settings[opening_hours][<?php echo esc_attr( $day ); ?>][blocks][<?php echo esc_attr( $index ); ?>][end]" value="<?php echo esc_attr( $block['end'] ); ?>"></label><button type="button" class="button-link-delete" data-gelsendiele-remove-block>Entfernen</button></div>
					<?php endforeach; ?>
					</div>
					<button type="button" class="button" data-gelsendiele-add-block>Zeitblock hinzufügen</button>
					<template data-gelsendiele-block-template><div class="gelsendiele-time-block"><label>Von <input type="time" name="gelsendiele_settings[opening_hours][<?php echo esc_attr( $day ); ?>][blocks][__INDEX__][start]" value="11:00"></label><label>Bis <input type="time" name="gelsendiele_settings[opening_hours][<?php echo esc_attr( $day ); ?>][blocks][__INDEX__][end]" value="22:00"></label><button type="button" class="button-link-delete" data-gelsendiele-remove-block>Entfernen</button></div></template>
				</section>
			<?php endforeach; ?>
			</div>
			<?php self::render_submit_button( 'Öffnungszeiten speichern' ); ?>
		</form>
		<?php
	}

	private static function render_reservations_tab( $settings ) {
		$res = $settings['reservations'];
		$fields = array(
			'min_party' => array( 'Minimale Personenzahl', 1, 100 ), 'max_party' => array( 'Maximale Personenzahl', 1, 500 ),
			'lead_minutes' => array( 'Vorlaufzeit in Minuten', 0, 10080 ), 'advance_days' => array( 'Maximale Vorausbuchung in Tagen', 1, 730 ),
			'time_interval' => array( 'Reservierungsintervall in Minuten', 5, 180 ), 'booking_duration' => array( 'Standard-Aufenthaltsdauer in Minuten', 15, 1440 ),
			'buffer_minutes' => array( 'Pufferzeit in Minuten', 0, 240 ), 'max_bookings' => array( 'Maximale gleichzeitige Reservierungen (0 = unbegrenzt)', 0, 1000 ),
			'max_people' => array( 'Maximale Gesamtkapazität pro Zeitfenster (0 = unbegrenzt)', 0, 10000 ),
		);
		?>
		<form method="post" class="gelsendiele-settings-form"><?php wp_nonce_field( 'gelsendiele_save_settings', 'gelsendiele_settings_nonce' ); ?><input type="hidden" name="gelsendiele_settings_action" value="save"><section class="gelsendiele-settings-card"><h2>Reservierungsregeln</h2><div class="gelsendiele-field-grid">
		<?php foreach ( $fields as $key => $field ) : ?><label><span><?php echo esc_html( $field[0] ); ?></span><input type="number" min="<?php echo esc_attr( $field[1] ); ?>" max="<?php echo esc_attr( $field[2] ); ?>" name="gelsendiele_settings[reservations][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $res[ $key ] ); ?>"></label><?php endforeach; ?>
		</div></section><?php self::render_submit_button( 'Reservierungseinstellungen speichern' ); ?></form>
		<?php
	}

	private static function render_availability_tab( $settings ) {
		$rules = isset( $settings['availability']['rules'] ) && is_array( $settings['availability']['rules'] ) ? $settings['availability']['rules'] : array();
		?>
		<form method="post" class="gelsendiele-settings-form" data-gelsendiele-availability-form>
			<?php wp_nonce_field( 'gelsendiele_save_settings', 'gelsendiele_settings_nonce' ); ?>
			<input type="hidden" name="gelsendiele_settings_action" value="save">
			<section class="gelsendiele-settings-card">
				<h2>Sondertage & Verfügbarkeiten</h2>
				<p>Regeln wirken sofort auf den öffentlichen Kalender und die verfügbaren Uhrzeiten. Interne Kommentare werden niemals öffentlich ausgegeben.</p>
				<div class="gelsendiele-rule-summary">
					<span><strong>Ganztägig:</strong> Schließtag oder Betriebsurlaub</span>
					<span><strong>Zeitbezogen:</strong> Sonderöffnung oder gesperrter Zeitraum</span>
					<span><strong>Kapazität:</strong> reduzierte Reservierungen oder Personen</span>
				</div>
			</section>
			<div class="gelsendiele-availability-list" data-gelsendiele-availability-list data-next-index="<?php echo esc_attr( count( $rules ) ); ?>">
				<?php foreach ( $rules as $index => $rule ) : ?>
					<?php self::render_availability_rule( $rule, $index ); ?>
				<?php endforeach; ?>
			</div>
			<div class="gelsendiele-empty-rules" data-gelsendiele-empty-rules <?php echo empty( $rules ) ? '' : 'hidden'; ?>>Noch keine Sonderregel angelegt.</div>
			<p><button type="button" class="button button-secondary" data-gelsendiele-add-rule>Sonderregel hinzufügen</button></p>
			<template data-gelsendiele-rule-template>
				<?php self::render_availability_rule( self::empty_availability_rule(), '__RULE_INDEX__' ); ?>
			</template>
			<?php self::render_submit_button( 'Verfügbarkeiten speichern' ); ?>
		</form>
		<?php
	}

	private static function render_availability_rule( $rule, $index ) {
		$rule  = wp_parse_args( is_array( $rule ) ? $rule : array(), self::empty_availability_rule() );
		$name  = 'gelsendiele_settings[availability][rules][' . $index . ']';
		$types = array(
			'closed'      => 'Ganztägig geschlossen',
			'vacation'    => 'Betriebsurlaub',
			'special_open' => 'Sonderöffnung',
			'blocked_time' => 'Uhrzeit sperren',
			'capacity'    => 'Kapazität reduzieren',
		);
		?>
		<section class="gelsendiele-day-card gelsendiele-availability-rule" data-gelsendiele-rule>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $rule['id'] ); ?>" data-gelsendiele-rule-id>
			<header>
				<label class="gelsendiele-rule-type"><span>Art</span><select name="<?php echo esc_attr( $name ); ?>[type]" data-gelsendiele-rule-type><?php foreach ( $types as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rule['type'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><input type="hidden" name="<?php echo esc_attr( $name ); ?>[enabled]" value="0"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>> Aktiv</label>
				<button type="button" class="button-link-delete" data-gelsendiele-remove-rule>Regel entfernen</button>
			</header>
			<div class="gelsendiele-field-grid">
				<label><span>Von Datum</span><input type="date" name="<?php echo esc_attr( $name ); ?>[start_date]" value="<?php echo esc_attr( $rule['start_date'] ); ?>" required></label>
				<label><span>Bis Datum</span><input type="date" name="<?php echo esc_attr( $name ); ?>[end_date]" value="<?php echo esc_attr( $rule['end_date'] ); ?>" required></label>
				<label data-gelsendiele-rule-group="time"><span>Von Uhrzeit</span><input type="time" name="<?php echo esc_attr( $name ); ?>[start_time]" value="<?php echo esc_attr( $rule['start_time'] ); ?>"></label>
				<label data-gelsendiele-rule-group="time"><span>Bis Uhrzeit</span><input type="time" name="<?php echo esc_attr( $name ); ?>[end_time]" value="<?php echo esc_attr( $rule['end_time'] ); ?>"></label>
				<label data-gelsendiele-rule-group="capacity"><span>Max. gleichzeitige Reservierungen</span><input type="number" min="0" max="1000" name="<?php echo esc_attr( $name ); ?>[max_bookings]" value="<?php echo esc_attr( $rule['max_bookings'] ); ?>"><small>0 = allgemeine Grenze</small></label>
				<label data-gelsendiele-rule-group="capacity"><span>Max. Personen</span><input type="number" min="0" max="10000" name="<?php echo esc_attr( $name ); ?>[max_people]" value="<?php echo esc_attr( $rule['max_people'] ); ?>"><small>0 = allgemeine Grenze</small></label>
				<label class="gelsendiele-field-wide"><span>Nur diese Bereiche öffnen (optional)</span><input type="text" name="<?php echo esc_attr( $name ); ?>[areas]" value="<?php echo esc_attr( implode( ', ', (array) $rule['areas'] ) ); ?>" placeholder="Gastraum, Gastgarten"><small>Kommagetrennt; für die spätere bereichsbezogene Tischvergabe vorbereitet.</small></label>
				<label class="gelsendiele-field-wide"><span>Interner Kommentar</span><textarea name="<?php echo esc_attr( $name ); ?>[comment]" rows="2"><?php echo esc_textarea( $rule['comment'] ); ?></textarea></label>
				<label class="gelsendiele-field-wide"><span>Öffentliche Information (optional)</span><textarea name="<?php echo esc_attr( $name ); ?>[public_message]" rows="2" placeholder="Wird nach Auswahl des Datums im Formular angezeigt."><?php echo esc_textarea( $rule['public_message'] ); ?></textarea></label>
			</div>
		</section>
		<?php
	}

	private static function empty_availability_rule() {
		$today = wp_date( 'Y-m-d' );
		return array(
			'id' => '', 'enabled' => 1, 'type' => 'closed', 'start_date' => $today, 'end_date' => $today,
			'start_time' => '11:00', 'end_time' => '22:00', 'max_bookings' => 0, 'max_people' => 0,
			'areas' => array(), 'comment' => '', 'public_message' => '',
		);
	}

	private static function render_emails_tab( $settings ) {
		$emails     = $settings['emails'];
		$templates  = $emails['templates'];
		$current     = wp_get_current_user();
		$test_email  = is_email( $current->user_email ) ? $current->user_email : $settings['general']['internal_email'];
		$placeholders = array( 'guest_name', 'date', 'time', 'party', 'table', 'area', 'phone', 'email', 'message', 'allergies', 'highchair', 'dog', 'booking_id', 'business_name', 'cancellation_link' );
		?>
		<form method="post" class="gelsendiele-settings-form gelsensystem-email-settings" data-gelsensystem-email-settings>
			<?php wp_nonce_field( 'gelsendiele_save_settings', 'gelsendiele_settings_nonce' ); ?>
			<input type="hidden" name="gelsendiele_settings_action" value="save">
			<section class="gelsendiele-settings-card">
				<h2>E-Mail-Vorlagen</h2>
				<p>Alle Benachrichtigungen werden zentral vom Gelsensystem verwaltet.</p>
				<div class="gelsendiele-field-grid">
					<label><span>Erinnerung vor dem Termin</span><input type="number" min="1" max="336" name="gelsendiele_settings[emails][reminder_hours]" value="<?php echo esc_attr( $emails['reminder_hours'] ); ?>"><small>Stunden vor dem Termin; die Erinnerung muss zusätzlich in ihrer Vorlage aktiviert sein.</small></label>
					<label><span>Empfänger für Test-E-Mails</span><input type="email" name="gelsensystem_test_recipient" value="<?php echo esc_attr( $test_email ); ?>"></label>
				</div>
				<div class="gelsensystem-placeholder-list" aria-label="Verfügbare Platzhalter">
					<?php foreach ( $placeholders as $placeholder ) : ?><code>{<?php echo esc_html( $placeholder ); ?>}</code><?php endforeach; ?>
				</div>
				<p class="description">Der Platzhalter {cancellation_link} bleibt leer, bis ein gesichertes Gast-Stornomodul aktiviert wird.</p>
			</section>

			<div class="gelsensystem-template-list">
			<?php foreach ( $templates as $slug => $template ) : $name = 'gelsendiele_settings[emails][templates][' . $slug . ']'; ?>
				<details class="gelsendiele-settings-card gelsensystem-email-template" <?php echo 'internal_new' === $slug ? 'open' : ''; ?>>
					<summary><strong><?php echo esc_html( $template['label'] ); ?></strong><span><?php echo ! empty( $template['enabled'] ) ? 'Aktiv' : 'Deaktiviert'; ?></span></summary>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $template['label'] ); ?>">
					<div class="gelsendiele-field-grid gelsensystem-template-grid">
						<label class="gelsensystem-inline-check"><input type="hidden" name="<?php echo esc_attr( $name ); ?>[enabled]" value="0"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! empty( $template['enabled'] ) ); ?>> <span>Vorlage aktiv</span></label>
						<label><span>Format</span><select name="<?php echo esc_attr( $name ); ?>[format]"><option value="text" <?php selected( $template['format'], 'text' ); ?>>Text</option><option value="html" <?php selected( $template['format'], 'html' ); ?>>HTML</option></select></label>
						<label><span>Empfänger</span><select name="<?php echo esc_attr( $name ); ?>[recipient]" data-gelsensystem-recipient><option value="internal" <?php selected( $template['recipient'], 'internal' ); ?>>Interne Empfänger-E-Mail</option><option value="guest" <?php selected( $template['recipient'], 'guest' ); ?>>Gast-E-Mail</option><option value="custom" <?php selected( $template['recipient'], 'custom' ); ?>>Eigene Adresse</option></select></label>
						<label><span>Eigene Empfängeradresse</span><input type="email" name="<?php echo esc_attr( $name ); ?>[custom_recipient]" value="<?php echo esc_attr( $template['custom_recipient'] ); ?>" placeholder="reservierung@example.at"></label>
						<label class="gelsendiele-field-wide"><span>Betreff</span><input type="text" name="<?php echo esc_attr( $name ); ?>[subject]" value="<?php echo esc_attr( $template['subject'] ); ?>"></label>
						<label class="gelsendiele-field-wide"><span>Inhalt</span><textarea name="<?php echo esc_attr( $name ); ?>[body]" rows="9"><?php echo esc_textarea( $template['body'] ); ?></textarea></label>
					</div>
					<p><button type="submit" class="button" name="gelsensystem_test_template" value="<?php echo esc_attr( $slug ); ?>">Diese Vorlage speichern & testen</button></p>
				</details>
			<?php endforeach; ?>
			</div>
			<?php self::render_submit_button( 'E-Mail-Einstellungen speichern' ); ?>
		</form>
		<?php
	}

	private static function render_form_tab( $settings ) {
		$form   = $settings['form'];
		$fields = $form['fields'];
		?>
		<form method="post" class="gelsendiele-settings-form" data-gelsensystem-form-settings>
			<?php wp_nonce_field( 'gelsendiele_save_settings', 'gelsendiele_settings_nonce' ); ?>
			<input type="hidden" name="gelsendiele_settings_action" value="save">
			<section class="gelsendiele-settings-card">
				<h2>Formulartexte & Darstellung</h2>
				<div class="gelsendiele-field-grid">
					<label><span>Überschrift</span><input type="text" name="gelsendiele_settings[form][headline]" value="<?php echo esc_attr( $form['headline'] ); ?>"></label>
					<label><span>Buttontext</span><input type="text" name="gelsendiele_settings[form][button_text]" value="<?php echo esc_attr( $form['button_text'] ); ?>"></label>
					<label class="gelsendiele-field-wide"><span>Einleitung</span><textarea name="gelsendiele_settings[form][intro]" rows="2"><?php echo esc_textarea( $form['intro'] ); ?></textarea></label>
					<label class="gelsendiele-field-wide"><span>Erfolgsmeldung</span><textarea name="gelsendiele_settings[form][success_text]" rows="2"><?php echo esc_textarea( $form['success_text'] ); ?></textarea></label>
					<label class="gelsendiele-field-wide"><span>Allgemeine Fehlermeldung</span><textarea name="gelsendiele_settings[form][error_text]" rows="2"><?php echo esc_textarea( $form['error_text'] ); ?></textarea></label>
					<label class="gelsendiele-field-wide"><span>Datenschutztext</span><textarea name="gelsendiele_settings[form][privacy_text]" rows="3"><?php echo esc_textarea( $form['privacy_text'] ); ?></textarea></label>
					<label><span>Maximale Formularbreite (px)</span><input type="number" min="320" max="1400" name="gelsendiele_settings[form][width]" value="<?php echo esc_attr( $form['width'] ); ?>"></label>
					<label><span>Darstellung</span><select name="gelsendiele_settings[form][theme_mode]"><option value="inherit" <?php selected( $form['theme_mode'], 'inherit' ); ?>>Betriebseinstellung übernehmen</option><option value="light" <?php selected( $form['theme_mode'], 'light' ); ?>>Hell</option><option value="dark" <?php selected( $form['theme_mode'], 'dark' ); ?>>Dunkel</option></select></label>
					<label><span>Optionale Primärfarbe</span><input type="text" pattern="#[0-9a-fA-F]{6}" name="gelsendiele_settings[form][primary_color]" value="<?php echo esc_attr( $form['primary_color'] ); ?>" placeholder="Markenfarbe übernehmen"></label>
					<label><span>Optionale Flächenfarbe</span><input type="text" pattern="#[0-9a-fA-F]{6}" name="gelsendiele_settings[form][surface_color]" value="<?php echo esc_attr( $form['surface_color'] ); ?>" placeholder="Markenfarbe übernehmen"></label>
					<label><span>Optionale Textfarbe</span><input type="text" pattern="#[0-9a-fA-F]{6}" name="gelsendiele_settings[form][text_color]" value="<?php echo esc_attr( $form['text_color'] ); ?>" placeholder="Automatisch"></label>
				</div>
			</section>

			<section class="gelsendiele-settings-card">
				<h2>Formularfelder</h2>
				<p>Datum, Uhrzeit und Personenzahl sind für eine buchbare Reservierung technisch erforderlich und bleiben deshalb geschützt.</p>
				<div class="gelsensystem-form-fields">
					<div class="gelsensystem-form-field gelsensystem-form-field-head"><strong>Feld</strong><strong>Beschriftung</strong><strong>Anzeigen</strong><strong>Pflichtfeld</strong></div>
					<?php foreach ( $fields as $slug => $field ) : $name = 'gelsendiele_settings[form][fields][' . $slug . ']'; $locked = ! empty( $field['locked'] ); ?>
					<div class="gelsensystem-form-field" data-gelsensystem-form-field>
						<strong><?php echo esc_html( $slug ); ?></strong>
						<label><span class="screen-reader-text">Beschriftung <?php echo esc_html( $slug ); ?></span><input type="text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $field['label'] ); ?>"></label>
						<label><?php if ( $locked ) : ?><input type="hidden" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1"><input type="checkbox" checked disabled><span>Fest</span><?php else : ?><input type="hidden" name="<?php echo esc_attr( $name ); ?>[enabled]" value="0"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! empty( $field['enabled'] ) ); ?> data-gelsensystem-field-enabled><span>An</span><?php endif; ?></label>
						<label><?php if ( $locked ) : ?><input type="hidden" name="<?php echo esc_attr( $name ); ?>[required]" value="1"><input type="checkbox" checked disabled><span>Fest</span><?php else : ?><input type="hidden" name="<?php echo esc_attr( $name ); ?>[required]" value="0"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?> data-gelsensystem-field-required><span>Pflicht</span><?php endif; ?></label>
					</div>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="gelsendiele-settings-card"><h2>Einbindung</h2><p>Bestehender Shortcode:</p><p><code>[gelsendiele_reservierungsformular]</code></p><p>Kompatibler Alias: <code>[booking-form]</code></p></section>
			<?php self::render_submit_button( 'Formulareinstellungen speichern' ); ?>
		</form>
		<?php
	}

	private static function render_roles_tab() {
		$roles = array(
			'Administrator' => 'Vollständiger Zugriff',
			'Gelsensystem Betriebsleitung' => 'Reservierungen, Gastro-Module, Statistiken und Einstellungen',
			'Gelsensystem Service' => 'Service und Kasse',
			'Gelsensystem Küche' => 'Küchenmonitor',
			'Gelsensystem Schank' => 'Schankmonitor',
			'Gelsensystem Reservierungen' => 'Reservierungen erstellen und bearbeiten',
		);
		echo '<section class="gelsendiele-settings-card"><h2>Rollenmodell</h2><div class="gelsendiele-status-list">';
		foreach ( $roles as $role => $description ) {
			echo '<div><strong>' . esc_html( $role ) . '</strong><span>' . esc_html( $description ) . '</span></div>';
		}
		echo '</div></section>';
	}

	private static function render_system_status() {
		global $wpdb;
		$tables = array( 'tables', 'menu_categories', 'menu_items', 'orders', 'order_items', 'payments' );
		$db_ok  = true;
		foreach ( $tables as $table ) {
			$name = $wpdb->prefix . 'gdg_' . $table;
			if ( $name !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) ) {
				$db_ok = false;
				break;
			}
		}
		$booking_counts = wp_count_posts( defined( 'RTB_BOOKING_POST_TYPE' ) ? RTB_BOOKING_POST_TYPE : 'rtb-booking' );
		$booking_total  = array_sum( array_map( 'absint', (array) $booking_counts ) );
		$items = array(
			'Plugin-Version' => GELSENDIELE_VERSION,
			'Migrationsversion' => get_option( Gelsendiele_Migrator::VERSION_OPTION, 'nicht ausgeführt' ),
			'WordPress' => get_bloginfo( 'version' ),
			'PHP' => PHP_VERSION,
			'Zeitzone' => wp_timezone_string(),
			'Datenbanktabellen' => $db_ok ? 'Vollständig' : 'Unvollständig',
			'Reservierungen' => (string) $booking_total,
			'Letzter Migrationsfehler' => get_option( Gelsendiele_Migrator::ERROR_OPTION, 'Keiner' ),
		);
		echo '<section class="gelsendiele-settings-card"><h2>Systemstatus</h2><div class="gelsendiele-status-list">';
		foreach ( $items as $label => $value ) {
			echo '<div><strong>' . esc_html( $label ) . '</strong><span>' . esc_html( $value ) . '</span></div>';
		}
		echo '</div><p class="description">Der Bericht enthält keine Passwörter, Nonces oder Zugangsdaten.</p></section>';
	}

	private static function render_settings_placeholder( $title ) {
		echo '<section class="gelsendiele-settings-card"><h2>' . esc_html( $title ) . '</h2><p>Die zentrale Datenstruktur für diesen Bereich ist vorbereitet. Die vollständige Oberfläche folgt in einer eigenen versionierten Entwicklungsstufe, damit bestehende Produktivfunktionen kontrolliert migriert werden.</p></section>';
	}

	private static function text_field( $label, $key, $value, $type = 'text' ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="gelsendiele_settings[general][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"></label>';
	}

	private static function color_field( $label, $key, $value ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input type="color" name="gelsendiele_settings[branding][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"></label>';
	}

	private static function render_submit_button( $text ) {
		if ( is_admin() && function_exists( 'submit_button' ) ) {
			submit_button( $text );
			return;
		}
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html( $text ) . '</button></p>';
	}

	private static function workspace_url( $view ) {
		$page_id = (int) get_option( 'gdg_page_' . sanitize_key( $view ), 0 );
		return $page_id ? get_permalink( $page_id ) : '';
	}

	private static function guard( $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'gelsendiele-dashboard' ) );
		}
	}
}
