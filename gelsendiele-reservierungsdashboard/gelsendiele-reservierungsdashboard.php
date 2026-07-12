<?php
/**
 * Plugin Name: Gelsensystem
 * Plugin URI: https://github.com/LEECHER1/Gelsensystem
 * Description: Zentrales Reservierungs-, Service-, Küchen- und Kassensystem für Gastronomiebetriebe.
 * Version: 2.4.3
 * Author: Andreas Schwarz / Gelsensystem
 * Text Domain: gelsendiele-dashboard
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

defined( 'GELSENDIELE_VERSION' ) || define( 'GELSENDIELE_VERSION', '2.4.3' );
defined( 'GELSENDIELE_FILE' ) || define( 'GELSENDIELE_FILE', __FILE__ );
defined( 'GELSENDIELE_DIR' ) || define( 'GELSENDIELE_DIR', plugin_dir_path( __FILE__ ) );
defined( 'GELSENDIELE_URL' ) || define( 'GELSENDIELE_URL', plugin_dir_url( __FILE__ ) );

require_once GELSENDIELE_DIR . 'includes/class-gelsendiele-settings.php';
require_once GELSENDIELE_DIR . 'includes/class-gelsendiele-availability.php';
require_once GELSENDIELE_DIR . 'includes/class-gelsensystem-email.php';
require_once GELSENDIELE_DIR . 'includes/class-gelsendiele-migrator.php';
require_once GELSENDIELE_DIR . 'includes/class-gelsendiele-admin.php';
require_once GELSENDIELE_DIR . 'includes/class-gelsendiele-github-updater.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-gd-reservation-engine.php';
require_once plugin_dir_path( __FILE__ ) . 'modules/gastro/gelsendiele-gastro-system.php';

Gelsendiele_Migrator::bootstrap();
Gelsendiele_Admin::bootstrap();
Gelsensystem_Email::bootstrap();
Gelsendiele_GitHub_Updater::bootstrap();

final class Gelsendiele_Reservierungsdashboard {
    const VERSION = GELSENDIELE_VERSION;
    const SHORTCODE = 'gelsendiele_reservierungen';
    const PAGE_OPTION = 'gd_reservierungsdashboard_page_id';
    const TABLE_META = '_gd_table_number';
    const COMMENT_META = '_gd_internal_comment';
    const AUTO_CONFIRM_OPTION = 'gd_auto_confirm_bookings';
    const REFRESH_INTERVAL_OPTION = 'gd_auto_refresh_interval';
    const WHATSAPP_TEMPLATE_OPTION = 'gd_whatsapp_message_template';
    const TABLE_COUNT_OPTION = 'gd_table_count';
    const TABLE_DEFAULT_CAPACITY_OPTION = 'gd_table_default_capacity';
    const TABLE_CAPACITY_OVERRIDES_OPTION = 'gd_table_capacity_overrides';

    /** Gewünschter Status während einer manuell angelegten Reservierung. */
    private $manual_creation_status = null;

    public function __construct() {
        // Bei Updates einer bereits aktiven Installation wird der Aktivierungs-Hook
        // nicht erneut ausgeführt. Deshalb den Standardwert auch hier dauerhaft anlegen.
        if ( false === get_option( self::TABLE_COUNT_OPTION, false ) ) {
            add_option( self::TABLE_COUNT_OPTION, 30, '', false );
        }
        if ( false === get_option( self::TABLE_DEFAULT_CAPACITY_OPTION, false ) ) {
            add_option( self::TABLE_DEFAULT_CAPACITY_OPTION, 5, '', false );
        }
        if ( false === get_option( self::TABLE_CAPACITY_OVERRIDES_OPTION, false ) ) {
            add_option( self::TABLE_CAPACITY_OVERRIDES_OPTION, array(), '', false );
        }

        add_shortcode( self::SHORTCODE, array( $this, 'render_dashboard' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_nopriv_gd_dashboard_login', array( $this, 'ajax_dashboard_login' ) );
        add_action( 'wp_ajax_gd_dashboard_login', array( $this, 'ajax_dashboard_login' ) );
        add_action( 'wp_ajax_gd_update_booking_status', array( $this, 'ajax_update_status' ) );
        add_action( 'wp_ajax_gd_delete_booking', array( $this, 'ajax_delete_booking' ) );
        add_action( 'wp_ajax_gd_restore_booking', array( $this, 'ajax_restore_booking' ) );
        add_action( 'wp_ajax_gd_delete_booking_permanently', array( $this, 'ajax_delete_booking_permanently' ) );
        add_action( 'wp_ajax_gd_get_bookings', array( $this, 'ajax_get_bookings' ) );
        add_action( 'wp_ajax_gd_update_table_number', array( $this, 'ajax_update_table_number' ) );
        add_action( 'wp_ajax_gd_update_internal_comment', array( $this, 'ajax_update_internal_comment' ) );
        add_action( 'wp_ajax_gd_update_auto_confirm', array( $this, 'ajax_update_auto_confirm' ) );
        add_action( 'wp_ajax_gd_update_refresh_interval', array( $this, 'ajax_update_refresh_interval' ) );
        add_action( 'wp_ajax_gd_update_whatsapp_template', array( $this, 'ajax_update_whatsapp_template' ) );
        add_action( 'wp_ajax_gd_update_table_count', array( $this, 'ajax_update_table_count' ) );
        add_action( 'wp_ajax_gd_update_table_capacity_settings', array( $this, 'ajax_update_table_capacity_settings' ) );
        add_action( 'wp_ajax_gd_get_table_availability', array( $this, 'ajax_get_table_availability' ) );
        add_action( 'wp_ajax_gd_get_manual_booking_slots', array( $this, 'ajax_get_manual_booking_slots' ) );
        add_action( 'wp_ajax_gd_get_manual_table_availability', array( $this, 'ajax_get_manual_table_availability' ) );
        add_action( 'wp_ajax_gd_create_manual_booking', array( $this, 'ajax_create_manual_booking' ) );
        add_action( 'wp_ajax_gd_update_manual_booking', array( $this, 'ajax_update_manual_booking' ) );
        // Automatische Bestätigung in den nativen Buchungsablauf des Five-Star-Plugins integrieren.
        add_filter( 'rtb_determine_booking_status', array( $this, 'maybe_auto_confirm_booking' ), 999, 2 );
        add_action( 'admin_post_gd_export_bookings_csv', array( $this, 'export_bookings_csv' ) );
        add_action( 'admin_post_gd_export_bookings_xlsx', array( $this, 'export_bookings_xlsx' ) );
        // Der zentrale Gelsendiele-Menübaum ersetzt die frühere, Five-Star-nahe
        // Dashboard-Unterseite. Die Render-Methode bleibt für Rückwärtskompatibilität erhalten.

        // Tischnummer auch in der normalen WordPress-Verwaltung anzeigen.
        add_action( 'add_meta_boxes', array( $this, 'register_table_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_table_meta_box' ) );
        add_filter( 'manage_' . $this->booking_post_type() . '_posts_columns', array( $this, 'add_table_admin_column' ) );
        add_action( 'manage_' . $this->booking_post_type() . '_posts_custom_column', array( $this, 'render_table_admin_column' ), 10, 2 );
        add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );

        // Eigenständiger App-Modus für die zentrale Gelsensystem-Oberfläche.
        add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_on_dashboard' ) );
        add_filter( 'template_include', array( $this, 'use_standalone_dashboard_template' ), 999 );
        add_filter( 'body_class', array( $this, 'add_dashboard_body_class' ) );
        add_action( 'template_redirect', array( $this, 'serve_pwa_assets' ), -1 );

        // Verwaltungsseite zuverlässig vor Suchmaschinen und der XML-Sitemap verbergen.
        add_filter( 'wp_robots', array( $this, 'add_dashboard_robots' ) );
        add_action( 'template_redirect', array( $this, 'send_dashboard_robots_header' ), 0 );
        add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_dashboard_from_sitemap' ), 10, 2 );
    }

    public static function activate() {
        GD_Reservation_Engine::activate();
        Gelsendiele_Migrator::activate();
        if ( false === get_option( self::TABLE_COUNT_OPTION, false ) ) {
            add_option( self::TABLE_COUNT_OPTION, 30, '', false );
        }
        if ( false === get_option( self::TABLE_DEFAULT_CAPACITY_OPTION, false ) ) {
            add_option( self::TABLE_DEFAULT_CAPACITY_OPTION, 5, '', false );
        }
        if ( false === get_option( self::TABLE_CAPACITY_OVERRIDES_OPTION, false ) ) {
            add_option( self::TABLE_CAPACITY_OVERRIDES_OPTION, array(), '', false );
        }

        $existing = get_page_by_path( 'reservierungsverwaltung' );
        if ( $existing ) {
            update_option( self::PAGE_OPTION, (int) $existing->ID );
            return;
        }

        $page_id = wp_insert_post( array(
            'post_title'   => 'Gelsensystem',
            'post_name'    => 'reservierungsverwaltung',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[' . self::SHORTCODE . ']',
        ) );

        if ( ! is_wp_error( $page_id ) ) {
            update_option( self::PAGE_OPTION, (int) $page_id );
        }
    }

    public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! empty( $_REQUEST['gd_dashboard_login'] ) && $user instanceof WP_User && user_can( $user, 'manage_bookings' ) ) {
            return $this->dashboard_url();
        }
        return $redirect_to;
    }

    /**
     * Verbirgt die schwarze WordPress-Adminleiste ausschließlich auf der
     * Reservierungsverwaltung. Alle anderen Seiten bleiben unverändert.
     */
    public function hide_admin_bar_on_dashboard( $show ) {
        return $this->is_dashboard_page() ? false : $show;
    }

    /**
     * Nutzt für die Verwaltungsseite ein eigenes, schlankes App-Template ohne
     * Theme-Header, Theme-Footer und WordPress-Adminleiste.
     */
    public function use_standalone_dashboard_template( $template ) {
        if ( ! $this->is_dashboard_page() ) {
            return $template;
        }

        $app_template = plugin_dir_path( __FILE__ ) . 'templates/dashboard-app.php';
        return file_exists( $app_template ) ? $app_template : $template;
    }

    public function add_dashboard_body_class( $classes ) {
        if ( $this->is_dashboard_page() ) {
            $classes[] = 'gd-standalone-app';
            if ( is_user_logged_in() && current_user_can( 'manage_bookings' ) ) {
                $classes[] = 'gd-dashboard-active';
            }
        }
        return $classes;
    }

    /**
     * Liefert Manifest und einen bewusst nicht-cachenden Service Worker aus.
     * Reservierungsdaten werden nie offline gespeichert.
     */
    public function serve_pwa_assets() {
        if ( isset( $_GET['gd-pwa-manifest'] ) ) {
            $this->output_pwa_manifest();
        }

        if ( isset( $_GET['gd-pwa-sw'] ) ) {
            $this->output_pwa_service_worker();
        }
    }

    private function output_pwa_manifest() {
        $icon_192 = add_query_arg( 'ver', self::VERSION, plugin_dir_url( __FILE__ ) . 'assets/gelsendiele-app-icon-192.png' );
        $icon_512 = add_query_arg( 'ver', self::VERSION, plugin_dir_url( __FILE__ ) . 'assets/gelsendiele-app-icon-512.png' );
        $business_name = Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' );
        $theme_color   = Gelsendiele_Settings::get( 'branding', 'dark_surface_color', '#08110b' );

        nocache_headers();
        header( 'Content-Type: application/manifest+json; charset=UTF-8' );

        echo wp_json_encode( array(
            'id'               => trailingslashit( (string) wp_parse_url( $this->dashboard_url(), PHP_URL_PATH ) ),
            'name'             => 'Gelsensystem · ' . $business_name,
            'short_name'       => 'Gelsensystem',
            'description'      => 'Gastronomiebetrieb ' . $business_name . ' verwalten',
            'start_url'        => $this->dashboard_url(),
            'scope'            => trailingslashit( (string) wp_parse_url( $this->dashboard_url(), PHP_URL_PATH ) ),
            'display'          => 'standalone',
            'display_override' => array( 'standalone', 'minimal-ui' ),
            'categories'       => array( 'business', 'productivity' ),
            'shortcuts'        => array(
                array(
                    'name'      => 'Offene Reservierungen',
                    'short_name'=> 'Offen',
                    'url'       => add_query_arg( 'gd-view', 'pending', $this->dashboard_url() ),
                ),
                array(
                    'name'      => 'Heutige Reservierungen',
                    'short_name'=> 'Heute',
                    'url'       => add_query_arg( 'gd-view', 'today', $this->dashboard_url() ),
                ),
            ),
            'background_color' => $theme_color,
            'theme_color'      => $theme_color,
            'orientation'      => 'any',
            'icons'            => array(
                array(
                    'src'     => $icon_192,
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ),
                array(
                    'src'     => $icon_512,
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ),
            ),
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        exit;
    }

    private function output_pwa_service_worker() {
        nocache_headers();
        header( 'Content-Type: application/javascript; charset=UTF-8' );
        header( 'Service-Worker-Allowed: /' );

        echo "self.addEventListener('install',function(){self.skipWaiting();});\n";
        echo "self.addEventListener('activate',function(event){event.waitUntil(self.clients.claim());});\n";
        echo "self.addEventListener('fetch',function(event){event.respondWith(fetch(event.request));});\n";
        exit;
    }

    /**
     * Fügt auf der Verwaltungsseite ein restriktives Robots-Meta-Tag ein.
     */
    public function add_dashboard_robots( $robots ) {
        if ( ! $this->is_dashboard_page() ) {
            return $robots;
        }

        $robots['noindex']   = true;
        $robots['nofollow']  = true;
        $robots['noarchive'] = true;
        $robots['nosnippet'] = true;

        return $robots;
    }

    /**
     * Sendet zusätzlich einen X-Robots-Tag HTTP-Header.
     */
    public function send_dashboard_robots_header() {
        if ( $this->is_dashboard_page() && ! headers_sent() ) {
            header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true );
        }
    }

    /**
     * Entfernt die automatisch erstellte Verwaltungsseite aus der WordPress XML-Sitemap.
     */
    public function exclude_dashboard_from_sitemap( $args, $post_type ) {
        if ( 'page' !== $post_type ) {
            return $args;
        }

        $page_id = (int) get_option( self::PAGE_OPTION );
        if ( $page_id ) {
            $excluded = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] )
                ? $args['post__not_in']
                : array();
            $excluded[] = $page_id;
            $args['post__not_in'] = array_values( array_unique( array_map( 'absint', $excluded ) ) );
        }

        return $args;
    }

    public function enqueue_assets() {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
            return;
        }

        wp_enqueue_style(
            'gd-reservierungsdashboard',
            plugin_dir_url( __FILE__ ) . 'assets/dashboard.css',
            array(),
            self::VERSION
        );

        $app_section = isset( $_GET['gd-section'] ) ? sanitize_key( wp_unslash( $_GET['gd-section'] ) ) : 'reservations';
        if ( in_array( $app_section, array( 'settings', 'users' ), true ) ) {
            wp_enqueue_style( 'gelsendiele-admin', GELSENDIELE_URL . 'admin/assets/settings.css', array( 'gd-reservierungsdashboard' ), self::VERSION );
            wp_enqueue_script( 'gelsendiele-settings', GELSENDIELE_URL . 'admin/assets/settings.js', array(), self::VERSION, true );
        }

        wp_enqueue_script(
            'gd-reservierungsdashboard',
            plugin_dir_url( __FILE__ ) . 'assets/dashboard.js',
            array(),
            self::VERSION,
            true
        );

        wp_localize_script( 'gd-reservierungsdashboard', 'GDReservations', array(
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'gd_reservierungsdashboard' ),
            'loginNonce'       => wp_create_nonce( 'gd_dashboard_login' ),
            'dashboardUrl'     => $this->dashboard_url(),
            'autoConfirm'      => (bool) get_option( self::AUTO_CONFIRM_OPTION, false ),
            'refreshInterval'   => $this->get_refresh_interval(),
            'whatsappTemplate'  => $this->get_whatsapp_template(),
            'tableCount'          => $this->get_table_count(),
            'tableDefaultCapacity'=> $this->get_table_default_capacity(),
            'tableCapacityOverrides' => $this->get_table_capacity_overrides(),
            'today'            => current_datetime()->format( 'Y-m-d' ),
            'openWeekdays'     => $this->get_open_weekdays(),
            'dateExceptions'   => $this->get_date_exceptions(),
            'csvExportUrl'     => wp_nonce_url( admin_url( 'admin-post.php?action=gd_export_bookings_csv' ), 'gd_export_bookings' ),
            'xlsxExportUrl'    => wp_nonce_url( admin_url( 'admin-post.php?action=gd_export_bookings_xlsx' ), 'gd_export_bookings' ),
            'confirmDelete'    => 'Reservierung wirklich in den Papierkorb verschieben?',
            'autoConfirmOn'    => 'Neue Reservierungen werden automatisch bestätigt.',
            'autoConfirmOff'   => 'Neue Reservierungen müssen manuell bestätigt werden.',
            'error'            => 'Die Aktion konnte nicht ausgeführt werden.',
            'updated'          => 'Reservierungen aktualisiert.',
            'offline'          => 'Keine Internetverbindung.',
            'online'           => 'Verbindung wiederhergestellt.',
            'installReady'     => 'App kann installiert werden.',
            'pwaServiceWorker' => add_query_arg( 'gd-pwa-sw', '1', home_url( '/' ) ),
            'pwaScope'         => trailingslashit( (string) wp_parse_url( $this->dashboard_url(), PHP_URL_PATH ) ),
        ) );
    }

    public function render_dashboard() {
        if ( ! is_user_logged_in() ) {
            return $this->render_login();
        }

        $app_section = isset( $_GET['gd-section'] ) ? sanitize_key( wp_unslash( $_GET['gd-section'] ) ) : 'reservations';
        if ( ! in_array( $app_section, array( 'reservations', 'settings', 'users' ), true ) ) {
            $app_section = 'reservations';
        }
        $section_capability = 'settings' === $app_section ? 'gelsendiele_manage_settings' : ( 'users' === $app_section ? 'manage_options' : 'manage_bookings' );
        if ( ! current_user_can( $section_capability ) ) {
            return '<div class="gd-dashboard-shell"><div class="gd-notice gd-notice-error"><strong>Kein Zugriff.</strong><br>Dieses Benutzerkonto darf diesen Bereich des Gelsensystems nicht verwenden.</div></div>';
        }

        if ( 'reservations' !== $app_section ) {
            return $this->render_central_app_section( $app_section );
        }

        if ( ! $this->booking_post_type_exists() ) {
            return '<div class="gd-dashboard-shell"><div class="gd-notice gd-notice-error"><strong>Reservierungsmodul konnte nicht gestartet werden.</strong><br>Bitte Gelsensystem erneut aktivieren.</div></div>';
        }

        $allowed_views = array( 'pending', 'today', 'upcoming', 'confirmed', 'all', 'trash' );
        $default_view  = isset( $_GET['gd-view'] ) ? sanitize_key( wp_unslash( $_GET['gd-view'] ) ) : 'pending';
        if ( ! in_array( $default_view, $allowed_views, true ) ) {
            $default_view = 'pending';
        }

        $user          = wp_get_current_user();
        $logout_url    = wp_logout_url( $this->dashboard_url() );
        $business_name = Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' );
        $branding      = Gelsendiele_Settings::get( 'branding', null, array() );
        $brand_style   = Gelsendiele_Settings::css_variables()
            . '--gd-green:' . $branding['primary_color'] . ';'
            . '--gd-green-dark:' . $branding['secondary_color'] . ';'
            . '--gd-bg:' . $branding['surface_color'] . ';';
        ob_start();
        ?>
        <div class="gd-dashboard-shell gd-has-central-nav" data-default-view="<?php echo esc_attr( $default_view ); ?>" data-app-section="reservations" style="<?php echo esc_attr( $brand_style ); ?>">
            <div id="gd-network-banner" class="gd-network-banner" hidden aria-live="polite">Keine Internetverbindung</div>
            <?php $this->render_central_navigation( 'reservations' ); ?>

            <header class="gd-mobile-appbar">
                <div class="gd-mobile-brand">
                    <?php echo $this->render_brand_logo( 'gd-mobile-logo', 'G', $business_name ); ?>
                    <div>
                        <small>Gelsensystem · <?php echo esc_html( $business_name ); ?></small>
                        <strong id="gd-mobile-title">Reservierungen</strong>
                    </div>
                </div>
                <div class="gd-appbar-actions">
                    <button type="button" class="gd-icon-button gd-add-booking-button" data-open-manual-booking aria-label="Reservierung manuell hinzufügen" title="Neue Reservierung">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                    <button type="button" class="gd-icon-button gd-theme-button" data-theme-button aria-label="Darstellung wechseln" title="Hell-/Dunkelmodus">
                        <svg class="gd-theme-icon gd-theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8z"/></svg>
                        <svg class="gd-theme-icon gd-theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </button>
                    <button type="button" class="gd-icon-button" data-refresh aria-label="Reservierungen aktualisieren">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8.1 8.1 0 0 0-14.7-4.7L3 9m0 0V4m0 5h5M4 13a8.1 8.1 0 0 0 14.7 4.7L21 15m0 0v5m0-5h-5"/></svg>
                    </button>
                    <button type="button" class="gd-icon-button" data-open-more aria-label="Weitere Einstellungen öffnen">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg>
                    </button>
                </div>
            </header>

            <header class="gd-dashboard-header">
                <div>
                    <span class="gd-eyebrow">Gelsensystem · <?php echo esc_html( $business_name ); ?></span>
                    <h1>Reservierungen</h1>
                    <p>Anfragen schnell prüfen, bestätigen oder ablehnen.</p>
                </div>
                <div class="gd-user-menu">
                    <button type="button" class="gd-icon-button gd-theme-button gd-desktop-theme-button" data-theme-button aria-label="Darstellung wechseln" title="Hell-/Dunkelmodus">
                        <svg class="gd-theme-icon gd-theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8z"/></svg>
                        <svg class="gd-theme-icon gd-theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </button>
                    <span class="gd-user-avatar"><?php echo esc_html( strtoupper( mb_substr( $user->display_name, 0, 1 ) ) ); ?></span>
                    <div>
                        <strong><?php echo esc_html( $user->display_name ); ?></strong>
                        <a href="<?php echo esc_url( $logout_url ); ?>">Abmelden</a>
                    </div>
                </div>
            </header>

            <nav class="gd-tabs gd-desktop-tabs" aria-label="Reservierungsfilter">
                <button class="gd-tab gd-view-button" data-view="pending">Offen <span data-count="pending">0</span></button>
                <button class="gd-tab gd-view-button" data-view="today">Heute <span data-count="today">0</span></button>
                <button class="gd-tab gd-view-button" data-view="upcoming">Kommend <span data-count="upcoming">0</span></button>
                <button class="gd-tab gd-view-button" data-view="confirmed">Bestätigt <span data-count="confirmed">0</span></button>
                <button class="gd-tab gd-view-button" data-view="all">Alle</button>
            </nav>

            <section class="gd-automation-panel gd-desktop-only">
                <div>
                    <strong>Automatische Bestätigung</strong>
                    <p>Neue Reservierungsanfragen sofort bestätigen und die Bestätigungs-E-Mail versenden.</p>
                </div>
                <label class="gd-switch">
                    <input type="checkbox" data-auto-confirm <?php checked( (bool) get_option( self::AUTO_CONFIRM_OPTION, false ) ); ?>>
                    <span class="gd-switch-slider" aria-hidden="true"></span>
                    <span class="screen-reader-text">Automatische Bestätigung aktivieren</span>
                </label>
                <span class="gd-setting-feedback" data-auto-confirm-feedback aria-live="polite"></span>
            </section>

            <section class="gd-automation-panel gd-desktop-only">
                <div>
                    <strong>Automatische Aktualisierung</strong>
                    <p>Reservierungen regelmäßig im Hintergrund neu laden.</p>
                </div>
                <label class="gd-select-setting">
                    <span class="screen-reader-text">Aktualisierungsintervall</span>
                    <select data-refresh-interval>
                        <?php echo $this->render_refresh_interval_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </select>
                </label>
                <span class="gd-setting-feedback" data-refresh-interval-feedback aria-live="polite"></span>
            </section>

            <div class="gd-toolbar">
                <label class="gd-search">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <span class="screen-reader-text">Reservierungen durchsuchen</span>
                    <input type="search" id="gd-booking-search" placeholder="Name, Telefon, E-Mail oder Tisch" autocomplete="off" enterkeyhint="search">
                    <button type="button" class="gd-search-clear" id="gd-search-clear" aria-label="Suche löschen" hidden>×</button>
                </label>
                <div class="gd-toolbar-actions gd-desktop-only">
                    <button type="button" class="gd-manual-add-desktop" data-open-manual-booking><span aria-hidden="true">＋</span> Reservierung hinzufügen</button>
                    <button type="button" class="gd-export gd-export-csv" data-export-csv>CSV exportieren</button>
                    <button type="button" class="gd-export gd-export-xlsx" data-export-xlsx>Excel exportieren</button>
                    <button type="button" class="gd-refresh" data-refresh>Aktualisieren</button>
                </div>
            </div>

            <main class="gd-main-content">
                <div id="gd-booking-list" class="gd-booking-list"></div>
                <div id="gd-empty" class="gd-empty" hidden>
                    <div class="gd-empty-icon">✓</div>
                    <h2>Alles erledigt</h2>
                    <p>In dieser Ansicht gibt es derzeit keine Reservierungen.</p>
                </div>
            </main>

            <nav class="gd-bottom-nav" aria-label="App-Navigation">
                <button type="button" class="gd-bottom-item gd-view-button" data-view="pending">
                    <span class="gd-bottom-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg><b data-count="pending">0</b></span>
                    <span>Offen</span>
                </button>
                <button type="button" class="gd-bottom-item gd-view-button" data-view="today">
                    <span class="gd-bottom-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg><b data-count="today">0</b></span>
                    <span>Heute</span>
                </button>
                <button type="button" class="gd-bottom-item gd-view-button" data-view="upcoming">
                    <span class="gd-bottom-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><b data-count="upcoming">0</b></span>
                    <span>Kommend</span>
                </button>
                <button type="button" class="gd-bottom-item gd-view-button" data-view="confirmed">
                    <span class="gd-bottom-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg><b data-count="confirmed">0</b></span>
                    <span>Bestätigt</span>
                </button>
                <button type="button" class="gd-bottom-item" data-open-more>
                    <span class="gd-bottom-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg></span>
                    <span>Mehr</span>
                </button>
            </nav>

            <div class="gd-sheet-layer" id="gd-more-layer" aria-hidden="true">
                <button type="button" class="gd-sheet-backdrop" data-close-more aria-label="Menü schließen"></button>
                <aside class="gd-more-sheet" role="dialog" aria-modal="true" aria-labelledby="gd-more-title">
                    <div class="gd-sheet-handle" aria-hidden="true"></div>
                    <header class="gd-sheet-header">
                        <div>
                            <span class="gd-eyebrow">Verwaltung</span>
                            <h2 id="gd-more-title">Mehr</h2>
                        </div>
                        <button type="button" class="gd-sheet-close" data-close-more aria-label="Einstellungsmenü schließen" title="Schließen">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </header>

                    <button type="button" class="gd-sheet-row gd-sheet-row-primary" data-open-manual-booking>
                        <span class="gd-sheet-row-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span>
                        <span><strong>Reservierung hinzufügen</strong><small>Telefonische oder persönliche Reservierung eintragen</small></span>
                        <span class="gd-row-chevron">›</span>
                    </button>

                    <button type="button" class="gd-sheet-row gd-view-button" data-view="all" data-close-on-select>
                        <span class="gd-sheet-row-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16"/></svg></span>
                        <span><strong>Alle Reservierungen</strong><small>Auch abgelehnte und ältere Einträge anzeigen</small></span>
                        <span class="gd-row-chevron">›</span>
                    </button>

                    <button type="button" class="gd-sheet-row gd-view-button" data-view="trash" data-close-on-select>
                        <span class="gd-sheet-row-icon gd-sheet-row-icon-trash"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg></span>
                        <span><strong>Papierkorb</strong><small>Gelöschte Reservierungen ansehen oder wiederherstellen</small></span>
                        <span class="gd-sheet-count" data-count="trash">0</span>
                    </button>

                    <section class="gd-sheet-section">
                        <div class="gd-sheet-setting">
                            <div>
                                <strong>Automatisch bestätigen</strong>
                                <small>Neue Anfragen sofort bestätigen und E-Mail senden.</small>
                            </div>
                            <label class="gd-switch">
                                <input type="checkbox" data-auto-confirm <?php checked( (bool) get_option( self::AUTO_CONFIRM_OPTION, false ) ); ?>>
                                <span class="gd-switch-slider" aria-hidden="true"></span>
                                <span class="screen-reader-text">Automatische Bestätigung aktivieren</span>
                            </label>
                        </div>
                        <span class="gd-setting-feedback" data-auto-confirm-feedback aria-live="polite"></span>
                    </section>

                    <section class="gd-sheet-section">
                        <div class="gd-sheet-setting gd-sheet-setting-stacked">
                            <div>
                                <strong>Automatisch aktualisieren</strong>
                                <small>Reservierungen regelmäßig neu laden.</small>
                            </div>
                            <label class="gd-select-setting gd-select-setting-wide">
                                <span class="screen-reader-text">Aktualisierungsintervall</span>
                                <select data-refresh-interval>
                                    <?php echo $this->render_refresh_interval_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </select>
                            </label>
                        </div>
                        <span class="gd-setting-feedback" data-refresh-interval-feedback aria-live="polite"></span>
                    </section>

                    <section class="gd-sheet-section">
                        <div class="gd-sheet-setting gd-sheet-setting-stacked">
                            <div>
                                <strong>Anzahl der Tische</strong>
                                <small>Bestimmt die Auswahl im Tisch-Popup. Standard: 30.</small>
                            </div>
                            <div class="gd-table-count-setting">
                                <input type="number" min="1" max="300" step="1" value="<?php echo esc_attr( $this->get_table_count() ); ?>" data-table-count inputmode="numeric">
                                <button type="button" class="gd-sheet-action gd-sheet-action-primary" data-save-table-count>Speichern</button>
                            </div>
                        </div>
                        <span class="gd-setting-feedback" data-table-count-feedback aria-live="polite"></span>
                    </section>

                    <section class="gd-sheet-section">
                        <div class="gd-sheet-setting gd-sheet-setting-stacked">
                            <div>
                                <strong>Tischkapazitäten</strong>
                                <small>Standardmäßig 5 Plätze je Tisch. Abweichungen optional als z. B. 3=6 oder 8=2, jeweils eine Zeile.</small>
                            </div>
                            <div class="gd-table-capacity-setting">
                                <label>
                                    <span>Standardplätze</span>
                                    <input type="number" min="1" max="50" step="1" value="<?php echo esc_attr( $this->get_table_default_capacity() ); ?>" data-table-default-capacity inputmode="numeric">
                                </label>
                                <label>
                                    <span>Abweichungen je Tisch</span>
                                    <textarea rows="5" data-table-capacity-overrides placeholder="3=6&#10;8=2"><?php echo esc_textarea( $this->format_table_capacity_overrides() ); ?></textarea>
                                </label>
                                <button type="button" class="gd-sheet-action gd-sheet-action-primary" data-save-table-capacity-settings>Kapazitäten speichern</button>
                            </div>
                        </div>
                        <span class="gd-setting-feedback" data-table-capacity-feedback aria-live="polite"></span>
                    </section>

                    <section class="gd-sheet-section">
                        <div class="gd-sheet-setting gd-sheet-setting-stacked">
                            <div>
                                <strong>WhatsApp-Standardtext</strong>
                                <small>Platzhalter: {name}, {date}, {time}, {party}</small>
                            </div>
                            <textarea class="gd-whatsapp-template-input" data-whatsapp-template rows="5"><?php echo esc_textarea( $this->get_whatsapp_template() ); ?></textarea>
                            <button type="button" class="gd-sheet-action gd-sheet-action-primary gd-whatsapp-template-save" data-save-whatsapp-template>Text speichern</button>
                        </div>
                        <span class="gd-setting-feedback" data-whatsapp-template-feedback aria-live="polite"></span>
                    </section>

                    <section class="gd-sheet-section">
                        <div class="gd-sheet-setting">
                            <div>
                                <strong>Dark Mode</strong>
                                <small>Dunkle Darstellung für den Abendbetrieb.</small>
                            </div>
                            <label class="gd-switch">
                                <input type="checkbox" data-theme-switch>
                                <span class="gd-switch-slider" aria-hidden="true"></span>
                                <span class="screen-reader-text">Dark Mode aktivieren</span>
                            </label>
                        </div>
                    </section>

                    <section class="gd-sheet-section">
                        <h3>Export</h3>
                        <div class="gd-sheet-button-grid">
                            <button type="button" class="gd-sheet-action" data-export-csv>CSV</button>
                            <button type="button" class="gd-sheet-action gd-sheet-action-primary" data-export-xlsx>Excel</button>
                        </div>
                    </section>

                    <section class="gd-sheet-section gd-install-section">
                        <button type="button" class="gd-sheet-row" data-install-app hidden>
                            <span class="gd-sheet-row-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 20h14"/></svg></span>
                            <span><strong>App installieren</strong><small>Zum Home-Bildschirm hinzufügen</small></span>
                            <span class="gd-row-chevron">›</span>
                        </button>
                        <p class="gd-ios-install-hint" hidden>Auf iPhone/iPad: Teilen antippen und „Zum Home-Bildschirm“ wählen.</p>
                    </section>

                    <?php if ( current_user_can( 'gelsendiele_manage_settings' ) || current_user_can( 'manage_options' ) ) : ?>
                    <section class="gd-sheet-section">
                        <?php if ( current_user_can( 'gelsendiele_manage_settings' ) ) : ?><a class="gd-sheet-row" href="<?php echo esc_url( add_query_arg( 'gd-section', 'settings', $this->dashboard_url() ) ); ?>"><span class="gd-sheet-row-icon">E</span><span><strong>Gelsensystem Einstellungen</strong><small>Betrieb, Öffnungszeiten, E-Mails und Formular</small></span><span class="gd-row-chevron">›</span></a><?php endif; ?>
                        <?php if ( current_user_can( 'manage_options' ) ) : ?><a class="gd-sheet-row" href="<?php echo esc_url( add_query_arg( 'gd-section', 'users', $this->dashboard_url() ) ); ?>"><span class="gd-sheet-row-icon">U</span><span><strong>Benutzer &amp; Rechte</strong><small>Zugriff auf die Arbeitsbereiche verwalten</small></span><span class="gd-row-chevron">›</span></a><?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <section class="gd-sheet-section gd-account-section">
                        <div class="gd-account-row">
                            <span class="gd-user-avatar"><?php echo esc_html( strtoupper( mb_substr( $user->display_name, 0, 1 ) ) ); ?></span>
                            <div><strong><?php echo esc_html( $user->display_name ); ?></strong><small>WordPress-Konto</small></div>
                            <a href="<?php echo esc_url( $logout_url ); ?>">Abmelden</a>
                        </div>
                    </section>
                </aside>
            </div>

            <div class="gd-manual-dialog" id="gd-manual-dialog" aria-hidden="true">
                <button type="button" class="gd-manual-backdrop" data-close-manual-booking aria-label="Reservierungsformular schließen"></button>
                <section class="gd-manual-panel" role="dialog" aria-modal="true" aria-labelledby="gd-manual-title">
                    <header class="gd-manual-header">
                        <div>
                            <span class="gd-eyebrow" data-manual-eyebrow>Telefonisch / persönlich</span>
                            <h2 id="gd-manual-title" data-manual-title>Reservierung hinzufügen</h2>
                            <p data-manual-description>Der Eintrag wird direkt mit dem Gelsensystem und WordPress synchronisiert.</p>
                        </div>
                        <button type="button" class="gd-table-picker-close" data-close-manual-booking aria-label="Schließen">×</button>
                    </header>
                    <form id="gd-manual-form" class="gd-manual-form" novalidate><input type="hidden" data-manual-booking-id value="">
                        <div class="gd-manual-grid gd-manual-grid-booking">
                            <label class="gd-manual-date-field"><span>Datum *</span><button type="button" class="gd-manual-date-button" data-open-manual-calendar><span data-manual-date-label>Datum wählen</span><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4M17 3v4M3 10h18"/></svg></button><input type="hidden" name="date" data-manual-date value=""></label>
                            <label><span>Personen *</span><input type="number" name="party" data-manual-party min="1" max="100" step="1" value="2" inputmode="numeric" required></label>
                            <label class="gd-manual-time-field"><span>Uhrzeit *</span><select name="time" data-manual-time required disabled><option value="">Datum wählen</option></select><input type="time" data-manual-custom-time hidden step="300"><small data-manual-slots-status></small></label>
                            <label><span>Status</span><select name="status" data-manual-status><option value="confirmed">Bestätigt</option><option value="pending">Offen</option></select></label>
                        </div>

                        <div class="gd-manual-section">
                            <h3>Kontaktdaten</h3>
                            <div class="gd-manual-grid">
                                <label class="gd-manual-full"><span>Name *</span><input type="text" name="name" data-manual-name maxlength="100" autocomplete="name" required></label>
                                <label><span>Telefon</span><input type="tel" name="phone" data-manual-phone maxlength="50" autocomplete="tel"></label>
                                <label><span>E-Mail</span><input type="email" name="email" data-manual-email maxlength="190" autocomplete="email"></label>
                            </div>
                            <p class="gd-manual-help">Telefonnummer und E-Mail sind optional. Ohne Kontaktdaten erscheint vor dem Speichern ein zusätzlicher Sicherheitshinweis.</p>
                        </div>

                        <div class="gd-manual-section">
                            <h3>Tisch</h3>
                            <input type="hidden" name="tableNumber" data-manual-table-value value="">
                            <input type="hidden" name="allowOccupied" data-manual-allow-occupied value="0">
                            <button type="button" class="gd-manual-table-select" data-open-manual-table>
                                <span class="gd-manual-table-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 5.2h4"/><path d="M12 3.6v1.6"/><path d="M8.2 10.2h7.6"/><path d="M12 10.2v5.2"/><path d="M9.5 15.4h5"/><path d="M8.2 10.2V8.1c0-1 .8-1.8 1.8-1.8h4c1 0 1.8.8 1.8 1.8v2.1"/><path d="M6.1 9v4.2"/><path d="M17.9 9v4.2"/><path d="M4.9 9h2.9c.8 0 1.4.6 1.4 1.4v2.8"/><path d="M19.1 9h-2.9c-.8 0-1.4.6-1.4 1.4v2.8"/><path d="M4.9 17l1.2-3.8"/><path d="M19.1 17l-1.2-3.8"/></svg></span>
                                <span><strong data-manual-table-label>Kein Tisch ausgewählt</strong><small>Verfügbarkeit anhand von Datum, Uhrzeit und Personen prüfen</small></span>
                                <span class="gd-row-chevron">›</span>
                            </button>
                        </div>

                        <div class="gd-manual-section">
                            <div class="gd-manual-grid">
                                <label class="gd-manual-full"><span>Nachricht / Wunsch des Gastes</span><textarea name="guestMessage" data-manual-guest-message rows="3" maxlength="1000" placeholder="z. B. Kinderstuhl oder besondere Wünsche"></textarea></label>
                                <label class="gd-manual-full"><span>Interne Notiz – nur Team</span><textarea name="internalComment" data-manual-internal-comment rows="3" maxlength="1000" placeholder="z. B. telefonisch angenommen oder Stammgast"></textarea></label>
                            </div>
                        </div>

                        <div class="gd-manual-feedback" data-manual-feedback role="status" aria-live="polite"></div>
                        <div class="gd-manual-actions">
                            <button type="button" class="gd-button gd-button-secondary" data-close-manual-booking>Abbrechen</button>
                            <button type="submit" class="gd-button gd-button-primary" data-manual-submit>Reservierung speichern</button>
                        </div>
                    </form>
                </section>
            </div>

            <div class="gd-table-picker-dialog" id="gd-table-picker-dialog" aria-hidden="true">
                <button type="button" class="gd-table-picker-backdrop" data-close-table-picker aria-label="Tischauswahl schließen"></button>
                <section class="gd-table-picker-panel" role="dialog" aria-modal="true" aria-labelledby="gd-table-picker-title">
                    <header class="gd-table-picker-header">
                        <div>
                            <span class="gd-eyebrow">Tischzuweisung</span>
                            <h2 id="gd-table-picker-title">Tisch auswählen</h2>
                            <p id="gd-table-picker-booking-name"></p>
                        </div>
                        <button type="button" class="gd-table-picker-close" data-close-table-picker aria-label="Schließen">×</button>
                    </header>
                    <div class="gd-table-picker-grid" id="gd-table-picker-grid" role="listbox" aria-label="Verfügbare Tischnummern"></div>
                    <button type="button" class="gd-table-picker-clear" data-table-value="">Tischzuweisung entfernen</button>
                </section>
            </div>

            <div class="gd-table-conflict-dialog" id="gd-table-conflict-dialog" aria-hidden="true">
                <button type="button" class="gd-table-conflict-backdrop" data-close-table-conflict aria-label="Hinweis schließen"></button>
                <section class="gd-table-conflict-panel" role="dialog" aria-modal="true" aria-labelledby="gd-table-conflict-title">
                    <header class="gd-table-conflict-header">
                        <div>
                            <span class="gd-eyebrow">Tisch bereits belegt</span>
                            <h2 id="gd-table-conflict-title">Tisch <span id="gd-table-conflict-number"></span></h2>
                        </div>
                        <button type="button" class="gd-table-picker-close" data-close-table-conflict aria-label="Schließen">×</button>
                    </header>
                    <div class="gd-table-conflict-summary" id="gd-table-conflict-summary"></div>
                    <div class="gd-table-conflict-bookings" id="gd-table-conflict-bookings"></div>
                    <div class="gd-table-conflict-actions">
                        <button type="button" class="gd-button gd-button-secondary" data-close-table-conflict>Abbrechen</button>
                        <button type="button" class="gd-button gd-button-primary" id="gd-table-conflict-confirm" data-confirm-table-conflict>Trotzdem zuweisen</button>
                    </div>
                </section>
            </div>

            <div class="gd-removal-dialog" id="gd-removal-dialog" aria-hidden="true">
                <button type="button" class="gd-removal-backdrop" data-close-removal aria-label="Dialog schließen"></button>
                <section class="gd-removal-panel" role="dialog" aria-modal="true" aria-labelledby="gd-removal-title" aria-describedby="gd-removal-description">
                    <div class="gd-removal-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                    </div>
                    <h2 id="gd-removal-title">Was möchtest du tun?</h2>
                    <p id="gd-removal-description">Wähle, ob <strong id="gd-removal-booking-name">diese Reservierung</strong> storniert oder nur in den Papierkorb verschoben werden soll.</p>
                    <div class="gd-removal-actions">
                        <button type="button" class="gd-removal-option gd-removal-option-cancel" data-cancel-booking>
                            <span class="gd-removal-option-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>
                            </span>
                            <span><strong>Reservierung stornieren</strong><small>Der Status wird auf „Storniert“ gesetzt. Die konfigurierte Stornierungs-E-Mail kann versendet werden.</small></span>
                        </button>
                        <button type="button" class="gd-removal-option gd-removal-option-trash" data-move-trash>
                            <span class="gd-removal-option-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                            </span>
                            <span><strong>Aus der Liste entfernen</strong><small>Die Reservierung wird ohne Stornierungs-E-Mail in den Papierkorb verschoben und kann wiederhergestellt werden.</small></span>
                        </button>
                    </div>
                    <button type="button" class="gd-removal-close" data-close-removal>Abbrechen</button>
                </section>
            </div>

            <div class="gd-toast" id="gd-toast" role="status" aria-live="polite" aria-atomic="true"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_login() {
        $business_name = Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' );
        $branding      = Gelsendiele_Settings::get( 'branding', null, array() );
        $brand_style   = Gelsendiele_Settings::css_variables() . '--gd-green:' . $branding['primary_color'] . ';--gd-green-dark:' . $branding['secondary_color'] . ';';
        ob_start();
        ?>
        <div class="gd-login-page" style="<?php echo esc_attr( $brand_style ); ?>">
            <section class="gd-login-card">
                <?php echo $this->render_brand_logo( 'gd-login-logo', 'G', $business_name ); ?>
                <span class="gd-eyebrow">Gelsensystem</span>
                <h1><?php echo esc_html( $business_name ); ?> verwalten</h1>
                <p>Melden Sie sich mit Ihren normalen WordPress-Zugangsdaten an.</p>
                <form id="gd-loginform" method="post" novalidate>
                    <p>
                        <label for="gd-user-login">Benutzername oder E-Mail-Adresse</label>
                        <input type="text" name="log" id="gd-user-login" autocomplete="username" required>
                    </p>
                    <p>
                        <label for="gd-user-pass">Passwort</label>
                        <input type="password" name="pwd" id="gd-user-pass" autocomplete="current-password" required>
                    </p>
                    <p class="login-remember">
                        <label><input type="checkbox" name="rememberme" value="forever" checked> Angemeldet bleiben</label>
                    </p>
                    <p class="login-submit">
                        <button type="submit" class="gd-login-submit">Anmelden</button>
                    </p>
                    <div class="gd-login-status" id="gd-login-status" role="status" aria-live="polite"></div>
                </form>
                <a class="gd-forgot-password" href="<?php echo esc_url( wp_lostpassword_url( $this->dashboard_url() ) ); ?>">Passwort vergessen?</a>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_dashboard_login() {
        check_ajax_referer( 'gd_dashboard_login', 'nonce' );

        $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
        $password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
        $remember = ! empty( $_POST['remember'] );

        if ( '' === $username || '' === $password ) {
            wp_send_json_error( array( 'message' => 'Bitte Benutzername und Passwort eingeben.' ), 400 );
        }

        $user = wp_signon( array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ), is_ssl() );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array( 'message' => 'Anmeldung fehlgeschlagen. Bitte Zugangsdaten prüfen.' ), 401 );
        }

        if ( ! user_can( $user, 'manage_bookings' ) ) {
            wp_logout();
            wp_send_json_error( array( 'message' => 'Dieses Benutzerkonto darf keine Reservierungen verwalten.' ), 403 );
        }

        wp_set_current_user( $user->ID );
        wp_send_json_success( array(
            'redirect' => $this->dashboard_url(),
        ) );
    }

    public function ajax_get_bookings() {
        $this->verify_ajax_request();
        $view = isset( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : 'pending';
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        wp_send_json_success( array(
            'bookings' => $this->get_bookings( $view, $search ),
            'counts'   => $this->get_counts(),
        ) );
    }

    public function ajax_update_status() {
        $this->verify_ajax_request();

        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
        $allowed = array_values( array_unique( array_merge( array_keys( $this->get_booking_statuses() ), array( 'cancelled' ) ) ) );

        if ( ! $booking_id || ! in_array( $status, $allowed, true ) || get_post_type( $booking_id ) !== $this->booking_post_type() ) {
            wp_send_json_error( array( 'message' => 'Ungültige Reservierung oder Status.' ), 400 );
        }

        $result = $this->update_booking_status_with_rtb( $booking_id, $status );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        do_action( 'gd_reservierungsdashboard_status_changed', $booking_id, $status, $result );

        wp_send_json_success( array(
            'bookingId' => $booking_id,
            'status'    => $status,
            'label'     => $this->status_label( $status ),
            'counts'    => $this->get_counts(),
        ) );
    }

    public function ajax_delete_booking() {
        $this->verify_ajax_request();
        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;

        if ( ! $booking_id || get_post_type( $booking_id ) !== $this->booking_post_type() ) {
            wp_send_json_error( array( 'message' => 'Ungültige Reservierung.' ), 400 );
        }

        $result = wp_trash_post( $booking_id );
        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Reservierung konnte nicht verschoben werden.' ), 500 );
        }

        wp_send_json_success( array(
            'bookingId' => $booking_id,
            'counts'    => $this->get_counts(),
        ) );
    }


    /**
     * Schaltet die automatische Bestätigung im Frontend-Dashboard um.
     */
    public function ajax_update_auto_confirm() {
        $this->verify_ajax_request();
        $enabled = isset( $_POST['enabled'] ) && '1' === (string) wp_unslash( $_POST['enabled'] );
        update_option( self::AUTO_CONFIRM_OPTION, $enabled ? 1 : 0, false );
        $general = Gelsendiele_Settings::get( 'general', null, array() );
        $general['confirmation_mode'] = $enabled ? 'automatic' : 'manual';
        Gelsendiele_Settings::save_sections( array( 'general' => $general ) );

        wp_send_json_success( array(
            'enabled' => $enabled,
            'message' => $enabled
                ? 'Automatische Bestätigung ist aktiviert.'
                : 'Automatische Bestätigung ist deaktiviert.',
        ) );
    }

    /**
     * Bestätigt neue Reservierungen innerhalb des nativen Five-Star-Ablaufs.
     *
     * Dadurch wird die Buchung bereits vor dem Speichern als bestätigt markiert.
     * Das offizielle Plugin kann anschließend Metadaten, Status und E-Mails in
     * derselben Verarbeitung korrekt erzeugen. Zahlungsstatus werden nicht
     * überschrieben.
     *
     * @param string     $status  Vom Five-Star-Plugin ermittelter Status.
     * @param rtbBooking $booking Aktuelles Buchungsobjekt.
     * @return string
     */
    public function ajax_update_refresh_interval() {
        $this->verify_ajax_request();

        $interval = isset( $_POST['interval'] ) ? absint( $_POST['interval'] ) : 5;
        $allowed  = array( 0, 1, 2, 5, 10, 15, 30 );
        if ( ! in_array( $interval, $allowed, true ) ) {
            $interval = 5;
        }

        update_option( self::REFRESH_INTERVAL_OPTION, $interval, false );
        wp_send_json_success( array(
            'interval' => $interval,
            'message'  => 0 === $interval
                ? 'Automatische Aktualisierung deaktiviert.'
                : sprintf( 'Automatische Aktualisierung alle %d Minuten.', $interval ),
        ) );
    }

    public function ajax_update_whatsapp_template() {
        $this->verify_ajax_request();

        $template = isset( $_POST['template'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['template'] ) )
            : '';

        if ( '' === trim( $template ) ) {
            $template = $this->default_whatsapp_template();
        }

        update_option( self::WHATSAPP_TEMPLATE_OPTION, $template, false );

        wp_send_json_success( array(
            'template' => $template,
            'message'  => 'WhatsApp-Standardtext gespeichert.',
        ) );
    }

    public function ajax_update_table_count() {
        $this->verify_ajax_request();

        $count = isset( $_POST['tableCount'] ) ? absint( $_POST['tableCount'] ) : 30;
        $count = max( 1, min( 300, $count ) );
        update_option( self::TABLE_COUNT_OPTION, $count, false );

        wp_send_json_success( array(
            'tableCount' => $count,
            'message'    => sprintf( 'Anzahl der Tische auf %d gespeichert.', $count ),
        ) );
    }

    public function ajax_update_table_capacity_settings() {
        $this->verify_ajax_request();

        $default_capacity = isset( $_POST['defaultCapacity'] ) ? absint( $_POST['defaultCapacity'] ) : 5;
        $default_capacity = max( 1, min( 50, $default_capacity ) );
        $overrides_text = isset( $_POST['overrides'] ) ? sanitize_textarea_field( wp_unslash( $_POST['overrides'] ) ) : '';
        $overrides = $this->parse_table_capacity_overrides( $overrides_text );

        update_option( self::TABLE_DEFAULT_CAPACITY_OPTION, $default_capacity, false );
        update_option( self::TABLE_CAPACITY_OVERRIDES_OPTION, $overrides, false );

        wp_send_json_success( array(
            'defaultCapacity' => $default_capacity,
            'overrides'       => $overrides,
            'formatted'       => $this->format_table_capacity_overrides( $overrides ),
            'message'         => 'Tischkapazitäten gespeichert.',
        ) );
    }

    public function ajax_get_table_availability() {
        $this->verify_ajax_request();

        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        if ( ! $booking_id || get_post_type( $booking_id ) !== $this->booking_post_type() ) {
            wp_send_json_error( array( 'message' => 'Ungültige Reservierung.' ), 400 );
        }

        wp_send_json_success( array(
            'tables' => array_values( $this->get_table_availability_for_booking( $booking_id ) ),
        ) );
    }

    public function ajax_get_manual_booking_slots() {
        $this->verify_ajax_request();

        $date  = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $party = isset( $_POST['party'] ) ? max( 1, min( 100, absint( $_POST['party'] ) ) ) : 1;
        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        $date_object = DateTime::createFromFormat( '!Y-m-d', $date, wp_timezone() );

        if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date ) {
            wp_send_json_error( array( 'message' => 'Bitte ein gültiges Datum auswählen.' ), 400 );
        }

        $slots = $this->calculate_available_slots( $date_object, $party, $booking_id );
        wp_send_json_success( array(
            'date'  => $date,
            'slots' => array_values( $slots ),
        ) );
    }

    public function ajax_get_manual_table_availability() {
        $this->verify_ajax_request();

        $date  = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $time  = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
        $party = isset( $_POST['party'] ) ? max( 1, min( 100, absint( $_POST['party'] ) ) ) : 1;
        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        $selected_table = isset( $_POST['selectedTable'] ) ? $this->sanitize_table_number( wp_unslash( $_POST['selectedTable'] ) ) : '';

        $result = $this->get_table_availability_for_datetime( $date, $time, $party, $booking_id, $selected_table );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }

        wp_send_json_success( array( 'tables' => array_values( $result ) ) );
    }

    public function ajax_create_manual_booking() {
        $this->verify_ajax_request();

        $date             = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $time             = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
        $party            = isset( $_POST['party'] ) ? max( 1, min( 100, absint( $_POST['party'] ) ) ) : 0;
        $name             = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $guest_message    = isset( $_POST['guestMessage'] ) ? sanitize_textarea_field( wp_unslash( $_POST['guestMessage'] ) ) : '';
        $internal_comment = isset( $_POST['internalComment'] ) ? $this->sanitize_internal_comment( wp_unslash( $_POST['internalComment'] ) ) : '';
        $status           = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'confirmed';
        $table_number     = isset( $_POST['tableNumber'] ) ? $this->sanitize_table_number( wp_unslash( $_POST['tableNumber'] ) ) : '';
        $allow_occupied      = isset( $_POST['allowOccupied'] ) && '1' === (string) wp_unslash( $_POST['allowOccupied'] );
        $allow_outside_hours = isset( $_POST['allowOutsideHours'] ) && '1' === (string) wp_unslash( $_POST['allowOutsideHours'] );
        $allow_no_contact    = isset( $_POST['allowNoContact'] ) && '1' === (string) wp_unslash( $_POST['allowNoContact'] );

        if ( ! in_array( $status, array( 'pending', 'confirmed' ), true ) ) {
            $status = 'confirmed';
        }
        if ( '' === $name ) {
            wp_send_json_error( array( 'message' => 'Bitte den Namen des Gastes eintragen.', 'field' => 'name' ), 400 );
        }
        if ( ( '' === $phone || '' === $email ) && ! $allow_no_contact ) {
            wp_send_json_error( array(
                'message' => 'Telefonnummer oder E-Mail-Adresse fehlt. Bitte den Sicherheitshinweis bestätigen.',
                'field'   => '' === $phone ? 'phone' : 'email',
                'warning' => 'missing_contact',
            ), 409 );
        }

        $date_object = DateTime::createFromFormat( '!Y-m-d', $date, wp_timezone() );
        if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
            wp_send_json_error( array( 'message' => 'Datum oder Uhrzeit ist ungültig.' ), 400 );
        }

        $slots = $this->calculate_available_slots( $date_object, $party );
        $outside_hours = ! in_array( $time, $slots, true );
        if ( $outside_hours && ! $allow_outside_hours ) {
            wp_send_json_error( array(
                'message' => 'Diese Uhrzeit liegt außerhalb der regulären Verfügbarkeit. Bitte den Sicherheitshinweis bestätigen.',
                'warning' => 'outside_hours',
            ), 409 );
        }

        if ( '' !== $table_number ) {
            if ( ! ctype_digit( $table_number ) || (int) $table_number < 1 || (int) $table_number > $this->get_table_count() ) {
                wp_send_json_error( array( 'message' => 'Die ausgewählte Tischnummer ist ungültig.' ), 400 );
            }
            $availability = $this->get_table_availability_for_datetime( $date, $time, $party );
            if ( is_wp_error( $availability ) ) {
                wp_send_json_error( array( 'message' => $availability->get_error_message() ), 400 );
            }
            $selected = isset( $availability[ (int) $table_number ] ) ? $availability[ (int) $table_number ] : null;
            if ( $selected && $selected['occupiedSeats'] > 0 && ! $allow_occupied ) {
                wp_send_json_error( array(
                    'message'  => 'Dieser Tisch wurde inzwischen belegt. Bitte die Tischwahl erneut bestätigen.',
                    'conflict' => $selected,
                ), 409 );
            }
        }

        $result = $this->create_manual_booking( array(
            'date'             => $date,
            'time'             => $time,
            'party'            => $party,
            'name'             => $name,
            'phone'            => $phone,
            'email'            => $email,
            'guest_message'    => $guest_message,
            'internal_comment' => $internal_comment,
            'status'           => $status,
            'table_number'     => $table_number,
            'force_direct'     => $outside_hours || ( '' === $phone && '' === $email ),
            'outside_hours'    => $outside_hours,
            'no_contact'       => ( '' === $phone || '' === $email ),
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
        }

        wp_send_json_success( array(
            'bookingId' => (int) $result,
            'status'    => $status,
            'message'   => 'Reservierung wurde gespeichert und mit WordPress synchronisiert.',
            'counts'    => $this->get_counts(),
        ) );
    }

    public function ajax_update_manual_booking() {
        $this->verify_ajax_request();

        $booking_id       = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        $date             = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $time             = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
        $party            = isset( $_POST['party'] ) ? max( 1, min( 100, absint( $_POST['party'] ) ) ) : 0;
        $name             = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $guest_message    = isset( $_POST['guestMessage'] ) ? sanitize_textarea_field( wp_unslash( $_POST['guestMessage'] ) ) : '';
        $internal_comment = isset( $_POST['internalComment'] ) ? $this->sanitize_internal_comment( wp_unslash( $_POST['internalComment'] ) ) : '';
        $status           = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'confirmed';
        $table_number     = isset( $_POST['tableNumber'] ) ? $this->sanitize_table_number( wp_unslash( $_POST['tableNumber'] ) ) : '';
        $allow_occupied      = isset( $_POST['allowOccupied'] ) && '1' === (string) wp_unslash( $_POST['allowOccupied'] );
        $allow_outside_hours = isset( $_POST['allowOutsideHours'] ) && '1' === (string) wp_unslash( $_POST['allowOutsideHours'] );
        $allow_no_contact    = isset( $_POST['allowNoContact'] ) && '1' === (string) wp_unslash( $_POST['allowNoContact'] );

        $post = $booking_id ? get_post( $booking_id ) : null;
        if ( ! $post || get_post_type( $booking_id ) !== $this->booking_post_type() || 'trash' === $post->post_status ) {
            wp_send_json_error( array( 'message' => 'Die Reservierung konnte nicht gefunden werden.' ), 404 );
        }
        $allowed_statuses = array_values( array_diff( array_keys( $this->get_booking_statuses() ), array( 'trash' ) ) );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = $post->post_status;
        }
        if ( '' === $name ) {
            wp_send_json_error( array( 'message' => 'Bitte den Namen des Gastes eintragen.', 'field' => 'name' ), 400 );
        }
        if ( ( '' === $phone || '' === $email ) && ! $allow_no_contact ) {
            wp_send_json_error( array(
                'message' => 'Telefonnummer oder E-Mail-Adresse fehlt. Bitte den Sicherheitshinweis bestätigen.',
                'field'   => '' === $phone ? 'phone' : 'email',
                'warning' => 'missing_contact',
            ), 409 );
        }

        $date_object = DateTime::createFromFormat( '!Y-m-d', $date, wp_timezone() );
        if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
            wp_send_json_error( array( 'message' => 'Datum oder Uhrzeit ist ungültig.' ), 400 );
        }

        $slots = $this->calculate_available_slots( $date_object, $party, $booking_id );
        $outside_hours = ! in_array( $time, $slots, true );
        if ( $outside_hours && ! $allow_outside_hours ) {
            wp_send_json_error( array(
                'message' => 'Diese Uhrzeit liegt außerhalb der regulären Verfügbarkeit. Bitte den Sicherheitshinweis bestätigen.',
                'warning' => 'outside_hours',
            ), 409 );
        }

        if ( '' !== $table_number ) {
            if ( ! ctype_digit( $table_number ) || (int) $table_number < 1 || (int) $table_number > $this->get_table_count() ) {
                wp_send_json_error( array( 'message' => 'Die ausgewählte Tischnummer ist ungültig.' ), 400 );
            }
            $availability = $this->get_table_availability_for_datetime( $date, $time, $party, $booking_id, $table_number );
            if ( is_wp_error( $availability ) ) {
                wp_send_json_error( array( 'message' => $availability->get_error_message() ), 400 );
            }
            $selected = isset( $availability[ (int) $table_number ] ) ? $availability[ (int) $table_number ] : null;
            if ( $selected && $selected['occupiedSeats'] > 0 && ! $allow_occupied ) {
                wp_send_json_error( array(
                    'message'  => 'Dieser Tisch ist zur neuen Reservierungszeit bereits belegt. Bitte die Tischwahl bestätigen.',
                    'conflict' => $selected,
                ), 409 );
            }
        }

        $meta = get_post_meta( $booking_id, 'rtb', true );
        $meta = is_array( $meta ) ? $meta : array();
        $local_date = $date . ' ' . $time . ':00';
        $post_update = array( 'ID' => $booking_id );
        $changed = array();

        if ( $post->post_title !== $name ) {
            $post_update['post_title'] = $name;
            $changed[] = 'Name';
        }
        if ( $post->post_content !== $guest_message ) {
            $post_update['post_content'] = $guest_message;
            $changed[] = 'Gastnachricht';
        }
        if ( $post->post_date !== $local_date ) {
            $post_update['post_date'] = $local_date;
            $post_update['post_date_gmt'] = get_gmt_from_date( $local_date );
            $post_update['edit_date'] = true;
            $changed[] = 'Datum/Uhrzeit';
        }
        if ( count( $post_update ) > 1 ) {
            $updated_post = wp_update_post( $post_update, true );
            if ( is_wp_error( $updated_post ) ) {
                wp_send_json_error( array( 'message' => $updated_post->get_error_message() ), 500 );
            }
        }

        $new_meta = array_merge( $meta, array(
            'party' => $party,
            'email' => $email,
            'phone' => $phone,
        ) );
        if ( absint( $meta['party'] ?? 0 ) !== $party || (string) ( $meta['email'] ?? '' ) !== $email || (string) ( $meta['phone'] ?? '' ) !== $phone ) {
            update_post_meta( $booking_id, 'rtb', $new_meta );
            $changed[] = 'Kontaktdaten/Personen';
        }

        $old_table = (string) get_post_meta( $booking_id, self::TABLE_META, true );
        if ( $old_table !== $table_number ) {
            if ( '' === $table_number ) {
                delete_post_meta( $booking_id, self::TABLE_META );
            } else {
                update_post_meta( $booking_id, self::TABLE_META, $table_number );
            }
            $changed[] = 'Tisch';
        }

        $old_comment = (string) get_post_meta( $booking_id, self::COMMENT_META, true );
        if ( $old_comment !== $internal_comment ) {
            if ( '' === $internal_comment ) {
                delete_post_meta( $booking_id, self::COMMENT_META );
            } else {
                update_post_meta( $booking_id, self::COMMENT_META, $internal_comment );
            }
            $changed[] = 'Interne Notiz';
        }

        if ( $outside_hours ) {
            update_post_meta( $booking_id, '_gd_manual_outside_hours', 1 );
        } else {
            delete_post_meta( $booking_id, '_gd_manual_outside_hours' );
        }
        if ( '' === $phone || '' === $email ) {
            update_post_meta( $booking_id, '_gd_manual_no_contact', 1 );
        } else {
            delete_post_meta( $booking_id, '_gd_manual_no_contact' );
        }

        $status_changed = $post->post_status !== $status;
        if ( $status_changed ) {
            $status_result = $this->update_booking_status_with_rtb( $booking_id, $status );
            if ( is_wp_error( $status_result ) ) {
                wp_send_json_error( array( 'message' => $status_result->get_error_message() ), 500 );
            }
            $changed[] = 'Status';
        } elseif ( $this->ensure_rtb_class( 'rtbBooking', array( 'includes/Booking.class.php' ) ) ) {
            $booking = new rtbBooking();
            if ( $booking->load_post( $booking_id ) ) {
                if ( method_exists( $booking, 'add_log' ) && ! empty( $changed ) ) {
                    $booking->add_log( 'update', 'Reservierung bearbeitet', 'Geändert: ' . implode( ', ', array_unique( $changed ) ) . '.' );
                    $booking->insert_post_data();
                }
                do_action( 'rtb_update_booking', $booking );
            }
        }

        do_action( 'gd_reservierungsdashboard_manual_booking_updated', $booking_id, array_unique( $changed ) );

        wp_send_json_success( array(
            'bookingId' => $booking_id,
            'status'    => $status,
            'changed'   => array_values( array_unique( $changed ) ),
            'message'   => empty( $changed ) ? 'Es wurden keine Änderungen erkannt.' : 'Reservierung wurde aktualisiert und mit WordPress synchronisiert.',
            'counts'    => $this->get_counts(),
        ) );
    }

    public function maybe_auto_confirm_booking( $status, $booking ) {
        if ( null !== $this->manual_creation_status ) {
            if ( 'confirmed' === $this->manual_creation_status && is_object( $booking ) && property_exists( $booking, 'temp_confirmed_user' ) ) {
                $booking->temp_confirmed_user = get_current_user_id() ?: -1;
            }
            return $this->manual_creation_status;
        }

        if ( ! get_option( self::AUTO_CONFIRM_OPTION, false ) ) {
            return $status;
        }

        // Nur normale offene Anfragen bestätigen. Deposit-/Zahlungsstatus bleiben unangetastet.
        if ( 'pending' !== $status || ! is_object( $booking ) ) {
            return $status;
        }

        // Kennzeichnung wie bei einer nativen automatischen Bestätigung.
        if ( property_exists( $booking, 'temp_confirmed_user' ) ) {
            $booking->temp_confirmed_user = -1;
        }

        return 'confirmed';
    }

    /**
     * Ändert einen Buchungsstatus über das native Buchungsobjekt des
     * Five-Star-Plugins. Die Metadaten werden vor dem WordPress-Statuswechsel
     * gespeichert, damit die offiziellen Benachrichtigungs-Hooks vollständige
     * Empfänger- und Buchungsdaten erhalten.
     *
     * @param int    $booking_id Buchungs-ID.
     * @param string $status     Zielstatus.
     * @return rtbBooking|WP_Error
     */
    private function update_booking_status_with_rtb( $booking_id, $status ) {
        global $rtb_controller;

        if ( ! class_exists( 'rtbBooking' ) && defined( 'RTB_PLUGIN_DIR' ) ) {
            $booking_file = trailingslashit( RTB_PLUGIN_DIR ) . 'includes/Booking.class.php';
            if ( file_exists( $booking_file ) ) {
                require_once $booking_file;
            }
        }

        if ( ! class_exists( 'rtbBooking' ) ) {
            return new WP_Error(
                'gd_rtb_booking_class_missing',
                'Das Gelsendiele-Reservierungsmodul wurde nicht gefunden.'
            );
        }

        $booking = new rtbBooking();
        if ( ! $booking->load_post( $booking_id ) ) {
            return new WP_Error( 'gd_booking_load_failed', 'Die Reservierung konnte nicht geladen werden.' );
        }

        $old_status = (string) $booking->post_status;
        if ( $old_status === $status ) {
            return $booking;
        }

        // Sicherstellen, dass in diesem eigenständigen AJAX-Aufruf die nativen
        // Benachrichtigungen nicht als deaktiviert markiert sind.
        if ( isset( $rtb_controller->notifications ) && is_object( $rtb_controller->notifications ) ) {
            $rtb_controller->notifications->notifications_disabled = false;
        }

        $booking->post_status = $status;

        if ( 'confirmed' === $status && empty( $booking->confirmed_user ) ) {
            $booking->temp_confirmed_user = get_current_user_id() ?: -1;
        }

        if ( method_exists( $booking, 'add_log' ) ) {
            $user = wp_get_current_user();
            $booking->add_log(
                'status',
                'Status geändert',
                sprintf(
                    'Status von %s auf %s geändert durch %s.',
                    $this->status_label( $old_status ),
                    $this->status_label( $status ),
                    $user instanceof WP_User ? $user->display_name : 'Dashboard'
                )
            );
        }

        if ( ! $booking->insert_post_data() ) {
            return new WP_Error( 'gd_booking_update_failed', 'Der Reservierungsstatus konnte nicht gespeichert werden.' );
        }

        // Kompatibilität mit Erweiterungen, die auf den offiziellen Update-Hook hören.
        do_action( 'rtb_update_booking', $booking );

        return $booking;
    }

    /**
     * Zusätzliche Einstellungsseite im normalen WordPress-Backend.
     */
    public function register_settings_page() {
        add_submenu_page(
            'edit.php?post_type=' . $this->booking_post_type(),
            'Gelsensystem Dashboard',
            'Dashboard-Einstellungen',
            'manage_bookings',
            'gd-dashboard-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_bookings' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'gelsendiele-dashboard' ) );
        }

        if ( isset( $_POST['gd_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gd_settings_nonce'] ) ), 'gd_save_dashboard_settings' ) ) {
            update_option( self::AUTO_CONFIRM_OPTION, isset( $_POST['gd_auto_confirm'] ) ? 1 : 0, false );
            $refresh_interval = isset( $_POST['gd_refresh_interval'] ) ? absint( $_POST['gd_refresh_interval'] ) : 5;
            if ( ! in_array( $refresh_interval, array( 0, 1, 2, 5, 10, 15, 30 ), true ) ) {
                $refresh_interval = 5;
            }
            update_option( self::REFRESH_INTERVAL_OPTION, $refresh_interval, false );
            $whatsapp_template = isset( $_POST['gd_whatsapp_template'] )
                ? sanitize_textarea_field( wp_unslash( $_POST['gd_whatsapp_template'] ) )
                : $this->default_whatsapp_template();
            update_option( self::WHATSAPP_TEMPLATE_OPTION, $whatsapp_template ?: $this->default_whatsapp_template(), false );
            $table_count = isset( $_POST['gd_table_count'] ) ? absint( $_POST['gd_table_count'] ) : 30;
            update_option( self::TABLE_COUNT_OPTION, max( 1, min( 300, $table_count ) ), false );
            $default_capacity = isset( $_POST['gd_table_default_capacity'] ) ? absint( $_POST['gd_table_default_capacity'] ) : 5;
            update_option( self::TABLE_DEFAULT_CAPACITY_OPTION, max( 1, min( 50, $default_capacity ) ), false );
            $capacity_text = isset( $_POST['gd_table_capacity_overrides'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gd_table_capacity_overrides'] ) ) : '';
            update_option( self::TABLE_CAPACITY_OVERRIDES_OPTION, $this->parse_table_capacity_overrides( $capacity_text ), false );
            echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
        }

        $enabled          = (bool) get_option( self::AUTO_CONFIRM_OPTION, false );
        $refresh_interval = $this->get_refresh_interval();
        $whatsapp_template = $this->get_whatsapp_template();
        $table_count       = $this->get_table_count();
        $table_default_capacity = $this->get_table_default_capacity();
        $table_capacity_overrides = $this->format_table_capacity_overrides();
        ?>
        <div class="wrap">
            <h1>Gelsensystem Dashboard</h1>
            <form method="post">
                <?php wp_nonce_field( 'gd_save_dashboard_settings', 'gd_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Automatische Bestätigung</th>
                        <td>
                            <label>
                                <input type="checkbox" name="gd_auto_confirm" value="1" <?php checked( $enabled ); ?>>
                                Neue Reservierungsanfragen sofort auf „Bestätigt“ setzen
                            </label>
                            <p class="description">Die konfigurierte Bestätigungs-E-Mail kann dadurch automatisch versendet werden. Bitte einmal mit einer Testreservierung prüfen.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd-refresh-interval">Automatische Aktualisierung</label></th>
                        <td>
                            <select id="gd-refresh-interval" name="gd_refresh_interval">
                                <?php echo $this->render_refresh_interval_options( $refresh_interval ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </select>
                            <p class="description">Standard: alle 5 Minuten. Bei „Aus“ wird nur manuell oder per Wischgeste aktualisiert.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd-table-count">Anzahl der Tische</label></th>
                        <td>
                            <input type="number" id="gd-table-count" name="gd_table_count" min="1" max="300" step="1" value="<?php echo esc_attr( $table_count ); ?>" class="small-text">
                            <p class="description">Legt fest, wie viele nummerierte Tische im Schnellwahl-Popup angezeigt werden. Standard: 30.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd-table-default-capacity">Standardplätze pro Tisch</label></th>
                        <td>
                            <input type="number" id="gd-table-default-capacity" name="gd_table_default_capacity" min="1" max="50" step="1" value="<?php echo esc_attr( $table_default_capacity ); ?>" class="small-text">
                            <p class="description">Wird für alle Tische verwendet, für die keine eigene Kapazität eingetragen ist. Standard: 5 Plätze.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd-table-capacity-overrides">Abweichende Tischkapazitäten</label></th>
                        <td>
                            <textarea id="gd-table-capacity-overrides" name="gd_table_capacity_overrides" rows="8" class="large-text code" placeholder="3=6&#10;8=2"><?php echo esc_textarea( $table_capacity_overrides ); ?></textarea>
                            <p class="description">Optional, eine Zeile pro Tisch. Beispiel: <code>3=6</code> bedeutet Tisch 3 hat 6 Plätze.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd-whatsapp-template">WhatsApp-Standardtext</label></th>
                        <td>
                            <textarea id="gd-whatsapp-template" name="gd_whatsapp_template" rows="6" class="large-text"><?php echo esc_textarea( $whatsapp_template ); ?></textarea>
                            <p class="description">Verfügbare Platzhalter: <code>{name}</code>, <code>{date}</code>, <code>{time}</code>, <code>{party}</code>. Der Text wird in WordPress gespeichert und kann vor dem Absenden in WhatsApp noch geändert werden.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function export_bookings_csv() {
        $this->verify_export_request();
        $view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all';
        $search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        $rows   = $this->get_bookings( $view, $search, -1 );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="gelsensystem-reservierungen-' . wp_date( 'Y-m-d' ) . '.csv"' );
        echo "\xEF\xBB\xBF";

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, $this->export_headers(), ';' );
        foreach ( $rows as $booking ) {
            fputcsv( $output, $this->export_row( $booking ), ';' );
        }
        fclose( $output );
        exit;
    }

    public function export_bookings_xlsx() {
        $this->verify_export_request();
        if ( ! class_exists( 'ZipArchive' ) ) {
            wp_die( 'Der Server unterstützt den Excel-Export derzeit nicht (PHP-Erweiterung ZipArchive fehlt). Bitte CSV verwenden.' );
        }

        $view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all';
        $search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        $bookings = $this->get_bookings( $view, $search, -1 );
        $rows = array( $this->export_headers() );
        foreach ( $bookings as $booking ) {
            $rows[] = $this->export_row( $booking );
        }

        $file = wp_tempnam( 'gelsensystem-reservierungen.xlsx' );
        if ( ! $file || ! $this->create_xlsx_file( $file, $rows ) ) {
            wp_die( 'Die Excel-Datei konnte nicht erstellt werden.' );
        }

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="gelsensystem-reservierungen-' . wp_date( 'Y-m-d' ) . '.xlsx"' );
        header( 'Content-Length: ' . filesize( $file ) );
        readfile( $file );
        @unlink( $file );
        exit;
    }

    private function verify_export_request() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_bookings' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        check_admin_referer( 'gd_export_bookings' );
    }

    private function export_headers() {
        return array( 'ID', 'Datum', 'Uhrzeit', 'Status', 'Name', 'Personen', 'Tisch', 'Bereichswunsch', 'Tischwunsch', 'Kinderstuhl', 'Hund', 'Allergien', 'Interne Notiz (Team)', 'Telefon', 'E-Mail', 'Nachricht des Gastes' );
    }

    private function export_row( $booking ) {
        return array(
            $booking['id'],
            preg_replace( '/^[^,]+,\s*/u', '', $booking['date'] ),
            $booking['time'],
            $booking['statusLabel'],
            $booking['name'],
            $booking['party'],
            $booking['tableNumber'],
			isset( $booking['formDetails']['area'] ) ? $booking['formDetails']['area'] : '',
			isset( $booking['formDetails']['table'] ) ? $booking['formDetails']['table'] : '',
			! empty( $booking['formDetails']['highchair'] ) ? 'Ja' : 'Nein',
			! empty( $booking['formDetails']['dog'] ) ? 'Ja' : 'Nein',
			isset( $booking['formDetails']['allergies'] ) ? $booking['formDetails']['allergies'] : '',
            $booking['internalComment'],
            $booking['phone'],
            $booking['email'],
            $booking['message'],
        );
    }

    private function create_xlsx_file( $path, $rows ) {
        $zip = new ZipArchive();
        if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            return false;
        }

        $sheet_rows = '';
        foreach ( $rows as $row_index => $row ) {
            $cells = '';
            foreach ( array_values( $row ) as $column_index => $value ) {
                $ref = $this->xlsx_column_name( $column_index + 1 ) . ( $row_index + 1 );
                $style = 0 === $row_index ? ' s="1"' : '';
                $cells .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $this->xml_escape( (string) $value ) . '</t></is></c>';
            }
            $sheet_rows .= '<row r="' . ( $row_index + 1 ) . '">' . $cells . '</row>';
        }

        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Reservierungen" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>';
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="4" width="16" customWidth="1"/><col min="5" max="5" width="24" customWidth="1"/><col min="6" max="7" width="12" customWidth="1"/><col min="8" max="8" width="38" customWidth="1"/><col min="9" max="10" width="25" customWidth="1"/><col min="11" max="11" width="45" customWidth="1"/></cols><sheetData>' . $sheet_rows . '</sheetData><autoFilter ref="A1:K1"/></worksheet>';

        $zip->addFromString( '[Content_Types].xml', $content_types );
        $zip->addFromString( '_rels/.rels', $rels );
        $zip->addFromString( 'xl/workbook.xml', $workbook );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
        $zip->addFromString( 'xl/styles.xml', $styles );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
        return $zip->close();
    }

    private function xlsx_column_name( $number ) {
        $name = '';
        while ( $number > 0 ) {
            $number--;
            $name = chr( 65 + ( $number % 26 ) ) . $name;
            $number = intdiv( $number, 26 );
        }
        return $name;
    }

    private function xml_escape( $value ) {
        return htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
    }

    /**
     * Speichert die Tischnummer aus dem tabletfreundlichen Dashboard.
     */
    public function ajax_restore_booking() {
        $this->verify_ajax_request();

        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        if ( ! $booking_id || get_post_type( $booking_id ) !== $this->booking_post_type() || 'trash' !== get_post_status( $booking_id ) ) {
            wp_send_json_error( array( 'message' => 'Reservierung im Papierkorb nicht gefunden.' ), 404 );
        }

        $result = wp_untrash_post( $booking_id );
        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Reservierung konnte nicht wiederhergestellt werden.' ), 500 );
        }

        wp_send_json_success( array(
            'message' => 'Reservierung wiederhergestellt.',
            'counts'  => $this->get_counts(),
        ) );
    }

    public function ajax_delete_booking_permanently() {
        $this->verify_ajax_request();

        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        if ( ! $booking_id || get_post_type( $booking_id ) !== $this->booking_post_type() || 'trash' !== get_post_status( $booking_id ) ) {
            wp_send_json_error( array( 'message' => 'Reservierung im Papierkorb nicht gefunden.' ), 404 );
        }

        $result = wp_delete_post( $booking_id, true );
        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Reservierung konnte nicht endgültig gelöscht werden.' ), 500 );
        }

        wp_send_json_success( array(
            'message' => 'Reservierung endgültig gelöscht.',
            'counts'  => $this->get_counts(),
        ) );
    }

    public function ajax_update_table_number() {
        $this->verify_ajax_request();

        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        $table       = isset( $_POST['tableNumber'] ) ? $this->sanitize_table_number( wp_unslash( $_POST['tableNumber'] ) ) : '';
        $allow_occupied = isset( $_POST['allowOccupied'] ) && '1' === (string) wp_unslash( $_POST['allowOccupied'] );

        if ( ! $booking_id || get_post_type( $booking_id ) !== $this->booking_post_type() ) {
            wp_send_json_error( array( 'message' => 'Ungültige Reservierung.' ), 400 );
        }

        if ( '' !== $table && ctype_digit( $table ) ) {
            $availability = $this->get_table_availability_for_booking( $booking_id );
            $table_number = (int) $table;
            if ( isset( $availability[ $table_number ] ) && ! empty( $availability[ $table_number ]['occupiedSeats'] ) && ! $allow_occupied ) {
                wp_send_json_error( array(
                    'message'  => 'Dieser Tisch ist zur Reservierungszeit bereits belegt.',
                    'conflict' => $availability[ $table_number ],
                ), 409 );
            }
        }

        if ( '' === $table ) {
            delete_post_meta( $booking_id, self::TABLE_META );
        } else {
            update_post_meta( $booking_id, self::TABLE_META, $table );
        }

        wp_send_json_success( array(
            'bookingId'   => $booking_id,
            'tableNumber' => $table,
            'message'     => '' === $table ? 'Tischnummer entfernt.' : 'Tischnummer gespeichert.',
        ) );
    }

    /**
     * Speichert einen internen Kommentar aus dem tabletfreundlichen Dashboard.
     */
    public function ajax_update_internal_comment() {
        $this->verify_ajax_request();

        $booking_id = isset( $_POST['bookingId'] ) ? absint( $_POST['bookingId'] ) : 0;
        $comment    = isset( $_POST['internalComment'] ) ? $this->sanitize_internal_comment( wp_unslash( $_POST['internalComment'] ) ) : '';

        if ( ! $booking_id || get_post_type( $booking_id ) !== $this->booking_post_type() ) {
            wp_send_json_error( array( 'message' => 'Ungültige Reservierung.' ), 400 );
        }

        if ( '' === $comment ) {
            delete_post_meta( $booking_id, self::COMMENT_META );
        } else {
            update_post_meta( $booking_id, self::COMMENT_META, $comment );
        }

        wp_send_json_success( array(
            'bookingId'       => $booking_id,
            'internalComment' => $comment,
            'message'         => '' === $comment ? 'Kommentar entfernt.' : 'Kommentar gespeichert.',
        ) );
    }

    /**
     * Fügt auf der normalen WordPress-Bearbeitungsseite ein Feld hinzu.
     */
    public function register_table_meta_box() {
        if ( ! post_type_exists( $this->booking_post_type() ) ) {
            return;
        }

        add_meta_box(
            'gd-booking-table-number',
            'Tischzuweisung',
            array( $this, 'render_table_meta_box' ),
            $this->booking_post_type(),
            'side',
            'high'
        );
    }

    public function render_table_meta_box( $post ) {
        wp_nonce_field( 'gd_save_table_number', 'gd_table_number_nonce' );
        $table         = (string) get_post_meta( $post->ID, self::TABLE_META, true );
        $guest_message = $this->get_guest_message( $post );
        $comment       = $this->get_effective_internal_comment( $post->ID, $guest_message );
        ?>
        <p>
            <label for="gd_table_number"><strong>Tischnummer</strong></label>
        </p>
        <p>
            <input
                type="text"
                id="gd_table_number"
                name="gd_table_number"
                value="<?php echo esc_attr( $table ); ?>"
                placeholder="z. B. 7 oder Terrasse 3"
                maxlength="30"
                style="width:100%;"
            >
        </p>
        <?php if ( '' !== $guest_message ) : ?>
            <div style="margin:14px 0; padding:12px 13px; border-left:4px solid #4f7a49; background:#f2f7f1; border-radius:4px;">
                <strong style="display:block; margin-bottom:5px;">Nachricht des Gastes</strong>
                <div><?php echo nl2br( esc_html( $guest_message ) ); ?></div>
            </div>
        <?php endif; ?>
        <p>
            <label for="gd_internal_comment"><strong>Interne Notiz (nur Team)</strong></label>
        </p>
        <p>
            <textarea
                id="gd_internal_comment"
                name="gd_internal_comment"
                rows="5"
                maxlength="1000"
                placeholder="z. B. Stammgast, Rückruf erledigt oder interne Information"
                style="width:100%; resize:vertical;"
            ><?php echo esc_textarea( $comment ); ?></textarea>
        </p>
        <p class="description">Die Nachricht des Gastes ist oben separat dargestellt. Die interne Notiz ist ausschließlich für angemeldete Mitarbeitende sichtbar.</p>
        <?php
    }

    public function save_table_meta_box( $post_id ) {
        if ( ! isset( $_POST['gd_table_number_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gd_table_number_nonce'] ) ), 'gd_save_table_number' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( get_post_type( $post_id ) !== $this->booking_post_type() || ! current_user_can( 'manage_bookings' ) ) {
            return;
        }

        $table   = isset( $_POST['gd_table_number'] ) ? $this->sanitize_table_number( wp_unslash( $_POST['gd_table_number'] ) ) : '';
        $comment = isset( $_POST['gd_internal_comment'] ) ? $this->sanitize_internal_comment( wp_unslash( $_POST['gd_internal_comment'] ) ) : '';

        if ( '' === $table ) {
            delete_post_meta( $post_id, self::TABLE_META );
        } else {
            update_post_meta( $post_id, self::TABLE_META, $table );
        }

        if ( '' === $comment ) {
            delete_post_meta( $post_id, self::COMMENT_META );
        } else {
            update_post_meta( $post_id, self::COMMENT_META, $comment );
        }
    }

    public function add_table_admin_column( $columns ) {
        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[ $key ] = $label;
            if ( 'title' === $key ) {
                $result['gd_table_number'] = 'Tisch';
                $result['gd_internal_comment'] = 'Interne Notiz';
            }
        }
        if ( ! isset( $result['gd_table_number'] ) ) {
            $result['gd_table_number'] = 'Tisch';
        }
        if ( ! isset( $result['gd_internal_comment'] ) ) {
            $result['gd_internal_comment'] = 'Interne Notiz';
        }
        return $result;
    }

    public function render_table_admin_column( $column, $post_id ) {
        if ( 'gd_table_number' === $column ) {
            $table = (string) get_post_meta( $post_id, self::TABLE_META, true );
            echo '' !== $table ? '<strong>' . esc_html( $table ) . '</strong>' : '<span aria-hidden="true">—</span>';
            return;
        }

        if ( 'gd_internal_comment' === $column ) {
            $post = get_post( $post_id );
            $guest_message = $post instanceof WP_Post ? $this->get_guest_message( $post ) : '';
            $comment = $this->get_effective_internal_comment( $post_id, $guest_message );
            if ( '' === $comment ) {
                echo '<span aria-hidden="true">—</span>';
                return;
            }
            echo esc_html( wp_html_excerpt( $comment, 90, '…' ) );
        }
    }

    private function sanitize_table_number( $value ) {
        $value = sanitize_text_field( $value );
        return mb_substr( trim( $value ), 0, 30 );
    }

    private function sanitize_internal_comment( $value ) {
        $value = sanitize_textarea_field( $value );
        return mb_substr( trim( $value ), 0, 1000 );
    }

    private function normalize_note_text( $value ) {
        $value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, get_bloginfo( 'charset' ) );
        $value = preg_replace( '/\s+/u', ' ', trim( $value ) );
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
    }

    private function get_guest_message( $post ) {
        if ( ! ( $post instanceof WP_Post ) ) {
            return '';
        }
        return trim( wp_strip_all_tags( $post->post_content ) );
    }

    private function get_effective_internal_comment( $post_id, $guest_message = '' ) {
        $comment = trim( (string) get_post_meta( $post_id, self::COMMENT_META, true ) );
        if ( '' === $comment ) {
            return '';
        }

        // Version 1.0.4 des Gäste-Plugins kopierte die Gästenachricht irrtümlich
        // zusätzlich in das interne Feld. Wenn beide Inhalte identisch sind,
        // behandeln wir den Eintrag ausschließlich als Gästenachricht.
        if ( '' !== $guest_message && $this->normalize_note_text( $comment ) === $this->normalize_note_text( $guest_message ) ) {
            return '';
        }

        return $comment;
    }

    private function verify_ajax_request() {
        check_ajax_referer( 'gd_reservierungsdashboard', 'nonce' );
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_bookings' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
        }
    }

    private function get_bookings( $view, $search = '', $limit = 200 ) {
        $args = array(
            'post_type'      => $this->booking_post_type(),
            'posts_per_page' => (int) $limit,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'post_status'    => array_keys( $this->get_booking_statuses() ),
        );

        $now = current_datetime();
        $today_start = $now->format( 'Y-m-d 00:00:00' );
        $today_end   = $now->format( 'Y-m-d 23:59:59' );

        switch ( $view ) {
            case 'pending':
                $args['post_status'] = array( 'pending' );
                break;
            case 'confirmed':
                $args['post_status'] = array( 'confirmed' );
                $args['date_query'] = array( array( 'after' => $today_start, 'inclusive' => true ) );
                break;
            case 'today':
                $args['date_query'] = array( array( 'after' => $today_start, 'before' => $today_end, 'inclusive' => true ) );
                break;
            case 'upcoming':
                $args['date_query'] = array( array( 'after' => $today_start, 'inclusive' => true ) );
                break;
            case 'trash':
                $args['post_status'] = array( 'trash' );
                $args['order'] = 'DESC';
                break;
            case 'all':
            default:
                break;
        }

        $posts = get_posts( $args );
        $bookings = array();

		foreach ( $posts as $post ) {
			$meta = get_post_meta( $post->ID, 'rtb', true );
			$meta = is_array( $meta ) ? $meta : array();
			$form_details = get_post_meta( $post->ID, '_gelsensystem_form_details', true );
			$form_details = is_array( $form_details ) ? $form_details : array();
			$haystack = strtolower( implode( ' ', array(
                $post->post_title,
                $meta['email'] ?? '',
                $meta['phone'] ?? '',
                (string) get_post_meta( $post->ID, self::TABLE_META, true ),
				(string) get_post_meta( $post->ID, self::COMMENT_META, true ),
				isset( $form_details['area'] ) ? $form_details['area'] : '',
				isset( $form_details['table'] ) ? $form_details['table'] : '',
				isset( $form_details['allergies'] ) ? $form_details['allergies'] : '',
            ) ) );

            if ( $search && false === strpos( $haystack, strtolower( $search ) ) ) {
                continue;
            }

            // post_date enthält bei diesem Plugin bereits die lokale WordPress-Zeit.
            // Ein explizites Parsen in wp_timezone() verhindert eine doppelte
            // UTC-Umrechnung (z. B. 18:00 wurde zuvor als 20:00 angezeigt).
            $booking_datetime = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $post->post_date,
                wp_timezone()
            );
            $timestamp = $booking_datetime instanceof DateTimeImmutable
                ? $booking_datetime->getTimestamp()
                : strtotime( $post->post_date );
            $guest_message    = $this->get_guest_message( $post );
            $internal_comment = $this->get_effective_internal_comment( $post->ID, $guest_message );

            $bookings[] = array(
                'id'         => (int) $post->ID,
                'name'       => $post->post_title,
                'message'    => $guest_message,
                'party'      => isset( $meta['party'] ) ? (int) $meta['party'] : 0,
                'email'      => isset( $meta['email'] ) ? sanitize_email( $meta['email'] ) : '',
                'phone'      => isset( $meta['phone'] ) ? sanitize_text_field( $meta['phone'] ) : '',
                'status'     => $post->post_status,
                'statusLabel'=> $this->status_label( $post->post_status ),
                'date'       => wp_date( 'D, d.m.Y', $timestamp ),
                'dateValue'  => wp_date( 'Y-m-d', $timestamp ),
                'time'       => wp_date( 'H:i', $timestamp ),
                'timestamp'  => $timestamp,
                'outsideHours' => (bool) get_post_meta( $post->ID, '_gd_manual_outside_hours', true ),
                'tableNumber'    => (string) get_post_meta( $post->ID, self::TABLE_META, true ),
				'internalComment'=> $internal_comment,
				'formDetails'    => array(
					'area'      => isset( $form_details['area'] ) ? sanitize_text_field( $form_details['area'] ) : '',
					'table'     => isset( $form_details['table'] ) ? sanitize_text_field( $form_details['table'] ) : '',
					'highchair' => ! empty( $form_details['highchair'] ),
					'dog'       => ! empty( $form_details['dog'] ),
					'allergies' => isset( $form_details['allergies'] ) ? sanitize_textarea_field( $form_details['allergies'] ) : '',
				),
                'editUrl'    => admin_url( 'admin.php?page=rtb-bookings' ),
            );
        }

        return $bookings;
    }

    private function get_counts() {
        $type = $this->booking_post_type();
        $counts_obj = wp_count_posts( $type );
        $pending = isset( $counts_obj->pending ) ? (int) $counts_obj->pending : 0;
        $confirmed = isset( $counts_obj->confirmed ) ? (int) $counts_obj->confirmed : 0;
        $trash = isset( $counts_obj->trash ) ? (int) $counts_obj->trash : 0;

        $now = current_datetime();
        $today_start = $now->format( 'Y-m-d 00:00:00' );
        $today_end   = $now->format( 'Y-m-d 23:59:59' );
        $statuses = array_keys( $this->get_booking_statuses() );

        $today = new WP_Query( array(
            'post_type' => $type,
            'post_status' => $statuses,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'date_query' => array( array( 'after' => $today_start, 'before' => $today_end, 'inclusive' => true ) ),
        ) );

        $upcoming = new WP_Query( array(
            'post_type' => $type,
            'post_status' => $statuses,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'date_query' => array( array( 'after' => $today_start, 'inclusive' => true ) ),
        ) );

        return array(
            'pending'   => $pending,
            'confirmed' => $confirmed,
            'today'     => (int) $today->found_posts,
            'upcoming'  => (int) $upcoming->found_posts,
            'trash'     => $trash,
        );
    }

    private function get_booking_statuses() {
        global $rtb_controller;

        if ( isset( $rtb_controller->cpts->booking_statuses ) && is_array( $rtb_controller->cpts->booking_statuses ) ) {
            $statuses = $rtb_controller->cpts->booking_statuses;
            if ( ! isset( $statuses['cancelled'] ) ) {
                $statuses['cancelled'] = array( 'label' => 'Storniert' );
            }
            return $statuses;
        }

        return array(
            'pending'   => array( 'label' => 'Offen' ),
            'confirmed' => array( 'label' => 'Bestätigt' ),
            'closed'    => array( 'label' => 'Abgelehnt' ),
            'cancelled' => array( 'label' => 'Storniert' ),
        );
    }

    private function status_label( $status ) {
        if ( 'trash' === $status ) {
            return 'Papierkorb';
        }
        if ( 'cancelled' === $status ) {
            return 'Storniert';
        }
        $statuses = $this->get_booking_statuses();
        if ( isset( $statuses[ $status ]['label'] ) ) {
            return wp_strip_all_tags( $statuses[ $status ]['label'] );
        }
        return ucfirst( $status );
    }

    private function booking_post_type() {
        return defined( 'RTB_BOOKING_POST_TYPE' ) ? RTB_BOOKING_POST_TYPE : 'rtb-booking';
    }

    private function booking_post_type_exists() {
        return post_type_exists( $this->booking_post_type() );
    }

    private function get_rtb_setting( $key, $default = '' ) {
        global $rtb_controller;
        $settings = isset( $rtb_controller->settings ) && is_object( $rtb_controller->settings ) ? $rtb_controller->settings : null;
        if ( ! $settings || ! method_exists( $settings, 'get_setting' ) ) {
            return $default;
        }
        $value = $settings->get_setting( $key );
        return ( null === $value || 'undefined' === $value ) ? $default : $value;
    }

    private function get_open_weekdays() {
        $hours = Gelsendiele_Settings::get( 'opening_hours', null, array() );
        $order = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
        $open  = array();
        foreach ( $order as $day ) {
            $open[] = ! empty( $hours[ $day ]['enabled'] ) && ! empty( $hours[ $day ]['blocks'] );
        }
        return $open;
    }

    private function get_date_exceptions() {
        $exceptions = array();
        $availability = Gelsendiele_Settings::get( 'availability', null, array() );
        foreach ( isset( $availability['closed_dates'] ) ? (array) $availability['closed_dates'] : array() as $date ) {
            $exceptions[] = array( 'type' => 'date', 'date' => $date, 'mode' => 'closed' );
        }
        return $exceptions;
    }

    private function get_refresh_interval() {
        $interval = absint( get_option( self::REFRESH_INTERVAL_OPTION, 5 ) );
        return in_array( $interval, array( 0, 1, 2, 5, 10, 15, 30 ), true ) ? $interval : 5;
    }

    private function render_refresh_interval_options( $selected = null ) {
        $selected = null === $selected ? $this->get_refresh_interval() : absint( $selected );
        $options  = array(
            0  => 'Aus',
            1  => 'Jede Minute',
            2  => 'Alle 2 Minuten',
            5  => 'Alle 5 Minuten',
            10 => 'Alle 10 Minuten',
            15 => 'Alle 15 Minuten',
            30 => 'Alle 30 Minuten',
        );

        $html = '';
        foreach ( $options as $value => $label ) {
            $html .= sprintf(
                '<option value="%1$d"%2$s>%3$s</option>',
                (int) $value,
                selected( $selected, $value, false ),
                esc_html( $label )
            );
        }
        return $html;
    }

    private function get_table_default_capacity() {
        $capacity = absint( get_option( self::TABLE_DEFAULT_CAPACITY_OPTION, 5 ) );
        return max( 1, min( 50, $capacity ?: 5 ) );
    }

    private function get_table_capacity_overrides() {
        $overrides = get_option( self::TABLE_CAPACITY_OVERRIDES_OPTION, array() );
        if ( ! is_array( $overrides ) ) {
            return array();
        }

        $clean = array();
        foreach ( $overrides as $table => $capacity ) {
            $table = absint( $table );
            $capacity = absint( $capacity );
            if ( $table >= 1 && $table <= 300 && $capacity >= 1 && $capacity <= 50 ) {
                $clean[ $table ] = $capacity;
            }
        }
        ksort( $clean, SORT_NUMERIC );
        return $clean;
    }

    private function parse_table_capacity_overrides( $text ) {
        $result = array();
        $lines = preg_split( '/\r\n|\r|\n|,|;/u', (string) $text );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line || ! preg_match( '/^(\d+)\s*[:=]\s*(\d+)$/', $line, $matches ) ) {
                continue;
            }
            $table = max( 1, min( 300, (int) $matches[1] ) );
            $capacity = max( 1, min( 50, (int) $matches[2] ) );
            $result[ $table ] = $capacity;
        }
        ksort( $result, SORT_NUMERIC );
        return $result;
    }

    private function format_table_capacity_overrides( $overrides = null ) {
        $overrides = null === $overrides ? $this->get_table_capacity_overrides() : $overrides;
        $lines = array();
        foreach ( $overrides as $table => $capacity ) {
            $lines[] = absint( $table ) . '=' . absint( $capacity );
        }
        return implode( "\n", $lines );
    }

    private function get_table_capacity( $table_number ) {
        $table_number = absint( $table_number );
        $overrides = $this->get_table_capacity_overrides();
        return isset( $overrides[ $table_number ] ) ? (int) $overrides[ $table_number ] : $this->get_table_default_capacity();
    }

    private function get_booking_duration_minutes() {
        $duration = absint( Gelsendiele_Settings::get( 'reservations', 'booking_duration', 120 ) );
        $buffer   = absint( Gelsendiele_Settings::get( 'reservations', 'buffer_minutes', 0 ) );
        return max( 30, min( 960, ( $duration ?: 120 ) + $buffer ) );
    }

    private function ensure_rtb_class( $class_name, $candidates ) {
        if ( class_exists( $class_name ) ) {
            return true;
        }
        if ( ! defined( 'RTB_PLUGIN_DIR' ) ) {
            return false;
        }
        foreach ( (array) $candidates as $candidate ) {
            $file = trailingslashit( RTB_PLUGIN_DIR ) . ltrim( $candidate, '/' );
            if ( file_exists( $file ) ) {
                require_once $file;
                if ( class_exists( $class_name ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function calculate_available_slots( DateTime $date, $party = 1, $exclude_booking_id = 0 ) {
        if ( ! class_exists( 'GD_Reservation_Engine' ) ) {
            return array();
        }
        return GD_Reservation_Engine::instance()->available_slots(
            $date->format( 'Y-m-d' ),
            max( 1, absint( $party ) ),
            absint( $exclude_booking_id )
        );
    }

    private function create_manual_booking( $data ) {
        $local_date = $data['date'] . ' ' . $data['time'] . ':00';
        $booking_id = wp_insert_post( array(
            'post_type'     => $this->booking_post_type(),
            'post_status'   => $data['status'],
            'post_title'    => $data['name'],
            'post_content'  => $data['guest_message'],
            'post_date'     => $local_date,
            'post_date_gmt' => get_gmt_from_date( $local_date ),
            'post_author'   => get_current_user_id(),
        ), true );
        if ( is_wp_error( $booking_id ) || ! $booking_id ) {
            return is_wp_error( $booking_id ) ? $booking_id : new WP_Error( 'gd_manual_booking_failed', 'Die Reservierung konnte nicht gespeichert werden.' );
        }

        update_post_meta( $booking_id, 'rtb', array(
            'party' => absint( $data['party'] ),
            'email' => $data['email'],
            'phone' => $data['phone'],
        ) );
        update_post_meta( $booking_id, '_gd_created_by_engine', GELSENDIELE_VERSION );

        update_post_meta( $booking_id, '_gd_manual_booking', 1 );
        if ( ! empty( $data['outside_hours'] ) ) {
            update_post_meta( $booking_id, '_gd_manual_outside_hours', 1 );
        }
        if ( ! empty( $data['no_contact'] ) ) {
            update_post_meta( $booking_id, '_gd_manual_no_contact', 1 );
        }
        if ( '' !== $data['table_number'] ) {
            update_post_meta( $booking_id, self::TABLE_META, $data['table_number'] );
        }
        if ( '' !== $data['internal_comment'] ) {
            update_post_meta( $booking_id, self::COMMENT_META, $data['internal_comment'] );
        }

        GD_Reservation_Engine::instance()->send_new_booking_emails( $booking_id );
        do_action( 'gd_reservierungsdashboard_manual_booking_created', $booking_id, $data );
        return $booking_id;
    }

    private function get_table_availability_for_datetime( $date, $time, $party, $exclude_booking_id = 0, $selected_table = '' ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $time ) ) {
            return new WP_Error( 'gd_invalid_datetime', 'Datum oder Uhrzeit ist ungültig.' );
        }
        $timezone = wp_timezone();
        $current_start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $timezone );
        if ( ! $current_start ) {
            return new WP_Error( 'gd_invalid_datetime', 'Datum oder Uhrzeit ist ungültig.' );
        }
        $party = max( 1, absint( $party ) );
        $duration_minutes = $this->get_booking_duration_minutes();
        $current_end = $current_start->modify( '+' . $duration_minutes . ' minutes' );
        $active_statuses = array_values( array_intersect( array_keys( $this->get_booking_statuses() ), array( 'pending', 'confirmed', 'arrived', 'payment_pending' ) ) );
        if ( empty( $active_statuses ) ) {
            $active_statuses = array( 'pending', 'confirmed' );
        }
        $args = array(
            'post_type'      => $this->booking_post_type(),
            'post_status'    => $active_statuses,
            'posts_per_page' => -1,
            'date_query'     => array( array(
                'after'     => $current_start->format( 'Y-m-d 00:00:00' ),
                'before'    => $current_start->format( 'Y-m-d 23:59:59' ),
                'inclusive' => true,
            ) ),
        );
        if ( $exclude_booking_id ) {
            $args['post__not_in'] = array( absint( $exclude_booking_id ) );
        }
        $others = get_posts( $args );
        $occupied = array();
        foreach ( $others as $other ) {
            $table_value = trim( (string) get_post_meta( $other->ID, self::TABLE_META, true ) );
            if ( '' === $table_value || ! ctype_digit( $table_value ) ) {
                continue;
            }
            $table_number = (int) $table_value;
            if ( $table_number < 1 || $table_number > $this->get_table_count() ) {
                continue;
            }
            $other_start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $other->post_date, $timezone );
            if ( ! $other_start ) {
                continue;
            }
            $other_end = $other_start->modify( '+' . $duration_minutes . ' minutes' );
            if ( ! ( $current_start < $other_end && $current_end > $other_start ) ) {
                continue;
            }
            $meta = get_post_meta( $other->ID, 'rtb', true );
            $meta = is_array( $meta ) ? $meta : array();
            $other_party = isset( $meta['party'] ) ? max( 0, (int) $meta['party'] ) : 0;
            if ( ! isset( $occupied[ $table_number ] ) ) {
                $occupied[ $table_number ] = array( 'seats' => 0, 'bookings' => array() );
            }
            $occupied[ $table_number ]['seats'] += $other_party;
            $occupied[ $table_number ]['bookings'][] = array(
                'id'    => (int) $other->ID,
                'name'  => $other->post_title,
                'party' => $other_party,
                'time'  => $other_start->format( 'H:i' ),
            );
        }

        $result = array();
        for ( $number = 1; $number <= $this->get_table_count(); $number++ ) {
            $capacity = $this->get_table_capacity( $number );
            $occupied_seats = isset( $occupied[ $number ] ) ? (int) $occupied[ $number ]['seats'] : 0;
            $remaining = max( 0, $capacity - $occupied_seats );
            $state = 0 === $occupied_seats ? 'free' : ( $remaining > 0 ? 'partial' : 'full' );
            $result[ $number ] = array(
                'number'         => $number,
                'capacity'       => $capacity,
                'occupiedSeats'  => $occupied_seats,
                'remainingSeats' => $remaining,
                'requestedSeats' => $party,
                'state'          => $state,
                'selected'       => (string) $number === (string) $selected_table,
                'canShare'       => $occupied_seats > 0 && $party <= $remaining,
                'bookings'       => isset( $occupied[ $number ] ) ? $occupied[ $number ]['bookings'] : array(),
            );
        }
        return $result;
    }

    private function get_table_availability_for_booking( $booking_id ) {
        $current = get_post( $booking_id );
        if ( ! ( $current instanceof WP_Post ) ) {
            return array();
        }
        $meta = get_post_meta( $booking_id, 'rtb', true );
        $meta = is_array( $meta ) ? $meta : array();
        $party = isset( $meta['party'] ) ? max( 1, (int) $meta['party'] ) : 1;
        $selected_table = trim( (string) get_post_meta( $booking_id, self::TABLE_META, true ) );
        $datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $current->post_date, wp_timezone() );
        if ( ! $datetime ) {
            return array();
        }
        $result = $this->get_table_availability_for_datetime(
            $datetime->format( 'Y-m-d' ),
            $datetime->format( 'H:i' ),
            $party,
            $booking_id,
            $selected_table
        );
        return is_wp_error( $result ) ? array() : $result;
    }

    private function get_table_count() {
        $count = absint( get_option( self::TABLE_COUNT_OPTION, 30 ) );
        return max( 1, min( 300, $count ?: 30 ) );
    }

    private function default_whatsapp_template() {
        return 'Hallo {name}, hier ist die Gelsendiele. Wir melden uns zu Ihrer Reservierung am {date} um {time} Uhr für {party}. Liebe Grüße, Ihr Gelsendiele-Team';
    }

    private function get_whatsapp_template() {
        $template = get_option( self::WHATSAPP_TEMPLATE_OPTION, $this->default_whatsapp_template() );
        return is_string( $template ) && '' !== trim( $template )
            ? $template
            : $this->default_whatsapp_template();
    }

    private function get_brand_logo_url( $size = 'medium' ) {
        $attachment_id = absint( Gelsendiele_Settings::get( 'branding', 'logo_attachment_id', 0 ) );
        if ( $attachment_id ) {
            $attachment = wp_get_attachment_image_url( $attachment_id, $size );
            if ( $attachment ) {
                return $attachment;
            }
        }
        $custom_url = Gelsendiele_Settings::get( 'branding', 'logo_url', '' );
        return $custom_url ? $custom_url : plugin_dir_url( __FILE__ ) . 'assets/gelsendiele-app-icon-192.png';
    }

    private function render_brand_logo( $wrapper_class = 'gd-mobile-logo', $fallback = 'G', $label = 'Gelsendiele' ) {
        $url = $this->get_brand_logo_url();
        if ( $url ) {
            return sprintf(
                '<span class="%1$s gd-brand-logo has-image" aria-label="%3$s"><img class="gd-brand-logo-image" src="%2$s" alt="%3$s" loading="eager" decoding="async"></span>',
                esc_attr( $wrapper_class ),
                esc_url( $url ),
                esc_attr( $label )
            );
        }

        return sprintf(
            '<span class="%1$s gd-brand-logo gd-brand-logo-fallback" aria-hidden="true">%2$s</span>',
            esc_attr( $wrapper_class ),
            esc_html( $fallback )
        );
    }


    private function is_dashboard_page() {
        $page_id = (int) get_option( self::PAGE_OPTION );
        if ( $page_id && is_page( $page_id ) ) {
            return true;
        }

        if ( ! is_singular( 'page' ) ) {
            return false;
        }

        global $post;
        return $post instanceof WP_Post && has_shortcode( $post->post_content, self::SHORTCODE );
    }

    private function dashboard_url() {
        $page_id = (int) get_option( self::PAGE_OPTION );
        return $page_id ? get_permalink( $page_id ) : home_url( '/reservierungsverwaltung/' );
    }

    private function render_central_app_section( $section ) {
        $business_name = Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' );
        $branding      = Gelsendiele_Settings::get( 'branding', null, array() );
        $brand_style   = Gelsendiele_Settings::css_variables()
            . '--gd-green:' . $branding['primary_color'] . ';'
            . '--gd-green-dark:' . $branding['secondary_color'] . ';'
            . '--gd-bg:' . $branding['surface_color'] . ';';
        ob_start();
        ?>
        <div class="gd-dashboard-shell gd-has-central-nav gd-central-section-shell" data-app-section="<?php echo esc_attr( $section ); ?>" style="<?php echo esc_attr( $brand_style ); ?>">
            <?php $this->render_central_navigation( $section ); ?>
            <header class="gd-central-mobile-header"><div><small>Gelsensystem</small><strong><?php echo esc_html( $business_name ); ?></strong></div><a href="<?php echo esc_url( add_query_arg( 'gd-section', 'reservations', $this->dashboard_url() ) ); ?>">Reservierungen</a></header>
            <main class="gd-central-content">
                <?php if ( 'settings' === $section ) { Gelsendiele_Admin::render_app_settings( $this->dashboard_url() ); } else { Gelsendiele_Admin::render_app_users(); } ?>
            </main>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_central_navigation( $active ) {
        $dashboard = $this->dashboard_url();
        $items = array(
            array( 'reservations', 'Reservierungen', 'manage_bookings', add_query_arg( 'gd-section', 'reservations', $dashboard ), 'R' ),
            array( 'service', 'Service', 'gdg_use_service', $this->workspace_url( 'service' ), 'S' ),
            array( 'kitchen', 'Küche', 'gdg_use_kitchen', $this->workspace_url( 'kitchen' ), 'K' ),
            array( 'bar', 'Schank', 'gdg_use_bar', $this->workspace_url( 'bar' ), 'B' ),
            array( 'checkout', 'Kasse', 'gdg_use_checkout', $this->workspace_url( 'checkout' ), '€' ),
            array( 'tables', 'Tische & Bereiche', 'gdg_manage', admin_url( 'admin.php?page=gdg-tables' ), 'T' ),
            array( 'menu', 'Speisekarte', 'gdg_manage', admin_url( 'admin.php?page=gdg-menu' ), 'M' ),
            array( 'settings', 'Einstellungen', 'gelsendiele_manage_settings', add_query_arg( 'gd-section', 'settings', $dashboard ), 'E' ),
            array( 'users', 'Benutzer & Rechte', 'manage_options', add_query_arg( 'gd-section', 'users', $dashboard ), 'U' ),
        );
        $business_name = Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' );
        ?>
        <aside class="gelsensystem-sidebar" aria-label="Gelsensystem Bereiche">
            <a class="gelsensystem-sidebar-brand" href="<?php echo esc_url( $dashboard ); ?>"><span>GS</span><div><strong>Gelsensystem</strong><small><?php echo esc_html( $business_name ); ?></small></div></a>
            <nav><?php foreach ( $items as $item ) : if ( ! current_user_can( $item[2] ) || ! $item[3] ) { continue; } ?><a class="<?php echo $active === $item[0] ? 'is-active' : ''; ?>" href="<?php echo esc_url( $item[3] ); ?>"><span><?php echo esc_html( $item[4] ); ?></span><?php echo esc_html( $item[1] ); ?></a><?php endforeach; ?></nav>
            <div class="gelsensystem-sidebar-footer"><small>Angemeldet als</small><strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong><a href="<?php echo esc_url( wp_logout_url( $dashboard ) ); ?>">Abmelden</a></div>
        </aside>
        <?php
    }

    private function workspace_url( $view ) {
        $page_id = (int) get_option( 'gdg_page_' . sanitize_key( $view ), 0 );
        return $page_id ? get_permalink( $page_id ) : '';
    }
}

register_activation_hook( __FILE__, array( 'Gelsendiele_Reservierungsdashboard', 'activate' ) );
new Gelsendiele_Reservierungsdashboard();
