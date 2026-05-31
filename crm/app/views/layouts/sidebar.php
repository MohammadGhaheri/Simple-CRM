<aside class="sidebar">
    <div class="brand">
        <div class="brand-mark">MC</div>
        <div>
            <strong>ماموت کانکت</strong>
            <span>CRM فروش</span>
        </div>
    </div>
    <nav>
        <a class="<?= ($page ?? '') === 'dashboard' ? 'active' : '' ?>" href="index.php">داشبورد</a>
        <a class="<?= ($page ?? '') === 'customers' ? 'active' : '' ?>" href="<?= e(url('customers')) ?>">مشتریان</a>
        <a class="<?= ($page ?? '') === 'deals' ? 'active' : '' ?>" href="<?= e(url('deals')) ?>">فرصت‌ها</a>
        <a class="<?= ($page ?? '') === 'activities' ? 'active' : '' ?>" href="<?= e(url('activities')) ?>">فعالیت‌ها</a>
    </nav>
</aside>
<main class="main">
    <header class="topbar">
        <div>
            <h1><?= e($title ?? 'داشبورد') ?></h1>
            <p>مدیریت مشتریان، فرصت‌ها و پیگیری‌های فروش تلماتیک</p>
        </div>
        <div class="user-menu">
            <span><?= e($_SESSION['user']['name'] ?? '') ?></span>
            <a class="btn btn-light" href="logout.php">خروج</a>
        </div>
    </header>
    <section class="content">
