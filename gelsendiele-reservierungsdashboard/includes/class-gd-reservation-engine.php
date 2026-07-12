<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'RTB_BOOKING_POST_TYPE' ) ) { define( 'RTB_BOOKING_POST_TYPE', 'rtb-booking' ); }

final class GD_Reservation_Engine {
    const VERSION = '2.0.3';
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
        $this->bootstrap_compatibility_controller();
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

    public function settings() { return wp_parse_args( (array) get_option( self::SETTINGS_OPTION, array() ), self::defaults() ); }

    public function register_content() {
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
        if ( ! get_role('gd_reservation_manager') ) add_role('gd_reservation_manager','Reservierungsverwaltung',array('read'=>true,'manage_bookings'=>true,'edit_booking'=>true,'read_booking'=>true,'delete_booking'=>true));
    }

    private function bootstrap_compatibility_controller() {
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
        $settings = $this->settings();
        wp_localize_script('gd-reservation-form','GDReservationForm',array(
            'ajaxUrl'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('gd_public_reservation'),
            'today'=>wp_date('Y-m-d'),
            'maxDate'=>wp_date('Y-m-d',time()+DAY_IN_SECONDS*absint($settings['advance_days'])),
            'locale'=>array(
                'months'=>array('Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'),
                'weekdays'=>array('Mo','Di','Mi','Do','Fr','Sa','So')
            )
        ));
        $s=$this->settings(); ob_start(); ?>
        <div class="gdrf-shell"><form class="gdrf-form" data-gdrf-form novalidate>
          <div class="gdrf-head"><span class="gdrf-eyebrow">Gelsendiele</span><h2>Tisch reservieren</h2><p>Wir freuen uns auf Ihren Besuch.</p></div>
          <div class="gdrf-grid">
            <label class="gdrf-date-field"><span>Datum *</span>
              <input type="hidden" name="date" required>
              <button type="button" class="gdrf-picker-button" data-gdrf-date-button aria-expanded="false"><span data-gdrf-date-label>Datum auswählen</span><span aria-hidden="true">▾</span></button>
              <div class="gdrf-calendar" data-gdrf-calendar hidden>
                <div class="gdrf-calendar-head"><button type="button" data-gdrf-prev aria-label="Vorheriger Monat">‹</button><strong data-gdrf-month></strong><button type="button" data-gdrf-next aria-label="Nächster Monat">›</button></div>
                <div class="gdrf-weekdays" data-gdrf-weekdays></div><div class="gdrf-days" data-gdrf-days></div>
                <div class="gdrf-calendar-note">Ausgegraute Tage sind geschlossen oder nicht mehr verfügbar.</div>
              </div>
            </label>
            <label><span>Uhrzeit *</span><select name="time" required disabled><option value="">Zuerst Datum wählen</option></select><small class="gdrf-field-note">Nicht verfügbare Zeiten werden nicht angezeigt.</small></label>
            <label><span>Personen *</span><input type="number" name="party" min="<?php echo esc_attr($s['min_party']); ?>" max="<?php echo esc_attr($s['max_party']); ?>" value="2" required></label>
            <label><span>Name *</span><input type="text" name="name" autocomplete="name" required></label>
            <label><span>E-Mail *</span><input type="email" name="email" autocomplete="email" required></label>
            <label><span>Telefon *</span><input type="tel" name="phone" autocomplete="tel" required></label>
            <label class="gdrf-wide"><span>Nachricht</span><textarea name="message" rows="4" placeholder="Kinderstuhl, Allergien oder andere Wünsche"></textarea></label>
          </div>
          <label class="gdrf-consent"><input type="checkbox" name="consent" value="1" required><span><?php echo esc_html($s['privacy_text']); ?></span></label>
          <input type="text" name="website" class="gdrf-hp" tabindex="-1" autocomplete="off">
          <button type="submit" class="gdrf-submit">Reservierung anfragen</button>
          <div class="gdrf-message" data-gdrf-message aria-live="polite"></div>
        </form></div><?php return ob_get_clean();
    }

    public function ajax_slots() {
        check_ajax_referer('gd_public_reservation','nonce');
        $date=sanitize_text_field(wp_unslash($_POST['date']??'')); $party=max(1,absint($_POST['party']??1));
        if(!$this->valid_date($date)){ $this->send_json(false,array('message'=>'Ungültiges Datum.'),400); }
        $this->send_json(true,array('slots'=>$this->available_slots($date,$party)));
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
        $data=array('date'=>sanitize_text_field(wp_unslash($_POST['date']??'')),'time'=>sanitize_text_field(wp_unslash($_POST['time']??'')),'party'=>absint($_POST['party']??0),'name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'email'=>sanitize_email(wp_unslash($_POST['email']??'')),'phone'=>sanitize_text_field(wp_unslash($_POST['phone']??'')),'message'=>sanitize_textarea_field(wp_unslash($_POST['message']??'')));
        if(empty($_POST['consent'])||!$this->valid_date($data['date'])||!preg_match('/^\d{2}:\d{2}$/',$data['time'])||!$data['party']||!$data['name']||!is_email($data['email'])||!$data['phone']) $this->send_json(false,array('message'=>'Bitte füllen Sie alle Pflichtfelder korrekt aus.'),422);
        if(!in_array($data['time'],$this->available_slots($data['date'],$data['party']),true)) $this->send_json(false,array('message'=>'Diese Uhrzeit ist leider nicht mehr verfügbar.'),409);
        // Deliberately save with our own engine. When Five Star is still active its rtbBooking
        // class owns the same name and may terminate or alter the AJAX response.
        $local = $data['date'].' '.$data['time'].':00';
        $id = wp_insert_post(array(
            'post_type'=>RTB_BOOKING_POST_TYPE,
            'post_status'=>'pending',
            'post_title'=>$data['name'],
            'post_content'=>$data['message'],
            'post_date'=>$local,
            'post_date_gmt'=>get_gmt_from_date($local),
            'post_author'=>get_current_user_id(),
        ), true);
        if (is_wp_error($id) || !$id) {
            $this->send_json(false,array('message'=>'Die Reservierung konnte nicht gespeichert werden.'),500);
        }
        update_post_meta($id,'rtb',array('party'=>$data['party'],'email'=>$data['email'],'phone'=>$data['phone']));
        update_post_meta($id,'_gd_created_by_engine',self::VERSION);
        $this->send_new_booking_emails($id);
        do_action('gd_reservation_created',$id,$data);
        $this->send_json(true,array('message'=>$this->settings()['success_text'],'bookingId'=>$id));
    }

    private function send_json($success,$data,$status=200) {
        // Some third-party hooks print notices or whitespace while a booking is saved.
        // Remove this output so the browser always receives valid JSON.
        while ( ob_get_level() > 0 ) { @ob_end_clean(); }
        nocache_headers();
        if ( $success ) { wp_send_json_success($data,$status); }
        wp_send_json_error($data,$status);
    }

    public function valid_date($date) {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone()); if(!$d||$d->format('Y-m-d')!==$date)return false;
        $today=new DateTimeImmutable('today',wp_timezone()); $max=$today->modify('+'.absint($this->settings()['advance_days']).' days'); return $d>=$today&&$d<=$max;
    }

    public function ranges_for_date($date) {
        $s=$this->settings(); if(in_array($date,(array)$s['closed_dates'],true))return array();
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone()); if(!$d)return array(); $keys=array(1=>'mon',2=>'tue',3=>'wed',4=>'thu',5=>'fri',6=>'sat',7=>'sun'); return (array)($s['opening_hours'][$keys[(int)$d->format('N')]]??array());
    }

    public function available_slots($date,$party=1,$exclude=0) {
        $s=$this->settings(); $slots=array(); $interval=max(5,absint($s['time_interval'])); $duration=max($interval,absint($s['booking_duration']));
        $now=new DateTimeImmutable('now',wp_timezone());
        foreach($this->ranges_for_date($date) as $range){
            $start=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$range['start'],wp_timezone()); $end=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$range['end'],wp_timezone()); if(!$start||!$end)continue;
            for($t=$start;$t<=$end->modify('-'.$duration.' minutes');$t=$t->modify('+'.$interval.' minutes')){
                if($t<$now->modify('+'.absint($s['lead_minutes']).' minutes'))continue;
                if($this->has_capacity($t,$party,$exclude))$slots[]=$t->format('H:i');
            }
        } return array_values(array_unique($slots));
    }

    private function has_capacity(DateTimeImmutable $start,$party,$exclude=0){
        $s=$this->settings(); $duration=max(30,absint($s['booking_duration'])); $end=$start->modify('+'.$duration.' minutes'); $count=0;$people=0;
        $posts=get_posts(array('post_type'=>RTB_BOOKING_POST_TYPE,'post_status'=>array('pending','payment_pending','confirmed','arrived'),'posts_per_page'=>-1,'post__not_in'=>$exclude?array($exclude):array(),'date_query'=>array(array('after'=>$start->format('Y-m-d 00:00:00'),'before'=>$start->format('Y-m-d 23:59:59'),'inclusive'=>true))));
        foreach($posts as $p){$ps=new DateTimeImmutable($p->post_date,wp_timezone());$pe=$ps->modify('+'.$duration.' minutes');if($start<$pe&&$end>$ps){$m=(array)get_post_meta($p->ID,'rtb',true);$count++;$people+=absint($m['party']??0);}}
        return !(absint($s['max_tables'])>0&&$count>=absint($s['max_tables'])) && !(absint($s['max_people'])>0&&($people+$party)>absint($s['max_people']));
    }

    public function maybe_send_status_email($booking){ if(!is_object($booking)||empty($booking->ID))return; $old=get_post_meta($booking->ID,'_gd_last_emailed_status',true);$new=get_post_status($booking->ID);if($old===$new)return;update_post_meta($booking->ID,'_gd_last_emailed_status',$new); if(in_array($new,array('confirmed','cancelled'),true))$this->send_guest_email($booking->ID,$new); }
    public function send_new_booking_emails($id){$p=get_post($id);$m=(array)get_post_meta($id,'rtb',true);$admin=$this->settings()['admin_email'];wp_mail($admin,'Neue Reservierungsanfrage: '.$p->post_title,"Neue Anfrage\n\nName: {$p->post_title}\nDatum: ".wp_date('d.m.Y H:i',strtotime($p->post_date))."\nPersonen: ".absint($m['party']??0)."\nTelefon: ".($m['phone']??'')."\nE-Mail: ".($m['email']??''));wp_mail($m['email']??'','Ihre Reservierungsanfrage bei der Gelsendiele',"Hallo {$p->post_title},\n\nvielen Dank für Ihre Anfrage am ".wp_date('d.m.Y \u\m H:i',strtotime($p->post_date))." Uhr für ".absint($m['party']??0)." Personen. Wir melden uns mit der Bestätigung.\n\nIhre Gelsendiele");}
    private function send_guest_email($id,$status){$p=get_post($id);$m=(array)get_post_meta($id,'rtb',true);$email=$m['email']??'';if(!is_email($email))return;$confirmed='confirmed'===$status;$subject=$confirmed?'Ihre Reservierung ist bestätigt':'Ihre Reservierung wurde storniert';$text=$confirmed?'Ihre Reservierung wurde bestätigt.':'Leider wurde Ihre Reservierung storniert.';wp_mail($email,$subject,"Hallo {$p->post_title},\n\n{$text}\n\nTermin: ".wp_date('d.m.Y \u\m H:i',strtotime($p->post_date))." Uhr\nPersonen: ".absint($m['party']??0)."\n\nIhre Gelsendiele");}
}

final class GD_RTB_Settings_Compat {
    private $engine; public function __construct($engine){$this->engine=$engine;}
    public function get_setting($key,$default='',$timeslot=false){$s=$this->engine->settings(); if('schedule-open'===$key){$days=array('sun'=>'sunday','mon'=>'monday','tue'=>'tuesday','wed'=>'wednesday','thu'=>'thursday','fri'=>'friday','sat'=>'saturday');$weekdays=array();foreach($days as $short=>$long){$weekdays[$long]=!empty($s['opening_hours'][$short]);}return array(array('weekdays'=>$weekdays));} if('schedule-closed'===$key){return array_map(function($date){return array('date'=>$date);},(array)$s['closed_dates']);}$map=array('time-interval'=>'time_interval','rtb-dining-block-length'=>'booking_duration','rtb-enable-max-tables'=>'max_tables','rtb-max-tables-count'=>'max_tables','rtb-max-people-count'=>'max_people');$k=$map[$key]??$key;return $s[$k]??$default;}
}

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

function gd_reservation_engine(){return GD_Reservation_Engine::instance();}
gd_reservation_engine();
