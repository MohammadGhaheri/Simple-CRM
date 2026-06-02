<?php

declare(strict_types=1);

class Setting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defaults = self::defaults();
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
            foreach ($rows as $row) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            // Settings table may not exist before migrations are imported.
        }

        self::$cache = $defaults;
        return self::$cache;
    }

    public static function get(string $key): string
    {
        $settings = self::all();
        return (string) ($settings[$key] ?? '');
    }

    public static function saveMany(array $settings): void
    {
        $stmt = db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, self::defaults())) {
                continue;
            }
            $stmt->execute([$key, (string) $value]);
        }
        self::$cache = null;
    }

    public static function defaults(): array
    {
        return [
            'app_title' => 'Elm Simple CRM',
            'app_subtitle' => 'مدیریت مشتریان، فرصت‌ها و پیگیری‌های فروش',
            'primary_color' => '#155eef',
            'sidebar_color' => '#111827',
            'app_icon' => '',
            'home_title' => 'Elm Simple CRM',
            'home_text' => 'یک سامانه سبک برای مدیریت مشتریان، مخاطبین، فرصت‌های فروش، پیگیری‌ها و تیکت‌های مشتریان.',
            'show_about_page' => '1',
            'currency_unit' => 'ریال',
            'customer_code_mode' => 'manual',
            'customer_code_format' => 'CUS-{YYYY}-{SEQ4}',
            'contract_renewal_reminder_days' => '30',
            'options_customer_types' => "B2B Fleet|ناوگان سازمانی\nB2C Owner|مالک شخصی\nB2D Dealer|نمایندگی\nOEM|خودروساز\nStrategic Partner|شریک راهبردی\nOther|سایر",
            'options_sales_statuses' => "New|جدید\nContacted|تماس گرفته شده\nMeeting Scheduled|جلسه تنظیم شده\nProposal Sent|پیشنهاد ارسال شده\nNegotiation|مذاکره\nWon|برنده\nLost|از دست رفته\nInactive|غیرفعال",
            'options_products' => "FMS|FMS\nTBox|TBox\nConnected Vehicle Platform|Connected Vehicle Platform\nOwner App|Owner App\nAPI Integration|API Integration\nDashboard / BI|Dashboard / BI\nonCloud|onCloud\nonPremises|onPremises\nOther|سایر",
            'options_deal_stages' => "Lead|سرنخ\nQualified|تایید شده\nProposal|پیشنهاد\nNegotiation|مذاکره\nWon|برنده\nLost|از دست رفته",
            'options_activity_types' => "Call|تماس\nMeeting|جلسه\nWhatsApp / Message|پیام\nEmail|ایمیل\nProposal Sent|پیشنهاد ارسال شده\nDemo|دمو\nFollow-up|پیگیری\nContract|قرارداد\nContract Renewal|تمدید قرارداد\nSupport|پشتیبانی\nOther|سایر",
            'options_activity_statuses' => "Open|باز\nDone|انجام شده\nCancelled|لغو شده\nWaiting|در انتظار",
            'options_contract_statuses' => "Active|فعال\nRenewal Due|نیازمند تمدید\nRenewed|تمدید شده\nExpired|منقضی شده\nCancelled|لغو شده",
            'options_ticket_statuses' => "Open|باز\nIn Progress|در حال بررسی\nWaiting Customer|در انتظار مشتری\nResolved|حل شده\nClosed|بسته",
            'options_ticket_priorities' => "Low|کم\nNormal|عادی\nHigh|زیاد\nUrgent|فوری",
            'options_ticket_categories' => "Support|پشتیبانی\nRequest|درخواست\nBug|خطا\nTraining|آموزش\nBilling|مالی\nOther|سایر",
            'sms_enabled' => '0',
            'sms_ticket_created_enabled' => '0',
            'sms_ticket_answered_enabled' => '0',
            'sms_portal_credentials_enabled' => '1',
            'sms_portal_credentials_template' => "سلام {contact_name}\nدسترسی شما به پرتال مشتری {app_title} فعال شد.\nنام کاربری: {email}\nرمز عبور: {password}\nورود: {portal_url}",
            'sms_daily_summary_enabled' => '0',
            'sms_api_key' => '',
            'sms_line_number' => '',
            'sms_admin_mobile' => '',
            'sms_default_assigned_user_id' => '',
            'portal_public_url' => '',
            'email_enabled' => '0',
            'email_transport' => 'mail',
            'email_from_name' => 'Elm Simple CRM',
            'email_from_address' => '',
            'email_smtp_host' => '',
            'email_smtp_port' => '587',
            'email_smtp_username' => '',
            'email_smtp_password' => '',
            'email_smtp_encryption' => 'tls',
            'email_test_recipient' => '',
            'email_portal_credentials_enabled' => '1',
            'email_ticket_answered_enabled' => '1',
            'email_activity_reminder_enabled' => '1',
            'email_portal_credentials_subject' => 'اطلاعات ورود پرتال مشتری',
            'email_portal_credentials_template' => "سلام {contact_name}\nدسترسی شما به پرتال مشتری {app_title} فعال شد.\nنام کاربری: {email}\nرمز عبور: {password}\nورود: {portal_url}",
        ];
    }
}
