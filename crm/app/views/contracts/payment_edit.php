<div class="toolbar"><h2>ویرایش دریافتی قرارداد</h2><a class="btn btn-light" href="<?= e(url('contracts', ['action' => 'show', 'id' => $contract['id']])) ?>">بازگشت</a></div>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form class="card" method="post">
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_payment_form.php'; ?>
    <div class="form-actions"><button class="btn btn-primary">ذخیره تغییرات</button></div>
</form>
