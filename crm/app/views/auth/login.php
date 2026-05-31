<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود به Mammut Connect CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <form class="login-card" method="post">
        <?= csrf_field() ?>
        <div class="brand login-brand">
            <div class="brand-mark">MC</div>
            <div>
                <strong>ماموت کانکت</strong>
                <span>ورود به CRM</span>
            </div>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>
        <label>ایمیل</label>
        <input required type="email" name="email" value="<?= e($_POST['email'] ?? 'admin@mammutconnect.local') ?>">
        <label>رمز عبور</label>
        <input required type="password" name="password" value="">
        <button class="btn btn-primary full" type="submit">ورود</button>
        <p class="hint">کاربر پیش‌فرض: admin@mammutconnect.local / Admin@12345</p>
    </form>
</body>
</html>
