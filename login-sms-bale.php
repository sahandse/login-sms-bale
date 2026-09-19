<?php
/**
 * Plugin Name: ورود با پیامک و بله
 * Plugin URI: https://github.com/sahandse/login-sms-bale
 * Description: ورود و ثبت‌نام وردپرس با شماره موبایل، کد یکبارمصرف و بله با پنل تنظیمات فارسی.
 * Version: 1.0.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: login-sms-bale
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class LSB_Plugin {
    const VERSION = '1.0.0';
    const OPTION  = 'lsb_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('login_sms_bale', [$this, 'shortcode']);
    }

    public function defaults() {
        return [
            'enabled_sms' => 'yes',
            'enabled_bale' => 'yes',
            'sms_provider' => 'none',
            'bale_bot_token' => '',
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
            'theme' => in_array($in['theme'] ?? '', ['minimal','card','dark'], true) ? $in['theme'] : $d['theme'],
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
            'title' => sanitize_text_field($in['title'] ?? $d['title']),
            'otp_length' => min(8, max(4, absint($in['otp_length'] ?? 5))),
            'otp_expire' => min(600, max(60, absint($in['otp_expire'] ?? 120))),
        ];
    }

    public function admin_menu() {
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
                    </section>

                    <section class="lsb-card">
                        <h2>بله</h2>
                        <label>توکن ربات بله
                            <input type="password" name="<?php echo self::OPTION; ?>[bale_bot_token]" value="<?php echo esc_attr($s['bale_bot_token']); ?>" autocomplete="off">
                        </label>
                        <p>توکن به‌صورت تنظیمات داخلی ذخیره می‌شود و در صفحه عمومی نمایش داده نمی‌شود.</p>
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
                        <p>هسته تنظیمات و رابط کاربری آماده است. ارسال OTP واقعی و فرآیند بازگشت از بله در نسخه‌های بعدی همین Repo تکمیل می‌شود.</p>
                    </section>
                </div>

                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    public function shortcode() {
        $s = $this->settings();
        $classes = 'lsb-login lsb-theme-' . esc_attr($s['theme']);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>" style="--lsb-accent:<?php echo esc_attr($s['accent']); ?>">
            <div class="lsb-login-card">
                <h3><?php echo esc_html($s['title']); ?></h3>

                <?php if ('yes' === $s['enabled_sms']) : ?>
                    <div class="lsb-field">
                        <label>شماره موبایل</label>
                        <input type="tel" inputmode="numeric" placeholder="09xxxxxxxxx">
                    </div>
                    <button type="button" class="lsb-btn">دریافت کد</button>
                <?php endif; ?>

                <?php if ('yes' === $s['enabled_bale']) : ?>
                    <button type="button" class="lsb-btn lsb-bale">ورود با بله</button>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .lsb-login{direction:rtl}
            .lsb-login-card{max-width:420px;padding:20px;border:1px solid #e5e7eb;border-radius:18px;background:#fff}
            .lsb-field{margin:12px 0}
            .lsb-field label{display:block;margin-bottom:6px}
            .lsb-field input{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #d1d5db;border-radius:12px}
            .lsb-btn{width:100%;border:0;border-radius:12px;padding:11px 14px;background:var(--lsb-accent);color:#fff;cursor:pointer;margin-top:8px}
            .lsb-bale{background:#00a8e8}
            .lsb-theme-dark .lsb-login-card{background:#111827;color:#fff;border-color:#1f2937}
        </style>
        <?php
        return ob_get_clean();
    }
}

new LSB_Plugin();
