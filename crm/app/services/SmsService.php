<?php

declare(strict_types=1);

class SmsService
{
    public static function send(string $mobile, string $message): bool
    {
        $settings = Setting::all();
        if (($settings['sms_enabled'] ?? '0') !== '1' || empty($settings['sms_api_key']) || empty($settings['sms_line_number']) || trim($mobile) === '') {
            return false;
        }

        $payload = json_encode([
            'lineNumber' => $settings['sms_line_number'],
            'messageText' => $message,
            'mobiles' => [$mobile],
            'sendDateTime' => null,
        ], JSON_UNESCAPED_UNICODE);

        if (!function_exists('curl_init')) {
            self::log($mobile, $message, 'failed', 'PHP cURL extension is not enabled.');
            return false;
        }

        $ch = curl_init('https://api.sms.ir/v1/send/bulk');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-KEY: ' . $settings['sms_api_key'],
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        self::log($mobile, $message, $httpCode >= 200 && $httpCode < 300 ? 'sent' : 'failed', (string) ($response ?: $error));
        return $httpCode >= 200 && $httpCode < 300;
    }

    public static function notifyTicketCreated(array $ticket): void
    {
        $settings = Setting::all();
        if (($settings['sms_ticket_created_enabled'] ?? '0') !== '1') {
            return;
        }
        $mobile = '';
        if (!empty($ticket['assigned_user_id']) && class_exists('User')) {
            $user = User::find((int) $ticket['assigned_user_id']);
            $mobile = $user['mobile'] ?? '';
        }
        $mobile = $mobile ?: ($settings['sms_admin_mobile'] ?? '');
        $link = self::baseUrl() . '/index.php?page=tickets&action=edit&id=' . (int) $ticket['id'];
        self::send($mobile, 'تیکت جدید برای بررسی ثبت شد: ' . $ticket['ticket_code'] . "\n" . $link);
    }

    public static function notifyVipTicketCreated(array $ticket): void
    {
        if ((int) ($ticket['is_vip'] ?? 0) !== 1) {
            return;
        }

        $settings = Setting::all();
        $mobile = '';
        if (!empty($ticket['assigned_user_id']) && class_exists('User')) {
            $user = User::find((int) $ticket['assigned_user_id']);
            $mobile = $user['mobile'] ?? '';
        }
        $mobile = $mobile ?: ($settings['sms_admin_mobile'] ?? '');
        $link = self::baseUrl() . '/index.php?page=tickets&action=edit&id=' . (int) $ticket['id'];
        self::send($mobile, 'تیکت VIP جدید ثبت شد: ' . $ticket['ticket_code'] . "\n" . $link);
    }

    public static function notifyTicketAnswered(array $ticket): void
    {
        $settings = Setting::all();
        if (($settings['sms_ticket_answered_enabled'] ?? '0') !== '1') {
            return;
        }
        $contact = Contact::find((int) $ticket['contact_id']);
        $link = self::baseUrl() . '/portal.php?action=ticket&id=' . (int) $ticket['id'];
        self::send($contact['mobile'] ?? '', 'نتیجه تیکت شما ثبت شد: ' . $ticket['ticket_code'] . "\n" . $link);
    }

    public static function sendPortalCredentials(array $contact, string $plainPassword): bool
    {
        $settings = Setting::all();
        if (($settings['sms_portal_credentials_enabled'] ?? '0') !== '1') {
            return false;
        }

        $template = trim((string) ($settings['sms_portal_credentials_template'] ?? ''));
        if ($template === '') {
            $template = "سلام {contact_name}\nدسترسی شما به پرتال مشتری {app_title} فعال شد.\nنام کاربری: {email}\nرمز عبور: {password}\nورود: {portal_url}";
        }

        $message = strtr($template, [
            '{app_title}' => (string) ($settings['app_title'] ?? 'Elm Simple CRM'),
            '{contact_name}' => (string) ($contact['contact_name'] ?? ''),
            '{customer_name}' => (string) ($contact['customer_name'] ?? ''),
            '{email}' => (string) ($contact['email'] ?? ''),
            '{password}' => $plainPassword,
            '{portal_url}' => self::portalUrl($settings),
        ]);

        return self::send((string) ($contact['mobile'] ?? ''), $message);
    }

    public static function sendDailySummary(): bool
    {
        $settings = Setting::all();
        if (($settings['sms_daily_summary_enabled'] ?? '0') !== '1') {
            return false;
        }
        $openTickets = Ticket::needsReviewCount();
        $customers = (int) db()->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL')->fetchColumn();
        $openDeals = (int) db()->query("SELECT COUNT(*) FROM deals d JOIN customers c ON c.id = d.customer_id WHERE d.deal_stage NOT IN ('Won','Lost') AND d.deleted_at IS NULL AND c.deleted_at IS NULL")->fetchColumn();
        $overdue = Activity::overdueCount();
        $message = "خلاصه روزانه CRM\nمشتریان: $customers\nفرصت‌های باز: $openDeals\nپیگیری عقب‌افتاده: $overdue\nتیکت نیازمند بررسی: $openTickets";
        return self::send($settings['sms_admin_mobile'] ?? '', $message);
    }

    private static function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/crm/public/index.php')), '/');
        return $scheme . '://' . $host . $dir;
    }

    private static function portalUrl(array $settings): string
    {
        $configured = trim((string) ($settings['portal_public_url'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return self::baseUrl() . '/portal.php?action=login';
    }

    private static function log(string $mobile, string $message, string $status, string $response): void
    {
        db()->exec("CREATE TABLE IF NOT EXISTS sms_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mobile VARCHAR(40) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(30) NOT NULL,
            provider_response TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $stmt = db()->prepare('INSERT INTO sms_logs (mobile, message, status, provider_response) VALUES (?, ?, ?, ?)');
        $stmt->execute([$mobile, $message, $status, $response]);
    }
}
