<?php $contract = $contract ?? []; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="grid grid-3">
    <div><label class="required">نوع ثبت</label><select name="contract_type" data-contract-type><?php foreach (Contract::types() as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($contract['contract_type'] ?? 'formal', $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label class="required" data-contract-required-label>شماره قرارداد</label><input data-contract-required name="contract_number" value="<?= e($contract['contract_number'] ?? '') ?>"></div>
    <div><label class="required">عنوان قرارداد / فروش</label><input required name="contract_title" value="<?= e($contract['contract_title'] ?? '') ?>"></div>
    <div><label class="required">مشتری</label><select required name="customer_id"><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>" <?= selected($contract['customer_id'] ?? '', $customer['id']) ?>><?= e($customer['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>فرصت مرتبط</label><select name="deal_id"><option value="">بدون فرصت</option><?php foreach ($deals as $deal): ?><option value="<?= e((string) $deal['id']) ?>" <?= selected($contract['deal_id'] ?? '', $deal['id']) ?>><?= e($deal['deal_name']) ?> - <?= e($deal['customer_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>محصول / خدمت</label><select name="product"><?php foreach (product_options() as $option): ?><option value="<?= e($option) ?>" <?= selected($contract['product'] ?? '', $option) ?>><?= e(product_label($option)) ?></option><?php endforeach; ?></select></div>
    <div><label>تعداد خودرو در قرارداد</label><input type="number" min="0" name="vehicle_count" value="<?= e((string) ($contract['vehicle_count'] ?? 0)) ?>"></div>
    <div><label>مبلغ قرارداد / فروش</label><input type="number" min="0" step="0.01" name="contract_amount" value="<?= e((string) ($contract['contract_amount'] ?? 0)) ?>"></div>
    <div><label>تاریخ شروع</label><input class="date-input" name="start_date" placeholder="مثلا 1405/04/01" value="<?= e(fa_date($contract['start_date'] ?? '')) ?>"></div>
    <div><label class="required" data-contract-required-label>تاریخ پایان</label><input data-contract-required class="date-input" name="end_date" placeholder="برای فروش مستقیم اختیاری است" value="<?= e(fa_date($contract['end_date'] ?? '')) ?>"></div>
    <div data-renewal-field><label>تاریخ یادآوری تمدید</label><input class="date-input" name="renewal_reminder_date" placeholder="خالی = محاسبه خودکار" value="<?= e(fa_date($contract['renewal_reminder_date'] ?? '')) ?>"></div>
    <div>
        <label>وضعیت قرارداد / فروش</label>
        <select name="status">
            <?php foreach (Contract::statuses() as $status): ?><option value="<?= e($status) ?>" data-formal-status="<?= in_array($status, ['Active', 'Cancelled'], true) ? '0' : '1' ?>" <?= selected($contract['status'] ?? 'Active', $status) ?>><?= e(fa_label($status)) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div><label>مسئول قرارداد / فروش</label><select name="owner_user_id"><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= selected($contract['owner_user_id'] ?? current_user_id(), $user['id']) ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
</div>
<p class="muted" data-renewal-field>برای قرارداد رسمی، تاریخ یادآوری خالی به‌صورت خودکار از تاریخ پایان محاسبه می‌شود. فروش بدون قرارداد رسمی فعالیت تمدید ندارد.</p>
<div style="margin-top:14px"><label>یادداشت</label><textarea name="notes"><?= e($contract['notes'] ?? '') ?></textarea></div>
<?php if (($action ?? '') === 'create'): ?>
<fieldset class="card" style="margin-top:16px">
    <legend>اقلام قرارداد / فروش (اختیاری)</legend>
    <p class="muted">می‌توانید اقلام را همین حالا همراه قرارداد ثبت کنید؛ همه موارد یک‌جا ذخیره می‌شوند. ردیف خالی نادیده گرفته می‌شود و ردیف ناقص مانع ثبت خواهد شد. امکان افزودن اقلام در صفحه قرارداد نیز باقی است.</p>
    <div data-contract-new-items>
    <?php
    $draftRows = (isset($contract['items']) && is_array($contract['items'])) ? $contract['items'] : [[]];
    foreach ($draftRows as $i => $draft):
        if (!is_array($draft)) { continue; }
    ?>
        <div class="card" style="margin-top:12px">
            <strong>قلم <?= e((string) ((int) $i + 1)) ?></strong>
            <div class="grid grid-3">
                <div><label>نوع قلم</label><select name="items[<?= e((string) $i) ?>][item_type]">
                    <?php foreach (['device' => 'دستگاه', 'product' => 'کالا', 'service' => 'خدمت'] as $type => $typeLabel): ?>
                        <option value="<?= e($type) ?>" <?= selected($draft['item_type'] ?? 'device', $type) ?>><?= e($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div><label>دسته محصول</label><select name="items[<?= e((string) $i) ?>][product]">
                    <option value="">انتخاب نشده</option>
                    <?php foreach (product_options() as $product): ?>
                        <option value="<?= e($product) ?>" <?= selected($draft['product'] ?? '', $product) ?>><?= e(product_label($product)) ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div><label>مدل / SKU</label><input maxlength="120" name="items[<?= e((string) $i) ?>][sku]" value="<?= e((string) ($draft['sku'] ?? '')) ?>"></div>
                <div><label>عنوان قلم</label><input maxlength="190" name="items[<?= e((string) $i) ?>][item_title]" value="<?= e((string) ($draft['item_title'] ?? '')) ?>"></div>
                <div><label>تعداد</label><input type="number" min="1" max="1000000000" name="items[<?= e((string) $i) ?>][quantity]" value="<?= e((string) ($draft['quantity'] ?? '')) ?>"></div>
                <div><label>قیمت واحد</label><input inputmode="decimal" name="items[<?= e((string) $i) ?>][unit_amount]" value="<?= e((string) ($draft['unit_amount'] ?? '')) ?>"></div>
                <div><label>نیاز به نصب</label><select name="items[<?= e((string) $i) ?>][requires_installation]">
                    <option value="0">خیر</option>
                    <option value="1" <?= selected($draft['requires_installation'] ?? '0', '1') ?>>بله (فقط دستگاه)</option>
                </select></div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-light" data-add-contract-row style="margin-top:12px">افزودن ردیف دیگر</button>
    <template data-contract-row-template>
        <div class="card" style="margin-top:12px">
            <strong>قلم جدید</strong>
            <div class="grid grid-3">
                <div><label>نوع قلم</label><select name="items[__INDEX__][item_type]"><option value="device">دستگاه</option><option value="product">کالا</option><option value="service">خدمت</option></select></div>
                <div><label>دسته محصول</label><select name="items[__INDEX__][product]">
                    <option value="">انتخاب نشده</option>
                    <?php foreach (product_options() as $product): ?>
                        <option value="<?= e($product) ?>"><?= e(product_label($product)) ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div><label>مدل / SKU</label><input name="items[__INDEX__][sku]" maxlength="120"></div>
                <div><label>عنوان قلم</label><input name="items[__INDEX__][item_title]" maxlength="190"></div>
                <div><label>تعداد</label><input name="items[__INDEX__][quantity]" type="number" min="1" max="1000000000"></div>
                <div><label>قیمت واحد</label><input name="items[__INDEX__][unit_amount]" inputmode="decimal"></div>
                <div><label>نیاز به نصب</label><select name="items[__INDEX__][requires_installation]"><option value="0">خیر</option><option value="1">بله (فقط دستگاه)</option></select></div>
            </div>
        </div>
    </template>
    <script>
    (() => {
        const container = document.querySelector('[data-contract-new-items]');
        const trigger = document.querySelector('[data-add-contract-row]');
        const template = document.querySelector('[data-contract-row-template]');
        if (!container || !trigger || !template) return;
        let nextIndex = <?= e((string) (count($draftRows) ? max(array_map('intval', array_keys($draftRows))) + 1 : 0)) ?>;
        trigger.addEventListener('click', () => {
            if (container.children.length >= 40) return;
            container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        });
    })();
    </script>
</fieldset>
<?php else: ?>
<p class="muted">اقلام دستگاه و خدمت را در صفحه جزئیات قرارداد ثبت یا اصلاح کنید. تغییرات تعداد با دلیل و تاریخچه ذخیره می‌شوند.</p>
<?php endif; ?>
<div class="form-actions">
    <button class="btn btn-primary" type="submit">ذخیره</button>
    <a class="btn btn-light" href="<?= e(url('contracts')) ?>">انصراف</a>
</div>
