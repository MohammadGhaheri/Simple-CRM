<div class="toolbar">
    <h2>دعوتنامه مخاطبین - <?= e($customer['customer_name']) ?></h2>
    <a class="btn btn-light" href="<?= e(url('customers', ['action' => 'show', 'id' => $customer['id']])) ?>">بازگشت</a>
</div>

<div class="grid grid-2">
    <div class="card">
        <h3>لینک اختصاصی ثبت‌نام</h3>
        <p class="muted">این لینک فقط مخاطبین همین مشتری را به فرم ثبت‌نام وصل می‌کند. بعد از ثبت فرم، مخاطب غیرفعال می‌ماند تا پشتیبان اطلاعات را بررسی و حساب را فعال کند.</p>
        <label>لینک ثبت‌نام</label>
        <input class="ltr-field" dir="ltr" readonly value="<?= e($inviteUrl) ?>" data-copy-source="invite-url">
        <div class="form-actions">
            <button class="btn btn-primary" type="button" data-copy-button data-copy-target="invite-url" data-copy-label="کپی لینک">کپی لینک</button>
            <form method="post" data-confirm="لینک قبلی غیرفعال و لینک جدید ساخته شود؟">
                <?= csrf_field() ?>
                <button class="btn btn-light">ساخت لینک جدید</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h3>متن آماده ارسال</h3>
        <p class="muted">این متن را می‌توانید در پیامک یا پیام‌رسان برای مخاطبین مشتری ارسال کنید.</p>
        <textarea readonly rows="8" data-copy-source="invite-text"><?= e($inviteText) ?></textarea>
        <div class="form-actions">
            <button class="btn btn-primary" type="button" data-copy-button data-copy-target="invite-text" data-copy-label="کپی متن دعوتنامه">کپی متن دعوتنامه</button>
        </div>
    </div>
</div>
