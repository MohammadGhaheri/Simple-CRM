<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/csrf.php';
require __DIR__ . '/../app/models/Contact.php';
require __DIR__ . '/../app/models/Ticket.php';
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
                <input required type="email" name="email" value="<?= e($_POST['email'] ?? 'reza.mohammadi@example.com') ?>">
                <label>رمز عبور</label>
                <input required type="password" name="password">
                <button class="btn btn-primary full" type="submit">ورود</button>
                <p class="hint">نمونه تست: reza.mohammadi@example.com / Contact@12345</p>
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
        if (!$errors) {
            $ticketId = Ticket::createFromPortal($contact, $_POST);
            $ticket = Ticket::find($ticketId);
            if ($ticket) {
                SmsService::notifyTicketCreated($ticket);
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
        <form class="card" method="post">
            <?= csrf_field() ?>
            <?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
            <div class="grid grid-2">
                <div><label class="required">موضوع</label><input required name="subject" value="<?= e($_POST['subject'] ?? '') ?>"></div>
                <div><label>دسته</label><select name="category"><?php foreach (Ticket::categories() as $option): ?><option value="<?= e($option) ?>"><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
                <div><label>اولویت</label><select name="priority"><?php foreach (Ticket::priorities() as $option): ?><option value="<?= e($option) ?>" <?= selected($option, 'Normal') ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
            </div>
            <div style="margin-top:14px"><label class="required">شرح درخواست</label><textarea required name="description"><?= e($_POST['description'] ?? '') ?></textarea></div>
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

    portal_layout('جزئیات تیکت', function () use ($ticket) {
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
            </div>
            <h3>شرح درخواست</h3>
            <p><?= nl2br(e($ticket['description'])) ?></p>
            <?php if (!empty($ticket['response'])): ?>
                <h3>پاسخ تیم پشتیبانی</h3>
                <p><?= nl2br(e($ticket['response'])) ?></p>
            <?php endif; ?>
        </div>
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
                    <td><?= e($ticket['updated_at']) ?></td>
                    <td><a class="btn btn-small btn-light" href="portal.php?action=ticket&id=<?= e((string) $ticket['id']) ?>">نمایش</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?><tr><td colspan="7" class="empty">هنوز تیکتی ثبت نکرده‌اید.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
});
