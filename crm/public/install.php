<?php

declare(strict_types=1);

/*
 * Elm Simple CRM
 * Author: Mohammad Ghaheri Najafabadi
 * Email: mohammad.ghaheri@gmail.com
 */

session_start();

$rootPath = dirname(__DIR__);
$configPath = $rootPath . '/app/config/database.php';
$schemaPath = $rootPath . '/database/schema.sql';
$lockPath = $rootPath . '/database/install.lock';
$errors = [];
$success = false;

function install_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function install_value(string $key, string $default = ''): string
{
    return (string) ($_POST[$key] ?? $default);
}

function install_token(): string
{
    if (empty($_SESSION['install_csrf'])) {
        $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['install_csrf'];
}

function verify_install_token(): void
{
    if (!hash_equals($_SESSION['install_csrf'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('درخواست نصب معتبر نیست. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.');
    }
}

function validate_identifier(string $value, string $label): string
{
    $value = trim($value);
    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        throw new InvalidArgumentException($label . ' فقط می‌تواند شامل حروف انگلیسی، عدد و زیرخط باشد.');
    }

    return $value;
}

function create_database_config(array $data): string
{
    return "<?php\n\n"
        . "declare(strict_types=1);\n\n"
        . "return [\n"
        . "    'host' => " . var_export($data['host'], true) . ",\n"
        . "    'port' => " . (int) $data['port'] . ",\n"
        . "    'dbname' => " . var_export($data['dbname'], true) . ",\n"
        . "    'username' => " . var_export($data['username'], true) . ",\n"
        . "    'password' => " . var_export($data['password'], true) . ",\n"
        . "    'charset' => 'utf8mb4',\n"
        . "];\n";
}

function schema_for_database(string $schemaSql, string $databaseName): string
{
    $schemaSql = preg_replace('/CREATE DATABASE IF NOT EXISTS\s+`?simple_crm`?.*?;\s*/is', '', $schemaSql) ?? $schemaSql;
    $schemaSql = preg_replace('/USE\s+`?simple_crm`?\s*;\s*/i', '', $schemaSql) ?? $schemaSql;
    return "USE `$databaseName`;\n" . $schemaSql;
}

function table_exists(PDO $pdo, string $databaseName, string $tableName): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([$databaseName, $tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function normalize_database_charset(PDO $pdo, string $databaseName): void
{
    $pdo->exec("ALTER DATABASE `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'");
    $stmt->execute([$databaseName]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
        if (!is_string($tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            continue;
        }
        $pdo->exec("ALTER TABLE `$databaseName`.`$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}

function execute_sql_file(PDO $pdo, string $sql): void
{
    $statement = '';
    $lines = preg_split('/\R/', $sql) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }

        $statement .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            $pdo->exec($statement);
            $statement = '';
        }
    }

    if (trim($statement) !== '') {
        $pdo->exec($statement);
    }
}

function seed_initial_data(PDO $pdo, array $data): void
{
    $settings = [
        'app_title' => $data['app_title'],
        'app_subtitle' => $data['app_subtitle'],
        'primary_color' => $data['primary_color'],
        'sidebar_color' => $data['sidebar_color'],
        'app_icon' => '',
        'home_title' => $data['app_title'],
        'home_text' => $data['home_text'],
        'currency_unit' => 'ریال',
        'customer_code_mode' => 'manual',
        'customer_code_format' => 'CUS-{YYYY}-{SEQ4}',
        'options_customer_types' => "B2B Fleet|ناوگان سازمانی\nB2C Owner|مالک شخصی\nB2D Dealer|نمایندگی\nOEM|خودروساز\nStrategic Partner|شریک راهبردی\nOther|سایر",
        'options_sales_statuses' => "New|جدید\nContacted|تماس گرفته شده\nMeeting Scheduled|جلسه تنظیم شده\nProposal Sent|پیشنهاد ارسال شده\nNegotiation|مذاکره\nWon|برنده\nLost|از دست رفته\nInactive|غیرفعال",
        'options_products' => "FMS|FMS\nTBox|TBox\nConnected Vehicle Platform|Connected Vehicle Platform\nOwner App|Owner App\nAPI Integration|API Integration\nDashboard / BI|Dashboard / BI\nonCloud|onCloud\nonPremises|onPremises\nOther|سایر",
        'options_deal_stages' => "Lead|سرنخ\nQualified|تایید شده\nProposal|پیشنهاد\nNegotiation|مذاکره\nWon|برنده\nLost|از دست رفته",
        'options_activity_types' => "Call|تماس\nMeeting|جلسه\nWhatsApp / Message|پیام\nEmail|ایمیل\nProposal Sent|پیشنهاد ارسال شده\nDemo|دمو\nFollow-up|پیگیری\nContract|قرارداد\nSupport|پشتیبانی\nOther|سایر",
        'options_activity_statuses' => "Open|باز\nDone|انجام شده\nCancelled|لغو شده\nWaiting|در انتظار",
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
        'sms_admin_mobile' => $data['admin_mobile'],
        'sms_default_assigned_user_id' => '',
        'portal_public_url' => '',
        'email_enabled' => '0',
        'email_from_name' => 'Elm Simple CRM',
        'email_from_address' => '',
        'email_portal_credentials_enabled' => '1',
        'email_ticket_answered_enabled' => '1',
        'email_activity_reminder_enabled' => '1',
        'email_portal_credentials_subject' => 'اطلاعات ورود پرتال مشتری',
        'email_portal_credentials_template' => "سلام {contact_name}\nدسترسی شما به پرتال مشتری {app_title} فعال شد.\nنام کاربری: {email}\nرمز عبور: {password}\nورود: {portal_url}",
    ];

    $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    $stmt = $pdo->prepare('INSERT INTO users (name, email, mobile, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $stmt->execute([
        $data['admin_name'],
        $data['admin_email'],
        $data['admin_mobile'],
        password_hash($data['admin_password'], PASSWORD_DEFAULT),
        'admin',
    ]);

    if (!empty($data['with_samples'])) {
        $ownerId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO customers (customer_code, customer_name, customer_type, industry, city, lead_source, interested_product, vehicle_count, estimated_contract_value, sales_status, owner_user_id, last_followup_date, next_followup_date, notes) VALUES
            ('ELM-1001', 'شرکت نمونه ناوگان سبز', 'B2B Fleet', 'حمل و نقل', 'تهران', 'معرفی مستقیم', 'FMS', 80, 4200000000, 'New', ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'نمونه اولیه برای شروع کار با CRM.'),
            ('ELM-1002', 'نمایندگی نمونه خودرو', 'B2D Dealer', 'فروش و خدمات', 'اصفهان', 'وب‌سایت', 'Owner App', 35, 950000000, 'Contacted', ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'نمونه مشتری نمایندگی.')")->execute([$ownerId, $ownerId]);

        $pdo->prepare("INSERT INTO contacts (customer_id, contact_name, position, mobile, phone, email, portal_enabled, is_primary, notes) VALUES
            (1, 'علی رضایی', 'مدیر عملیات', '09120000001', '02100000001', 'ali.rezaei@example.com', 0, 1, 'مخاطب نمونه مشتری ناوگانی.'),
            (2, 'سارا کریمی', 'مدیر فروش', '09120000002', '03100000002', 'sara.karimi@example.com', 0, 1, 'مخاطب نمونه نمایندگی.')")->execute();

        $pdo->prepare("INSERT INTO deals (deal_name, customer_id, product, vehicle_count, estimated_amount, probability, weighted_amount, deal_stage, expected_close_date, owner_user_id, notes) VALUES
            ('استقرار اولیه FMS', 1, 'FMS', 80, 4200000000, 40, 1680000000, 'Qualified', DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, 'فرصت نمونه برای تست قیف فروش.'),
            ('راه‌اندازی اپلیکیشن مالک', 2, 'Owner App', 35, 950000000, 30, 285000000, 'Lead', DATE_ADD(CURDATE(), INTERVAL 25 DAY), ?, 'فرصت نمونه برای نمایندگی.')")->execute([$ownerId, $ownerId]);

        $pdo->prepare("INSERT INTO activities (customer_id, deal_id, activity_date, activity_type, summary, next_action, next_followup_date, owner_user_id, status, notes) VALUES
            (1, 1, CURDATE(), 'Call', 'تماس اولیه با مشتری نمونه انجام شد.', 'ارسال معرفی کوتاه سامانه', DATE_ADD(CURDATE(), INTERVAL 3 DAY), ?, 'Open', ''),
            (2, 2, CURDATE(), 'Follow-up', 'پیگیری نیازهای نمایندگی نمونه ثبت شد.', 'هماهنگی دمو', DATE_ADD(CURDATE(), INTERVAL 4 DAY), ?, 'Open', '')")->execute([$ownerId, $ownerId]);
    }
}

$isLocked = is_file($lockPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    try {
        verify_install_token();

        $data = [
            'host' => trim(install_value('host', '127.0.0.1')),
            'port' => (int) install_value('port', '3306'),
            'dbname' => validate_identifier(install_value('dbname', 'elm_simple_crm'), 'نام دیتابیس'),
            'username' => trim(install_value('username', 'root')),
            'password' => (string) install_value('password'),
            'admin_name' => trim(install_value('admin_name')),
            'admin_email' => trim(install_value('admin_email')),
            'admin_mobile' => trim(install_value('admin_mobile')),
            'admin_password' => (string) install_value('admin_password'),
            'app_title' => trim(install_value('app_title', 'Elm Simple CRM')),
            'app_subtitle' => trim(install_value('app_subtitle', 'مدیریت مشتریان، فرصت‌ها و پیگیری‌های فروش')),
            'home_text' => trim(install_value('home_text', 'یک سامانه سبک برای مدیریت مشتریان، مخاطبین، فرصت‌های فروش، پیگیری‌ها و تیکت‌های مشتریان.')),
            'primary_color' => trim(install_value('primary_color', '#155eef')),
            'sidebar_color' => trim(install_value('sidebar_color', '#111827')),
            'reset_database' => isset($_POST['reset_database']),
            'with_samples' => isset($_POST['with_samples']),
        ];

        foreach (['host' => 'هاست دیتابیس', 'username' => 'نام کاربری دیتابیس', 'admin_name' => 'نام مدیر', 'admin_email' => 'ایمیل مدیر', 'admin_password' => 'رمز عبور مدیر', 'app_title' => 'عنوان سامانه'] as $key => $label) {
            if ($data[$key] === '') {
                $errors[] = $label . ' الزامی است.';
            }
        }
        if ($data['port'] <= 0 || $data['port'] > 65535) {
            $errors[] = 'پورت دیتابیس معتبر نیست.';
        }
        if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ایمیل مدیر معتبر نیست.';
        }
        if (strlen($data['admin_password']) < 8) {
            $errors[] = 'رمز عبور مدیر باید حداقل ۸ کاراکتر باشد.';
        }
        if (!is_file($schemaPath)) {
            $errors[] = 'فایل schema.sql پیدا نشد.';
        }
        if (!is_writable(dirname($configPath)) || (is_file($configPath) && !is_writable($configPath))) {
            $errors[] = 'مسیر app/config/database.php قابل نوشتن نیست.';
        }
        if (!is_writable(dirname($lockPath))) {
            $errors[] = 'مسیر database برای ساخت فایل lock قابل نوشتن نیست.';
        }

        if (!$errors) {
            $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $data['host'], $data['port']);
            $pdo = new PDO($serverDsn, $data['username'], $data['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$data['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("ALTER DATABASE `{$data['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            if (!$data['reset_database'] && table_exists($pdo, $data['dbname'], 'users')) {
                throw new RuntimeException('این دیتابیس قبلا نصب شده یا جدول users دارد. برای نصب مجدد، گزینه پاکسازی دیتابیس را فعال کنید.');
            }

            $schemaSql = schema_for_database((string) file_get_contents($schemaPath), $data['dbname']);
            execute_sql_file($pdo, $schemaSql);
            $pdo->exec("USE `{$data['dbname']}`");
            normalize_database_charset($pdo, $data['dbname']);
            seed_initial_data($pdo, $data);
            file_put_contents($configPath, create_database_config($data));
            file_put_contents($lockPath, 'Installed at ' . date('c') . PHP_EOL);
            $success = true;
            $isLocked = true;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصب Elm Simple CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <main class="auth-shell install-shell">
        <section class="auth-visual">
            <div class="auth-leaf"></div>
            <div class="brand auth-brand">
                <div class="brand-mark">Elm</div>
                <div>
                    <strong>Elm Simple CRM</strong>
                    <span>راه‌اندازی اولیه سامانه</span>
                </div>
            </div>
            <h1>نصب سریع CRM</h1>
            <p>اطلاعات اتصال دیتابیس و مدیر سیستم را وارد کنید تا جداول، تنظیمات پایه و حساب مدیر ساخته شوند.</p>
        </section>
        <section class="auth-card install-card">
            <?php if ($success): ?>
                <div class="alert alert-info">نصب با موفقیت انجام شد. فایل lock ساخته شد و نصب‌کننده غیرفعال است.</div>
                <a class="btn btn-primary full" href="login.php">ورود به سامانه</a>
            <?php elseif ($isLocked): ?>
                <div class="alert alert-info">سامانه قبلا نصب شده است. برای اجرای دوباره نصب، فایل <code>crm/database/install.lock</code> را فقط در محیط امن حذف کنید.</div>
                <a class="btn btn-primary full" href="login.php">ورود به سامانه</a>
            <?php else: ?>
                <div class="auth-card-head">
                    <span>مرحله نصب</span>
                    <h2>اطلاعات اولیه</h2>
                </div>
                <?php if ($errors): ?><div class="alert alert-danger"><?= install_e(implode(' ', $errors)) ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= install_e(install_token()) ?>">
                    <h3>اتصال دیتابیس</h3>
                    <div class="grid grid-2">
                        <div><label class="required">هاست</label><input required name="host" value="<?= install_e(install_value('host', '127.0.0.1')) ?>"></div>
                        <div><label class="required">پورت</label><input required type="number" name="port" value="<?= install_e(install_value('port', '3306')) ?>"></div>
                        <div><label class="required">نام دیتابیس</label><input required name="dbname" value="<?= install_e(install_value('dbname', 'elm_simple_crm')) ?>"></div>
                        <div><label class="required">نام کاربری</label><input required name="username" value="<?= install_e(install_value('username', 'root')) ?>"></div>
                        <div><label>رمز دیتابیس</label><input type="password" name="password" value="<?= install_e(install_value('password')) ?>"></div>
                    </div>

                    <h3>مدیر سیستم</h3>
                    <div class="grid grid-2">
                        <div><label class="required">نام مدیر</label><input required name="admin_name" value="<?= install_e(install_value('admin_name', 'مدیر سیستم')) ?>"></div>
                        <div><label class="required">ایمیل مدیر</label><input required type="email" name="admin_email" value="<?= install_e(install_value('admin_email', 'admin@elm-crm.local')) ?>"></div>
                        <div><label>موبایل مدیر</label><input name="admin_mobile" value="<?= install_e(install_value('admin_mobile')) ?>"></div>
                        <div><label class="required">رمز عبور مدیر</label><input required type="password" name="admin_password"></div>
                    </div>

                    <h3>تنظیمات ظاهری</h3>
                    <div class="grid grid-2">
                        <div><label class="required">عنوان سامانه</label><input required name="app_title" value="<?= install_e(install_value('app_title', 'Elm Simple CRM')) ?>"></div>
                        <div><label>زیرعنوان</label><input name="app_subtitle" value="<?= install_e(install_value('app_subtitle', 'مدیریت مشتریان، فرصت‌ها و پیگیری‌های فروش')) ?>"></div>
                        <div><label>رنگ اصلی</label><input type="color" name="primary_color" value="<?= install_e(install_value('primary_color', '#155eef')) ?>"></div>
                        <div><label>رنگ منوی کناری</label><input type="color" name="sidebar_color" value="<?= install_e(install_value('sidebar_color', '#111827')) ?>"></div>
                    </div>
                    <div style="margin-top:14px"><label>متن صفحه خانه</label><textarea name="home_text"><?= install_e(install_value('home_text', 'یک سامانه سبک برای مدیریت مشتریان، مخاطبین، فرصت‌های فروش، پیگیری‌ها و تیکت‌های مشتریان.')) ?></textarea></div>

                    <label class="check-row"><input type="checkbox" name="with_samples" <?= isset($_POST['with_samples']) ? 'checked' : '' ?>> داده نمونه اولیه ایجاد شود</label>
                    <label class="check-row danger-check"><input type="checkbox" name="reset_database" <?= isset($_POST['reset_database']) ? 'checked' : '' ?>> اگر جدول‌های CRM وجود دارد، دیتابیس پاکسازی و دوباره ساخته شود</label>
                    <div class="form-actions"><button class="btn btn-primary full" type="submit">نصب Elm Simple CRM</button></div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
