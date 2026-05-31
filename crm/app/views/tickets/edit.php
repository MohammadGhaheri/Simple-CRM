<div class="toolbar">
    <h2><?= e($ticket['ticket_code']) ?> - <?= e($ticket['subject']) ?></h2>
    <a class="btn btn-light" href="<?= e(url('tickets')) ?>">بازگشت</a>
</div>

<div class="grid grid-2">
    <div class="card">
        <h3>درخواست مشتری</h3>
        <div class="detail-list">
            <div><span>مشتری</span><?= e($ticket['customer_name']) ?></div>
            <div><span>مخاطب</span><?= e($ticket['contact_name']) ?></div>
            <div><span>ایجاد</span><?= e($ticket['created_at']) ?></div>
            <div><span>دسته</span><?= e(Ticket::label($ticket['category'])) ?></div>
            <div><span>اولویت</span><?= e(Ticket::label($ticket['priority'])) ?></div>
            <div><span>وضعیت</span><?= e(Ticket::label($ticket['status'])) ?></div>
        </div>
        <p><?= nl2br(e($ticket['description'])) ?></p>
    </div>

    <form class="card" method="post">
        <?= csrf_field() ?>
        <h3>پیگیری داخلی</h3>
        <div class="grid grid-2">
            <div><label>وضعیت</label><select name="status"><?php foreach (Ticket::statuses() as $option): ?><option value="<?= e($option) ?>" <?= selected($ticket['status'], $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
            <div><label>اولویت</label><select name="priority"><?php foreach (Ticket::priorities() as $option): ?><option value="<?= e($option) ?>" <?= selected($ticket['priority'], $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
            <div><label>دسته</label><select name="category"><?php foreach (Ticket::categories() as $option): ?><option value="<?= e($option) ?>" <?= selected($ticket['category'], $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
            <div><label>مسئول رسیدگی</label><select name="assigned_user_id"><option value="">بدون مسئول</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($ticket['assigned_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div style="margin-top:14px"><label>پاسخ / نتیجه رسیدگی</label><textarea name="response"><?= e($ticket['response'] ?? '') ?></textarea></div>
        <div class="form-actions"><button class="btn btn-primary">ذخیره</button></div>
    </form>
</div>
