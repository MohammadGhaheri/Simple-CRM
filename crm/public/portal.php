<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/csrf.php';
require __DIR__ . '/../app/models/Contact.php';
require __DIR__ . '/../app/models/Ticket.php';
require __DIR__ . '/../app/models/TicketMessage.php';
require __DIR__ . '/../app/models/Setting.php';
require __DIR__ . '/../app/models/UsageReport.php';
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
                    <span><?= e($contact['contact_name']) ?> - <?= e($contact['customer_name']) ?></span>
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

    portal_layout('جزئیات تیکت', function () use ($ticket, $messages) {
        ?>
        <div class="toolbar">
            <h2><?= e($ticket['ticket_code']) ?> - <?= e($ticket['subject']) ?></h2>
            <a class="btn btn-light" href="portal.php">بازگشت</a>
        </div>
        <div class="card">
            <div class="detail-list">
                <div><span>وضعیت</span><?= e(Ticket::label($ticket['status'])) ?></div>
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
            <div class="ticket-thread ticket-thread-chat">
                <?php foreach ($messages as $message): ?>
                    <div class="ticket-message <?= e($message['sender_type'] === 'contact' ? 'from-contact' : 'from-user') ?>">
                        <div class="ticket-avatar"><?= e($message['sender_type'] === 'contact' ? 'م' : 'پ') ?></div>
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

$tickets = Ticket::byContact((int) $contact['id']);
portal_layout('داشبورد', function () use ($tickets) {
    ?>
    <div class="toolbar">
        <h2>تیکت‌های من</h2>
        <a class="btn btn-primary" href="portal.php?action=create_ticket">ثبت تیکت جدید</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>کد</th><th>موضوع</th><th>دسته</th><th>اولویت</th><th>وضعیت</th><th>آخرین تغییر</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= e($ticket['ticket_code']) ?></td>
                    <td><strong><?= e($ticket['subject']) ?></strong></td>
                    <td><?= e(Ticket::label($ticket['category'])) ?></td>
                    <td><?= e(Ticket::label($ticket['priority'])) ?></td>
                    <td><span class="badge badge-info"><?= e(Ticket::label($ticket['status'])) ?></span></td>
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
