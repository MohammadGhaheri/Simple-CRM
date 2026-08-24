<div class="toolbar">
    <h2>ویرایش اطلاعیه</h2>
    <a class="btn btn-light" href="<?= e(url('announcements')) ?>">بازگشت</a>
</div>

<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form class="card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_form.php'; ?>
</form>
