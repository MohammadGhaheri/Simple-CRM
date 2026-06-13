<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/csrf.php';
require __DIR__ . '/../app/core/auth.php';
require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/models/Customer.php';
require __DIR__ . '/../app/models/Contact.php';
require __DIR__ . '/../app/models/Deal.php';
require __DIR__ . '/../app/models/Contract.php';
require __DIR__ . '/../app/models/Activity.php';
require __DIR__ . '/../app/models/Ticket.php';
require __DIR__ . '/../app/models/TicketMessage.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/models/UsageReport.php';
require __DIR__ . '/../app/services/SmsService.php';
require __DIR__ . '/../app/services/EmailService.php';
require __DIR__ . '/../app/services/BackupService.php';

if (!auth_check()) {
    redirect('home.php');
}

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';
$id = (int) ($_GET['id'] ?? 0);
$errors = [];
$notice = '';
$users = User::all();

function render(string $view, array $data = []): void
{
    global $page, $action;
    if (current_user_id() > 0) {
        $freshUser = User::find(current_user_id());
        if ($freshUser) {
            $_SESSION['user']['name'] = $freshUser['name'];
            $_SESSION['user']['email'] = $freshUser['email'];
            $_SESSION['user']['role'] = $freshUser['role'];
            $_SESSION['user']['avatar_path'] = $freshUser['avatar_path'] ?? '';
        }
    }
    $data['page'] = $page;
    $data['action'] = $action;
    extract($data);
    require __DIR__ . '/../app/views/layouts/header.php';
    require __DIR__ . '/../app/views/layouts/sidebar.php';
    require __DIR__ . '/../app/views/' . $view . '.php';
    require __DIR__ . '/../app/views/layouts/footer.php';
}

function delete_action(callable $delete, string $redirectTo): void
{
    verify_csrf();
    $delete();
    redirect($redirectTo);
}

function upload_image(string $field, string $folder): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('فرمت تصویر مجاز نیست. فقط jpg، png، webp یا gif قابل قبول است.');
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('حجم تصویر نباید بیشتر از ۲ مگابایت باشد.');
    }

    $dir = __DIR__ . '/uploads/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('آپلود تصویر ناموفق بود.');
    }

    return 'uploads/' . $folder . '/' . $filename;
}

try {
    UsageReport::logUsage('user', current_user_id(), $page, $action);

    if ($page === 'reports') {
        require_admin();
        render('reports/index', [
            'title' => 'گزارش استفاده',
            'summary' => UsageReport::summary(),
            'userLogins' => UsageReport::loginsByActor('user'),
            'contactLogins' => UsageReport::loginsByActor('contact'),
            'usageByArea' => UsageReport::usageByArea(),
        ]);
        exit;
    }

    if ($page === 'settings') {
        require_admin();
        $settings = Setting::all();
        if (is_post()) {
            verify_csrf();
            try {
                if (isset($_POST['restore_backup'])) {
                    BackupService::restoreUploaded($_FILES['backup_file'] ?? []);
                    redirect(url('settings'));
                }
                $uploadedIcon = upload_image('app_icon_file', 'settings');
                Setting::saveMany([
                    'app_title' => trim($_POST['app_title'] ?? ''),
                    'app_subtitle' => trim($_POST['app_subtitle'] ?? ''),
                    'primary_color' => trim($_POST['primary_color'] ?? '#155eef'),
                    'sidebar_color' => trim($_POST['sidebar_color'] ?? '#111827'),
                    'app_icon' => $uploadedIcon ?: ($settings['app_icon'] ?? ''),
                    'home_title' => trim($_POST['home_title'] ?? ''),
                    'home_text' => trim($_POST['home_text'] ?? ''),
                    'show_about_page' => isset($_POST['show_about_page']) ? '1' : '0',
                    'currency_unit' => trim($_POST['currency_unit'] ?? 'ریال'),
                    'customer_code_mode' => ($_POST['customer_code_mode'] ?? 'manual') === 'auto' ? 'auto' : 'manual',
                    'customer_code_format' => trim($_POST['customer_code_format'] ?? 'CUS-{YYYY}-{SEQ4}'),
                    'contract_renewal_reminder_days' => max(0, (int) ($_POST['contract_renewal_reminder_days'] ?? 30)),
                    'options_customer_types' => trim($_POST['options_customer_types'] ?? ''),
                    'options_sales_statuses' => trim($_POST['options_sales_statuses'] ?? ''),
                    'options_products' => trim($_POST['options_products'] ?? ''),
                    'options_deal_stages' => trim($_POST['options_deal_stages'] ?? ''),
                    'options_activity_types' => trim($_POST['options_activity_types'] ?? ''),
                    'options_activity_statuses' => trim($_POST['options_activity_statuses'] ?? ''),
                    'options_contract_statuses' => trim($_POST['options_contract_statuses'] ?? ''),
                    'options_ticket_statuses' => trim($_POST['options_ticket_statuses'] ?? ''),
                    'options_ticket_priorities' => trim($_POST['options_ticket_priorities'] ?? ''),
                    'options_ticket_categories' => trim($_POST['options_ticket_categories'] ?? ''),
                    'sms_enabled' => isset($_POST['sms_enabled']) ? '1' : '0',
                    'sms_ticket_created_enabled' => isset($_POST['sms_ticket_created_enabled']) ? '1' : '0',
                    'sms_ticket_answered_enabled' => isset($_POST['sms_ticket_answered_enabled']) ? '1' : '0',
                    'sms_portal_credentials_enabled' => isset($_POST['sms_portal_credentials_enabled']) ? '1' : '0',
                    'sms_portal_credentials_template' => trim($_POST['sms_portal_credentials_template'] ?? ''),
                    'sms_daily_summary_enabled' => isset($_POST['sms_daily_summary_enabled']) ? '1' : '0',
                    'sms_api_key' => trim($_POST['sms_api_key'] ?? ''),
                    'sms_line_number' => trim($_POST['sms_line_number'] ?? ''),
                    'sms_admin_mobile' => trim($_POST['sms_admin_mobile'] ?? ''),
                    'sms_default_assigned_user_id' => trim($_POST['sms_default_assigned_user_id'] ?? ''),
                    'portal_public_url' => trim($_POST['portal_public_url'] ?? ''),
                    'email_enabled' => isset($_POST['email_enabled']) ? '1' : '0',
                    'email_transport' => ($_POST['email_transport'] ?? 'mail') === 'smtp' ? 'smtp' : 'mail',
                    'email_from_name' => trim($_POST['email_from_name'] ?? ''),
                    'email_from_address' => trim($_POST['email_from_address'] ?? ''),
                    'email_smtp_host' => trim($_POST['email_smtp_host'] ?? ''),
                    'email_smtp_port' => trim($_POST['email_smtp_port'] ?? '587'),
                    'email_smtp_username' => trim($_POST['email_smtp_username'] ?? ''),
                    'email_smtp_password' => trim($_POST['email_smtp_password'] ?? ''),
                    'email_smtp_encryption' => in_array(($_POST['email_smtp_encryption'] ?? 'tls'), ['none', 'ssl', 'tls'], true) ? $_POST['email_smtp_encryption'] : 'tls',
                    'email_test_recipient' => trim($_POST['email_test_recipient'] ?? ''),
                    'email_portal_credentials_enabled' => isset($_POST['email_portal_credentials_enabled']) ? '1' : '0',
                    'email_ticket_answered_enabled' => isset($_POST['email_ticket_answered_enabled']) ? '1' : '0',
                    'email_activity_reminder_enabled' => isset($_POST['email_activity_reminder_enabled']) ? '1' : '0',
                    'email_portal_credentials_subject' => trim($_POST['email_portal_credentials_subject'] ?? ''),
                    'email_portal_credentials_template' => trim($_POST['email_portal_credentials_template'] ?? ''),
                ]);
                if (isset($_POST['send_test_email'])) {
                    $recipient = trim($_POST['email_test_recipient'] ?? '');
                    if ($recipient === '') {
                        throw new RuntimeException('برای ارسال ایمیل تست، گیرنده تست را وارد کنید.');
                    }
                    $sent = EmailService::send($recipient, 'تست ارسال ایمیل Elm Simple CRM', "سلام\nاین یک ایمیل تست از Elm Simple CRM است.");
                    if (!$sent) {
                        throw new RuntimeException('ارسال ایمیل تست ناموفق بود. لاگ ایمیل را بررسی کنید.');
                    }
                    $notice = 'ایمیل تست با موفقیت ارسال شد.';
                    $settings = Setting::all();
                } else {
                    redirect(url('settings'));
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
                $settings = array_merge($settings, $_POST);
            }
        }
        render('settings/index', ['title' => 'تنظیمات سامانه', 'settings' => $settings, 'users' => $users, 'errors' => $errors, 'notice' => $notice]);
        exit;
    }

    if ($page === 'help') {
        render('help/index', ['title' => 'راهنمای سیستم']);
        exit;
    }

    if ($page === 'backup') {
        require_admin();
        if ($action === 'download') {
            BackupService::download();
        }
        redirect(url('settings'));
    }

    if ($page === 'users') {
        require_admin();

        if ($action === 'create') {
            $user = ['role' => 'sales', 'is_active' => 1];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['name' => 'نام', 'email' => 'ایمیل', 'password' => 'رمز عبور']);
                if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'ایمیل معتبر نیست.';
                }
                if (strlen((string) ($_POST['password'] ?? '')) < 8) {
                    $errors[] = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
                }
                if (User::emailExists(trim($_POST['email'] ?? ''))) {
                    $errors[] = 'این ایمیل قبلا ثبت شده است.';
                }
                if (!$errors) {
                    try {
                        $_POST['avatar_path'] = upload_image('avatar_file', 'avatars') ?: '';
                    } catch (RuntimeException $e) {
                        $errors[] = $e->getMessage();
                    }
                }
                if (!$errors) {
                    User::create($_POST);
                    redirect(url('users'));
                }
                $user = $_POST;
            }
            render('users/create', ['title' => 'کاربر جدید', 'user' => $user, 'errors' => $errors]);
            exit;
        }

        if ($action === 'edit') {
            $user = User::find($id);
            if (!$user) {
                redirect(url('users'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['name' => 'نام', 'email' => 'ایمیل']);
                if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'ایمیل معتبر نیست.';
                }
                if (trim((string) ($_POST['password'] ?? '')) !== '' && strlen((string) $_POST['password']) < 8) {
                    $errors[] = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
                }
                if (User::emailExists(trim($_POST['email'] ?? ''), $id)) {
                    $errors[] = 'این ایمیل قبلا ثبت شده است.';
                }
                if ($id === current_user_id() && ($_POST['role'] ?? '') !== 'admin') {
                    $errors[] = 'مدیر سیستم نمی‌تواند نقش خودش را از مدیر سیستم خارج کند.';
                }
                if ($id === current_user_id() && !isset($_POST['is_active'])) {
                    $errors[] = 'مدیر سیستم نمی‌تواند حساب خودش را غیرفعال کند.';
                }
                if (!$errors) {
                    try {
                        $_POST['avatar_path'] = upload_image('avatar_file', 'avatars') ?: ($user['avatar_path'] ?? '');
                    } catch (RuntimeException $e) {
                        $errors[] = $e->getMessage();
                    }
                }
                if (!$errors) {
                    User::update($id, $_POST);
                    redirect(url('users'));
                }
                $user = array_merge($user, $_POST);
            }
            render('users/edit', ['title' => 'ویرایش کاربر', 'user' => $user, 'errors' => $errors]);
            exit;
        }

        if ($action === 'transfer_tasks') {
            if (is_post()) {
                verify_csrf();
                $fromUserId = (int) ($_POST['from_user_id'] ?? 0);
                $toUserId = (int) ($_POST['to_user_id'] ?? 0);
                if ($fromUserId <= 0 || $toUserId <= 0 || $fromUserId === $toUserId) {
                    $errors[] = 'مبدا و مقصد انتقال وظایف را درست انتخاب کنید.';
                }
                if (!$errors) {
                    $activityCount = Activity::transferOwner($fromUserId, $toUserId);
                    $contractCount = Contract::transferOwner($fromUserId, $toUserId);
                    redirect(url('users', ['transferred' => $activityCount + $contractCount]));
                }
            }
        }

        render('users/index', ['title' => 'مدیریت کاربران', 'usersList' => User::all(), 'errors' => $errors, 'transferred' => (int) ($_GET['transferred'] ?? 0)]);
        exit;
    }

    if ($page === 'my_tasks') {
        if ($action === 'done' && is_post()) {
            verify_csrf();
            Activity::markDoneForOwner($id, current_user_id());
            redirect(url('my_tasks'));
        }

        render('my_tasks/index', [
            'title' => 'برنامه کاری من',
            'counts' => Activity::agendaCountsForOwner(current_user_id()),
            'overdueActivities' => Activity::agendaForOwner(current_user_id(), 'overdue'),
            'todayActivities' => Activity::agendaForOwner(current_user_id(), 'today'),
            'upcomingActivities' => Activity::agendaForOwner(current_user_id(), 'upcoming'),
        ]);
        exit;
    }

    if ($page === 'dashboard') {
        $stats = [
            'customers' => (int) db()->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'open_deals' => (int) db()->query("SELECT COUNT(*) FROM deals WHERE deal_stage NOT IN ('Won','Lost')")->fetchColumn(),
            'pipeline' => (float) db()->query("SELECT COALESCE(SUM(estimated_amount),0) FROM deals WHERE deal_stage NOT IN ('Won','Lost')")->fetchColumn(),
            'weighted' => (float) db()->query("SELECT COALESCE(SUM(weighted_amount),0) FROM deals WHERE deal_stage NOT IN ('Won','Lost')")->fetchColumn(),
            'won_value' => (float) db()->query("SELECT COALESCE(SUM(estimated_amount),0) FROM deals WHERE deal_stage = 'Won'")->fetchColumn(),
            'lost_count' => (int) db()->query("SELECT COUNT(*) FROM deals WHERE deal_stage = 'Lost'")->fetchColumn(),
            'overdue' => Activity::overdueCount(),
            'renewal_due' => (int) db()->query("SELECT COUNT(*) FROM contracts WHERE renewal_reminder_date <= CURDATE() AND status IN ('Active','Renewal Due')")->fetchColumn(),
        ];
        render('dashboard/index', [
            'title' => 'داشبورد فروش',
            'stats' => $stats,
            'dealsByStage' => Deal::statsByStage(),
            'customersByType' => Customer::statsByType(),
            'recentActivities' => Activity::recent(),
            'upcomingActivities' => Activity::upcoming(),
            'renewalContracts' => Contract::renewalDue(),
        ]);
        exit;
    }

    if ($page === 'customers') {
        if ($action === 'delete' && is_post()) {
            delete_action(fn() => Customer::delete($id), url('customers'));
        }
        if ($action === 'create') {
            $customer = [];
            if (is_post()) {
                verify_csrf();
                $required = ['customer_name' => 'نام مشتری'];
                if (Setting::get('customer_code_mode') !== 'auto') {
                    $required['customer_code'] = 'کد مشتری';
                }
                $errors = required_fields($_POST, $required);
                if (!$errors) {
                    $newId = Customer::create($_POST);
                    redirect(url('customers', ['action' => 'show', 'id' => $newId]));
                }
                $customer = $_POST;
            }
            render('customers/create', compact('customer', 'users', 'errors') + ['title' => 'مشتری جدید']);
            exit;
        }
        if ($action === 'edit') {
            $customer = Customer::find($id);
            if (!$customer) {
                redirect(url('customers'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_code' => 'کد مشتری', 'customer_name' => 'نام مشتری']);
                if (!$errors) {
                    Customer::update($id, $_POST);
                    redirect(url('customers', ['action' => 'show', 'id' => $id]));
                }
                $customer = array_merge($customer, $_POST);
            }
            render('customers/edit', compact('customer', 'users', 'errors') + ['title' => 'ویرایش مشتری']);
            exit;
        }
        if ($action === 'show') {
            $customer = Customer::find($id);
            if (!$customer) {
                redirect(url('customers'));
            }
            render('customers/show', [
                'title' => $customer['customer_name'],
                'customer' => $customer,
                'contacts' => Contact::byCustomer($id),
                'deals' => Deal::byCustomer($id),
                'contracts' => Contract::byCustomer($id),
                'activities' => Activity::byCustomer($id),
            ]);
            exit;
        }
        if ($action === 'invite_contacts') {
            $customer = Customer::find($id);
            if (!$customer) {
                redirect(url('customers'));
            }
            if (is_post()) {
                verify_csrf();
                Customer::regenerateInviteToken($id);
                redirect(url('customers', ['action' => 'invite_contacts', 'id' => $id]));
            }
            $token = Customer::ensureInviteToken($id);
            $settings = Setting::all();
            $inviteUrl = absolute_public_url('portal.php', ['action' => 'contact_invite', 'token' => $token]);
            $inviteText = "سلام، شما به عنوان همکار " . $customer['customer_name'] . " برای ایجاد حساب کاربری در سامانه " . ($settings['app_title'] ?? 'Elm Simple CRM') . " دعوت شده‌اید.\n"
                . "لینک اختصاصی ثبت‌نام:\n" . $inviteUrl;
            render('customers/invite', [
                'title' => 'دعوتنامه مخاطبین',
                'customer' => $customer,
                'inviteUrl' => $inviteUrl,
                'inviteText' => $inviteText,
            ]);
            exit;
        }
        render('customers/index', [
            'title' => 'مشتریان',
            'customers' => Customer::search($_GET),
            'users' => $users,
            'filters' => $_GET,
        ]);
        exit;
    }

    if ($page === 'contacts') {
        if ($action === 'delete' && is_post()) {
            $contact = Contact::find($id);
            delete_action(fn() => Contact::delete($id), url('customers', ['action' => 'show', 'id' => (int) ($contact['customer_id'] ?? 0)]));
        }
        if ($action === 'create') {
            $contact = ['customer_id' => (int) ($_GET['customer_id'] ?? 0)];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_id' => 'مشتری', 'contact_name' => 'نام مخاطب']);
                if (!empty($_POST['portal_enabled']) && trim((string) ($_POST['portal_password'] ?? '')) === '') {
                    $errors[] = 'برای فعال کردن پرتال، رمز عبور مخاطب الزامی است.';
                }
                if (!empty($_POST['send_portal_sms'])) {
                    if (empty($_POST['portal_enabled'])) {
                        $errors[] = 'برای ارسال پیامک اطلاعات ورود، دسترسی پرتال باید فعال باشد.';
                    }
                    if (trim((string) ($_POST['mobile'] ?? '')) === '') {
                        $errors[] = 'برای ارسال پیامک اطلاعات ورود، موبایل مخاطب الزامی است.';
                    }
                    if (trim((string) ($_POST['email'] ?? '')) === '') {
                        $errors[] = 'برای ارسال پیامک اطلاعات ورود، ایمیل مخاطب الزامی است.';
                    }
                    if (trim((string) ($_POST['portal_password'] ?? '')) === '') {
                        $errors[] = 'برای ارسال پیامک اطلاعات ورود، رمز عبور جدید را وارد کنید.';
                    }
                }
                if (!empty($_POST['send_portal_email'])) {
                    if (empty($_POST['portal_enabled'])) {
                        $errors[] = 'برای ارسال ایمیل اطلاعات ورود، دسترسی پرتال باید فعال باشد.';
                    }
                    if (trim((string) ($_POST['email'] ?? '')) === '') {
                        $errors[] = 'برای ارسال ایمیل اطلاعات ورود، ایمیل مخاطب الزامی است.';
                    }
                    if (trim((string) ($_POST['portal_password'] ?? '')) === '') {
                        $errors[] = 'برای ارسال ایمیل اطلاعات ورود، رمز عبور جدید را وارد کنید.';
                    }
                }
                if (!$errors) {
                    $newId = Contact::create($_POST);
                    if (!empty($_POST['send_portal_sms'])) {
                        $newContact = Contact::find($newId);
                        if ($newContact) {
                            SmsService::sendPortalCredentials($newContact, (string) $_POST['portal_password']);
                        }
                    }
                    if (!empty($_POST['send_portal_email'])) {
                        $newContact = $newContact ?? Contact::find($newId);
                        if ($newContact) {
                            EmailService::sendPortalCredentials($newContact, (string) $_POST['portal_password']);
                        }
                    }
                    redirect(url('customers', ['action' => 'show', 'id' => (int) $_POST['customer_id']]));
                }
                $contact = $_POST;
            }
            render('contacts/create', ['title' => 'مخاطب جدید', 'contact' => $contact, 'customers' => Customer::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'edit') {
            $contact = Contact::find($id);
            if (!$contact) {
                redirect(url('customers'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_id' => 'مشتری', 'contact_name' => 'نام مخاطب']);
                if (!empty($_POST['portal_enabled']) && empty($contact['password_hash']) && trim((string) ($_POST['portal_password'] ?? '')) === '') {
                    $errors[] = 'برای فعال کردن پرتال، رمز عبور مخاطب الزامی است.';
                }
                if (!empty($_POST['send_portal_sms'])) {
                    if (empty($_POST['portal_enabled'])) {
                        $errors[] = 'برای ارسال پیامک اطلاعات ورود، دسترسی پرتال باید فعال باشد.';
                    }
                    if (trim((string) ($_POST['mobile'] ?? '')) === '') {
                        $errors[] = 'برای ارسال پیامک اطلاعات ورود، موبایل مخاطب الزامی است.';
                    }
                    if (trim((string) ($_POST['email'] ?? '')) === '') {
                        $errors[] = 'برای ارسال پیامک اطلاعات ورود، ایمیل مخاطب الزامی است.';
                    }
                    if (trim((string) ($_POST['portal_password'] ?? '')) === '') {
                        $errors[] = 'برای ارسال پیامک اطلاعات ورود، رمز عبور جدید را وارد کنید.';
                    }
                }
                if (!empty($_POST['send_portal_email'])) {
                    if (empty($_POST['portal_enabled'])) {
                        $errors[] = 'برای ارسال ایمیل اطلاعات ورود، دسترسی پرتال باید فعال باشد.';
                    }
                    if (trim((string) ($_POST['email'] ?? '')) === '') {
                        $errors[] = 'برای ارسال ایمیل اطلاعات ورود، ایمیل مخاطب الزامی است.';
                    }
                    if (trim((string) ($_POST['portal_password'] ?? '')) === '') {
                        $errors[] = 'برای ارسال ایمیل اطلاعات ورود، رمز عبور جدید را وارد کنید.';
                    }
                }
                if (!$errors) {
                    Contact::update($id, $_POST);
                    if (!empty($_POST['send_portal_sms'])) {
                        $updatedContact = Contact::find($id);
                        if ($updatedContact) {
                            SmsService::sendPortalCredentials($updatedContact, (string) $_POST['portal_password']);
                        }
                    }
                    if (!empty($_POST['send_portal_email'])) {
                        $updatedContact = $updatedContact ?? Contact::find($id);
                        if ($updatedContact) {
                            EmailService::sendPortalCredentials($updatedContact, (string) $_POST['portal_password']);
                        }
                    }
                    redirect(url('customers', ['action' => 'show', 'id' => (int) $_POST['customer_id']]));
                }
                $contact = array_merge($contact, $_POST);
            }
            render('contacts/edit', ['title' => 'ویرایش مخاطب', 'contact' => $contact, 'customers' => Customer::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        render('contacts/index', [
            'title' => 'مخاطبین',
            'contacts' => Contact::search($_GET),
            'customers' => Customer::search(),
            'filters' => $_GET,
        ]);
        exit;
    }

    if ($page === 'tickets') {
        if ($action === 'edit') {
            $ticket = Ticket::find($id);
            if (!$ticket) {
                redirect(url('tickets'));
            }
            if (is_post()) {
                verify_csrf();
                try {
                    $ticketAction = $_POST['ticket_action'] ?? 'meta';
                    if ($ticketAction === 'reply') {
                        if (Ticket::isClosed($ticket)) {
                            $errors[] = 'این تیکت بسته شده و امکان ارسال پیام جدید ندارد.';
                        }
                        $message = trim((string) ($_POST['message'] ?? ''));
                        $attachment = upload_ticket_image('attachment');
                        if (!$message && !$attachment) {
                            $errors[] = 'برای ارسال پیام، متن یا تصویر را وارد کنید.';
                        }
                        if (!$errors) {
                            TicketMessage::createFromUser($id, current_user_id(), $message, $attachment);
                            $after = Ticket::find($id);
                            if ($after) {
                                SmsService::notifyTicketAnswered($after);
                                EmailService::notifyTicketAnswered($after);
                            }
                            redirect(url('tickets', ['action' => 'edit', 'id' => $id]));
                        }
                    } elseif ($ticketAction === 'close') {
                        Ticket::close($id);
                        redirect(url('tickets', ['action' => 'edit', 'id' => $id]));
                    } else {
                        Ticket::updateMeta($id, $_POST);
                        redirect(url('tickets', ['action' => 'edit', 'id' => $id]));
                    }
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
                $ticket = Ticket::find($id) ?: $ticket;
            }
            $messages = TicketMessage::byTicket($id);
            TicketMessage::markReadForUser($id);
            render('tickets/edit', ['title' => 'جزئیات تیکت', 'ticket' => $ticket, 'messages' => $messages, 'users' => $users, 'errors' => $errors]);
            exit;
        }
        render('tickets/index', [
            'title' => 'تیکت‌ها',
            'tickets' => Ticket::search($_GET),
            'filters' => $_GET,
        ]);
        exit;
    }

    if ($page === 'deals') {
        if ($action === 'delete' && is_post()) {
            delete_action(fn() => Deal::delete($id), url('deals'));
        }
        if ($action === 'create') {
            $deal = ['customer_id' => (int) ($_GET['customer_id'] ?? 0), 'probability' => 20];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['deal_name' => 'نام فرصت', 'customer_id' => 'مشتری']);
                if (!$errors) {
                    $newId = Deal::create($_POST);
                    redirect(url('deals', ['action' => 'show', 'id' => $newId]));
                }
                $deal = $_POST;
            }
            render('deals/create', ['title' => 'فرصت جدید', 'deal' => $deal, 'customers' => Customer::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'edit') {
            $deal = Deal::find($id);
            if (!$deal) {
                redirect(url('deals'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['deal_name' => 'نام فرصت', 'customer_id' => 'مشتری']);
                if (!$errors) {
                    Deal::update($id, $_POST);
                    redirect(url('deals', ['action' => 'show', 'id' => $id]));
                }
                $deal = array_merge($deal, $_POST);
            }
            render('deals/edit', ['title' => 'ویرایش فرصت', 'deal' => $deal, 'customers' => Customer::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'show') {
            $deal = Deal::find($id);
            if (!$deal) {
                redirect(url('deals'));
            }
            render('deals/show', ['title' => $deal['deal_name'], 'deal' => $deal, 'contracts' => Contract::byDeal($id), 'activities' => Activity::byDeal($id)]);
            exit;
        }
        render('deals/index', ['title' => 'فرصت‌های فروش', 'deals' => Deal::search($_GET), 'users' => $users, 'filters' => $_GET]);
        exit;
    }

    if ($page === 'contracts') {
        if ($action === 'delete' && is_post()) {
            delete_action(fn() => Contract::delete($id), url('contracts'));
        }
        if ($action === 'create') {
            $dealId = (int) ($_GET['deal_id'] ?? 0);
            $deal = $dealId > 0 ? Deal::find($dealId) : null;
            $contract = [
                'customer_id' => (int) ($deal['customer_id'] ?? ($_GET['customer_id'] ?? 0)),
                'deal_id' => $dealId,
                'product' => $deal['product'] ?? 'Other',
                'vehicle_count' => (int) ($deal['vehicle_count'] ?? 0),
                'contract_amount' => (float) ($deal['estimated_amount'] ?? 0),
                'owner_user_id' => (int) ($deal['owner_user_id'] ?? current_user_id()),
                'status' => 'Active',
            ];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['contract_number' => 'شماره قرارداد', 'contract_title' => 'عنوان قرارداد', 'customer_id' => 'مشتری', 'end_date' => 'تاریخ پایان']);
                if (!$errors) {
                    $newId = Contract::create($_POST);
                    redirect(url('contracts', ['action' => 'show', 'id' => $newId]));
                }
                $contract = $_POST;
            }
            render('contracts/create', ['title' => 'قرارداد جدید', 'contract' => $contract, 'customers' => Customer::search(), 'deals' => Deal::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'edit') {
            $contract = Contract::find($id);
            if (!$contract) {
                redirect(url('contracts'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['contract_number' => 'شماره قرارداد', 'contract_title' => 'عنوان قرارداد', 'customer_id' => 'مشتری', 'end_date' => 'تاریخ پایان']);
                if (!$errors) {
                    Contract::update($id, $_POST);
                    redirect(url('contracts', ['action' => 'show', 'id' => $id]));
                }
                $contract = array_merge($contract, $_POST);
            }
            render('contracts/edit', ['title' => 'ویرایش قرارداد', 'contract' => $contract, 'customers' => Customer::search(), 'deals' => Deal::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'show') {
            $contract = Contract::find($id);
            if (!$contract) {
                redirect(url('contracts'));
            }
            render('contracts/show', ['title' => $contract['contract_title'], 'contract' => $contract, 'activities' => Activity::byContract($id)]);
            exit;
        }
        render('contracts/index', ['title' => 'قراردادها', 'contracts' => Contract::search($_GET), 'users' => $users, 'filters' => $_GET]);
        exit;
    }

    if ($page === 'activities') {
        if ($action === 'delete' && is_post()) {
            delete_action(fn() => Activity::delete($id), url('activities'));
        }
        if ($action === 'create') {
            $completeId = (int) ($_GET['complete_id'] ?? ($_POST['complete_id'] ?? 0));
            $sourceActivity = $completeId > 0 ? Activity::findForOwner($completeId, current_user_id()) : null;
            $activity = [
                'customer_id' => (int) ($sourceActivity['customer_id'] ?? ($_GET['customer_id'] ?? 0)),
                'deal_id' => (int) ($sourceActivity['deal_id'] ?? ($_GET['deal_id'] ?? 0)),
                'contract_id' => (int) ($sourceActivity['contract_id'] ?? ($_GET['contract_id'] ?? 0)),
                'activity_date' => fa_date(date('Y-m-d')),
                'status' => 'Open',
                'activity_type' => $sourceActivity['activity_type'] ?? 'Follow-up',
                'complete_id' => $sourceActivity ? $completeId : 0,
            ];
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_id' => 'مشتری', 'activity_date' => 'تاریخ فعالیت', 'summary' => 'خلاصه']);
                if (!$errors) {
                    $createdActivityId = Activity::create($_POST);
                    if (!empty($_POST['send_activity_email'])) {
                        $createdActivity = Activity::find($createdActivityId);
                        if ($createdActivity) {
                            EmailService::sendActivityReminder($createdActivity);
                        }
                    }
                    if ($sourceActivity) {
                        Activity::markDoneForOwner($completeId, current_user_id());
                    }
                    if ($sourceActivity) {
                        redirect(url('my_tasks'));
                    }
                    if (!empty($_POST['contract_id'])) {
                        $target = url('contracts', ['action' => 'show', 'id' => (int) $_POST['contract_id']]);
                    } elseif (!empty($_POST['deal_id'])) {
                        $target = url('deals', ['action' => 'show', 'id' => (int) $_POST['deal_id']]);
                    } else {
                        $target = url('customers', ['action' => 'show', 'id' => (int) $_POST['customer_id']]);
                    }
                    redirect($target);
                }
                $activity = $_POST;
                $activity['complete_id'] = $sourceActivity ? $completeId : 0;
            }
            render('activities/create', ['title' => 'فعالیت جدید', 'activity' => $activity, 'customers' => Customer::search(), 'deals' => Deal::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        if ($action === 'edit') {
            $activity = Activity::find($id);
            if (!$activity) {
                redirect(url('activities'));
            }
            if (is_post()) {
                verify_csrf();
                $errors = required_fields($_POST, ['customer_id' => 'مشتری', 'activity_date' => 'تاریخ فعالیت', 'summary' => 'خلاصه']);
                if (!$errors) {
                    Activity::update($id, $_POST);
                    redirect(url('activities'));
                }
                $activity = array_merge($activity, $_POST);
            }
            render('activities/edit', ['title' => 'ویرایش فعالیت', 'activity' => $activity, 'customers' => Customer::search(), 'deals' => Deal::search(), 'users' => $users, 'errors' => $errors]);
            exit;
        }
        render('activities/index', ['title' => 'فعالیت‌ها', 'activities' => Activity::search($_GET), 'users' => $users, 'filters' => $_GET]);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo '<div dir="rtl" style="font-family:tahoma;padding:30px">خطای پایگاه داده: ' . e($e->getMessage()) . '</div>';
    exit;
}

redirect('index.php');
