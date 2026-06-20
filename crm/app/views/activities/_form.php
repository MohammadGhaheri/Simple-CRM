<?php
$activity = $activity ?? [];
$selectedCustomerName = '';
foreach ($customers as $customerOption) {
    if ((string) ($activity['customer_id'] ?? '') === (string) $customerOption['id']) {
        $selectedCustomerName = (string) $customerOption['customer_name'];
        break;
    }
}
?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div data-activity-dependent-form>
    <div class="grid grid-2">
        <div class="customer-autocomplete">
            <label>جستجوی سریع مشتری</label>
            <input type="search" value="<?= e($selectedCustomerName) ?>" placeholder="نام، کد یا شهر مشتری..." autocomplete="off" data-customer-search>
            <div class="autocomplete-results" data-customer-results hidden></div>
            <span class="muted">می‌توانید جستجو کنید یا از فهرست مشتریان انتخاب کنید.</span>
        </div>
        <div>
            <label class="required">انتخاب مشتری</label>
            <select required name="customer_id" data-customer-select>
                <option value="">انتخاب مشتری</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e((string) $customer['id']) ?>" <?= selected($activity['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?><?= $customer['customer_code'] ? ' - ' . e($customer['customer_code']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="grid grid-3" style="margin-top:14px">
        <div>
            <label>فرصت مرتبط</label>
            <select name="deal_id" data-dependent-select="deals" data-selected="<?= e((string) ($activity['deal_id'] ?? '')) ?>">
                <option value="">بدون فرصت</option>
                <?php foreach ($deals as $deal): ?><option value="<?= e((string) $deal['id']) ?>" <?= selected($activity['deal_id'] ?? '', $deal['id']) ?>><?= e($deal['deal_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>قرارداد مرتبط</label>
            <select name="contract_id" data-dependent-select="contracts" data-selected="<?= e((string) ($activity['contract_id'] ?? '')) ?>">
                <option value="">بدون قرارداد</option>
                <?php foreach (($contracts ?? []) as $contract): ?><option value="<?= e((string) $contract['id']) ?>" <?= selected($activity['contract_id'] ?? '', $contract['id']) ?>><?= e($contract['contract_title']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label class="required">تاریخ فعالیت</label><input required class="date-input" name="activity_date" placeholder="مثلا 1405/03/12" title="تاریخ را به شمسی و با فرمت 1405/03/12 وارد کنید" value="<?= e(fa_date($activity['activity_date'] ?? date('Y-m-d'))) ?>"></div>
        <div><label>نوع فعالیت</label><select name="activity_type"><?php foreach (activity_type_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($activity['activity_type'] ?? 'Follow-up', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select></div>
        <div><label>وضعیت</label><select name="status"><?php foreach (activity_status_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($activity['status'] ?? 'Open', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select></div>
        <div><label>مالک</label><select name="owner_user_id"><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($activity['owner_user_id'] ?? current_user_id(), $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
        <div><label>تاریخ پیگیری بعدی</label><input class="date-input" name="next_followup_date" placeholder="مثلا 1405/03/15" title="تاریخ را به شمسی و با فرمت 1405/03/15 وارد کنید" value="<?= e(fa_date($activity['next_followup_date'] ?? '')) ?>"></div>
    </div>
</div>

<div style="margin-top:14px"><label class="required">خلاصه</label><input required name="summary" value="<?= e($activity['summary'] ?? '') ?>"></div>
<div style="margin-top:14px"><label>اقدام بعدی</label><input name="next_action" value="<?= e($activity['next_action'] ?? '') ?>"></div>

<div class="grid grid-2" style="margin-top:14px">
    <div>
        <label>فایل پیوست</label>
        <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx">
        <span class="muted">حداکثر ۵ مگابایت؛ تصویر، PDF یا فایل Word.</span>
        <?php if (!empty($activity['attachment_path'])): ?>
            <div class="current-attachment">
                <a href="<?= e(url('activities', ['action' => 'attachment', 'id' => $activity['id']])) ?>"><?= e($activity['attachment_name'] ?: 'دریافت فایل فعلی') ?></a>
                <label><input type="checkbox" name="remove_attachment" value="1" style="width:auto"> حذف فایل فعلی</label>
            </div>
        <?php endif; ?>
    </div>
    <div class="activity-flags">
        <label><input type="checkbox" name="is_internal_task" value="1" <?= checked($activity['is_internal_task'] ?? false) ?> style="width:auto"> این فعالیت یک تسک داخلی است</label>
        <label><input type="checkbox" name="send_activity_email" value="1" <?= checked($activity['send_activity_email'] ?? false) ?> style="width:auto"> ارسال یادآوری ایمیلی به مخاطب اصلی مشتری</label>
    </div>
</div>

<div style="margin-top:14px"><label>یادداشت</label><textarea name="notes"><?= e($activity['notes'] ?? '') ?></textarea></div>
<div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn btn-light" href="<?= e(url('activities')) ?>">انصراف</a></div>
