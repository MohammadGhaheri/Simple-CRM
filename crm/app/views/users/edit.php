<form class="card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_form.php'; ?>
</form>
