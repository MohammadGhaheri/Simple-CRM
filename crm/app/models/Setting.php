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
        ];
    }
}
