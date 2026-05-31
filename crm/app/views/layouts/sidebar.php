<aside class="sidebar">
    <div class="brand">
        <?php $appSettings = class_exists('Setting') ? Setting::all() : ['app_title' => 'Simple CRM', 'app_subtitle' => 'مدیریت فروش', 'app_icon' => '']; ?>
        <?php if (!empty($appSettings['app_icon'])): ?>
            <img class="brand-icon" src="<?= e($appSettings['app_icon']) ?>" alt="">
        <?php else: ?>
            <div class="brand-mark">SC</div>
        <?php endif; ?>
        <div>
            <strong><?= e($appSettings['app_title']) ?></strong>
            <span><?= e($appSettings['app_subtitle']) ?></span>
        </div>
    </div>
    <nav>
        <a class="<?= ($page ?? '') === 'dashboard' ? 'active' : '' ?>" href="index.php">داشبورد</a>
        <a class="<?= ($page ?? '') === 'my_tasks' ? 'active' : '' ?>" href="<?= e(url('my_tasks')) ?>">برنامه من</a>
        <a class="<?= ($page ?? '') === 'customers' ? 'active' : '' ?>" href="<?= e(url('customers')) ?>">مشتریان</a>
        <a class="<?= ($page ?? '') === 'contacts' ? 'active' : '' ?>" href="<?= e(url('contacts')) ?>">مخاطبین</a>
        <a class="<?= ($page ?? '') === 'deals' ? 'active' : '' ?>" href="<?= e(url('deals')) ?>">فرصت‌ها</a>
        <a class="<?= ($page ?? '') === 'activities' ? 'active' : '' ?>" href="<?= e(url('activities')) ?>">فعالیت‌ها</a>
        <?php $ticketNeedsReview = class_exists('Ticket') ? Ticket::needsReviewCount() : 0; ?>
        <a class="nav-with-badge <?= ($page ?? '') === 'tickets' ? 'active' : '' ?>" href="<?= e(url('tickets')) ?>">
            <span>تیکت‌ها</span>
            <?php if ($ticketNeedsReview > 0): ?>
                <span class="nav-badge"><?= e((string) $ticketNeedsReview) ?></span>
            <?php endif; ?>
        </a>
        <?php if (is_admin()): ?>
            <a class="<?= ($page ?? '') === 'users' ? 'active' : '' ?>" href="<?= e(url('users')) ?>">کاربران</a>
            <a class="<?= ($page ?? '') === 'settings' ? 'active' : '' ?>" href="<?= e(url('settings')) ?>">تنظیمات</a>
        <?php endif; ?>
    </nav>
</aside>
<main class="main">
    <header class="topbar">
        <div>
            <h1><?= e($title ?? 'داشبورد') ?></h1>
            <p>مدیریت مشتریان، فرصت‌ها و پیگیری‌های فروش تلماتیک</p>
        </div>
        <div class="user-menu">
            <?php if (!empty($_SESSION['user']['avatar_path'])): ?><img class="user-avatar" src="<?= e($_SESSION['user']['avatar_path']) ?>" alt=""><?php endif; ?>
            <span><?= e($_SESSION['user']['name'] ?? '') ?></span>
            <a class="btn btn-light" href="logout.php">خروج</a>
        </div>
    </header>
    <section class="content">
