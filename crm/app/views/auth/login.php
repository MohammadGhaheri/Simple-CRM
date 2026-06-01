<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $appSettings = class_exists('Setting') ? Setting::all() : ['app_title' => 'Elm Simple CRM', 'primary_color' => '#155eef', 'sidebar_color' => '#111827', 'app_icon' => '']; ?>
    <title>ورود به <?= e($appSettings['app_title']) ?></title>
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
                    <span>ریشه منظم برای ارتباط با مشتریان</span>
                </div>
            </div>
            <h1>سامانه داخلی فروش</h1>
            <p>مدیریت مشتریان، فرصت‌ها، پیگیری‌های روزانه و تیکت‌ها در یک فضای ساده و رسمی.</p>
        </section>
        <form class="auth-card" method="post">
            <?= csrf_field() ?>
            <div class="auth-card-head">
                <span>ورود کاربران</span>
                <h2>ورود به CRM</h2>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <label>ایمیل</label>
            <input required type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
            <label>رمز عبور</label>
            <input required type="password" name="password" value="">
            <button class="btn btn-primary full" type="submit">ورود</button>
        </form>
    </main>
</body>
</html>
