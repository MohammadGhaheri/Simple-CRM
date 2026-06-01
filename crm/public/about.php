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
    <title>درباره پروژه - <?= e($settings['app_title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>:root { --primary: <?= e($settings['primary_color']) ?>; --primary-dark: <?= e($settings['primary_color']) ?>; --sidebar: <?= e($settings['sidebar_color']) ?>; }</style>
</head>
<body class="home-page">
    <main class="home-hero about-page">
        <div class="home-brand">
            <?php if (!empty($settings['app_icon'])): ?><img class="brand-icon large" src="<?= e($settings['app_icon']) ?>" alt=""><?php else: ?><div class="brand-mark">Elm</div><?php endif; ?>
            <div>
                <strong><?= e($settings['app_title']) ?></strong>
                <span>پروژه متن‌باز CRM ساده</span>
            </div>
        </div>

        <h1>درباره سازنده و پروژه</h1>
        <div class="about-content">
            <p>من محمد قاهری هستم؛ فعال حوزه فناوری، محصول و داده.</p>
            <p>در سال‌های گذشته روی طراحی و توسعه سامانه‌های نرم‌افزاری، پلتفرم‌های داده، هوش تجاری، اینترنت اشیا و راهکارهای مرتبط با خودروهای متصل کار کرده‌ام. تمرکز من همیشه روی ساخت محصولات واقعی، حل مسائل عملیاتی و تبدیل نیازهای ساده یا پراکنده به سامانه‌های قابل استفاده بوده است.</p>
            <p>پروژه <strong>Elm Simple CRM</strong> را ابتدا برای پاسخ به نیازهای عملیاتی ماموت کانکت توسعه دادم. بعد تصمیم گرفتم آن را به‌صورت متن‌باز منتشر کنم تا اگر برای تیم‌ها یا کسب‌وکارهای دیگر هم مفید بود، بتوانند از آن استفاده کنند یا توسعه‌اش دهند.</p>
        </div>

        <div class="about-license">
            <strong>مجوز متن‌باز</strong>
            <span>این پروژه با مجوز MIT منتشر شده است.</span>
        </div>

        <div class="home-actions">
            <a class="btn btn-primary" href="home.php">بازگشت به صفحه خانه</a>
            <a class="btn btn-portal" href="https://www.linkedin.com/in/mohammadghaheri/" target="_blank" rel="noopener">LinkedIn محمد قاهری</a>
        </div>
    </main>
</body>
</html>
