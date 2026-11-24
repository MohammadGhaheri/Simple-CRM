<?php
/**
 * Plugin Name: Mammut Connect CRM Integration
 * Description: Reliable server-to-server Contact Form 7 website lead intake with HMAC, idempotency and a durable retry queue.
 * Version: 0.1.2
 * Requires PHP: 8.1
 * Text Domain: mammut-connect-crm
 */
defined('ABSPATH') || exit;

define('MC_CRM_DB_VERSION', '1');
define('MC_CRM_FORM_ID', 13);
define('MC_CRM_CRON_HOOK', 'mc_crm_retry_pending');

register_activation_hook(__FILE__, 'mc_crm_activate');
register_deactivation_hook(__FILE__, 'mc_crm_deactivate');

function mc_crm_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'mc_crm_queue';
}
function mc_crm_activate(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = mc_crm_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        request_id varchar(100) NOT NULL,
        payload longtext NOT NULL,
        state varchar(20) NOT NULL DEFAULT 'pending',
        email_status varchar(32) NOT NULL DEFAULT 'unknown',
        flamingo_status varchar(32) NOT NULL DEFAULT 'unverified',
        attempts int(11) NOT NULL DEFAULT 0,
        next_attempt_at datetime NOT NULL,
        locked_until datetime NULL,
        last_error varchar(200) NULL,
        crm_lead_id bigint(20) unsigned NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY request_id (request_id),
        KEY next_attempt (state,next_attempt_at)
    ) $charset;");
    if (!wp_next_scheduled(MC_CRM_CRON_HOOK)) wp_schedule_event(time() + 120, 'mc_crm_five_minutes', MC_CRM_CRON_HOOK);
}
function mc_crm_deactivate(): void {
    wp_clear_scheduled_hook(MC_CRM_CRON_HOOK);
}
add_filter('cron_schedules', static function (array $schedules): array {
    $schedules['mc_crm_five_minutes'] = ['interval'=>300,'display'=>'Every five minutes'];
    return $schedules;
});
add_action('init', static function (): void {
    if (get_option('mc_crm_db_version') !== MC_CRM_DB_VERSION) {
        // Only admins can trigger an upgrade, never anonymous traffic.
        if (current_user_can('manage_options')) {
            mc_crm_activate();
            update_option('mc_crm_db_version', MC_CRM_DB_VERSION, false);
        }
    }
});
function mc_crm_settings(): array {
    $saved = (array) get_option('mc_crm_settings', []);
    // Server-side wp-config.php constants override database-stored options.
    return [
        'endpoint' => defined('MC_CRM_ENDPOINT') ? (string) constant('MC_CRM_ENDPOINT') : (string) ($saved['endpoint'] ?? ''),
        'secret' => defined('MC_CRM_HMAC_SECRET') ? (string) constant('MC_CRM_HMAC_SECRET') : (string) ($saved['secret'] ?? ''),
    ];
}
function mc_crm_clean_text($value): string {
    if (is_array($value)) $value = implode(', ', array_map('strval', $value));
    return sanitize_text_field(is_scalar($value) ? (string) $value : '');
}
function mc_crm_code(string $text, string $category): string {
    $t = function_exists('mb_strtolower') ? mb_strtolower(trim($text), 'UTF-8') : strtolower(trim($text));
    if ($category === 'service') {
        $map = [
            'iotrailer' => ['iotrailer','تریلر هوشمند'],
            'iotruck' => ['iotruck','کامیون هوشمند','ردیاب کامیون'],
            'ifms' => ['ifms','سامانه مدیریت ناوگان','مدیریت ناوگان','fms'],
            'dsp' => ['dsp','پلتفرم داده'],
            'tracking' => ['ردیابی','ردیاب','tracking','مانیتورینگ','تلتونیکا'],
            'consultation' => ['مشاوره','دمو','همکاری','درخواست جلسه'],
        ];
    } else {
        $map = [
            'mixed'=>['ترکیبی','مختلط','چندنوع'],
            'trailer'=>['تریلر','نیمه یدک','نیمه‌یدک','کانتینر'],
            'van'=>['وانت','کامیونت','ون'],
            'truck'=>['کشنده','کامیون','تریلی'],
            'car'=>['سواری','خودرو شخصی'],
            'bus'=>['اتوبوس','مینی بوس','مینی‌بوس'],
        ];
    }
    foreach ($map as $code=>$terms) {
        foreach ($terms as $term) {
            if (function_exists('mb_stripos') ? mb_stripos($t, $term, 0, 'UTF-8') !== false : stripos($t, $term) !== false) {
                return $code;
            }
        }
    }
    return 'other';
}
function mc_crm_posted($posted, string $name): string {
    return mc_crm_clean_text($posted[$name] ?? '');
}
// Reject malformed mobiles at the form boundary, before a permanent CRM 422 is queued.
add_filter('wpcf7_validate_tel*', 'mc_crm_validate_lead_mobile', 20, 2);
add_filter('wpcf7_validate_tel', 'mc_crm_validate_lead_mobile', 20, 2);
function mc_crm_validate_lead_mobile($result, $tag) {
    if (($tag->name ?? '') !== 'your-phone') return $result;
    $v = trim((string) wp_unslash($_POST['your-phone'] ?? ''));
    $v = strtr($v, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    if (preg_match('/^\+989[0-9]{9}$/D', $v)) $v = '0' . substr($v, 3);
    if (!preg_match('/^09[0-9]{9}$/D', $v)) {
        $result->invalidate($tag, 'شماره موبایل معتبر وارد کنید (مانند 09120000000).');
    }
    return $result;
}
function mc_crm_payload_validation_hint($json): string {
    $p = json_decode((string)$json, true);
    if (!is_array($p)) return 'invalid_json';
    $v = strtr(trim((string)($p['mobile'] ?? '')), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
    if (preg_match('/^\+989[0-9]{9}$/D', $v)) $v = '0' . substr($v, 3);
    if (!preg_match('/^09[0-9]{9}$/D', $v)) return 'invalid_mobile';
    if (($p['consent'] ?? false) !== true) return 'invalid_consent';
    return 'inspect_other_fields';
}
function mc_crm_is_synthetic_test($p): bool {
    if (!is_array($p)) return false;
    $name = strtoupper(trim((string)($p['name'] ?? '')));
    $company = strtoupper(trim((string)($p['company'] ?? '')));
    $message = strtoupper(trim((string)($p['message'] ?? '')));
    return str_starts_with($name, 'QA TEST')
        || str_contains($name, 'NOT A REAL CUSTOMER')
        || (str_contains($company, 'TEST ONLY') && str_contains($message, 'TEST ONLY'));
}
function mc_crm_consent($value): bool {
    if (is_array($value)) return count(array_filter($value, static fn($x) => trim((string) $x) !== '')) > 0;
    $t = trim((string) $value);
    return $t !== '' && !in_array(strtolower($t), ['0','false','off','no'], true);
}
function mc_crm_payload(array $posted, $submission): array {
    $pageId = absint($posted['_wpcf7_container_post'] ?? ($_POST['_wpcf7_container_post'] ?? 3597));
    $url = $pageId ? get_permalink($pageId) : '';
    if (!$url || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' ||
        !in_array(strtolower((string) wp_parse_url($url, PHP_URL_HOST)), ['mammutconnect.com','www.mammutconnect.com'], true)) {
        $url = 'https://mammutconnect.com/contact-us-2/';
    }
    $serviceLabel = mc_crm_posted($posted, 'service-need');
    $fleetLabel = mc_crm_posted($posted, 'fleet-type');
    $inferredProduct = mc_crm_posted($posted, 'mc_product');
    $inferred = mc_crm_code($inferredProduct, 'service');
    $service = $inferred !== 'other' ? $inferred : mc_crm_code($serviceLabel, 'service');
    $count = mc_crm_posted($posted, 'vehicle-count');
    $count = strtr($count, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
    preg_match('/[0-9]+/', $count, $countMatches);
    $count = $countMatches[0] ?? '';
    return [
        'request_id' => 'mc-' . wp_generate_uuid4(),
        'source' => 'mammutconnect.com',
        'lead_source_detail' => mc_crm_posted($posted, 'lead-source'),
        'submitted_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'name' => mc_crm_posted($posted, 'your-name'),
        'mobile' => mc_crm_posted($posted, 'your-phone'),
        'email' => mc_crm_posted($posted, 'your-email'),
        'company' => mc_crm_posted($posted, 'company-name'),
        'fleet_type' => mc_crm_code($fleetLabel, 'fleet'),
        'fleet_type_label' => $fleetLabel,
        'service' => $service,
        'service_label' => $serviceLabel ?: $inferredProduct,
        'vehicle_count' => $count === '' ? 0 : (int) $count,
        'message' => sanitize_textarea_field(is_scalar($posted['your-message'] ?? '') ? (string) $posted['your-message'] : ''),
        'consent' => true,
        'page_url' => esc_url_raw($url),
        'utm_source' => mc_crm_posted($posted, 'mc_utm_source'),
        'utm_medium' => mc_crm_posted($posted, 'mc_utm_medium'),
        'utm_campaign' => mc_crm_posted($posted, 'mc_utm_campaign'),
    ];
}
// Capture whitelisted UTM and product parameters server-side in CF7-generated hidden fields.
add_filter('wpcf7_form_hidden_fields', static function (array $fields): array {
    $form = class_exists('WPCF7_ContactForm') ? WPCF7_ContactForm::get_current() : null;
    if (!$form || (int) $form->id() !== MC_CRM_FORM_ID) return $fields;
    foreach (['mc_utm_source'=>'utm_source','mc_utm_medium'=>'utm_medium','mc_utm_campaign'=>'utm_campaign','mc_product'=>'mc_product'] as $name=>$query) {
        if (isset($_GET[$query]) && is_string($_GET[$query])) {
            $fields[$name] = substr(sanitize_text_field(wp_unslash($_GET[$query])), 0, 120);
        }
    }
    return $fields;
});
add_action('wpcf7_submit', static function ($form, $result): void {
    if ((int) $form->id() !== MC_CRM_FORM_ID) return;
    $status = (string) ($result['status'] ?? '');
    if (!in_array($status, ['mail_sent','mail_failed'], true)) return; // no invalid/spam/acceptance-missing
    if (!class_exists('WPCF7_Submission')) return;
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;
    $posted = (array) $submission->get_posted_data();
    if (!mc_crm_consent($posted['privacy-consent'] ?? '')) return;
    $payload = mc_crm_payload($posted, $submission);
    if ($payload['name'] === '' || $payload['mobile'] === '' || strlen($payload['message']) > 12000) return;
    mc_crm_enqueue($payload, $status);
}, 20, 2);
function mc_crm_enqueue(array $payload, string $emailStatus): void {
    global $wpdb;
    $now = gmdate('Y-m-d H:i:s');
    // Durable before outbound I/O; CRM unavailability must not erase the request.
    $ok = $wpdb->insert(mc_crm_table(), [
        'request_id'=>$payload['request_id'],
        'payload'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'state'=>'pending', 'email_status'=>$emailStatus, 'flamingo_status'=>'unverified',
        'attempts'=>0, 'next_attempt_at'=>$now, 'created_at'=>$now, 'updated_at'=>$now,
    ], ['%s','%s','%s','%s','%s','%d','%s','%s','%s']);
    if ($ok === false) {
        error_log('mc_crm_queue_database_write_failed');
        return;
    }
    $id = (int) $wpdb->insert_id;
    mc_crm_send_row($id);
}
function mc_crm_send_row(int $id): bool {
    global $wpdb;
    $table = mc_crm_table();
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE $table SET state='sending',locked_until=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 SECOND),updated_at=UTC_TIMESTAMP()
        WHERE id=%d AND ((state='pending' AND next_attempt_at <= UTC_TIMESTAMP()) OR (state='sending' AND locked_until < UTC_TIMESTAMP()))", $id
    ));
    if ($claimed !== 1) return false;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id), ARRAY_A);
    if (!$row) return false;
    $config = mc_crm_settings();
    $url = esc_url_raw($config['endpoint']);
    $error = '';
    $permanent = false;
    if (strlen($config['secret']) < 32 || wp_parse_url($url, PHP_URL_SCHEME) !== 'https') {
        $error = 'integration_not_configured';
    } else {
        $time = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $sig = hash_hmac('sha256', $time . "\n" . $nonce . "\n" . $row['payload'], $config['secret']);
        $response = wp_safe_remote_post($url, [
            'timeout'=>8, 'redirection'=>0, 'sslverify'=>true,
            'headers'=>[
                'Content-Type'=>'application/json; charset=utf-8',
                'X-MC-Timestamp'=>$time,
                'X-MC-Nonce'=>$nonce,
                'X-MC-Signature'=>'sha256='.$sig,
            ],
            'body'=>$row['payload'],
        ]);
        if (is_wp_error($response)) {
            $error = 'network_error';
        } else {
            $code = (int) wp_remote_retrieve_response_code($response);
            $parsed = json_decode((string) wp_remote_retrieve_body($response), true);
            if (in_array($code, [200, 201], true) && is_array($parsed) &&
                ($parsed['request_id'] ?? '') === $row['request_id'] && (int) ($parsed['lead_id'] ?? 0) > 0) {
                $wpdb->update($table, [
                    'state'=>'sent','attempts'=>(int)$row['attempts']+1,
                    'crm_lead_id'=>(int)$parsed['lead_id'],
                    'last_error'=>null,'locked_until'=>null,'updated_at'=>gmdate('Y-m-d H:i:s')
                ], ['id'=>$id]);
                return true;
            }
            $error = 'crm_http_' . $code;
            // Permanent remote rejections are retained as blocked, not retried forever.
            $permanent = in_array($code, [401,403,409,413,415,422], true);
            if ($permanent) $error .= '_check_configuration';
        }
    }
    $attempts = (int) $row['attempts'] + 1;
    $delay = min(43200, 300 * (2 ** min(7, max(0, $attempts - 1))));
    $wpdb->update($table, [
        'state'=>$permanent ? 'blocked' : 'pending','attempts'=>$attempts,
        'next_attempt_at'=>gmdate('Y-m-d H:i:s', time()+$delay),
        'last_error'=>$error,'locked_until'=>null,'updated_at'=>gmdate('Y-m-d H:i:s')
    ], ['id'=>$id]);
    return false;
}
add_action(MC_CRM_CRON_HOOK, static function (): void {
    global $wpdb;
    $table = mc_crm_table();
    $ids = $wpdb->get_col("SELECT id FROM $table WHERE (state='pending' AND next_attempt_at <= UTC_TIMESTAMP()) OR (state='sending' AND locked_until < UTC_TIMESTAMP()) ORDER BY created_at LIMIT 15");
    foreach ($ids as $id) mc_crm_send_row((int)$id);
});
add_action('wp_mail_failed', static function ($error): void {
    if (!is_wp_error($error)) return;
    // Metadata only; never store full mail content, addresses, SMTP credentials or headers.
    $errors = (array) get_option('mc_crm_mail_errors', []);
    array_unshift($errors, ['time'=>gmdate('Y-m-d H:i:s'), 'code'=>sanitize_key($error->get_error_code())]);
    update_option('mc_crm_mail_errors', array_slice($errors, 0, 10), false);
}, 10, 1);
add_action('admin_menu', static function (): void {
    add_management_page('Mammut Connect CRM', 'اتصال CRM ماموت', 'manage_options', 'mc-crm', 'mc_crm_admin');
});
function mc_crm_admin(): void {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $table = mc_crm_table();
    if (isset($_POST['mc_crm_save'])) {
        check_admin_referer('mc_crm_save');
        $old = (array) get_option('mc_crm_settings', []);
        $endpoint = esc_url_raw((string) wp_unslash($_POST['endpoint'] ?? ''));
        if (wp_parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            echo '<div class="notice notice-error"><p>نشانی API باید HTTPS باشد.</p></div>';
        } else {
            $secret = (string) wp_unslash($_POST['secret'] ?? '');
            update_option('mc_crm_settings', ['endpoint'=>$endpoint,'secret'=>$secret !== '' ? $secret : (string) ($old['secret'] ?? '')], false);
            echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد.</p></div>';
        }
    }
    if (isset($_POST['mc_crm_quarantine_test'])) {
        check_admin_referer('mc_crm_quarantine_test');
        $id = absint($_POST['id'] ?? 0);
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT id,payload,state,last_error FROM $table WHERE id=%d", $id), ARRAY_A);
        $p = is_array($current) ? json_decode((string)$current['payload'], true) : null;
        if ($current && $current['state'] === 'pending'
            && $current['last_error'] === 'crm_http_422_check_configuration'
            && is_array($p)
            && mc_crm_is_synthetic_test($p)
            && mc_crm_payload_validation_hint($current['payload']) === 'invalid_mobile') {
            $wpdb->update($table, [
                'state'=>'blocked',
                'last_error'=>'invalid_test_mobile_422_preserved',
                'updated_at'=>gmdate('Y-m-d H:i:s')
            ], ['id'=>$id, 'state'=>'pending']);
            echo '<div class="notice notice-success"><p>درخواست تست نامعتبر بدون حذف اطلاعات و بدون ارسال دوباره از صف Retry خارج شد.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>الگوی دقیق رکورد تست تأیید نشد؛ هیچ تغییری انجام نشد.</p></div>';
        }
    }
    if (isset($_POST['mc_crm_retry'])) {
        check_admin_referer('mc_crm_retry');
        $id = absint($_POST['id'] ?? 0);
        $wpdb->query($wpdb->prepare("UPDATE $table SET state='pending',next_attempt_at=UTC_TIMESTAMP() WHERE id=%d AND state IN ('pending','blocked')", $id));
        mc_crm_send_row($id);
    }
    $settings = mc_crm_settings();
    $records = $wpdb->get_results("SELECT id,request_id,payload,state,email_status,flamingo_status,attempts,crm_lead_id,last_error,created_at FROM $table ORDER BY id DESC LIMIT 50", ARRAY_A);
    $counts = $wpdb->get_results("SELECT state,COUNT(*) AS total FROM $table GROUP BY state", ARRAY_A);
    echo '<div class="wrap"><h1>اتصال ماموت کانکت به CRM</h1><p>وضعیت Flamingo باید در صندوق دریافتی Flamingo بررسی شود؛ unverified به‌معنای تأییدنشده است.</p>';
    echo '<h2>وضعیت صف</h2><pre>' . esc_html(wp_json_encode($counts, JSON_UNESCAPED_UNICODE)) . '</pre>';
    echo '<h2>تنظیمات امن</h2><form method="post">';
    wp_nonce_field('mc_crm_save');
    echo '<p><label>HTTPS API endpoint <input type="url" size="65" name="endpoint" value="' . esc_attr($settings['endpoint']) . '"></label></p>';
    echo '<p><label>HMAC shared secret (leave blank to retain) <input type="password" name="secret" value="" autocomplete="new-password"></label></p>';
    if (defined('MC_CRM_HMAC_SECRET') || defined('MC_CRM_ENDPOINT')) echo '<p>مقادیر wp-config.php بر تنظیمات اینجا اولویت دارند.</p>';
    echo '<p><button class="button button-primary" name="mc_crm_save" value="1">ذخیره</button></p></form>';
    echo '<h2>۵۰ ارسال آخر</h2><table class="widefat striped"><thead><tr><th>شناسه</th><th>زمان</th><th>CRM</th><th>ایمیل</th><th>Flamingo</th><th>تلاش‌ها</th><th>خطا</th><th>تشخیص</th><th>اقدام</th></tr></thead><tbody>';
    foreach ($records as $r) {
        echo '<tr><td>' . esc_html($r['request_id']) . '</td><td>' . esc_html($r['created_at']) .
        '</td><td>' . esc_html($r['state'] . ($r['crm_lead_id'] ? ' #' . $r['crm_lead_id'] : '')) . '</td><td>' .
        esc_html($r['email_status']) . '</td><td>' . esc_html($r['flamingo_status']) . '</td><td>' .
        (int)$r['attempts'] . '</td><td>' . esc_html($r['last_error'] ?: '-') .
        '</td><td>' . esc_html(mc_crm_payload_validation_hint($r['payload'])) . '</td><td>';
        if ($r['state'] === 'pending' && $r['last_error'] === 'crm_http_422_check_configuration') {
            $p = json_decode((string)$r['payload'], true);
            if (is_array($p) && mc_crm_is_synthetic_test($p)
                && mc_crm_payload_validation_hint($r['payload']) === 'invalid_mobile') {
                echo '<form method="post">'; wp_nonce_field('mc_crm_quarantine_test');
                echo '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="button" name="mc_crm_quarantine_test" value="1">بایگانی تست نامعتبر</button></form>';
            }
        } elseif ($r['state'] === 'pending' || $r['state'] === 'blocked') {
            echo '<form method="post">'; wp_nonce_field('mc_crm_retry');
            echo '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="button" name="mc_crm_retry" value="1">تلاش مجدد دستی</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<h2>خطاهای ثبت‌شده wp_mail_failed</h2><pre>' . esc_html(wp_json_encode(get_option('mc_crm_mail_errors', []), JSON_UNESCAPED_UNICODE)) . '</pre></div>';
}
