<?php

declare(strict_types=1);

class EmailService
{
    public static function send(string $to, string $subject, string $message): bool
    {
        $settings = Setting::all();
        $to = trim($to);
        if (($settings['email_enabled'] ?? '0') !== '1' || $to === '') {
            return false;
        }

        $fromEmail = trim((string) ($settings['email_from_address'] ?? ''));
        $fromName = trim((string) ($settings['email_from_name'] ?? 'Elm Simple CRM'));
        if ($fromEmail === '') {
            self::log($to, $subject, $message, 'failed', 'Sender email is empty.');
            return false;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . $encodedFrom . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
        ];

        $sent = mail($to, $encodedSubject, $message, implode("\r\n", $headers));
        self::log($to, $subject, $message, $sent ? 'sent' : 'failed', $sent ? 'mail() accepted message.' : 'mail() returned false.');
        return $sent;
    }

    public static function sendPortalCredentials(array $contact, string $plainPassword): bool
    {
        $settings = Setting::all();
        if (($settings['email_portal_credentials_enabled'] ?? '0') !== '1') {
            return false;
        }

        $subject = self::render((string) ($settings['email_portal_credentials_subject'] ?? 'اطلاعات ورود پرتال مشتری'), $contact, $plainPassword);
        $body = self::render((string) ($settings['email_portal_credentials_template'] ?? ''), $contact, $plainPassword);
        if (trim($body) === '') {
            $body = self::render("سلام {contact_name}\nدسترسی شما به پرتال مشتری {app_title} فعال شد.\nنام کاربری: {email}\nرمز عبور: {password}\nورود: {portal_url}", $contact, $plainPassword);
        }

        return self::send((string) ($contact['email'] ?? ''), $subject, $body);
    }

    public static function notifyTicketAnswered(array $ticket): bool
    {
        $settings = Setting::all();
        if (($settings['email_ticket_answered_enabled'] ?? '0') !== '1') {
            return false;
        }

        $contact = Contact::find((int) $ticket['contact_id']);
        if (!$contact) {
            return false;
        }

        $link = self::portalActionUrl($settings, 'ticket', (int) $ticket['id']);
        $subject = 'پاسخ تیکت ' . ($ticket['ticket_code'] ?? '');
        $message = "سلام " . ($contact['contact_name'] ?? '') . "\nنتیجه تیکت شما ثبت شد.\nکد تیکت: " . ($ticket['ticket_code'] ?? '') . "\n$link";
        return self::send((string) ($contact['email'] ?? ''), $subject, $message);
    }

    public static function sendActivityReminder(array $activity): bool
    {
        $settings = Setting::all();
        if (($settings['email_activity_reminder_enabled'] ?? '0') !== '1') {
            return false;
        }

        $contact = Contact::primaryByCustomer((int) $activity['customer_id']);
        if (!$contact) {
            return false;
        }

        $subject = 'یادآوری پیگیری';
        $message = "سلام " . ($contact['contact_name'] ?? '') . "\n" . ($activity['summary'] ?? '') . "\nاقدام بعدی: " . ($activity['next_action'] ?? '') . "\nتاریخ پیگیری: " . fa_date($activity['next_followup_date'] ?? '');
        return self::send((string) ($contact['email'] ?? ''), $subject, $message);
    }

    private static function render(string $template, array $contact, string $plainPassword): string
    {
        $settings = Setting::all();
        return strtr($template, [
            '{app_title}' => (string) ($settings['app_title'] ?? 'Elm Simple CRM'),
            '{contact_name}' => (string) ($contact['contact_name'] ?? ''),
            '{customer_name}' => (string) ($contact['customer_name'] ?? ''),
            '{email}' => (string) ($contact['email'] ?? ''),
            '{password}' => $plainPassword,
            '{portal_url}' => self::portalUrl($settings),
        ]);
    }

    private static function portalUrl(array $settings): string
    {
        $configured = trim((string) ($settings['portal_public_url'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/crm/public/index.php')), '/');
        return $scheme . '://' . $host . $dir . '/portal.php?action=login';
    }

    private static function portalActionUrl(array $settings, string $action, int $id): string
    {
        $url = self::portalUrl($settings);
        $base = preg_replace('/\?.*$/', '', $url) ?: $url;
        return $base . '?action=' . urlencode($action) . '&id=' . $id;
    }

    private static function log(string $to, string $subject, string $message, string $status, string $response): void
    {
        db()->exec("CREATE TABLE IF NOT EXISTS email_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(190) NOT NULL,
            subject VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(30) NOT NULL,
            provider_response TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stmt = db()->prepare('INSERT INTO email_logs (recipient_email, subject, message, status, provider_response) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$to, $subject, $message, $status, $response]);
    }
}
