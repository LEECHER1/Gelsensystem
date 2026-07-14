<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'RTB_BOOKING_POST_TYPE' ) ) { define( 'RTB_BOOKING_POST_TYPE', 'rtb-booking' ); }

final class GD_Reservation_Engine {
    const VERSION = GELSENDIELE_VERSION;
    const FORM_SHORTCODE = 'gelsendiele_reservierungsformular';
    const LEGACY_SHORTCODE = 'booking-form';
    const SETTINGS_OPTION = 'gd_reservation_engine_settings';

    private static $instance;
    public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }

    private function __construct() {
        add_action( 'init', array( $this, 'register_content' ), 1 );
        add_action( 'init', array( $this, 'register_shortcodes' ), 20 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'wp_ajax_nopriv_gd_public_create_booking', array( $this, 'ajax_create_booking' ) );
        add_action( 'wp_ajax_gd_public_create_booking', array( $this, 'ajax_create_booking' ) );
        add_action( 'wp_ajax_nopriv_gd_public_slots', array( $this, 'ajax_slots' ) );
        add_action( 'wp_ajax_gd_public_slots', array( $this, 'ajax_slots' ) );
        add_action( 'wp_ajax_nopriv_gd_public_month_availability', array( $this, 'ajax_month_availability' ) );
        add_action( 'wp_ajax_gd_public_month_availability', array( $this, 'ajax_month_availability' ) );
        add_action( 'admin_init', array( $this, 'ensure_roles' ) );
        add_action( 'rtb_update_booking', array( $this, 'maybe_send_status_email' ), 10, 1 );
        add_action( 'gelsendiele_release_booking_lock', array( $this, 'release_booking_lock' ), 10, 1 );
        add_action( 'plugins_loaded', 'gelsendiele_register_rtb_compatibility_classes', 90 );
        add_action( 'plugins_loaded', array( $this, 'bootstrap_compatibility_controller' ), 100 );
    }

    public static function activate() {
        self::instance()->register_content();
        self::instance()->ensure_roles();
        if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
            add_option( self::SETTINGS_OPTION, self::defaults(), '', false );
        }
        $page = get_page_by_path( 'tisch-reservieren' );
        if ( ! $page ) {
            wp_insert_post( array(
                'post_type' => 'page', 'post_status' => 'publish',
                'post_title' => 'Tisch reservieren', 'post_name' => 'tisch-reservieren',
                'post_content' => '[' . self::FORM_SHORTCODE . ']'
            ) );
        }
        flush_rewrite_rules();
    }

    public static function defaults() {
        return array(
            'time_interval' => 30,
            'booking_duration' => 120,
            'min_party' => 1,
            'max_party' => 20,
            'max_tables' => 30,
            'max_people' => 120,
            'advance_days' => 120,
            'lead_minutes' => 60,
            'admin_email' => get_option( 'admin_email' ),
            'opening_hours' => array(
                'mon' => array(), 'tue' => array(),
                'wed' => array( array( 'start'=>'11:00','end'=>'22:00' ) ),
                'thu' => array( array( 'start'=>'11:00','end'=>'22:00' ) ),
                'fri' => array( array( 'start'=>'11:00','end'=>'23:00' ) ),
                'sat' => array( array( 'start'=>'11:00','end'=>'23:00' ) ),
                'sun' => array( array( 'start'=>'11:00','end'=>'21:00' ) ),
            ),
            'closed_dates' => array(),
            'privacy_text' => 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung der Reservierung zu.',
            'success_text' => 'Vielen Dank! Ihre Reservierungsanfrage wurde übermittelt.',
        );
    }

    public function settings() {
        if ( class_exists( 'Gelsendiele_Settings' ) ) {
            return wp_parse_args( Gelsendiele_Settings::reservation_engine_settings(), self::defaults() );
        }
        return wp_parse_args( (array) get_option( self::SETTINGS_OPTION, array() ), self::defaults() );
    }

    public function register_content() {
        // Solange Five Star aktiv ist, bleibt dessen Registrierung maßgeblich.
        // Dadurch überschreiben sich die beiden Plugins nicht gegenseitig.
        if ( defined( 'RTB_PLUGIN_DIR' ) ) {
            return;
        }
        register_post_type( RTB_BOOKING_POST_TYPE, array(
            'labels' => array('name'=>'Reservierungen','singular_name'=>'Reservierung','add_new_item'=>'Reservierung hinzufügen','edit_item'=>'Reservierung bearbeiten'),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-calendar-alt', 'supports' => array('title','editor','author'),
            'capability_type' => array('booking','bookings'), 'map_meta_cap' => true,
            'capabilities' => array(
                'edit_post'=>'edit_booking','read_post'=>'read_booking','delete_post'=>'delete_booking',
                'edit_posts'=>'manage_bookings','edit_others_posts'=>'manage_bookings','publish_posts'=>'manage_bookings',
                'read_private_posts'=>'manage_bookings','delete_posts'=>'manage_bookings','delete_private_posts'=>'manage_bookings',
                'delete_published_posts'=>'manage_bookings','delete_others_posts'=>'manage_bookings','edit_private_posts'=>'manage_bookings','edit_published_posts'=>'manage_bookings',
                'create_posts'=>'manage_bookings'
            )
        ) );
        foreach ( $this->statuses() as $key => $label ) {
            register_post_status( $key, array('label'=>$label,'public'=>false,'internal'=>false,'protected'=>true,'show_in_admin_all_list'=>true,'show_in_admin_status_list'=>true,'label_count'=>_n_noop("$label <span class='count'>(%s)</span>","$label <span class='count'>(%s)</span>")) );
        }
    }

    public function statuses() { return array('pending'=>'Offen','payment_pending'=>'Zahlung offen','confirmed'=>'Bestätigt','arrived'=>'Angekommen','closed'=>'Abgeschlossen','cancelled'=>'Storniert'); }

    public function ensure_roles() {
        foreach ( array('administrator','editor') as $role_name ) {
            $role = get_role($role_name); if($role){ foreach(array('manage_bookings','edit_booking','read_booking','delete_booking') as $cap){$role->add_cap($cap);} }
        }
        if ( ! get_role('gd_reservation_manager') ) add_role('gd_reservation_manager','Gelsensystem Reservierungen',array('read'=>true,'manage_bookings'=>true,'edit_booking'=>true,'read_booking'=>true,'delete_booking'=>true));
    }

    public function bootstrap_compatibility_controller() {
        global $rtb_controller;
        if ( ! isset($rtb_controller) || ! is_object($rtb_controller) ) $rtb_controller = new stdClass();
        if ( empty($rtb_controller->settings) ) $rtb_controller->settings = new GD_RTB_Settings_Compat($this);
        if ( empty($rtb_controller->cpts) ) { $rtb_controller->cpts = new stdClass(); $rtb_controller->cpts->booking_statuses = array_map(function($label){ return array('label'=>$label); }, $this->statuses()); }
        if ( empty($rtb_controller->notifications) ) { $rtb_controller->notifications = new stdClass(); $rtb_controller->notifications->notifications_disabled = false; }
    }

    public function register_shortcodes() {
        add_shortcode(self::FORM_SHORTCODE,array($this,'render_form'));
        if ( ! shortcode_exists(self::LEGACY_SHORTCODE) ) add_shortcode(self::LEGACY_SHORTCODE,array($this,'render_form'));
    }

    public function register_assets() {
        wp_register_style('gd-reservation-form',plugins_url('../assets/reservation-form.css',__FILE__),array(),self::VERSION);
        wp_register_script('gd-reservation-form',plugins_url('../assets/reservation-form.js',__FILE__),array(),self::VERSION,true);
    }

    public function render_form() {
        wp_enqueue_style('gd-reservation-form'); wp_enqueue_script('gd-reservation-form');
        $s               = $this->settings();
        $business_name   = Gelsendiele_Settings::get( 'general', 'business_name', 'Die Gelsendiele' );
        $form_settings   = Gelsendiele_Settings::get( 'form', null, array() );
        $branding        = Gelsendiele_Settings::get( 'branding', null, array() );
        $fields          = isset( $form_settings['fields'] ) ? $form_settings['fields'] : Gelsendiele_Settings::form_field_defaults();
        $brand_primary   = ! empty( $form_settings['primary_color'] ) ? $form_settings['primary_color'] : $branding['primary_color'];
        $theme_mode      = 'inherit' === $form_settings['theme_mode'] ? $branding['theme_mode'] : $form_settings['theme_mode'];
        $surface_color   = ! empty( $form_settings['surface_color'] ) ? $form_settings['surface_color'] : ( 'dark' === $theme_mode ? $branding['dark_surface_color'] : $branding['surface_color'] );
        $text_color      = ! empty( $form_settings['text_color'] ) ? $form_settings['text_color'] : ( 'dark' === $theme_mode ? '#f4f7f5' : '#17221b' );
        wp_add_inline_style(
            'gd-reservation-form',
            '.gdrf-panel{border-radius:var(--gelsendiele-radius,22px)}.gdrf-submit{background:' . esc_attr( $brand_primary ) . '}'
        );
        wp_localize_script('gd-reservation-form','GDReservationForm',array(
            'ajaxUrl'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('gd_public_reservation'),
            'today'=>wp_date('Y-m-d'),
            'maxDate'=>wp_date('Y-m-d',time()+DAY_IN_SECONDS*absint($s['advance_days'])),
            'buttonText'=>$form_settings['button_text'],
            'errorText'=>$form_settings['error_text'],
            'locale'=>array(
                'months'=>array('Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'),
                'weekdays'=>array('Mo','Di','Mi','Do','Fr','Sa','So')
            )
        ));
        $form_style = Gelsendiele_Settings::css_variables() . '--gd-accent:' . $brand_primary . ';--gd-secondary:' . $branding['secondary_color'] . ';--gd-bg:' . $surface_color . ';--gd-text:' . $text_color . ';--gd-dark-bg:' . $branding['dark_surface_color'] . ';max-width:' . absint( $form_settings['width'] ) . 'px;';
        $areas = array();
        $tables = array();
        if ( class_exists( 'GDG_DB' ) ) {
            foreach ( GDG_DB::get_tables( true ) as $table ) {
                $tables[] = $table['name'];
                if ( '' !== trim( $table['area'] ) ) {
                    $areas[] = $table['area'];
                }
            }
        }
        $areas  = array_values( array_unique( $areas ) );
        $tables = array_values( array_unique( $tables ) );
        $label = function( $slug ) use ( $fields ) {
            return isset( $fields[ $slug ]['label'] ) ? $fields[ $slug ]['label'] : ucfirst( $slug );
        };
        $enabled = function( $slug ) use ( $fields ) {
            return ! empty( $fields[ $slug ]['enabled'] );
        };
        $required = function( $slug ) use ( $fields ) {
            return ! empty( $fields[ $slug ]['required'] );
        };
        $marker = function( $slug ) use ( $required ) {
            return $required( $slug ) ? ' *' : '';
        };
        $theme_class = 'dark' === $theme_mode ? ' gdrf-theme-dark' : ( 'light' === $theme_mode ? ' gdrf-theme-light' : ' gdrf-theme-auto' );
        ob_start(); ?>
        <div class="gdrf-shell<?php echo esc_attr( $theme_class ); ?>" style="<?php echo esc_attr( $form_style ); ?>"><form class="gdrf-form" data-gdrf-form novalidate>
          <div class="gdrf-head"><span class="gdrf-eyebrow"><?php echo esc_html( $business_name ); ?></span><h2><?php echo esc_html( $form_settings['headline'] ); ?></h2><p><?php echo esc_html( $form_settings['intro'] ); ?></p></div>
          <div class="gdrf-panel">
          <div class="gdrf-grid">
            <label class="gdrf-date-field"><span><?php echo esc_html( $label( 'date' ) . $marker( 'date' ) ); ?></span>
              <input type="hidden" name="date" required>
              <button type="button" class="gdrf-picker-button" data-gdrf-date-button aria-expanded="false"><span data-gdrf-date-label><?php echo esc_html( $label( 'date' ) ); ?> auswählen</span><span aria-hidden="true">▾</span></button>
              <div class="gdrf-calendar" data-gdrf-calendar hidden>
                <div class="gdrf-calendar-head"><button type="button" data-gdrf-prev aria-label="Vorheriger Monat">‹</button><strong data-gdrf-month></strong><button type="button" data-gdrf-next aria-label="Nächster Monat">›</button></div>
                <div class="gdrf-weekdays" data-gdrf-weekdays></div><div class="gdrf-days" data-gdrf-days></div>
                <div class="gdrf-calendar-note">Ausgegraute Tage sind geschlossen oder nicht mehr verfügbar.</div>
              </div>
            </label>
            <label><span><?php echo esc_html( $label( 'time' ) . $marker( 'time' ) ); ?></span><select name="time" required disabled><option value="">Zuerst Datum wählen</option></select><small class="gdrf-field-note">* Nicht verfügbare Zeiten werden nicht angezeigt.</small><small class="gdrf-field-note gdrf-date-notice" data-gdrf-date-notice hidden></small></label>
            <label><span><?php echo esc_html( $label( 'party' ) . $marker( 'party' ) ); ?></span><input type="number" name="party" min="<?php echo esc_attr($s['min_party']); ?>" max="<?php echo esc_attr($s['max_party']); ?>" value="2" required></label>
            <?php if ( $enabled( 'name' ) ) : ?><label><span><?php echo esc_html( $label( 'name' ) . $marker( 'name' ) ); ?></span><input type="text" name="name" maxlength="180" autocomplete="name" <?php echo $required( 'name' ) ? 'required' : ''; ?>></label><?php endif; ?>
            <?php if ( $enabled( 'email' ) ) : ?><label><span><?php echo esc_html( $label( 'email' ) . $marker( 'email' ) ); ?></span><input type="email" name="email" maxlength="190" autocomplete="email" <?php echo $required( 'email' ) ? 'required' : ''; ?>></label><?php endif; ?>
            <?php if ( $enabled( 'phone' ) ) : ?><label><span><?php echo esc_html( $label( 'phone' ) . $marker( 'phone' ) ); ?></span><input type="tel" name="phone" maxlength="100" autocomplete="tel" <?php echo $required( 'phone' ) ? 'required' : ''; ?>></label><?php endif; ?>
            <?php if ( $enabled( 'area' ) ) : ?><label><span><?php echo esc_html( $label( 'area' ) . $marker( 'area' ) ); ?></span><?php if ( $areas ) : ?><select name="area" <?php echo $required( 'area' ) ? 'required' : ''; ?>><option value="">Keine Präferenz</option><?php foreach ( $areas as $area ) : ?><option value="<?php echo esc_attr( $area ); ?>"><?php echo esc_html( $area ); ?></option><?php endforeach; ?></select><?php else : ?><input type="text" name="area" <?php echo $required( 'area' ) ? 'required' : ''; ?>><?php endif; ?></label><?php endif; ?>
            <?php if ( $enabled( 'table' ) ) : ?><label><span><?php echo esc_html( $label( 'table' ) . $marker( 'table' ) ); ?></span><?php if ( $tables ) : ?><select name="table" <?php echo $required( 'table' ) ? 'required' : ''; ?>><option value="">Keine Präferenz</option><?php foreach ( $tables as $table ) : ?><option value="<?php echo esc_attr( $table ); ?>"><?php echo esc_html( $table ); ?></option><?php endforeach; ?></select><?php else : ?><input type="text" name="table" <?php echo $required( 'table' ) ? 'required' : ''; ?>><?php endif; ?></label><?php endif; ?>
            <?php if ( $enabled( 'message' ) ) : ?><label class="gdrf-wide"><span><?php echo esc_html( $label( 'message' ) . $marker( 'message' ) ); ?></span><textarea name="message" rows="3" maxlength="2000" <?php echo $required( 'message' ) ? 'required' : ''; ?>></textarea></label><?php endif; ?>
            <?php if ( $enabled( 'allergies' ) ) : ?><label class="gdrf-wide"><span><?php echo esc_html( $label( 'allergies' ) . $marker( 'allergies' ) ); ?></span><textarea name="allergies" rows="3" maxlength="2000" <?php echo $required( 'allergies' ) ? 'required' : ''; ?>></textarea></label><?php endif; ?>
            <?php if ( $enabled( 'highchair' ) ) : ?><label class="gdrf-option"><input type="checkbox" name="highchair" value="1" <?php echo $required( 'highchair' ) ? 'required' : ''; ?>><span><?php echo esc_html( $label( 'highchair' ) . $marker( 'highchair' ) ); ?></span></label><?php endif; ?>
            <?php if ( $enabled( 'dog' ) ) : ?><label class="gdrf-option"><input type="checkbox" name="dog" value="1" <?php echo $required( 'dog' ) ? 'required' : ''; ?>><span><?php echo esc_html( $label( 'dog' ) . $marker( 'dog' ) ); ?></span></label><?php endif; ?>
          </div>
          <?php if ( $enabled( 'privacy' ) ) : ?><label class="gdrf-consent"><input type="checkbox" name="consent" value="1" <?php echo $required( 'privacy' ) ? 'required' : ''; ?>><span><?php echo esc_html( $form_settings['privacy_text'] ); ?></span></label><?php endif; ?>
          <input type="hidden" name="submission_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
          <input type="text" name="website" class="gdrf-hp" tabindex="-1" autocomplete="off">
          <button type="submit" class="gdrf-submit"><?php echo esc_html( $form_settings['button_text'] ); ?></button>
          <div class="gdrf-message" data-gdrf-message aria-live="polite"></div>
          </div>
        </form></div><?php return ob_get_clean();
    }

    public function ajax_slots() {
        check_ajax_referer('gd_public_reservation','nonce');
        $date=sanitize_text_field(wp_unslash($_POST['date']??'')); $party=max(1,absint($_POST['party']??1));
        if(!$this->valid_date($date)){ $this->send_json(false,array('message'=>'Ungültiges Datum.'),400); }
        $this->send_json(true,array(
            'slots'=>$this->available_slots($date,$party),
            'notice'=>class_exists('Gelsendiele_Availability') ? Gelsendiele_Availability::public_notice($date) : '',
        ));
    }


    public function ajax_month_availability() {
        check_ajax_referer('gd_public_reservation','nonce');
        $year = absint($_POST['year'] ?? 0);
        $month = absint($_POST['month'] ?? 0);
        $party = max(1, absint($_POST['party'] ?? 1));
        if ($year < 2020 || $month < 1 || $month > 12) {
            $this->send_json(false, array('message'=>'Ungültiger Monat.'), 400);
        }
        $first = DateTimeImmutable::createFromFormat('!Y-n-j', $year.'-'.$month.'-1', wp_timezone());
        if (!$first) { $this->send_json(false, array('message'=>'Ungültiger Monat.'), 400); }
        $days = (int) $first->format('t');
        $available = array();
        for ($day=1; $day <= $days; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $available[$date] = $this->valid_date($date) && !empty($this->available_slots($date, $party));
        }
        $this->send_json(true, array('dates'=>$available));
    }

    public function ajax_create_booking() {
        check_ajax_referer('gd_public_reservation','nonce');
        if(!empty($_POST['website'])) $this->send_json(false,array('message'=>'Die Anfrage konnte nicht verarbeitet werden.'),400);
        $form_settings = Gelsendiele_Settings::get( 'form', null, array() );
        $fields        = isset( $form_settings['fields'] ) ? $form_settings['fields'] : Gelsendiele_Settings::form_field_defaults();
        $data=array(
            'date'=>sanitize_text_field(wp_unslash($_POST['date']??'')), 'time'=>sanitize_text_field(wp_unslash($_POST['time']??'')), 'party'=>absint($_POST['party']??0),
            'name'=>sanitize_text_field(wp_unslash($_POST['name']??'')), 'email'=>sanitize_email(wp_unslash($_POST['email']??'')), 'phone'=>sanitize_text_field(wp_unslash($_POST['phone']??'')),
            'message'=>sanitize_textarea_field(wp_unslash($_POST['message']??'')), 'area'=>sanitize_text_field(wp_unslash($_POST['area']??'')), 'table'=>sanitize_text_field(wp_unslash($_POST['table']??'')),
            'highchair'=>!empty($_POST['highchair'])?1:0, 'dog'=>!empty($_POST['dog'])?1:0, 'allergies'=>sanitize_textarea_field(wp_unslash($_POST['allergies']??'')),
            'submission_token'=>sanitize_key(wp_unslash($_POST['submission_token']??'')),
        );
        $data['name']=$this->limit_text($data['name'],180); $data['email']=$this->limit_text($data['email'],190); $data['phone']=$this->limit_text($data['phone'],100);
        $data['message']=$this->limit_text($data['message'],2000); $data['allergies']=$this->limit_text($data['allergies'],2000); $data['area']=$this->limit_text($data['area'],120); $data['table']=$this->limit_text($data['table'],120);
        $settings = $this->settings();
        $enabled = function( $slug ) use ( $fields ) { return ! empty( $fields[ $slug ]['enabled'] ); };
        $required = function( $slug ) use ( $fields, $enabled ) { return $enabled( $slug ) && ! empty( $fields[ $slug ]['required'] ); };
        $invalid = !$this->valid_date($data['date']) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$data['time']) || $data['party']<absint($settings['min_party']) || $data['party']>absint($settings['max_party']);
        $invalid = $invalid || ( $required('privacy') && empty($_POST['consent']) ) || ( $required('name') && !$data['name'] ) || ( $required('phone') && !$data['phone'] );
        $invalid = $invalid || ( $required('email') && !is_email($data['email']) ) || ( $enabled('email') && $data['email'] && !is_email($data['email']) );
        foreach ( array( 'message', 'area', 'table', 'allergies' ) as $required_text_field ) {
            if ( $required( $required_text_field ) && '' === $data[ $required_text_field ] ) { $invalid = true; }
        }
        if ( ( $required('highchair') && !$data['highchair'] ) || ( $required('dog') && !$data['dog'] ) ) { $invalid = true; }
        if ( $invalid ) $this->send_json(false,array('message'=>$form_settings['error_text']),422);
        if ( ! $enabled( 'name' ) || '' === $data['name'] ) { $data['name'] = 'Online-Reservierung'; }
        foreach ( array( 'email', 'phone', 'message', 'area', 'table', 'allergies' ) as $optional_field ) {
            if ( ! $enabled( $optional_field ) ) { $data[ $optional_field ] = ''; }
        }
        if ( ! $enabled( 'highchair' ) ) { $data['highchair'] = 0; }
        if ( ! $enabled( 'dog' ) ) { $data['dog'] = 0; }
        $submission_lock = $this->booking_lock_key( 'submission', array( $data['submission_token'], $data['date'], $data['time'], $data['party'], strtolower( $data['email'] ), preg_replace( '/\D+/', '', $data['phone'] ), strtolower( $data['name'] ) ) );
        if ( ! $this->acquire_lock( $submission_lock, 10 * MINUTE_IN_SECONDS ) ) {
            $this->send_json(false,array('message'=>'Diese Reservierungsanfrage wurde bereits übermittelt. Bitte prüfen Sie Ihre Bestätigung oder wenden Sie sich an den Betrieb.'),409);
        }
        $slot_lock = $this->booking_lock_key( 'slot', array( $data['date'], $data['time'] ) );
        if ( ! $this->acquire_lock( $slot_lock, 30 ) ) {
            delete_option( $submission_lock );
            $this->send_json(false,array('message'=>'Die Verfügbarkeit wird gerade aktualisiert. Bitte versuchen Sie es in wenigen Sekunden erneut.'),409);
        }
        // Unter dem Slot-Lock erneut prüfen, damit parallele Anfragen die
        // Kapazitätsgrenze nicht gleichzeitig überschreiten können.
        if(!in_array($data['time'],$this->available_slots($data['date'],$data['party']),true)) {
            delete_option( $slot_lock );
            delete_option( $submission_lock );
            $this->send_json(false,array('message'=>'Diese Uhrzeit ist leider nicht mehr verfügbar.'),409);
        }
        // Deliberately save with our own engine. When Five Star is still active its rtbBooking
        // class owns the same name and may terminate or alter the AJAX response.
        $local = $data['date'].' '.$data['time'].':00';
        $confirmation_mode = Gelsendiele_Settings::get( 'general', 'confirmation_mode', 'manual' );
        $initial_status = ( 'automatic' === $confirmation_mode || get_option( 'gd_auto_confirm_bookings', false ) ) ? 'confirmed' : 'pending';
        $id = wp_insert_post(array(
            'post_type'=>RTB_BOOKING_POST_TYPE,
            'post_status'=>$initial_status,
            'post_title'=>$data['name'],
            'post_content'=>$data['message'],
            'post_date'=>$local,
            'post_date_gmt'=>get_gmt_from_date($local),
            'post_author'=>get_current_user_id(),
        ), true);
        if (is_wp_error($id) || !$id) {
            delete_option( $slot_lock );
            delete_option( $submission_lock );
            $this->send_json(false,array('message'=>'Die Reservierung konnte nicht gespeichert werden.'),500);
        }
        update_post_meta($id,'rtb',array('party'=>$data['party'],'email'=>$data['email'],'phone'=>$data['phone']));
        update_post_meta($id,'_gelsensystem_form_details',array('area'=>$data['area'],'table'=>$data['table'],'highchair'=>$data['highchair'],'dog'=>$data['dog'],'allergies'=>$data['allergies']));
        update_post_meta($id,'_gd_created_by_engine',self::VERSION);
        update_post_meta($id,'_gelsendiele_submission_fingerprint',substr($submission_lock,-32));
        update_option( $submission_lock, array( 'created' => time(), 'booking_id' => absint( $id ) ), false );
        wp_schedule_single_event( time() + ( 10 * MINUTE_IN_SECONDS ), 'gelsendiele_release_booking_lock', array( $submission_lock ) );
        delete_option( $slot_lock );
        $this->send_new_booking_emails($id);
        do_action('gd_reservation_created',$id,$data);
        $this->send_json(true,array('message'=>$form_settings['success_text'],'bookingId'=>$id));
    }

    private function send_json($success,$data,$status=200) {
        // Some third-party hooks print notices or whitespace while a booking is saved.
        // Remove this output so the browser always receives valid JSON.
        while ( ob_get_level() > 0 ) { @ob_end_clean(); }
        nocache_headers();
        if ( $success ) { wp_send_json_success($data,$status); }
        wp_send_json_error($data,$status);
    }

    private function booking_lock_key( $type, $parts ) {
        return 'gelsendiele_booking_' . sanitize_key( $type ) . '_' . md5( implode( '|', array_map( 'strval', (array) $parts ) ) );
    }

    private function limit_text( $value, $length ) {
        $value = (string) $value;
        $length = max( 1, absint( $length ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
    }

    private function acquire_lock( $key, $ttl ) {
        $now = time();
        if ( add_option( $key, $now, '', false ) ) {
            return true;
        }
        $existing = get_option( $key, 0 );
        $created  = is_array( $existing ) && isset( $existing['created'] ) ? absint( $existing['created'] ) : absint( $existing );
        if ( $created > 0 && $created <= $now - max( 1, absint( $ttl ) ) ) {
            delete_option( $key );
            return add_option( $key, $now, '', false );
        }
        return false;
    }

    public function release_booking_lock( $key ) {
        $key = sanitize_key( $key );
        if ( 0 === strpos( $key, 'gelsendiele_booking_' ) ) {
            delete_option( $key );
        }
    }

    public function valid_date($date) {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone()); if(!$d||$d->format('Y-m-d')!==$date)return false;
        $today=new DateTimeImmutable('today',wp_timezone()); $max=$today->modify('+'.absint($this->settings()['advance_days']).' days'); return $d>=$today&&$d<=$max;
    }

    public function ranges_for_date( $date ) {
        $settings = $this->settings();
        if ( in_array( $date, (array) $settings['closed_dates'], true ) ) {
            return array();
        }
        $day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
        if ( ! $day ) {
            return array();
        }
        $keys   = array( 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun' );
        $ranges = (array) ( $settings['opening_hours'][ $keys[ (int) $day->format( 'N' ) ] ] ?? array() );

        // Der nach Mitternacht liegende Teil einer Öffnung gehört zum realen
        // Folgetag. Dadurch wird z. B. Samstag 01:00 auch als Samstag gespeichert.
        $previous     = $day->modify( '-1 day' );
        $previous_key = $keys[ (int) $previous->format( 'N' ) ];
        foreach ( (array) ( $settings['opening_hours'][ $previous_key ] ?? array() ) as $range ) {
            if ( isset( $range['start'], $range['end'] ) && $range['end'] < $range['start'] ) {
                $ranges[] = array( 'start' => '00:00', 'end' => $range['end'] );
            }
        }
        if ( class_exists( 'Gelsendiele_Availability' ) ) {
            $ranges = Gelsendiele_Availability::apply_to_ranges( $date, $ranges );
        }
        return $ranges;
    }

    public function available_slots( $date, $party = 1, $exclude = 0 ) {
        $settings = $this->settings();
        $slots    = array();
        $interval = max( 5, absint( $settings['time_interval'] ) );
        $duration = max( $interval, absint( $settings['booking_duration'] ) + absint( $settings['buffer_minutes'] ?? 0 ) );
        $now      = new DateTimeImmutable( 'now', wp_timezone() );
        foreach ( $this->ranges_for_date( $date ) as $range ) {
            $start = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $range['start'], wp_timezone() );
            $end   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $range['end'], wp_timezone() );
            if ( ! $start || ! $end ) {
                continue;
            }
            if ( $end <= $start ) {
                $end = $end->modify( '+1 day' );
            }
            for ( $time = $start; $time <= $end->modify( '-' . $duration . ' minutes' ); $time = $time->modify( '+' . $interval . ' minutes' ) ) {
                if ( $time->format( 'Y-m-d' ) !== $date || $time < $now->modify( '+' . absint( $settings['lead_minutes'] ) . ' minutes' ) ) {
                    continue;
                }
                if ( $this->has_capacity( $time, $party, $exclude ) ) {
                    $slots[] = $time->format( 'H:i' );
                }
            }
        }
        return array_values( array_unique( $slots ) );
    }

    private function has_capacity(DateTimeImmutable $start,$party,$exclude=0){
        $s=$this->settings(); $duration=max(30,absint($s['booking_duration'])+absint($s['buffer_minutes']??0)); $end=$start->modify('+'.$duration.' minutes'); $count=0;$people=0;
        $posts=get_posts(array('post_type'=>RTB_BOOKING_POST_TYPE,'post_status'=>array('pending','payment_pending','confirmed','arrived'),'posts_per_page'=>-1,'post__not_in'=>$exclude?array($exclude):array(),'date_query'=>array(array('after'=>$start->modify('-1 day')->format('Y-m-d 00:00:00'),'before'=>$start->modify('+1 day')->format('Y-m-d 23:59:59'),'inclusive'=>true))));
        foreach($posts as $p){$ps=new DateTimeImmutable($p->post_date,wp_timezone());$pe=$ps->modify('+'.$duration.' minutes');if($start<$pe&&$end>$ps){$m=(array)get_post_meta($p->ID,'rtb',true);$count++;$people+=absint($m['party']??0);}}
        $max_tables = absint($s['max_tables']);
        $max_people = absint($s['max_people']);
        if ( class_exists( 'Gelsendiele_Availability' ) ) {
            $limits = Gelsendiele_Availability::capacity_limits( $start );
            if ( ! empty( $limits['max_bookings'] ) && ( 0 === $max_tables || $limits['max_bookings'] < $max_tables ) ) {
                $max_tables = absint( $limits['max_bookings'] );
            }
            if ( ! empty( $limits['max_people'] ) && ( 0 === $max_people || $limits['max_people'] < $max_people ) ) {
                $max_people = absint( $limits['max_people'] );
            }
        }
        return !( $max_tables > 0 && $count >= $max_tables ) && !( $max_people > 0 && ( $people + $party ) > $max_people );
    }

    public function maybe_send_status_email( $booking ) {
        // Bei aktivem Five Star besitzt dessen Benachrichtigungssystem diesen Hook.
        // Eigene Mails würden sonst doppelt versendet.
        if ( defined( 'RTB_PLUGIN_DIR' ) || ! is_object( $booking ) || empty( $booking->ID ) ) {
            return;
        }
        $old = get_post_meta( $booking->ID, '_gd_last_emailed_status', true );
        $new = get_post_status( $booking->ID );
        if ( $old === $new ) {
            return;
        }
        update_post_meta( $booking->ID, '_gd_last_emailed_status', $new );
        if ( in_array( $new, array( 'confirmed', 'rejected', 'cancelled' ), true ) && class_exists( 'Gelsensystem_Email' ) ) {
            Gelsensystem_Email::send_status( $booking->ID, $new );
        }
    }

    public function send_new_booking_emails( $id ) {
        if ( class_exists( 'Gelsensystem_Email' ) ) {
            Gelsensystem_Email::send_new_booking( $id );
        }
        update_post_meta( $id, '_gd_last_emailed_status', get_post_status( $id ) );
    }
}

final class GD_RTB_Settings_Compat {
    private $engine;
    public function __construct($engine){$this->engine=$engine;}
    public function get_setting($key,$default='',$timeslot=false){
        $s=$this->engine->settings();
        if('schedule-open'===$key){
            $days=array('sun'=>'sunday','mon'=>'monday','tue'=>'tuesday','wed'=>'wednesday','thu'=>'thursday','fri'=>'friday','sat'=>'saturday');
            $rules=array();
            foreach($days as $short=>$long){
                foreach((array)($s['opening_hours'][$short]??array()) as $range){
                    if(empty($range['start'])||empty($range['end']))continue;
                    $rules[]=array('weekdays'=>array($long=>1),'time'=>array('start'=>$range['start'],'end'=>$range['end']));
                }
            }
            return $rules;
        }
        if('schedule-closed'===$key){
            return array_map(function($date){return array('date'=>$date);},(array)$s['closed_dates']);
        }
        $map=array('time-interval'=>'time_interval','rtb-dining-block-length'=>'booking_duration','rtb-enable-max-tables'=>'max_tables','rtb-max-tables-count'=>'max_tables','rtb-max-people-count'=>'max_people');
        $k=$map[$key]??$key;
        return $s[$k]??$default;
    }
}

function gelsendiele_register_rtb_compatibility_classes() {
if ( ! class_exists('rtbBooking') ) {
class rtbBooking {
    public $ID=0,$post_status='pending',$name='',$email='',$phone='',$party=0,$message='',$confirmed_user=0,$temp_confirmed_user=0,$validation_errors=array(),$data=array();
    public function load_post($id){$p=get_post($id);if(!$p||RTB_BOOKING_POST_TYPE!==$p->post_type)return false;$m=(array)get_post_meta($id,'rtb',true);$this->ID=$id;$this->post_status=$p->post_status;$this->name=$p->post_title;$this->message=$p->post_content;$this->email=$m['email']??'';$this->phone=$m['phone']??'';$this->party=absint($m['party']??0);return true;}
    public function insert_booking(){
        $d=$this->data?:array('date'=>sanitize_text_field(wp_unslash($_POST['rtb-date']??'')),'time'=>sanitize_text_field(wp_unslash($_POST['rtb-time']??'')),'party'=>absint($_POST['rtb-party']??0),'name'=>sanitize_text_field(wp_unslash($_POST['rtb-name']??'')),'email'=>sanitize_email(wp_unslash($_POST['rtb-email']??'')),'phone'=>sanitize_text_field(wp_unslash($_POST['rtb-phone']??'')),'message'=>sanitize_textarea_field(wp_unslash($_POST['rtb-message']??'')));
        $status=apply_filters('rtb_determine_booking_status','pending',$this);$local=$d['date'].' '.$d['time'].':00';$id=wp_insert_post(array('post_type'=>RTB_BOOKING_POST_TYPE,'post_status'=>$status,'post_title'=>$d['name'],'post_content'=>$d['message'],'post_date'=>$local,'post_date_gmt'=>get_gmt_from_date($local),'post_author'=>get_current_user_id()),true);if(is_wp_error($id))return false;$this->ID=$id;$this->post_status=$status;update_post_meta($id,'rtb',array('party'=>absint($d['party']),'email'=>$d['email'],'phone'=>$d['phone']));GD_Reservation_Engine::instance()->send_new_booking_emails($id);do_action('rtb_insert_booking',$this);return true;
    }
    public function insert_post_data(){if(!$this->ID)return false;$r=wp_update_post(array('ID'=>$this->ID,'post_status'=>$this->post_status),true);return !is_wp_error($r);}
    public function add_log($type,$title,$message){$logs=(array)get_post_meta($this->ID,'_gd_booking_log',true);$logs[]=array('time'=>current_time('mysql'),'type'=>$type,'title'=>$title,'message'=>$message,'user'=>get_current_user_id());update_post_meta($this->ID,'_gd_booking_log',$logs);}
}
}

if ( ! class_exists('rtbAJAX') ) {
class rtbAJAX {
    public $year,$month,$day,$location;
    public function get_opening_hours(){return GD_Reservation_Engine::instance()->ranges_for_date(sprintf('%04d-%02d-%02d',$this->year,$this->month,$this->day));}
    public function get_all_possible_timeslots($hours){$date=sprintf('%04d-%02d-%02d',$this->year,$this->month,$this->day);$slots=GD_Reservation_Engine::instance()->available_slots($date,1);return array_map(function($t)use($date){return DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$t,wp_timezone())->getTimestamp();},$slots);}
}
}
}

function gd_reservation_engine(){return GD_Reservation_Engine::instance();}
gd_reservation_engine();
