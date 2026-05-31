<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        (int) ($config['port'] ?? 3306),
        $config['dbname'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function asset(string $path): string
{
    return 'assets/' . ltrim($path, '/');
}

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(array_merge(['page' => $page], $params));
}

function current_user_id(): int
{
    return (int) ($_SESSION['user']['id'] ?? 0);
}

function format_money($value): string
{
    return number_format((float) $value) . ' ریال';
}

function selected($actual, $expected): string
{
    return (string) $actual === (string) $expected ? 'selected' : '';
}

function checked($actual): string
{
    return (bool) $actual ? 'checked' : '';
}

function normalize_digits(string $value): string
{
    return strtr($value, [
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
    ]);
}

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [$jy, $jm, $jd];
}

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd;
    $days += $jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186;
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;

    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }

    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    $gd = $days + 1;
    $salA = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 1;
    while ($gm <= 12 && $gd > $salA[$gm]) {
        $gd -= $salA[$gm];
        $gm++;
    }

    return [$gy, $gm, $gd];
}

function fa_date(?string $gregorianDate): string
{
    if (!$gregorianDate) {
        return '';
    }

    $date = substr($gregorianDate, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $gregorianDate;
    }

    [$gy, $gm, $gd] = array_map('intval', explode('-', $date));
    [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

function db_date(?string $input): ?string
{
    $input = trim(normalize_digits((string) $input));
    if ($input === '') {
        return null;
    }

    $input = str_replace(['.', '\\'], ['/', '/'], $input);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
        return $input;
    }
    if (!preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', $input, $matches)) {
        return null;
    }

    $year = (int) $matches[1];
    $month = (int) $matches[2];
    $day = (int) $matches[3];

    if ($year < 1700) {
        [$gy, $gm, $gd] = jalali_to_gregorian($year, $month, $day);
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function required_fields(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $errors[] = $label . ' الزامی است.';
        }
    }
    return $errors;
}

function badge_class(string $value): string
{
    $map = [
        'New' => 'badge-info',
        'Contacted' => 'badge-primary',
        'Meeting Scheduled' => 'badge-warning',
        'Proposal Sent' => 'badge-purple',
        'Negotiation' => 'badge-warning',
        'Won' => 'badge-success',
        'Lost' => 'badge-danger',
        'Inactive' => 'badge-muted',
        'Lead' => 'badge-info',
        'Qualified' => 'badge-primary',
        'Proposal' => 'badge-purple',
        'Open' => 'badge-info',
        'Done' => 'badge-success',
        'Cancelled' => 'badge-danger',
        'Waiting' => 'badge-warning',
    ];

    return $map[$value] ?? 'badge-muted';
}

function customer_type_options(): array
{
    return ['B2B Fleet', 'B2C Owner', 'B2D Dealer', 'OEM', 'Strategic Partner', 'Other'];
}

function sales_status_options(): array
{
    return ['New', 'Contacted', 'Meeting Scheduled', 'Proposal Sent', 'Negotiation', 'Won', 'Lost', 'Inactive'];
}

function product_options(): array
{
    return ['FMS', 'TBox', 'Connected Vehicle Platform', 'Owner App', 'API Integration', 'Dashboard / BI', 'onCloud', 'onPremises', 'Other'];
}

function deal_stage_options(): array
{
    return ['Lead', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
}

function activity_type_options(): array
{
    return ['Call', 'Meeting', 'WhatsApp / Message', 'Email', 'Proposal Sent', 'Demo', 'Follow-up', 'Contract', 'Support', 'Other'];
}

function activity_status_options(): array
{
    return ['Open', 'Done', 'Cancelled', 'Waiting'];
}

function fa_label(string $value): string
{
    $labels = [
        'B2B Fleet' => 'ناوگان سازمانی',
        'B2C Owner' => 'مالک شخصی',
        'B2D Dealer' => 'نمایندگی',
        'OEM' => 'خودروساز',
        'Strategic Partner' => 'شریک راهبردی',
        'Other' => 'سایر',
        'New' => 'جدید',
        'Contacted' => 'تماس گرفته شده',
        'Meeting Scheduled' => 'جلسه تنظیم شده',
        'Proposal Sent' => 'پیشنهاد ارسال شده',
        'Negotiation' => 'مذاکره',
        'Won' => 'برنده',
        'Lost' => 'از دست رفته',
        'Inactive' => 'غیرفعال',
        'Lead' => 'سرنخ',
        'Qualified' => 'تایید شده',
        'Proposal' => 'پیشنهاد',
        'Open' => 'باز',
        'Done' => 'انجام شده',
        'Cancelled' => 'لغو شده',
        'Waiting' => 'در انتظار',
        'Call' => 'تماس',
        'Meeting' => 'جلسه',
        'WhatsApp / Message' => 'پیام',
        'Email' => 'ایمیل',
        'Demo' => 'دمو',
        'Follow-up' => 'پیگیری',
        'Contract' => 'قرارداد',
        'Support' => 'پشتیبانی',
    ];

    return $labels[$value] ?? $value;
}
