<form class="card" method="post">
    <?= csrf_field() ?>
    <?php if (!empty($activity['complete_id'])): ?>
        <input type="hidden" name="complete_id" value="<?= e((string) $activity['complete_id']) ?>">
        <div class="alert alert-info">نتیجه این پیگیری را ثبت کنید. پس از ذخیره، فعالیت قبلی از برنامه کاری شما خارج می‌شود.</div>
    <?php endif; ?>
    <?php require __DIR__ . '/_form.php'; ?>
</form>
