<?php

declare(strict_types=1);

/*
 * Elm Simple CRM core helpers
 * Author: Mohammad Ghaheri Najafabadi
 * Email: mohammad.ghaheri@gmail.com
 */

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

function linkify_text(?string $value): string
{
    $text = (string) $value;
    $result = '';
    $offset = 0;

    if (preg_match_all('~https?://[^\s<]+~u', $text, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as [$match, $position]) {
            $result .= e(substr($text, $offset, $position - $offset));
            $url = rtrim($match, ".,؛،)");
            $suffix = substr($match, strlen($url));
            $result .= '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($url) . '</a>' . e($suffix);
            $offset = $position + strlen($match);
        }
    }

    $result .= e(substr($text, $offset));
    return nl2br($result);
}

function text_excerpt(?string $value, int $length = 120): string
{
    $text = trim(strip_tags((string) $value));
    if ($text === '' || $length <= 0) {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $length ? mb_substr($text, 0, $length, 'UTF-8') . '...' : $text;
    }

    if (preg_match_all('/./us', $text, $matches) && count($matches[0]) > $length) {
        return implode('', array_slice($matches[0], 0, $length)) . '...';
    }

    return $text;
}

function sanitize_rich_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    $allowedTags = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img'];
    $allowedAttrs = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title'],
    ];

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $cleanNode = static function (DOMNode $node) use (&$cleanNode, $allowedTags, $allowedAttrs): void {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if ($tag !== 'div' && !in_array($tag, $allowedTags, true)) {
                $fragment = $node->ownerDocument->createDocumentFragment();
                while ($node->firstChild) {
                    $fragment->appendChild($node->firstChild);
                }
                $node->parentNode?->replaceChild($fragment, $node);
                return;
            }

            $remove = [];
            foreach ($node->attributes as $attribute) {
                $attr = strtolower($attribute->name);
                if (!in_array($attr, $allowedAttrs[$tag] ?? [], true)) {
                    $remove[] = $attribute->name;
                }
            }
            foreach ($remove as $attr) {
                $node->removeAttribute($attr);
            }

            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                if (!preg_match('~^https?://~i', $href) && !str_starts_with($href, 'mailto:')) {
                    $node->removeAttribute('href');
                } else {
                    $node->setAttribute('target', '_blank');
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
            }

            if ($tag === 'img') {
                $src = trim($node->getAttribute('src'));
                if (!preg_match('~^https?://~i', $src)) {
                    $node->parentNode?->removeChild($node);
                    return;
                }
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $cleanNode($child);
        }
    };

    $cleanNode($dom);
    $body = '';
    foreach ($dom->documentElement?->childNodes ?? [] as $child) {
        $body .= $dom->saveHTML($child);
    }

    return trim($body);
}

function announcement_attachment_root(): string
{
    return dirname(__DIR__, 2) . '/storage/announcement-attachments';
}

function upload_announcement_attachments(string $field): array
{
    if (empty($_FILES[$field]['name'])) {
        return [];
    }

    $files = [];
    $names = is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'] : [$_FILES[$field]['name']];
    foreach ($names as $index => $name) {
        $error = is_array($_FILES[$field]['error']) ? (int) $_FILES[$field]['error'][$index] : (int) $_FILES[$field]['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('آپلود فایل اطلاعیه ناموفق بود.');
        }

        $tmp = is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][$index] : $_FILES[$field]['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('فایل اطلاعیه معتبر نیست.');
        }

        $size = is_array($_FILES[$field]['size']) ? (int) $_FILES[$field]['size'][$index] : (int) $_FILES[$field]['size'];
        if ($size > 5 * 1024 * 1024) {
            throw new RuntimeException('حجم هر فایل اطلاعیه نباید بیشتر از ۵ مگابایت باشد.');
        }

        $originalName = basename((string) $name);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedMimes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        if (!isset($allowedMimes[$extension])) {
            throw new RuntimeException('فرمت فایل اطلاعیه مجاز نیست. تصویر، PDF و Word قابل قبول است.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!in_array($mime, $allowedMimes[$extension], true)) {
            throw new RuntimeException('نوع واقعی فایل اطلاعیه با پسوند آن سازگار نیست.');
        }
        if ($extension === 'docx' && $mime === 'application/zip') {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('افزونه ZIP برای بررسی فایل Word روی سرور فعال نیست.');
            }
            $zip = new ZipArchive();
            $opened = $zip->open($tmp);
            $isDocx = $opened === true && $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('word/document.xml') !== false;
            if ($opened === true) {
                $zip->close();
            }
            if (!$isDocx) {
                throw new RuntimeException('فایل Word اطلاعیه معتبر نیست.');
            }
        }

        $relativeDir = date('Y/m');
        $dir = announcement_attachment_root() . '/' . $relativeDir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('پوشه نگهداری فایل اطلاعیه ساخته نشد.');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('پوشه نگهداری فایل اطلاعیه قابل نوشتن نیست.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($tmp, $dir . '/' . $filename)) {
            throw new RuntimeException('ذخیره فایل اطلاعیه ناموفق بود.');
        }

        $files[] = [
            'path' => $relativeDir . '/' . $filename,
            'name' => $originalName,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    return $files;
}

function delete_announcement_attachment(?string $relativePath): void
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    if ($relativePath === '' || str_contains($relativePath, '..')) {
        return;
    }

    $root = realpath(announcement_attachment_root());
    $target = realpath(announcement_attachment_root() . '/' . ltrim($relativePath, '/'));
    if ($root && $target && str_starts_with($target, $root . DIRECTORY_SEPARATOR) && is_file($target)) {
        @unlink($target);
    }
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
    $relativePath = 'assets/' . ltrim($path, '/');
    $file = public_upload_root() . '/' . $relativePath;
    $version = is_file($file) ? (string) filemtime($file) : app_version();
    return $relativePath . '?v=' . rawurlencode($version);
}

function app_version(): string
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }

    $versionPath = dirname(__DIR__, 2) . '/../VERSION';
    $version = is_file($versionPath) ? trim((string) file_get_contents($versionPath)) : '';
    return $version !== '' ? $version : 'dev';
}

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(array_merge(['page' => $page], $params));
}

function absolute_public_url(string $path, array $params = []): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $url = $scheme . '://' . $host . ($dir !== '' ? $dir : '') . '/' . ltrim($path, '/');
    if ($params) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

function current_user_id(): int
{
    return (int) ($_SESSION['user']['id'] ?? 0);
}

function format_money($value): string
{
    $unit = 'ریال';
    if (class_exists('Setting')) {
        $configured = trim(Setting::get('currency_unit'));
        if ($configured !== '') {
            $unit = $configured;
        }
    }
    return number_format((float) $value) . ' ' . $unit;
}

function selected($actual, $expected): string
{
    return (string) $actual === (string) $expected ? 'selected' : '';
}

function checked($actual): string
{
    return (bool) $actual ? 'checked' : '';
}

function upload_ticket_image(string $field): ?array
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }

    $maxSize = 2 * 1024 * 1024;
    if ((int) ($_FILES[$field]['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('حجم تصویر تیکت نباید بیشتر از ۲ مگابایت باشد.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('فرمت تصویر تیکت مجاز نیست. فقط jpg، png یا webp قابل قبول است.');
    }

    $publicRoot = public_upload_root();
    $dir = $publicRoot . '/uploads/tickets/' . date('Y/m');
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Ticket upload directory could not be created.');
        }
    }

    if (!is_writable($dir)) {
        throw new RuntimeException('Ticket upload directory is not writable.');
    }

    $originalName = basename((string) ($_FILES[$field]['name'] ?? 'attachment'));
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        throw new RuntimeException('آپلود تصویر تیکت ناموفق بود.');
    }

    return [
        'path' => 'uploads/tickets/' . date('Y/m') . '/' . $filename,
        'name' => $originalName,
        'mime' => $mime,
        'size' => (int) ($_FILES[$field]['size'] ?? 0),
    ];
}

function activity_attachment_root(): string
{
    return dirname(__DIR__, 2) . '/storage/activity-attachments';
}

function upload_activity_attachment(string $field): ?array
{
    $error = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        throw new RuntimeException('آپلود فایل فعالیت ناموفق بود.');
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('حجم فایل فعالیت نباید بیشتر از ۵ مگابایت باشد.');
    }

    $originalName = basename((string) ($_FILES[$field]['name'] ?? 'attachment'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedMimes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];
    if (!isset($allowedMimes[$extension])) {
        throw new RuntimeException('فقط تصویر، PDF و فایل Word برای فعالیت مجاز است.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($_FILES[$field]['tmp_name']);
    if (!in_array($mime, $allowedMimes[$extension], true)) {
        throw new RuntimeException('نوع واقعی فایل فعالیت با پسوند آن سازگار نیست.');
    }
    if ($extension === 'docx' && $mime === 'application/zip') {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZIP برای بررسی امنیتی فایل Word روی سرور فعال نیست.');
        }
        $zip = new ZipArchive();
        $opened = $zip->open($_FILES[$field]['tmp_name']);
        $isDocx = $opened === true
            && $zip->locateName('[Content_Types].xml') !== false
            && $zip->locateName('word/document.xml') !== false;
        if ($opened === true) {
            $zip->close();
        }
        if (!$isDocx) {
            throw new RuntimeException('فایل انتخاب‌شده یک سند Word معتبر نیست.');
        }
    }

    $relativeDir = date('Y/m');
    $dir = activity_attachment_root() . '/' . $relativeDir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('پوشه نگهداری فایل فعالیت ساخته نشد.');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('پوشه نگهداری فایل فعالیت قابل نوشتن نیست.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('ذخیره فایل فعالیت ناموفق بود.');
    }

    return [
        'path' => $relativeDir . '/' . $filename,
        'name' => $originalName,
        'mime' => $mime,
        'size' => (int) ($_FILES[$field]['size'] ?? 0),
    ];
}

function delete_activity_attachment(?string $relativePath): void
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    if ($relativePath === '' || str_contains($relativePath, '..')) {
        return;
    }

    $root = realpath(activity_attachment_root());
    $target = realpath(activity_attachment_root() . '/' . ltrim($relativePath, '/'));
    if ($root && $target && str_starts_with($target, $root . DIRECTORY_SEPARATOR) && is_file($target)) {
        @unlink($target);
    }
}

function public_upload_root(): string
{
    $projectRoot = dirname(__DIR__, 2);
    return is_dir($projectRoot . '/public_html') ? $projectRoot . '/public_html' : $projectRoot . '/public';
}

function upload_profile_image(string $field, string $ownerType): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }

    $maxSourceSize = 2 * 1024 * 1024;
    if ((int) ($_FILES[$field]['size'] ?? 0) > $maxSourceSize) {
        throw new RuntimeException('حجم تصویر پروفایل نباید بیشتر از ۲ مگابایت باشد. تصویر پس از آپلود کوچک‌سازی می‌شود.');
    }

    if (!extension_loaded('gd')) {
        throw new RuntimeException('افزونه GD برای پردازش تصویر روی سرور فعال نیست.');
    }

    $allowed = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $tmp = $_FILES[$field]['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('فرمت تصویر پروفایل مجاز نیست. فقط jpg، png یا webp قابل قبول است.');
    }

    $image = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmp),
        'image/png' => imagecreatefrompng($tmp),
        'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmp) : false,
        default => false,
    };
    if (!$image) {
        throw new RuntimeException('امکان پردازش تصویر پروفایل وجود ندارد.');
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $side = min($width, $height);
    $srcX = (int) floor(($width - $side) / 2);
    $srcY = (int) floor(($height - $side) / 2);

    $targetSize = 320;
    $thumb = imagecreatetruecolor($targetSize, $targetSize);
    $white = imagecolorallocate($thumb, 255, 255, 255);
    imagefill($thumb, 0, 0, $white);
    imagecopyresampled($thumb, $image, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $side, $side);
    imagedestroy($image);

    $safeOwnerType = preg_replace('/[^a-z0-9_-]/i', '', $ownerType) ?: 'profiles';
    $relativeDir = 'uploads/profiles/' . $safeOwnerType . '/' . date('Y/m');
    $dir = public_upload_root() . '/' . $relativeDir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($thumb);
        throw new RuntimeException('امکان ساخت پوشه تصویر پروفایل وجود ندارد.');
    }
    if (!is_writable($dir)) {
        imagedestroy($thumb);
        throw new RuntimeException('پوشه تصویر پروفایل قابل نوشتن نیست.');
    }

    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $target = $dir . '/' . $filename;
    if (!imagejpeg($thumb, $target, 78)) {
        imagedestroy($thumb);
        throw new RuntimeException('ذخیره تصویر پروفایل ناموفق بود.');
    }
    imagedestroy($thumb);

    return $relativeDir . '/' . $filename;
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

function fa_datetime(?string $gregorianDateTime): string
{
    if (!$gregorianDateTime) {
        return '';
    }

    $date = fa_date($gregorianDateTime);
    $time = '';
    if (preg_match('/\b(\d{2}:\d{2})(?::\d{2})?\b/', $gregorianDateTime, $matches)) {
        $time = $matches[1];
    }

    return trim($date . ($time !== '' ? ' ساعت ' . $time : ''));
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
        'In Progress' => 'badge-primary',
        'Waiting Customer' => 'badge-warning',
        'Resolved' => 'badge-success',
        'Closed' => 'badge-muted',
        'Done' => 'badge-success',
        'Cancelled' => 'badge-danger',
        'Waiting' => 'badge-warning',
        'Active' => 'badge-success',
        'Renewal Due' => 'badge-warning',
        'Renewed' => 'badge-primary',
        'Expired' => 'badge-danger',
        'Contract Renewal' => 'badge-warning',
    ];

    return $map[$value] ?? 'badge-muted';
}

function option_pairs(string $key): array
{
    $raw = class_exists('Setting') ? Setting::get($key) : '';
    $pairs = [];
    foreach (preg_split('/\r\n|\r|\n/u', trim($raw)) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        [$value, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
        if ($value === '') {
            continue;
        }
        $pairs[$value] = $label !== '' ? $label : $value;
    }
    return $pairs;
}

function option_values(string $key): array
{
    return array_keys(option_pairs($key));
}

function option_label(string $key, string $value): string
{
    $pairs = option_pairs($key);
    return $pairs[$value] ?? fa_label($value);
}

function customer_type_options(): array
{
    return option_values('options_customer_types');
}

function sales_status_options(): array
{
    return option_values('options_sales_statuses');
}

function product_options(): array
{
    return option_values('options_products');
}

function deal_stage_options(): array
{
    return option_values('options_deal_stages');
}

function activity_type_options(): array
{
    return option_values('options_activity_types');
}

function activity_status_options(): array
{
    return option_values('options_activity_statuses');
}

function contract_status_options(): array
{
    return option_values('options_contract_statuses');
}

function fa_label(string $value): string
{
    foreach ([
        'options_customer_types',
        'options_sales_statuses',
        'options_products',
        'options_deal_stages',
        'options_activity_types',
        'options_activity_statuses',
        'options_contract_statuses',
        'options_ticket_statuses',
        'options_ticket_priorities',
        'options_ticket_categories',
    ] as $key) {
        $pairs = option_pairs($key);
        if (isset($pairs[$value])) {
            return $pairs[$value];
        }
    }

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
        'Contract Renewal' => 'تمدید قرارداد',
        'Support' => 'پشتیبانی',
        'Active' => 'فعال',
        'Renewal Due' => 'نیازمند تمدید',
        'Renewed' => 'تمدید شده',
        'Expired' => 'منقضی شده',
    ];

    return $labels[$value] ?? $value;
}
