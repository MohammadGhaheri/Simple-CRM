<?php $activity = $activity ?? []; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="grid grid-3">
    <div><label class="required">مشتری</label><select required name="customer_id"><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>" <?= selected($activity['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>فرصت مرتبط</label><select name="deal_id"><option value="">بدون فرصت</option><?php foreach ($deals as $deal): ?><option value="<?= e((string) $deal['id']) ?>" <?= selected($activity['deal_id'] ?? '', $deal['id']) ?>><?= e($deal['deal_name']) ?> - <?= e($deal['customer_name']) ?></option><?php endforeach; ?></select></div>
    <input type="hidden" name="contract_id" value="<?= e((string) ($activity['contract_id'] ?? '')) ?>">
    <div><label class="required">تاریخ فعالیت</label><input required class="date-input" name="activity_date" placeholder="مثلا 1405/03/12" title="تاریخ را به شمسی و با فرمت 1405/03/12 وارد کنید" value="<?= e(fa_date($activity['activity_date'] ?? date('Y-m-d'))) ?>"></div>
    <div><label>نوع فعالیت</label><select name="activity_type"><?php foreach (activity_type_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($activity['activity_type'] ?? 'Follow-up', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select></div>
    <div><label>وضعیت</label><select name="status"><?php foreach (activity_status_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($activity['status'] ?? 'Open', $option) ?>><?= e(fa_label($option)) ?></option><?php endforeach; ?></select></div>
    <div><label>مالک</label><select name="owner_user_id"><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($activity['owner_user_id'] ?? current_user_id(), $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
    <div><label>تاریخ پیگیری بعدی</label><input class="date-input" name="next_followup_date" placeholder="مثلا 1405/03/15" title="تاریخ را به شمسی و با فرمت 1405/03/15 وارد کنید" value="<?= e(fa_date($activity['next_followup_date'] ?? '')) ?>"></div>
</div>
<div style="margin-top:14px"><label class="required">خلاصه</label><input required name="summary" value="<?= e($activity['summary'] ?? '') ?>"></div>
<div style="margin-top:14px"><label>اقدام بعدی</label><input name="next_action" value="<?= e($activity['next_action'] ?? '') ?>"></div>
<p><label><input type="checkbox" name="send_activity_email" value="1" <?= checked($activity['send_activity_email'] ?? false) ?> style="width:auto"> ارسال یادآوری ایمیلی به مخاطب اصلی مشتری</label></p>
<div style="margin-top:14px"><label>یادداشت</label><textarea name="notes"><?= e($activity['notes'] ?? '') ?></textarea></div>
<div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn btn-light" href="<?= e(url('activities')) ?>">انصراف</a></div>
