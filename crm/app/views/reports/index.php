<div class="grid grid-4">
    <div class="card stat"><span>لاگین کاربران داخلی ۳۰ روز</span><strong><?= e((string) $summary['user_logins_30']) ?></strong></div>
    <div class="card stat"><span>لاگین مخاطبین ۳۰ روز</span><strong><?= e((string) $summary['contact_logins_30']) ?></strong></div>
    <div class="card stat"><span>استفاده کاربران داخلی</span><strong><?= e((string) $summary['user_usage_30']) ?></strong></div>
    <div class="card stat"><span>استفاده مخاطبین</span><strong><?= e((string) $summary['contact_usage_30']) ?></strong></div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h3>لاگین کاربران داخلی</h3>
        <?php foreach ($userLogins as $row): ?>
            <p><strong><?= e($row['name']) ?></strong><br><span class="muted">تعداد: <?= e((string) $row['total']) ?> | آخرین ورود: <?= e($row['last_login']) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$userLogins): ?><div class="empty">هنوز لاگینی ثبت نشده است.</div><?php endif; ?>
    </div>
    <div class="card">
        <h3>لاگین مخاطبین مشتری</h3>
        <?php foreach ($contactLogins as $row): ?>
            <p><strong><?= e($row['name']) ?></strong><br><span class="muted">تعداد: <?= e((string) $row['total']) ?> | آخرین ورود: <?= e($row['last_login']) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$contactLogins): ?><div class="empty">هنوز لاگینی ثبت نشده است.</div><?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h3>استفاده بر اساس بخش</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>نوع کاربر</th><th>بخش</th><th>تعداد استفاده</th></tr></thead>
            <tbody>
            <?php foreach ($usageByArea as $row): ?>
                <tr><td><?= e($row['actor_type'] === 'user' ? 'کاربر داخلی' : 'مخاطب مشتری') ?></td><td><?= e($row['area']) ?></td><td><?= e((string) $row['total']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$usageByArea): ?><tr><td colspan="3" class="empty">هنوز مصرفی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
