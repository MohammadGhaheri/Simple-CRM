<?php
$report = $performanceReport;
$filters = $report['filters'];
$summary = $report['summary'];
$roleLabels = User::roles();
$entityLabels = [
    'customer' => 'مشتری', 'contact' => 'مخاطب', 'deal' => 'فرصت فروش',
    'contract' => 'قرارداد', 'activity' => 'فعالیت', 'ticket' => 'تیکت',
];
?>

<div class="card report-filter-card">
    <form method="get" class="filters report-filters">
        <input type="hidden" name="page" value="reports">
        <div><label>از تاریخ</label><input class="date-input" name="date_from" value="<?= e($filters['date_from']) ?>" placeholder="1405/01/01"></div>
        <div><label>تا تاریخ</label><input class="date-input" name="date_to" value="<?= e($filters['date_to']) ?>" placeholder="1405/01/31"></div>
        <div><label>کاربر</label><select name="user_id"><option value="">همه کاربران</option><?php foreach ($reportUsers as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= (int) $filters['user_id'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
        <div><label>نقش</label><select name="role"><option value="">همه نقش‌ها</option><?php foreach ($roleLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="report-filter-action"><button class="btn btn-primary" type="submit">اعمال فیلتر</button></div>
    </form>
</div>

<div class="report-note">برخی شاخص‌های رفتاری مانند زمان فعالیت، بازدید رکوردها، ثبت‌کننده فعالیت و تاریخچه وضعیت تیکت از زمان فعال‌شدن گزارش عملکرد ثبت می‌شوند و ممکن است برای دوره‌های قبل کامل نباشند.</div>

<div class="report-kpis">
    <div class="card stat"><span>کاربران فعال</span><strong><?= e((string) $summary['active_users']) ?></strong></div>
    <div class="card stat"><span>ورود کاربران</span><strong><?= e((string) $summary['logins']) ?></strong></div>
    <div class="card stat"><span>زمان فعالیت</span><strong><?= e(PerformanceReport::formatDuration($summary['active_seconds'])) ?></strong></div>
    <div class="card stat"><span>کل بازدیدها</span><strong><?= e((string) $summary['total_views']) ?></strong></div>
    <div class="card stat"><span>رکوردهای یکتا</span><strong><?= e((string) $summary['unique_views']) ?></strong></div>
    <div class="card stat"><span>فعالیت‌های ثبت‌شده</span><strong><?= e((string) $summary['activities']) ?></strong></div>
    <div class="card stat"><span>تیکت مشتریان</span><strong><?= e((string) $summary['customer_tickets']) ?></strong></div>
    <div class="card stat"><span>پاسخ‌داده‌شده</span><strong><?= e((string) $summary['answered']) ?></strong></div>
    <div class="card stat"><span>بدون پاسخ</span><strong><?= e((string) $summary['unanswered']) ?></strong></div>
    <div class="card stat"><span>حل‌شده / بسته</span><strong><?= e((string) $summary['closed']) ?></strong></div>
    <div class="card stat"><span>میانگین اولین پاسخ</span><strong><?= e(PerformanceReport::formatDuration($summary['avg_first_response'])) ?></strong></div>
    <div class="card stat"><span>میانه اولین پاسخ</span><strong><?= e(PerformanceReport::formatDuration($summary['median_first_response'])) ?></strong></div>
    <div class="card stat"><span>میانگین زمان حل</span><strong><?= e(PerformanceReport::formatDuration($summary['avg_resolution'])) ?></strong></div>
    <div class="card stat"><span>میانه زمان حل</span><strong><?= e(PerformanceReport::formatDuration($summary['median_resolution'])) ?></strong></div>
</div>

<section class="report-section">
    <h2>عملکرد پرسنل</h2>
    <div class="table-wrap"><table class="report-table">
        <thead><tr><th>نام</th><th>نقش</th><th>وضعیت</th><th>آخرین ورود</th><th>ورود</th><th>زمان فعالیت</th><th>بازدید</th><th>یکتا</th><th>فعالیت ثبت‌شده</th><th>اولین پاسخ</th><th>بدون پاسخ تخصیص‌یافته</th><th>تیکت بسته‌شده</th><th>میانگین پاسخ</th><th>میانگین حل</th></tr></thead>
        <tbody>
        <?php foreach ($report['users'] as $row): ?><tr>
            <td><strong><?= e($row['name']) ?></strong></td>
            <td><span class="badge badge-muted"><?= e($roleLabels[$row['role']] ?? $row['role']) ?></span></td>
            <td><span class="badge <?= $row['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $row['is_active'] ? 'فعال' : 'غیرفعال' ?></span></td>
            <td><?= $row['last_login'] ? e(fa_datetime($row['last_login'])) : '<span class="muted">بدون ورود ثبت‌شده</span>' ?></td>
            <td><?= e((string) $row['login_count']) ?></td><td><?= e(PerformanceReport::formatDuration((int) $row['active_seconds'])) ?></td>
            <td><?= e((string) $row['total_views']) ?></td><td><?= e((string) $row['unique_views']) ?></td><td><?= e((string) $row['activities_created']) ?></td>
            <td><?= e((string) $row['first_responses']) ?></td><td><?= e((string) $row['assigned_unanswered']) ?></td><td><?= e((string) $row['tickets_closed']) ?></td>
            <td><?= e(PerformanceReport::formatDuration($row['avg_first_response'])) ?></td><td><?= e(PerformanceReport::formatDuration($row['avg_resolution'])) ?></td>
        </tr><?php endforeach; ?>
        <?php if (!$report['users']): ?><tr><td colspan="14" class="empty">کاربری مطابق فیلترها پیدا نشد.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>

<section class="report-section">
    <h2>عملکرد پشتیبانی و تیکت‌ها</h2>
    <div class="report-kpis report-kpis-compact">
        <div class="card stat"><span>کل تیکت مشتری</span><strong><?= e((string) $summary['customer_tickets']) ?></strong></div>
        <div class="card stat"><span>پاسخ‌داده‌شده</span><strong><?= e((string) $summary['answered']) ?></strong></div>
        <div class="card stat"><span>بدون پاسخ</span><strong><?= e((string) $summary['unanswered']) ?></strong></div>
        <div class="card stat"><span>میانگین اولین پاسخ</span><strong><?= e(PerformanceReport::formatDuration($summary['avg_first_response'])) ?></strong></div>
        <div class="card stat"><span>میانه اولین پاسخ</span><strong><?= e(PerformanceReport::formatDuration($summary['median_first_response'])) ?></strong></div>
        <div class="card stat"><span>میانگین زمان حل</span><strong><?= e(PerformanceReport::formatDuration($summary['avg_resolution'])) ?></strong></div>
        <div class="card stat"><span>میانه زمان حل</span><strong><?= e(PerformanceReport::formatDuration($summary['median_resolution'])) ?></strong></div>
        <?php foreach ($report['ticket_statuses'] as $status => $total): ?><div class="card stat"><span><?= e(Ticket::label($status)) ?></span><strong><?= e((string) $total) ?></strong></div><?php endforeach; ?>
    </div>
    <div class="table-wrap"><table><thead><tr><th>کارشناس</th><th>اولین پاسخ‌ها</th><th>بدون پاسخ تخصیص‌یافته</th><th>بسته‌شده</th><th>میانگین پاسخ</th></tr></thead><tbody>
        <?php foreach ($report['support_users'] as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= e((string) $row['first_responses']) ?></td><td><?= e((string) $row['assigned_unanswered']) ?></td><td><?= e((string) $row['tickets_closed']) ?></td><td><?= e(PerformanceReport::formatDuration($row['avg_first_response'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$report['support_users']): ?><tr><td colspan="5" class="empty">کارشناس پشتیبانی مطابق فیلترها وجود ندارد.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<div class="grid grid-2 report-section">
    <section class="card"><h2>ورود و استفاده از سامانه</h2><div class="table-wrap report-inner-table"><table><thead><tr><th>کاربر</th><th>نقش</th><th>آخرین ورود</th><th>ورود</th><th>جلسه</th><th>زمان فعال</th><th>آخرین فعالیت</th></tr></thead><tbody>
        <?php foreach ($report['users'] as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= e($roleLabels[$row['role']] ?? $row['role']) ?></td><td><?= $row['last_login'] ? e(fa_datetime($row['last_login'])) : 'بدون ورود ثبت‌شده' ?></td><td><?= e((string) $row['login_count']) ?></td><td><?= e((string) $row['session_count']) ?></td><td><?= e(PerformanceReport::formatDuration((int) $row['active_seconds'])) ?></td><td><?= $row['last_seen'] ? e(fa_datetime($row['last_seen'])) : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
    <section class="card"><h2>بازدید بر اساس نوع رکورد</h2><div class="report-breakdown">
        <?php foreach ($report['view_breakdown'] as $item): ?><div><span><?= e($entityLabels[$item['entity_type']] ?? $item['entity_type']) ?></span><strong><?= e((string) $item['total']) ?></strong></div><?php endforeach; ?>
        <?php if (!$report['view_breakdown']): ?><div class="empty">در این بازه بازدیدی ثبت نشده است.</div><?php endif; ?>
    </div></section>
</div>

<details class="card report-section legacy-report">
    <summary>گزارش‌های قدیمی استفاده و ورود مخاطبین</summary>
    <div class="report-kpis report-kpis-compact">
        <div class="legacy-stat"><span>لاگین کاربران داخلی ۳۰ روز</span><strong><?= e((string) $legacySummary['user_logins_30']) ?></strong></div>
        <div class="legacy-stat"><span>لاگین مخاطبین ۳۰ روز</span><strong><?= e((string) $legacySummary['contact_logins_30']) ?></strong></div>
        <div class="legacy-stat"><span>استفاده کاربران داخلی</span><strong><?= e((string) $legacySummary['user_usage_30']) ?></strong></div>
        <div class="legacy-stat"><span>استفاده مخاطبین</span><strong><?= e((string) $legacySummary['contact_usage_30']) ?></strong></div>
    </div>
    <div class="grid grid-2">
        <div><h3>ورود کاربران داخلی</h3><?php foreach ($userLogins as $row): ?><p><strong><?= e($row['name']) ?></strong><br><span class="muted">تعداد: <?= e((string) $row['total']) ?> | آخرین ورود: <?= e(fa_datetime($row['last_login'])) ?></span></p><?php endforeach; ?><?php if (!$userLogins): ?><div class="empty">داده‌ای ثبت نشده است.</div><?php endif; ?></div>
        <div><h3>ورود مخاطبین مشتری</h3><?php foreach ($contactLogins as $row): ?><p><strong><?= e($row['name']) ?></strong><br><span class="muted">تعداد: <?= e((string) $row['total']) ?> | آخرین ورود: <?= e(fa_datetime($row['last_login'])) ?></span></p><?php endforeach; ?><?php if (!$contactLogins): ?><div class="empty">داده‌ای ثبت نشده است.</div><?php endif; ?></div>
    </div>
    <h3>استفاده بر اساس بخش</h3><div class="table-wrap"><table><thead><tr><th>نوع کاربر</th><th>بخش</th><th>تعداد</th></tr></thead><tbody><?php foreach ($usageByArea as $row): ?><tr><td><?= e($row['actor_type'] === 'user' ? 'کاربر داخلی' : 'مخاطب مشتری') ?></td><td><?= e($row['area']) ?></td><td><?= e((string) $row['total']) ?></td></tr><?php endforeach; ?><?php if (!$usageByArea): ?><tr><td colspan="3" class="empty">داده‌ای ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div>
</details>
