<?php
/**
 * Plugin Name: ورود با پیامک و بله
 * Plugin URI: https://github.com/sahandse/login-sms-bale
 * Description: ورود و ثبت‌نام وردپرس با شماره موبایل، کد یکبارمصرف و بله با پنل تنظیمات فارسی.
 * Version: 1.1.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: login-sms-bale
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class LSB_Plugin {
    const VERSION = '1.1.0';
    const OPTION  = 'lsb_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('login_sms_bale', [$this, 'shortcode']);
        add_action('wp_ajax_nopriv_lsb_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_lsb_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_nopriv_lsb_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_lsb_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_nopriv_lsb_bale_start', [$this, 'ajax_bale_start']);
        add_action('wp_ajax_lsb_bale_start', [$this, 'ajax_bale_start']);
        add_action('wp_ajax_nopriv_lsb_bale_poll', [$this, 'ajax_bale_poll']);
        add_action('wp_ajax_lsb_bale_poll', [$this, 'ajax_bale_poll']);
        add_action('rest_api_init', [$this, 'register_bale_webhook']);
    }

    public function defaults() {
        return [
            'enabled_sms' => 'yes',
            'enabled_bale' => 'yes',
            'sms_provider' => 'none',
            'bale_bot_token' => '',
            'bale_bot_username' => '',
            'bale_webhook_secret' => '',
            'sms_api_key' => '',
            'sms_username' => '',
            'sms_password' => '',
            'sms_sender' => '',
            'theme' => 'minimal',
            'accent' => '#111827',
            'title' => 'ورود یا ثبت‌نام',
            'otp_length' => 5,
            'otp_expire' => 120,
        ];
    }

    public function settings() {
        return wp_parse_args((array)get_option(self::OPTION, []), $this->defaults());
    }

    public function register_settings() {
        register_setting('lsb_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in) {
        $d = $this->defaults();

        return [
            'enabled_sms' => !empty($in['enabled_sms']) ? 'yes' : 'no',
            'enabled_bale' => !empty($in['enabled_bale']) ? 'yes' : 'no',
            'sms_provider' => in_array($in['sms_provider'] ?? '', ['none','melipayamak','farazsms','smsir','kavenegar','ghasedak'], true)
                ? $in['sms_provider']
                : $d['sms_provider'],
            'bale_bot_token' => sanitize_text_field($in['bale_bot_token'] ?? ''),
            'bale_bot_username' => sanitize_text_field($in['bale_bot_username'] ?? ''),
            'bale_webhook_secret' => sanitize_key($in['bale_webhook_secret'] ?? '') ?: wp_generate_password(24,false,false),
            'sms_api_key' => sanitize_text_field($in['sms_api_key'] ?? ''),
            'sms_username' => sanitize_text_field($in['sms_username'] ?? ''),
            'sms_password' => sanitize_text_field($in['sms_password'] ?? ''),
            'sms_sender' => sanitize_text_field($in['sms_sender'] ?? ''),
            'theme' => in_array($in['theme'] ?? '', ['minimal','card','dark'], true) ? $in['theme'] : $d['theme'],
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
            'title' => sanitize_text_field($in['title'] ?? $d['title']),
            'otp_length' => min(8, max(4, absint($in['otp_length'] ?? 5))),
            'otp_expire' => min(600, max(60, absint($in['otp_expire'] ?? 120))),
        ];
    }

    public function admin_menu() {
        if (function_exists('s_store_register_submenu')) {
            s_store_register_submenu('login-sms-bale', 'ورود با پیامک و بله', [$this, 'settings_page'], 'manage_options', 'ورود با پیامک و بله');
            return;
        }
        add_menu_page(
            'ورود با پیامک و بله',
            'ورود پیامکی',
            'manage_options',
            'login-sms-bale',
            [$this, 'settings_page'],
            'dashicons-smartphone',
            59
        );
    }

    public function admin_assets($hook) {
        if (false === strpos($hook, 'login-sms-bale')) return;
        wp_enqueue_style('lsb-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = $this->settings();
        ?>
        <div class="wrap lsb-admin">
            <div class="lsb-hero">
                <div>
                    <h1>ورود با پیامک و بله</h1>
                    <p>مدیریت روش‌های ورود، سرویس پیامک، بله و ظاهر فرم.</p>
                </div>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('lsb_group'); ?>
                <div class="lsb-grid">
                    <section class="lsb-card">
                        <h2>روش‌های ورود</h2>
                        <label class="lsb-switch">
                            <span>ورود با پیامک</span>
                            <input type="checkbox" name="<?php echo self::OPTION; ?>[enabled_sms]" value="1" <?php checked($s['enabled_sms'],'yes'); ?>>
                        </label>
                        <label class="lsb-switch">
                            <span>ورود با بله</span>
                            <input type="checkbox" name="<?php echo self::OPTION; ?>[enabled_bale]" value="1" <?php checked($s['enabled_bale'],'yes'); ?>>
                        </label>
                    </section>

                    <section class="lsb-card">
                        <h2>پیامک</h2>
                        <label>سرویس پیامک
                            <select name="<?php echo self::OPTION; ?>[sms_provider]">
                                <option value="none" <?php selected($s['sms_provider'],'none'); ?>>انتخاب نشده</option>
                                <option value="melipayamak" <?php selected($s['sms_provider'],'melipayamak'); ?>>ملی‌پیامک</option>
                                <option value="farazsms" <?php selected($s['sms_provider'],'farazsms'); ?>>فراز SMS</option>
                                <option value="smsir" <?php selected($s['sms_provider'],'smsir'); ?>>SMS.ir</option>
                                <option value="kavenegar" <?php selected($s['sms_provider'],'kavenegar'); ?>>کاوه‌نگار</option>
                                <option value="ghasedak" <?php selected($s['sms_provider'],'ghasedak'); ?>>قاصدک</option>
                            </select>
                        </label>
                        <label>طول کد OTP
                            <input type="number" min="4" max="8" name="<?php echo self::OPTION; ?>[otp_length]" value="<?php echo esc_attr($s['otp_length']); ?>">
                        </label>
                        <label>اعتبار کد (ثانیه)
                            <input type="number" min="60" max="600" name="<?php echo self::OPTION; ?>[otp_expire]" value="<?php echo esc_attr($s['otp_expire']); ?>">
                        </label>
                        <label>API Key / Token
                            <input type="password" name="<?php echo self::OPTION; ?>[sms_api_key]" value="<?php echo esc_attr($s['sms_api_key']); ?>" autocomplete="off">
                        </label>
                        <label>نام کاربری
                            <input type="text" name="<?php echo self::OPTION; ?>[sms_username]" value="<?php echo esc_attr($s['sms_username']); ?>">
                        </label>
                        <label>رمز عبور
                            <input type="password" name="<?php echo self::OPTION; ?>[sms_password]" value="<?php echo esc_attr($s['sms_password']); ?>" autocomplete="off">
                        </label>
                        <label>خط فرستنده
                            <input type="text" name="<?php echo self::OPTION; ?>[sms_sender]" value="<?php echo esc_attr($s['sms_sender']); ?>">
                        </label>
                    </section>

                    <section class="lsb-card">
                        <h2>بله</h2>
                        <label>توکن ربات بله
                            <input type="password" name="<?php echo self::OPTION; ?>[bale_bot_token]" value="<?php echo esc_attr($s['bale_bot_token']); ?>" autocomplete="off">
                        </label>
                        <label>Username ربات بله
                            <input type="text" name="<?php echo self::OPTION; ?>[bale_bot_username]" value="<?php echo esc_attr($s['bale_bot_username']); ?>" placeholder="mybot">
                        </label>
                        <label>Secret Webhook
                            <input type="text" name="<?php echo self::OPTION; ?>[bale_webhook_secret]" value="<?php echo esc_attr($s['bale_webhook_secret']); ?>">
                        </label>
                        <p><strong>Webhook URL:</strong><br><code><?php echo esc_html(rest_url('lsb/v1/bale/' . ($s['bale_webhook_secret'] ?: 'SAVE-FIRST'))); ?></code></p>
                    </section>

                    <section class="lsb-card">
                        <h2>ظاهر</h2>
                        <label>عنوان فرم
                            <input type="text" name="<?php echo self::OPTION; ?>[title]" value="<?php echo esc_attr($s['title']); ?>">
                        </label>
                        <label>تم
                            <select name="<?php echo self::OPTION; ?>[theme]">
                                <option value="minimal" <?php selected($s['theme'],'minimal'); ?>>مینیمال</option>
                                <option value="card" <?php selected($s['theme'],'card'); ?>>کارت</option>
                                <option value="dark" <?php selected($s['theme'],'dark'); ?>>تیره</option>
                            </select>
                        </label>
                        <label>رنگ اصلی
                            <input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>">
                        </label>
                    </section>

                    <section class="lsb-card">
                        <h2>شورت‌کد</h2>
                        <code>[login_sms_bale]</code>
                    </section>

                    <section class="lsb-card">
                        <h2>وضعیت توسعه</h2>
                        <p>OTP واقعی، ورود/ثبت‌نام با موبایل و جریان ورود با بله فعال است. برای بله Token، Username و Webhook را تنظیم کنید.</p>
                    </section>
                </div>

                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    private function normalize_phone($phone) {
        $phone=preg_replace('/\D+/','',(string)$phone);
        if(0===strpos($phone,'0098')) $phone=substr($phone,4);
        if(0===strpos($phone,'98')&&strlen($phone)>10) $phone='0'.substr($phone,2);
        return $phone;
    }

    private function send_sms($phone,$message) {
        $s=$this->settings();
        $provider=$s['sms_provider'];
        $phone=$this->normalize_phone($phone);
        if(!$phone) return new WP_Error('lsb_phone','شماره موبایل معتبر نیست.');
        if('none'===$provider) return new WP_Error('lsb_provider','سرویس پیامک انتخاب نشده است.');

        $args=['timeout'=>20,'headers'=>[]];
        if('kavenegar'===$provider){
            if(!$s['sms_api_key']) return new WP_Error('lsb_auth','API Key وارد نشده.');
            $url='https://api.kavenegar.com/v1/'.rawurlencode($s['sms_api_key']).'/sms/send.json';
            $args['body']=['receptor'=>$phone,'sender'=>$s['sms_sender'],'message'=>$message];
            $res=wp_remote_post($url,$args);
        }elseif('smsir'===$provider){
            if(!$s['sms_api_key']) return new WP_Error('lsb_auth','API Key وارد نشده.');
            $url='https://api.sms.ir/v1/send/bulk';
            $args['headers']=['Content-Type'=>'application/json','X-API-KEY'=>$s['sms_api_key']];
            $args['body']=wp_json_encode(['lineNumber'=>$s['sms_sender'],'messageText'=>$message,'mobiles'=>[$phone]]);
            $res=wp_remote_post($url,$args);
        }elseif('ghasedak'===$provider){
            if(!$s['sms_api_key']) return new WP_Error('lsb_auth','API Key وارد نشده.');
            $url='https://gateway.ghasedak.me/rest/api/v1/WebService/SendSingleSMS';
            $args['headers']=['Content-Type'=>'application/json','ApiKey'=>$s['sms_api_key']];
            $args['body']=wp_json_encode(['message'=>$message,'lineNumber'=>$s['sms_sender'],'receptor'=>$phone]);
            $res=wp_remote_post($url,$args);
        }elseif('farazsms'===$provider){
            if(!$s['sms_api_key']) return new WP_Error('lsb_auth','Token فراز/IPPanel وارد نشده.');
            $url='https://edge.ippanel.com/v1/api/send';
            $args['headers']=['Content-Type'=>'application/json','Authorization'=>$s['sms_api_key']];
            $args['body']=wp_json_encode(['sending_type'=>'peer_to_peer','from_number'=>$s['sms_sender'],'params'=>[['recipients'=>['+98'.ltrim($phone,'0')],'message'=>$message]]]);
            $res=wp_remote_post($url,$args);
        }elseif('melipayamak'===$provider){
            if(!$s['sms_username']||!$s['sms_password']) return new WP_Error('lsb_auth','نام کاربری/رمز وارد نشده.');
            $url='https://rest.payamak-panel.com/api/SendSMS/SendSMS';
            $args['headers']=['Content-Type'=>'application/json'];
            $args['body']=wp_json_encode(['username'=>$s['sms_username'],'password'=>$s['sms_password'],'to'=>$phone,'from'=>$s['sms_sender'],'text'=>$message,'isFlash'=>false]);
            $res=wp_remote_post($url,$args);
        }else return new WP_Error('lsb_provider','سرویس پشتیبانی نمی‌شود.');

        if(is_wp_error($res)) return $res;
        $code=(int)wp_remote_retrieve_response_code($res);
        if($code<200||$code>=300) return new WP_Error('lsb_http','خطای سرویس پیامک: HTTP '.$code);
        return true;
    }

    private function find_or_create_user($phone) {
        $users=get_users(['meta_key'=>'_lsb_phone','meta_value'=>$phone,'number'=>1,'count_total'=>false]);
        if($users) return $users[0]->ID;
        $users=get_users(['meta_key'=>'billing_phone','meta_value'=>$phone,'number'=>1,'count_total'=>false]);
        if($users) { update_user_meta($users[0]->ID,'_lsb_phone',$phone); return $users[0]->ID; }

        $base='u_'.preg_replace('/\D+/','',$phone);
        $login=$base; $i=1;
        while(username_exists($login)) $login=$base.'_'.(++$i);
        $uid=wp_create_user($login,wp_generate_password(24,true,true));
        if(is_wp_error($uid)) return $uid;
        update_user_meta($uid,'_lsb_phone',$phone);
        update_user_meta($uid,'billing_phone',$phone);
        return $uid;
    }

    private function login_user($uid) {
        wp_set_current_user($uid);
        wp_set_auth_cookie($uid,true,is_ssl());
        do_action('wp_login',get_userdata($uid)->user_login,get_userdata($uid));
    }

    public function ajax_send_otp() {
        check_ajax_referer('lsb_login','nonce');
        $s=$this->settings();
        if('yes'!==$s['enabled_sms']) wp_send_json_error(['message'=>'ورود پیامکی غیرفعال است.']);
        $phone=$this->normalize_phone(wp_unslash($_POST['phone']??''));
        if(strlen($phone)<10) wp_send_json_error(['message'=>'شماره موبایل معتبر وارد کنید.']);

        $rate='lsb_rate_'.md5($phone);
        if(get_transient($rate)) wp_send_json_error(['message'=>'کمی بعد دوباره تلاش کنید.']);

        $min=(int)str_pad('1',$s['otp_length'],'0');
        $max=(int)str_repeat('9',$s['otp_length']);
        $code=(string)random_int($min,$max);
        set_transient('lsb_otp_'.md5($phone),['hash'=>wp_hash_password($code),'tries'=>0],(int)$s['otp_expire']);
        set_transient($rate,1,45);

        $sent=$this->send_sms($phone,'کد ورود شما: '.$code);
        if(is_wp_error($sent)){ delete_transient('lsb_otp_'.md5($phone)); wp_send_json_error(['message'=>$sent->get_error_message()]); }
        wp_send_json_success(['message'=>'کد ارسال شد.','expires'=>(int)$s['otp_expire']]);
    }

    public function ajax_verify_otp() {
        check_ajax_referer('lsb_login','nonce');
        $phone=$this->normalize_phone(wp_unslash($_POST['phone']??''));
        $code=preg_replace('/\D+/','',wp_unslash($_POST['code']??''));
        $key='lsb_otp_'.md5($phone);
        $data=get_transient($key);
        if(!is_array($data)) wp_send_json_error(['message'=>'کد منقضی شده است.']);
        $data['tries']=(int)($data['tries']??0)+1;
        if($data['tries']>5){ delete_transient($key); wp_send_json_error(['message'=>'تعداد تلاش بیش از حد مجاز است.']); }
        set_transient($key,$data,(int)$this->settings()['otp_expire']);
        if(!wp_check_password($code,$data['hash'])) wp_send_json_error(['message'=>'کد صحیح نیست.']);

        delete_transient($key);
        $uid=$this->find_or_create_user($phone);
        if(is_wp_error($uid)) wp_send_json_error(['message'=>$uid->get_error_message()]);
        $this->login_user($uid);
        wp_send_json_success(['message'=>'با موفقیت وارد شدید.','redirect'=>apply_filters('lsb_login_redirect',home_url('/my-account/'),$uid)]);
    }

    public function ajax_bale_start() {
        check_ajax_referer('lsb_login','nonce');
        $s=$this->settings();
        if('yes'!==$s['enabled_bale']||!$s['bale_bot_token']||!$s['bale_bot_username']) wp_send_json_error(['message'=>'ورود با بله کامل پیکربندی نشده است.']);
        $state=wp_generate_password(32,false,false);
        set_transient('lsb_bale_state_'.$state,['status'=>'waiting'],5*MINUTE_IN_SECONDS);
        $url='https://ble.ir/'.ltrim($s['bale_bot_username'],'@').'?start='.rawurlencode($state);
        wp_send_json_success(['state'=>$state,'url'=>$url]);
    }

    public function ajax_bale_poll() {
        check_ajax_referer('lsb_login','nonce');
        $state=sanitize_text_field(wp_unslash($_POST['state']??''));
        $data=get_transient('lsb_bale_state_'.$state);
        if(!is_array($data)) wp_send_json_error(['message'=>'درخواست منقضی شده است.']);
        if(($data['status']??'')!=='authorized') wp_send_json_success(['status'=>'waiting']);
        $phone=$this->normalize_phone($data['phone']??'');
        $uid=$this->find_or_create_user($phone);
        if(is_wp_error($uid)) wp_send_json_error(['message'=>$uid->get_error_message()]);
        delete_transient('lsb_bale_state_'.$state);
        $this->login_user($uid);
        wp_send_json_success(['status'=>'authorized','redirect'=>apply_filters('lsb_login_redirect',home_url('/my-account/'),$uid)]);
    }

    public function register_bale_webhook() {
        register_rest_route('lsb/v1','/bale/(?P<secret>[A-Za-z0-9_-]+)',[
            'methods'=>'POST',
            'callback'=>[$this,'bale_webhook'],
            'permission_callback'=>'__return_true'
        ]);
    }

    private function bale_api($method,$body) {
        $token=$this->settings()['bale_bot_token'];
        if(!$token) return new WP_Error('lsb_bale','توکن بله تنظیم نشده.');
        return wp_remote_post('https://tapi.bale.ai/bot'.$token.'/'.$method,[
            'timeout'=>15,
            'headers'=>['Content-Type'=>'application/json'],
            'body'=>wp_json_encode($body)
        ]);
    }

    public function bale_webhook(WP_REST_Request $request) {
        $s=$this->settings();
        if(!$s['bale_webhook_secret']||!hash_equals((string)$s['bale_webhook_secret'],(string)$request['secret'])) return new WP_REST_Response(['ok'=>false],403);
        $u=$request->get_json_params();
        $msg=$u['message']??[];
        $chat=$msg['chat']['id']??null;
        if(!$chat) return ['ok'=>true];

        $text=trim((string)($msg['text']??''));
        if(preg_match('/^\/start\s+([A-Za-z0-9]+)$/',$text,$m)){
            $state=$m[1];
            if(get_transient('lsb_bale_state_'.$state)){
                set_transient('lsb_bale_chat_'.$chat,$state,5*MINUTE_IN_SECONDS);
                $this->bale_api('sendMessage',[
                    'chat_id'=>$chat,
                    'text'=>'برای ورود، شماره موبایل خود را ارسال کنید.',
                    'reply_markup'=>['keyboard'=>[[['text'=>'ارسال شماره موبایل','request_contact'=>true]]],'resize_keyboard'=>true,'one_time_keyboard'=>true]
                ]);
            }
            return ['ok'=>true];
        }

        if(!empty($msg['contact']['phone_number'])){
            $state=get_transient('lsb_bale_chat_'.$chat);
            if($state&&get_transient('lsb_bale_state_'.$state)){
                set_transient('lsb_bale_state_'.$state,['status'=>'authorized','phone'=>$msg['contact']['phone_number']],5*MINUTE_IN_SECONDS);
                delete_transient('lsb_bale_chat_'.$chat);
                $this->bale_api('sendMessage',['chat_id'=>$chat,'text'=>'تأیید شد. به سایت برگردید.','reply_markup'=>['remove_keyboard'=>true]]);
            }
        }
        return ['ok'=>true];
    }

    public function shortcode() {
        $s=$this->settings();
        $classes='lsb-login lsb-theme-'.esc_attr($s['theme']);
        ob_start(); ?>
        <div class="<?php echo esc_attr($classes); ?>" style="--lsb-accent:<?php echo esc_attr($s['accent']); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('lsb_login')); ?>">
            <div class="lsb-login-card">
                <h3><?php echo esc_html($s['title']); ?></h3>
                <div class="lsb-message" aria-live="polite"></div>
                <?php if('yes'===$s['enabled_sms']): ?>
                    <div class="lsb-field"><label>شماره موبایل</label><input class="lsb-phone" type="tel" inputmode="numeric" placeholder="09xxxxxxxxx"></div>
                    <button type="button" class="lsb-btn lsb-send">دریافت کد</button>
                    <div class="lsb-otp-wrap" hidden><div class="lsb-field"><label>کد تأیید</label><input class="lsb-code" type="text" inputmode="numeric" maxlength="<?php echo esc_attr($s['otp_length']); ?>"></div><button type="button" class="lsb-btn lsb-verify">ورود</button></div>
                <?php endif; ?>
                <?php if('yes'===$s['enabled_bale']): ?><button type="button" class="lsb-btn lsb-bale">ورود با بله</button><?php endif; ?>
            </div>
        </div>
        <script>(function(){const r=document.currentScript.previousElementSibling;if(!r)return;const ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>",nonce=r.dataset.nonce,msg=r.querySelector(".lsb-message"),phone=r.querySelector(".lsb-phone");async function post(action,data={}){const b=new URLSearchParams({action,nonce,...data});const x=await fetch(ajax,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:b,credentials:"same-origin"});return x.json()}r.querySelector(".lsb-send")?.addEventListener("click",async()=>{const j=await post("lsb_send_otp",{phone:phone.value});msg.textContent=j.success?j.data.message:j.data.message;if(j.success)r.querySelector(".lsb-otp-wrap").hidden=false});r.querySelector(".lsb-verify")?.addEventListener("click",async()=>{const j=await post("lsb_verify_otp",{phone:phone.value,code:r.querySelector(".lsb-code").value});msg.textContent=j.data.message;if(j.success)location.href=j.data.redirect});r.querySelector(".lsb-bale")?.addEventListener("click",async()=>{const j=await post("lsb_bale_start");if(!j.success){msg.textContent=j.data.message;return;}window.open(j.data.url,"_blank");msg.textContent="شماره را در بله ارسال کنید…";const timer=setInterval(async()=>{const p=await post("lsb_bale_poll",{state:j.data.state});if(p.success&&p.data.status==="authorized"){clearInterval(timer);location.href=p.data.redirect}},2000);setTimeout(()=>clearInterval(timer),300000)});})();</script>
        <style>
            .lsb-login{direction:rtl}.lsb-login-card{max-width:420px;padding:20px;border:1px solid #e5e7eb;border-radius:18px;background:#fff}.lsb-field{margin:12px 0}.lsb-field label{display:block;margin-bottom:6px}.lsb-field input{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #d1d5db;border-radius:12px}.lsb-btn{width:100%;border:0;border-radius:12px;padding:11px 14px;background:var(--lsb-accent);color:#fff;cursor:pointer;margin-top:8px}.lsb-bale{background:#00a8e8}.lsb-message{font-size:13px;margin:8px 0;color:#475569}.lsb-theme-dark .lsb-login-card{background:#111827;color:#fff;border-color:#1f2937}
        </style>
        <?php return ob_get_clean();
    }

}

new LSB_Plugin();
