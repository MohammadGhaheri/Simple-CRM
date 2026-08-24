<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/csrf.php';
require __DIR__ . '/../app/models/Customer.php';
require __DIR__ . '/../app/models/Contact.php';
require __DIR__ . '/../app/models/Ticket.php';
require __DIR__ . '/../app/models/TicketMessage.php';
require __DIR__ . '/../app/models/Announcement.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/models/UsageReport.php';
require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/services/SmsService.php';

$action = $_GET['action'] ?? 'dashboard';
$errors = [];

function portal_contact(): ?array
{
    if (empty($_SESSION['portal_contact']['id'])) {
        return null;
    }

    return Contact::find((int) $_SESSION['portal_contact']['id']);
}

function require_portal_auth(): array
{
    $contact = portal_contact();
    if (!$contact || (int) $contact['portal_enabled'] !== 1) {
        redirect('portal.php?action=login');
    }

    return $contact;
}

function portal_layout(string $title, callable $content): void
{
    $contact = portal_contact();
    $appSettings = Setting::all();
    $announcementUnread = $contact ? Announcement::unreadCountForContact($contact) : 0;
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> - پرتال مشتری <?= e($appSettings['app_title']) ?></title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>:root { --primary: <?= e($appSettings['primary_color']) ?>; --primary-dark: <?= e($appSettings['primary_color']) ?>; --sidebar: <?= e($appSettings['sidebar_color']) ?>; }</style>
    </head>
    <body>
    <div class="portal-shell">
        <header class="portal-topbar">
            <div class="brand">
                <?php if (!empty($appSettings['app_icon'])): ?><img class="brand-icon" src="<?= e($appSettings['app_icon']) ?>" alt=""><?php else: ?><div class="brand-mark">Elm</div><?php endif; ?>
                <div><strong>پرتال مشتری</strong><span><?= e($appSettings['app_title']) ?></span></div>
            </div>
            <?php if ($contact): ?>
                <div class="user-menu">
                    <?php if (!empty($contact['avatar_path'])): ?>
                        <img class="user-avatar" src="<?= e($contact['avatar_path']) ?>" alt="">
                    <?php else: ?>
                        <span class="user-avatar user-avatar-initial">م</span>
                    <?php endif; ?>
                    <span><?= e($contact['contact_name']) ?> - <?= e($contact['customer_name']) ?></span>
                    <a class="btn btn-light portal-nav-link" href="portal.php?action=announcements">اطلاعیه‌ها<?php if ($announcementUnread > 0): ?> <span class="nav-badge"><?= e((string) $announcementUnread) ?></span><?php endif; ?></a>
                    <a class="btn btn-light" href="portal.php?action=profile">پروفایل من</a>
                    <a class="btn btn-light" href="portal.php?action=logout">خروج</a>
                </div>
            <?php endif; ?>
        </header>
        <main class="content">
            <?php $content(); ?>
        </main>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}

if ($action === 'logout') {
    unset($_SESSION['portal_contact']);
    redirect('portal.php?action=login');
}

if ($action === 'contact_invite') {
    $appSettings = Setting::all();
    $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $customer = Customer::findByInviteToken($token);
    $inviteErrors = [];
    $inviteSuccess = false;

    if (!$customer) {
        $inviteErrors[] = 'لینک دعوتنامه معتبر نیست یا غیرفعال شده است.';
    } elseif (is_post()) {
        verify_csrf();
        $inviteErrors = required_fields($_POST, [
            'contact_name' => 'نام و نام خانوادگی',
            'mobile' => 'موبایل',
            'email' => 'ایمیل',
        ]);
        if (!$inviteErrors && Contact::emailExists(trim((string) ($_POST['email'] ?? '')))) {
            $inviteErrors[] = 'این ایمیل قبلاً در سامانه ثبت شده است. در صورت نیاز با پشتیبانی تماس بگیرید.';
        }
        if (!$inviteErrors) {
            $contactId = Contact::createFromInvitation($customer, $_POST);
            $contact = Contact::find($contactId);
            if ($contact) {
                Ticket::createContactActivationRequest($customer, $contact);
            }
            $inviteSuccess = true;
        }
    }

    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ثبت‌نام مخاطب - <?= e($appSettings['app_title']) ?></title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>:root { --primary: <?= e($appSettings['primary_color']) ?>; --primary-dark: <?= e($appSettings['primary_color']) ?>; --sidebar: <?= e($appSettings['sidebar_color']) ?>; }</style>
    </head>
    <body class="login-page">
        <main class="invite-register">
            <section class="card">
                <div class="brand" style="margin-bottom:18px">
                    <?php if (!empty($appSettings['app_icon'])): ?><img class="brand-icon" src="<?= e($appSettings['app_icon']) ?>" alt=""><?php else: ?><div class="brand-mark">Elm</div><?php endif; ?>
                    <div>
                        <strong><?= e($appSettings['app_title']) ?></strong>
                        <span>فرم ثبت‌نام مخاطب</span>
                    </div>
                </div>
                <?php if (!$customer): ?>
                    <div class="alert alert-danger"><?= e(implode(' ', $inviteErrors)) ?></div>
                <?php elseif ($inviteSuccess): ?>
                    <div class="alert alert-success">اطلاعات شما ثبت شد. درخواست فعال‌سازی حساب کاربری برای تیم پشتیبانی ارسال شد.</div>
                    <p class="muted">پس از بررسی و تأیید اطلاعات، دسترسی پرتال برای شما فعال و اطلاعات ورود ارسال می‌شود.</p>
                <?php else: ?>
                    <h2>ثبت اطلاعات مخاطب <?= e($customer['customer_name']) ?></h2>
                    <p class="muted">لطفاً اطلاعات خود را کامل کنید. حساب کاربری پس از بررسی توسط پشتیبان فعال می‌شود.</p>
                    <?php if ($inviteErrors): ?><div class="alert alert-danger"><?= e(implode(' ', $inviteErrors)) ?></div><?php endif; ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <div class="grid grid-2">
                            <div><label class="required">نام و نام خانوادگی</label><input required name="contact_name" value="<?= e($_POST['contact_name'] ?? '') ?>"></div>
                            <div><label>سمت</label><input name="position" value="<?= e($_POST['position'] ?? '') ?>"></div>
                            <div><label class="required">موبایل</label><input required name="mobile" value="<?= e($_POST['mobile'] ?? '') ?>"></div>
                            <div><label>تلفن</label><input name="phone" value="<?= e($_POST['phone'] ?? '') ?>"></div>
                            <div><label class="required">ایمیل</label><input required type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
                        </div>
                        <div style="margin-top:14px"><label>توضیحات</label><textarea name="notes"><?= e($_POST['notes'] ?? '') ?></textarea></div>
                        <div class="form-actions"><button class="btn btn-primary">ارسال درخواست ثبت‌نام</button></div>
                    </form>
                <?php endif; ?>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'login') {
    if (is_post()) {
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $contact = Contact::findPortalByEmail($email);
        if ($contact && !empty($contact['password_hash']) && password_verify($password, $contact['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['portal_contact'] = [
                'id' => (int) $contact['id'],
                'name' => $contact['contact_name'],
                'customer_id' => (int) $contact['customer_id'],
            ];
            UsageReport::logLogin('contact', (int) $contact['id']);
            redirect('portal.php');
        }
        $errors[] = 'ایمیل یا رمز عبور اشتباه است یا دسترسی پرتال فعال نیست.';
    }

    $appSettings = Setting::all();
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ورود به پرتال مشتری - <?= e($appSettings['app_title']) ?></title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>:root { --primary: <?= e($appSettings['primary_color']) ?>; --primary-dark: <?= e($appSettings['primary_color']) ?>; --sidebar: <?= e($appSettings['sidebar_color']) ?>; }</style>
    </head>
    <body class="login-page">
        <main class="auth-shell">
            <section class="auth-visual">
                <div class="auth-leaf"></div>
                <div class="brand auth-brand">
                    <?php if (!empty($appSettings['app_icon'])): ?><img class="brand-icon" src="<?= e($appSettings['app_icon']) ?>" alt=""><?php else: ?><div class="brand-mark">Elm</div><?php endif; ?>
                    <div>
                        <strong><?= e($appSettings['app_title']) ?></strong>
                        <span>دسترسی مشتریان و مخاطبین</span>
                    </div>
                </div>
                <h1>پرتال مشتری</h1>
                <p>ثبت و پیگیری درخواست‌ها، مشاهده وضعیت تیکت‌ها و ارتباط مستقیم با تیم پشتیبانی.</p>
            </section>
            <form class="auth-card" method="post">
                <?= csrf_field() ?>
                <div class="auth-card-head">
                    <span>ورود مخاطبین</span>
                    <h2>ورود به پرتال مشتری</h2>
                </div>
                <?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
                <label>ایمیل</label>
                <input required type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
                <label>رمز عبور</label>
                <input required type="password" name="password">
                <button class="btn btn-primary full" type="submit">ورود</button>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$contact = require_portal_auth();
UsageReport::logUsage('contact', (int) $contact['id'], 'portal_' . $action, $_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($action === 'profile') {
    $profileErrors = [];
    $profileSuccess = '';
    if (is_post()) {
        verify_csrf();
        if (($_POST['form_type'] ?? '') === 'password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            if (empty($contact['password_hash']) || !password_verify($currentPassword, $contact['password_hash'])) {
                $profileErrors[] = 'رمز عبور فعلی درست نیست.';
            }
            if (strlen($newPassword) < 8) {
                $profileErrors[] = 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.';
            }
            if ($newPassword !== $confirmPassword) {
                $profileErrors[] = 'تکرار رمز عبور با رمز جدید یکسان نیست.';
            }
            if (!$profileErrors) {
                Contact::updatePortalPassword((int) $contact['id'], $newPassword);
                $profileSuccess = 'رمز عبور با موفقیت تغییر کرد.';
                $contact = require_portal_auth();
            }
        }

        if (($_POST['form_type'] ?? '') === 'avatar') {
            try {
                $avatarPath = upload_profile_image('avatar_file', 'contacts');
                if (!$avatarPath) {
                    $profileErrors[] = 'لطفاً یک تصویر پروفایل انتخاب کنید.';
                } else {
                    Contact::updateAvatar((int) $contact['id'], $avatarPath);
                    $profileSuccess = 'تصویر پروفایل با موفقیت ذخیره شد.';
                    $contact = require_portal_auth();
                }
            } catch (RuntimeException $e) {
                $profileErrors[] = $e->getMessage();
            }
        }
    }

    portal_layout('پروفایل من', function () use ($contact, $profileErrors, $profileSuccess) {
        ?>
        <div class="toolbar">
            <h2>پروفایل من</h2>
            <a class="btn btn-light" href="portal.php">بازگشت</a>
        </div>
        <?php if ($profileSuccess): ?><div class="alert alert-success"><?= e($profileSuccess) ?></div><?php endif; ?>
        <?php if ($profileErrors): ?><div class="alert alert-danger"><?= e(implode(' ', $profileErrors)) ?></div><?php endif; ?>
        <div class="grid grid-2">
            <div class="card">
                <h3>اطلاعات ثبت‌شده</h3>
                <div class="portal-profile-head">
                    <?php if (!empty($contact['avatar_path'])): ?>
                        <img class="profile-photo" src="<?= e($contact['avatar_path']) ?>" alt="">
                    <?php else: ?>
                        <div class="profile-photo placeholder">م</div>
                    <?php endif; ?>
                    <div>
                        <strong><?= e($contact['contact_name']) ?></strong>
                        <span><?= e($contact['customer_name']) ?></span>
                    </div>
                </div>
                <div class="detail-list">
                    <div><span>نام</span><?= e($contact['contact_name']) ?></div>
                    <div><span>شرکت</span><?= e($contact['customer_name']) ?></div>
                    <div><span>سمت</span><?= e($contact['position']) ?></div>
                    <div><span>موبایل</span><?= e($contact['mobile']) ?></div>
                    <div><span>تلفن</span><?= e($contact['phone']) ?></div>
                    <div><span>ایمیل</span><?= e($contact['email']) ?></div>
                </div>
                <div class="empty" style="margin-top:14px">برای اصلاح اطلاعات پایه، یک تیکت با موضوع اصلاح اطلاعات ثبت کنید.</div>
                <div class="form-actions"><a class="btn btn-light" href="portal.php?action=create_ticket">ثبت درخواست اصلاح اطلاعات</a></div>
            </div>

            <div class="card">
                <h3>تصویر پروفایل</h3>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_type" value="avatar">
                    <label>تصویر جدید</label>
                    <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp">
                    <span class="muted">تصویر به صورت خودکار به avatar سبک ۳۲۰×۳۲۰ تبدیل می‌شود. فرمت‌های مجاز: jpg، png، webp</span>
                    <div class="form-actions"><button class="btn btn-primary">ذخیره تصویر</button></div>
                </form>
                <hr>
                <h3>تغییر رمز عبور</h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_type" value="password">
                    <div class="grid">
                        <div><label>رمز عبور فعلی</label><input required type="password" name="current_password"></div>
                        <div><label>رمز عبور جدید</label><input required minlength="8" type="password" name="new_password"></div>
                        <div><label>تکرار رمز عبور جدید</label><input required minlength="8" type="password" name="confirm_password"></div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">تغییر رمز عبور</button></div>
                </form>
            </div>
        </div>
        <?php
    });
    exit;
}

if ($action === 'create_ticket') {
    if (is_post()) {
        verify_csrf();
        $errors = required_fields($_POST, ['subject' => 'موضوع', 'description' => 'شرح درخواست']);
        try {
            $attachment = upload_ticket_image('attachment');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
            $attachment = null;
        }
        if (!$errors) {
            $_POST['attachment'] = $attachment;
            $ticketId = Ticket::createFromPortal($contact, $_POST);
            $ticket = Ticket::find($ticketId);
            if ($ticket) {
                if ((int) ($ticket['is_vip'] ?? 0) === 1) {
                    SmsService::notifyVipTicketCreated($ticket);
                } else {
                    SmsService::notifyTicketCreated($ticket);
                }
            }
            redirect('portal.php');
        }
    }

    portal_layout('ثبت تیکت', function () use ($errors) {
        ?>
        <div class="toolbar">
            <h2>ثبت تیکت جدید</h2>
            <a class="btn btn-light" href="portal.php">بازگشت</a>
        </div>
        <form class="card" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
            <div class="grid grid-2">
                <div><label class="required">موضوع</label><input required name="subject" value="<?= e($_POST['subject'] ?? '') ?>"></div>
                <div><label>دسته</label><select name="category"><?php foreach (Ticket::categories() as $option): ?><option value="<?= e($option) ?>"><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
                <div><label>اولویت</label><select name="priority"><?php foreach (Ticket::priorities() as $option): ?><option value="<?= e($option) ?>" <?= selected($option, 'Normal') ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
            </div>
            <div style="margin-top:14px"><label class="required">شرح درخواست</label><textarea required name="description"><?= e($_POST['description'] ?? '') ?></textarea></div>
            <div style="margin-top:14px"><label>تصویر پیوست</label><input type="file" name="attachment" accept="image/jpeg,image/png,image/webp"><span class="muted">حداکثر ۲ مگابایت. فرمت‌های مجاز: jpg، png، webp</span></div>
            <div class="form-actions"><button class="btn btn-primary">ثبت تیکت</button></div>
        </form>
        <?php
    });
    exit;
}

if ($action === 'ticket') {
    $ticket = Ticket::findForContact((int) ($_GET['id'] ?? 0), (int) $contact['id']);
    if (!$ticket) {
        redirect('portal.php');
    }
    $ticketErrors = [];
    if (is_post()) {
        verify_csrf();
        if (($_POST['ticket_action'] ?? '') === 'close') {
            Ticket::closeForContact((int) $ticket['id'], (int) $contact['id']);
            redirect('portal.php?action=ticket&id=' . (int) $ticket['id']);
        }
        $errors = [];
        if (Ticket::isClosed($ticket)) {
            $errors[] = 'این تیکت بسته شده و امکان ارسال پیام جدید ندارد.';
        }
        $message = trim((string) ($_POST['message'] ?? ''));
        try {
            $attachment = upload_ticket_image('attachment');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
            $attachment = null;
        }
        if (!$message && !$attachment) {
            $errors[] = 'برای ارسال پیام، متن یا تصویر را وارد کنید.';
        }
        if (!$errors) {
            TicketMessage::createFromContact((int) $ticket['id'], (int) $contact['id'], $message, $attachment);
            redirect('portal.php?action=ticket&id=' . (int) $ticket['id']);
        }
        $ticketErrors = $errors;
    }
    $ticket = Ticket::findForContact((int) ($_GET['id'] ?? 0), (int) $contact['id']) ?: $ticket;
    if (!empty($ticketErrors)) {
        $ticket['errors'] = $ticketErrors;
    }
    $messages = TicketMessage::byTicket((int) $ticket['id']);
    TicketMessage::markReadForContact((int) $ticket['id'], (int) $contact['id']);

    portal_layout('جزئیات تیکت', function () use ($ticket, $messages) {
        ?>
        <div class="toolbar">
            <h2><?= e($ticket['ticket_code']) ?> - <?= e($ticket['subject']) ?></h2>
            <a class="btn btn-light" href="portal.php">بازگشت</a>
        </div>
        <div class="card">
            <div class="detail-list">
                <div><span>وضعیت</span><span class="badge <?= e(badge_class($ticket['status'])) ?>"><?= e(Ticket::label($ticket['status'])) ?></span></div>
                <div><span>اولویت</span><?= e(Ticket::label($ticket['priority'])) ?></div>
                <div><span>دسته</span><?= e(Ticket::label($ticket['category'])) ?></div>
                <div><span>ایجاد</span><?= e(fa_datetime($ticket['created_at'])) ?></div>
                <div><span>آخرین تغییر</span><?= e(fa_datetime($ticket['updated_at'])) ?></div>
            </div>
            <h3>شرح درخواست</h3>
            <p><?= nl2br(e($ticket['description'])) ?></p>
        </div>
        <div class="card ticket-conversation-card" style="margin-top:16px">
            <h3>گفت‌وگوی تیکت</h3>
            <div class="ticket-thread ticket-thread-chat ticket-thread-portal">
                <?php foreach ($messages as $message): ?>
                    <?php $avatarPath = $message['sender_type'] === 'contact' ? ($message['contact_avatar_path'] ?? '') : ($message['user_avatar_path'] ?? ''); ?>
                    <div class="ticket-message <?= e($message['sender_type'] === 'contact' ? 'from-contact' : 'from-user') ?>">
                        <div class="ticket-avatar">
                            <?php if ($avatarPath): ?><img src="<?= e($avatarPath) ?>" alt=""><?php else: ?><?= e($message['sender_type'] === 'contact' ? 'م' : 'پ') ?><?php endif; ?>
                        </div>
                        <div class="ticket-bubble">
                        <div class="ticket-message-head">
                            <strong><?= e($message['sender_type'] === 'contact' ? ($message['contact_name'] ?? 'مشتری') : ($message['user_name'] ?? 'پشتیبانی')) ?></strong>
                            <span><?= e(fa_datetime($message['created_at'])) ?></span>
                        </div>
                        <?php if (trim((string) $message['message']) !== ''): ?><p><?= nl2br(e($message['message'])) ?></p><?php endif; ?>
                        <?php if (!empty($message['attachment_path'])): ?>
                            <a class="ticket-attachment" href="<?= e($message['attachment_path']) ?>" target="_blank" rel="noopener">
                                <img src="<?= e($message['attachment_path']) ?>" alt="">
                                <span><?= e($message['attachment_name'] ?: 'تصویر پیوست') ?></span>
                            </a>
                        <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$messages): ?><div class="empty">هنوز پیامی برای این تیکت ثبت نشده است.</div><?php endif; ?>
            </div>
        </div>
        <form class="card ticket-reply-card" style="margin-top:16px" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php if (!empty($ticket['errors'])): ?><div class="alert alert-danger"><?= e(implode(' ', $ticket['errors'])) ?></div><?php endif; ?>
            <?php if (Ticket::isClosed($ticket)): ?>
                <div class="empty">این تیکت بسته شده است.</div>
            <?php else: ?>
                <h3>ارسال پیام جدید</h3>
                <div class="ticket-reply-grid">
                    <textarea name="message" placeholder="متن پیام شما..."></textarea>
                </div>
                <div class="ticket-upload-field"><label>تصویر پیوست</label><input type="file" name="attachment" accept="image/jpeg,image/png,image/webp"><span class="muted">حداکثر ۲ مگابایت. فرمت‌های مجاز: jpg، png، webp</span></div>
                <div class="form-actions">
                    <button class="btn btn-primary" name="ticket_action" value="reply">ارسال پیام</button>
                    <button class="btn btn-danger" name="ticket_action" value="close" data-confirm="این تیکت بسته شود؟">بستن تیکت</button>
                </div>
            <?php endif; ?>
        </form>
        <?php
    });
    exit;
}

if ($action === 'announcements') {
    $announcements = Announcement::forContact($contact);
    portal_layout('اطلاعیه‌ها', function () use ($announcements) {
        ?>
        <div class="toolbar">
            <h2>اطلاعیه‌ها</h2>
            <a class="btn btn-light" href="portal.php">بازگشت</a>
        </div>
        <div class="announcement-list">
            <?php foreach ($announcements as $announcement): ?>
                <?php $isUnread = empty($announcement['read_at']); ?>
                <a class="announcement-item <?= $isUnread ? 'is-unread' : '' ?>" href="portal.php?action=announcement&id=<?= e((string) $announcement['id']) ?>">
                    <div>
                        <strong><?= e($announcement['title']) ?></strong>
                        <span><?= e(text_excerpt($announcement['body'] ?? '', 120)) ?></span>
                    </div>
                    <small><?= e(fa_datetime($announcement['published_at'])) ?></small>
                    <em><?= $isUnread ? 'خوانده‌نشده' : 'خوانده‌شده' ?><?= (int) ($announcement['attachment_count'] ?? 0) > 0 ? ' · پیوست' : '' ?></em>
                </a>
            <?php endforeach; ?>
            <?php if (!$announcements): ?><div class="empty">اطلاعیه‌ای برای شما ثبت نشده است.</div><?php endif; ?>
        </div>
        <?php
    });
    exit;
}

if ($action === 'announcement_attachment') {
    $attachment = Announcement::attachmentForContact((int) ($_GET['id'] ?? 0), $contact);
    if (!$attachment) {
        http_response_code(404);
        exit('File not found.');
    }
    $root = realpath(announcement_attachment_root());
    $file = realpath(announcement_attachment_root() . '/' . ltrim((string) $attachment['file_path'], '/\\'));
    if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
        http_response_code(404);
        exit('File not found.');
    }
    header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode((string) ($attachment['file_name'] ?: basename($file))));
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

if ($action === 'announcement') {
    $announcement = Announcement::findForContact((int) ($_GET['id'] ?? 0), $contact);
    if (!$announcement) {
        redirect('portal.php?action=announcements');
    }
    $attachments = Announcement::attachments((int) $announcement['id']);
    Announcement::markRead((int) $announcement['id'], (int) $contact['id']);
    portal_layout('مشاهده اطلاعیه', function () use ($announcement, $attachments) {
        ?>
        <div class="toolbar">
            <h2><?= e($announcement['title']) ?></h2>
            <a class="btn btn-light" href="portal.php?action=announcements">بازگشت</a>
        </div>
        <article class="card announcement-detail">
            <div class="muted"><?= e(fa_datetime($announcement['published_at'])) ?></div>
            <div class="rich-content"><?= sanitize_rich_html($announcement['body'] ?? '') ?></div>
            <?php if ($attachments): ?>
                <h3>فایل‌های پیوست</h3>
                <div class="announcement-attachments">
                    <?php foreach ($attachments as $attachment): ?>
                        <a class="badge badge-muted" href="portal.php?action=announcement_attachment&id=<?= e((string) $attachment['id']) ?>"><?= e($attachment['file_name'] ?: 'فایل پیوست') ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
    });
    exit;
}

$tickets = Ticket::byContact((int) $contact['id']);
$totalUnread = array_sum(array_map(static fn($ticket) => (int) ($ticket['unread_count'] ?? 0), $tickets));
$announcementUnread = Announcement::unreadCountForContact($contact);
portal_layout('داشبورد', function () use ($tickets, $totalUnread, $announcementUnread) {
    ?>
    <div class="toolbar">
        <h2>تیکت‌های من</h2>
        <div class="toolbar-actions">
            <?php if ($totalUnread > 0): ?><span class="badge badge-primary"><?= e((string) $totalUnread) ?> پیام جدید</span><?php endif; ?>
            <?php if ($announcementUnread > 0): ?><a class="badge badge-warning" href="portal.php?action=announcements"><?= e((string) $announcementUnread) ?> اطلاعیه جدید</a><?php endif; ?>
            <a class="btn btn-primary" href="portal.php?action=create_ticket">ثبت تیکت جدید</a>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>کد</th><th>موضوع</th><th>دسته</th><th>اولویت</th><th>وضعیت</th><th>آخرین تغییر</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <?php $unreadCount = (int) ($ticket['unread_count'] ?? 0); ?>
                <tr class="<?= $unreadCount > 0 ? 'ticket-row-unread' : '' ?>">
                    <td><?= e($ticket['ticket_code']) ?></td>
                    <td><strong><?= e($ticket['subject']) ?></strong><?php if ($unreadCount > 0): ?> <span class="badge badge-primary unread-badge"><?= e((string) $unreadCount) ?> جدید</span><?php endif; ?></td>
                    <td><?= e(Ticket::label($ticket['category'])) ?></td>
                    <td><?= e(Ticket::label($ticket['priority'])) ?></td>
                    <td><span class="badge <?= e(badge_class($ticket['status'])) ?>"><?= e(Ticket::label($ticket['status'])) ?></span></td>
                    <td><?= e(fa_datetime($ticket['updated_at'])) ?></td>
                    <td><a class="btn btn-small btn-light" href="portal.php?action=ticket&id=<?= e((string) $ticket['id']) ?>">نمایش</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?><tr><td colspan="7" class="empty">هنوز تیکتی ثبت نکرده‌اید.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
});
