<div class="toolbar">
    <h2><?= e($ticket['ticket_code']) ?> - <?= e($ticket['subject']) ?></h2>
    <a class="btn btn-light" href="<?= e(url('tickets')) ?>">بازگشت</a>
</div>

<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h3>درخواست مشتری</h3>
        <div class="detail-list">
            <div><span>مشتری</span><?= e($ticket['customer_name']) ?> <?= (int) ($ticket['is_vip'] ?? 0) === 1 ? '<span class="badge badge-warning">VIP</span>' : '' ?></div>
            <div><span>مخاطب</span><?= e($ticket['contact_name']) ?></div>
            <div><span>ایجاد</span><?= e(fa_datetime($ticket['created_at'])) ?></div>
            <div><span>دسته</span><?= e(Ticket::label($ticket['category'])) ?></div>
            <div><span>اولویت</span><?= e(Ticket::label($ticket['priority'])) ?></div>
            <div><span>وضعیت</span><span class="badge <?= e(badge_class($ticket['status'])) ?>"><?= e(Ticket::label($ticket['status'])) ?></span></div>
        </div>
        <p><?= nl2br(e($ticket['description'])) ?></p>
    </div>

    <form class="card" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="ticket_action" value="meta">
        <h3>مشخصات رسیدگی</h3>
        <div class="grid grid-2">
            <div><label>وضعیت</label><select name="status"><?php foreach (Ticket::statuses() as $option): ?><option value="<?= e($option) ?>" <?= selected($ticket['status'], $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
            <div><label>اولویت</label><select name="priority"><?php foreach (Ticket::priorities() as $option): ?><option value="<?= e($option) ?>" <?= selected($ticket['priority'], $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
            <div><label>دسته</label><select name="category"><?php foreach (Ticket::categories() as $option): ?><option value="<?= e($option) ?>" <?= selected($ticket['category'], $option) ?>><?= e(Ticket::label($option)) ?></option><?php endforeach; ?></select></div>
            <div><label>پشتیبان مسئول</label><select name="assigned_user_id"><option value="">بدون مسئول</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($ticket['assigned_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary">ذخیره مشخصات</button>
            <?php if (!Ticket::isClosed($ticket)): ?>
                <button class="btn btn-danger" name="ticket_action" value="close" data-confirm="این تیکت بسته شود؟">بستن تیکت</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card ticket-conversation-card" style="margin-top:16px">
    <h3>گفت‌وگوی تیکت</h3>
    <div class="ticket-thread ticket-thread-chat">
        <?php foreach ($messages as $message): ?>
            <?php $avatarPath = $message['sender_type'] === 'contact' ? ($message['contact_avatar_path'] ?? '') : ($message['user_avatar_path'] ?? ''); ?>
            <div class="ticket-message <?= e($message['sender_type'] === 'contact' ? 'from-contact' : 'from-user') ?>">
                <div class="ticket-avatar">
                    <?php if ($avatarPath): ?><img src="<?= e($avatarPath) ?>" alt=""><?php else: ?><?= e($message['sender_type'] === 'contact' ? 'م' : 'پ') ?><?php endif; ?>
                </div>
                <div class="ticket-bubble">
                <div class="ticket-message-head">
                    <strong><?= e($message['sender_type'] === 'contact' ? ($message['contact_name'] ?? 'مشتری') : ($message['user_name'] ?? 'پشتیبانی')) ?></strong>
                    <span><?= e(fa_datetime($message['created_at'])) ?></span>
                </div>
                <?php if (trim((string) $message['message']) !== ''): ?><p><?= nl2br(e($message['message'])) ?></p><?php endif; ?>
                <?php if (!empty($message['attachment_path'])): ?>
                    <a class="ticket-attachment" href="<?= e($message['attachment_path']) ?>" target="_blank" rel="noopener">
                        <img src="<?= e($message['attachment_path']) ?>" alt="">
                        <span><?= e($message['attachment_name'] ?: 'تصویر پیوست') ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($message['sender_type'] === 'user'): ?>
                    <div class="message-read-receipt <?= !empty($message['contact_read_at']) ? 'is-seen' : 'is-unseen' ?>">
                        <?php if (!empty($message['contact_read_at'])): ?>
                            دیده شده <?= e(fa_datetime($message['contact_read_at'])) ?>
                        <?php else: ?>
                            دیده نشده
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?><div class="empty">هنوز پیامی برای این تیکت ثبت نشده است.</div><?php endif; ?>
    </div>
</div>

<form class="card ticket-reply-card" style="margin-top:16px" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="ticket_action" value="reply">
    <?php if (Ticket::isClosed($ticket)): ?>
        <div class="empty">این تیکت بسته شده است. برای ادامه مکالمه ابتدا وضعیت را تغییر دهید.</div>
    <?php else: ?>
        <h3>ارسال پاسخ پشتیبانی</h3>
        <div class="ticket-reply-grid">
            <textarea name="message" placeholder="متن پاسخ یا توضیح تکمیلی..."></textarea>
        </div>
        <div class="ticket-upload-field">
            <label>تصویر پیوست</label>
            <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp">
            <span class="muted">حداکثر ۲ مگابایت. فرمت‌های مجاز: jpg، png، webp</span>
        </div>
        <div class="form-actions"><button class="btn btn-primary">ارسال پاسخ</button></div>
    <?php endif; ?>
</form>
