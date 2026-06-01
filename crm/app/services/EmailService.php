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

        try {
            if (($settings['email_transport'] ?? 'mail') === 'smtp') {
                return self::sendSmtp($settings, $to, $subject, $message, $fromEmail, $fromName);
            }

            return self::sendMail($to, $subject, $message, $fromEmail, $fromName);
        } catch (Throwable $e) {
            self::log($to, $subject, $message, 'failed', $e->getMessage());
            return false;
        }
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

    private static function sendMail(string $to, string $subject, string $message, string $fromEmail, string $fromName): bool
    {
        $headers = self::headers($fromEmail, $fromName);
        $sent = mail($to, self::encodeHeader($subject), $message, implode("\r\n", $headers));
        self::log($to, $subject, $message, $sent ? 'sent' : 'failed', $sent ? 'mail() accepted message.' : 'mail() returned false.');
        return $sent;
    }

    private static function sendSmtp(array $settings, string $to, string $subject, string $message, string $fromEmail, string $fromName): bool
    {
        $host = trim((string) ($settings['email_smtp_host'] ?? ''));
        $port = (int) ($settings['email_smtp_port'] ?? 587);
        $encryption = (string) ($settings['email_smtp_encryption'] ?? 'tls');
        $username = trim((string) ($settings['email_smtp_username'] ?? ''));
        $password = (string) ($settings['email_smtp_password'] ?? '');

        if ($host === '') {
            throw new RuntimeException('SMTP host is empty.');
        }

        $remote = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @fsockopen($remote, $port, $errno, $errstr, 20);
        if (!$socket) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_timeout($socket, 20);
        self::smtpExpect($socket, [220]);
        self::smtpCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);

        if ($encryption === 'tls') {
            self::smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start SMTP TLS encryption.');
            }
            self::smtpCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }

        if ($username !== '') {
            self::smtpCommand($socket, 'AUTH LOGIN', [334]);
            self::smtpCommand($socket, base64_encode($username), [334]);
            self::smtpCommand($socket, base64_encode($password), [235]);
        }

        self::smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        self::smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        self::smtpCommand($socket, 'DATA', [354]);

        $headers = self::headers($fromEmail, $fromName);
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . self::encodeHeader($subject);
        $headers[] = 'Date: ' . date(DATE_RFC2822);
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . self::dotStuff($message) . "\r\n.";
        self::smtpCommand($socket, $payload, [250]);
        self::smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);

        self::log($to, $subject, $message, 'sent', 'SMTP accepted message.');
        return true;
    }

    private static function headers(string $fromEmail, string $fromName): array
    {
        return [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
        ];
    }

    private static function smtpCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return self::smtpExpect($socket, $expectedCodes);
    }

    private static function smtpExpect($socket, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }

        return $response;
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function dotStuff(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $lines = explode("\n", $normalized);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }
        return implode("\r\n", $lines);
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
