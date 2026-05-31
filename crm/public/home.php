<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/models/Setting.php';

$settings = Setting::all();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($settings['home_title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>:root { --primary: <?= e($settings['primary_color']) ?>; --primary-dark: <?= e($settings['primary_color']) ?>; --sidebar: <?= e($settings['sidebar_color']) ?>; }</style>
</head>
<body class="home-page">
    <main class="home-hero">
        <div class="home-brand">
            <?php if (!empty($settings['app_icon'])): ?><img class="brand-icon large" src="<?= e($settings['app_icon']) ?>" alt=""><?php else: ?><div class="brand-mark">Elm</div><?php endif; ?>
            <div>
                <strong><?= e($settings['app_title']) ?></strong>
                <span><?= e($settings['app_subtitle']) ?></span>
            </div>
        </div>
        <h1><?= e($settings['home_title']) ?></h1>
        <p><?= nl2br(e($settings['home_text'])) ?></p>
        <div class="home-actions">
            <a class="btn btn-primary" href="login.php">ورود به سامانه داخلی</a>
            <a class="btn btn-light" href="portal.php?action=login">ورود به پرتال مشتری</a>
        </div>
    </main>
</body>
</html>
