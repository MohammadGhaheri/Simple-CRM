<?php
// Financially detailed contract items are not exposed to support/operations.
$itemsRole = current_user_role();
if (in_array($itemsRole, ['admin', 'sales', 'finance'], true)):
    $itemRows = ContractItem::byContract((int) $contract['id']);
    $invoiceRefs = ContractInvoiceRef::byContract((int) $contract['id']);
    $invoiceLines = ContractInvoiceRef::allocationsByContract((int) $contract['id']);
    $itemAmendments = ContractItem::amendmentsForContract((int) $contract['id']);
    $canEditItems = in_array($itemsRole, ['admin', 'sales'], true)
        && ($itemsRole === 'admin' || (int) $contract['owner_user_id'] === current_user_id());
    $lineTotal = '0.00';
    foreach ($itemRows as $itemRow) {
        $lineTotal = money_add($lineTotal, (string) $itemRow['line_amount']);
    }
?>
<div class="card" style="margin-top:16px">
    <h3>اقلام قرارداد / فروش</h3>
    <p class="muted">اقلام در ردیف‌های مستقل نگهداری می‌شوند. مبلغ قرارداد منبع رسمی مالی باقی می‌ماند و مجموع اقلام نباید از آن بیشتر شود. قراردادهای قدیمی تا زمانی که به‌صورت دستی قلم‌بندی نشوند، بدون تغییر باقی می‌مانند.</p>
    <?php if (isset($_GET['item_saved']) || isset($_GET['item_amended']) || isset($_GET['invoice_saved']) || isset($_GET['invoice_allocated'])): ?>
        <div class="alert alert-success">تغییرات اقلام یا مرجع فاکتور با موفقیت ثبت شد.</div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['contract_items_error'])): ?>
        <div class="alert alert-danger"><?= e((string) $_SESSION['contract_items_error']) ?></div>
        <?php unset($_SESSION['contract_items_error']); ?>
    <?php endif; ?>
    <div class="table-wrap"><table>
        <thead><tr><th>ردیف</th><th>نوع</th><th>عنوان</th><th>مدل / کد</th><th>تعداد تعهد</th><th>قیمت واحد</th><th>مبلغ ردیف</th><th>نیاز به نصب</th><?php if ($canEditItems): ?><th>عملیات</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($itemRows as $item): ?>
            <tr>
                <td><?= e((string) $item['line_number']) ?></td>
                <td><?= e(['device' => 'دستگاه', 'product' => 'کالا', 'service' => 'خدمت'][$item['item_type']] ?? $item['item_type']) ?></td>
                <td><?= e($item['item_title']) ?></td>
                <td><?= e($item['sku'] ?: product_label($item['product'])) ?></td>
                <td><?= e((string) $item['quantity']) ?></td>
                <td><?= e(format_money($item['unit_amount'])) ?></td>
                <td><?= e(format_money($item['line_amount'])) ?></td>
                <td><?= !empty($item['requires_installation']) ? 'بله' : 'خیر' ?></td>
                <?php if ($canEditItems): ?>
                    <td>
                        <details>
                            <summary>اصلاح تعداد</summary>
                            <form method="post" action="<?= e(url('contracts', ['action' => 'item_amend', 'id' => $contract['id']])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                                <label>تعداد جدید</label>
                                <input type="number" name="new_quantity" min="1" required value="<?= e((string) $item['quantity']) ?>">
                                <label>دلیل اصلاحیه</label>
                                <input name="reason" maxlength="500" required placeholder="الزامی">
                                <button class="btn btn-small btn-primary" type="submit">ثبت اصلاحیه</button>
                            </form>
                        </details>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$itemRows): ?><div class="empty">برای این فروش هنوز هیچ ردیف کالا یا خدمتی ثبت نشده است.</div><?php endif; ?>
    <p class="muted">جمع اقلام: <strong><?= e(format_money($lineTotal)) ?></strong> — مبلغ قرارداد: <strong><?= e(format_money($contract['contract_amount'])) ?></strong></p>
    <?php if ($canEditItems): ?>
        <details style="margin-top:12px">
            <summary class="btn btn-primary">افزودن قلم کالا / خدمت</summary>
            <form method="post" action="<?= e(url('contracts', ['action' => 'item_add', 'id' => $contract['id']])) ?>" style="margin-top:12px">
                <?= csrf_field() ?>
                <div class="grid grid-3">
                    <div><label>نوع قلم</label><select name="item_type" required>
                        <option value="device">دستگاه</option><option value="product">کالا</option><option value="service">خدمت</option>
                    </select></div>
                    <div><label>دسته محصول</label><select name="product">
                        <?php foreach (product_options() as $option): ?>
                            <option value="<?= e($option) ?>"><?= e(product_label($option)) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div><label>مدل / SKU</label><input name="sku" maxlength="120" placeholder="مثلاً کد مدل محصول"></div>
                    <div><label class="required">عنوان قلم</label><input name="item_title" maxlength="190" required></div>
                    <div><label class="required">تعداد</label><input type="number" name="quantity" min="1" max="1000000000" required></div>
                    <div><label class="required">قیمت واحد</label><input name="unit_amount" inputmode="decimal" required value="0.00"></div>
                    <div><label>الزام نصب</label><select name="requires_installation"><option value="0">خیر</option><option value="1">بله (فقط دستگاه)</option></select></div>
                    <div><label>توضیحات</label><input name="notes"></div>
                </div>
                <p class="muted">مبلغ ردیف به‌صورت سیستمی از تعداد و قیمت واحد محاسبه می‌شود. برای اصلاح نوع یا مدل قلم پس از ثبت، ردیف جدید یا گردش کار اصلاحی نیاز است.</p>
                <button class="btn btn-primary" type="submit">ثبت قلم</button>
            </form>
        </details>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <h3>مراجع فاکتور فروش</h3>
    <p class="muted">این بخش فقط شماره فاکتور صادرشده در سیستم مالی را به اقلام متصل می‌کند و فاکتور حسابداری صادر نمی‌کند.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>شماره فاکتور</th><th>تاریخ</th></tr></thead>
        <tbody><?php foreach ($invoiceRefs as $invoice): ?>
            <tr><td><?= e($invoice['invoice_number']) ?></td><td><?= e(fa_date($invoice['invoice_date'])) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php if (!$invoiceRefs): ?><div class="empty">هنوز فاکتوری مرتبط نشده است.</div><?php endif; ?>
    <?php if ($canEditItems): ?>
        <details style="margin-top:12px">
            <summary class="btn btn-light">ثبت مرجع فاکتور</summary>
            <form method="post" action="<?= e(url('contracts', ['action' => 'invoice_add', 'id' => $contract['id']])) ?>">
                <?= csrf_field() ?>
                <div class="grid grid-2">
                    <div><label class="required">شماره فاکتور</label><input name="invoice_number" maxlength="120" required></div>
                    <div><label>تاریخ فاکتور</label><input name="invoice_date" class="date-input" placeholder="1405/08/11"></div>
                </div>
                <button class="btn btn-primary" type="submit">ثبت مرجع</button>
            </form>
        </details>
    <?php endif; ?>
    <h4>اقلام تخصیص‌یافته به فاکتورها</h4>
    <div class="table-wrap"><table>
        <thead><tr><th>فاکتور</th><th>قلم قرارداد</th><th>تعداد تخصیص</th></tr></thead>
        <tbody><?php foreach ($invoiceLines as $line): ?>
            <tr><td><?= e($line['invoice_number']) ?></td><td><?= e($line['item_title']) ?></td><td><?= e((string) $line['invoiced_quantity']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php if (!$invoiceLines): ?><div class="empty">هیچ قلمی به مرجع فاکتور تخصیص داده نشده است.</div><?php endif; ?>
    <?php if ($canEditItems && $itemRows && $invoiceRefs): ?>
        <details style="margin-top:12px">
            <summary class="btn btn-light">تخصیص تعداد به فاکتور</summary>
            <form method="post" action="<?= e(url('contracts', ['action' => 'invoice_allocate', 'id' => $contract['id']])) ?>">
                <?= csrf_field() ?>
                <div class="grid grid-3">
                    <div><label>فاکتور</label><select name="invoice_ref_id" required>
                        <?php foreach ($invoiceRefs as $invoice): ?>
                            <option value="<?= e((string) $invoice['id']) ?>"><?= e($invoice['invoice_number']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div><label>قلم</label><select name="contract_item_id" required>
                        <?php foreach ($itemRows as $item): ?>
                            <option value="<?= e((string) $item['id']) ?>"><?= e($item['item_title']) ?> (<?= e((string) $item['quantity']) ?>)</option>
                        <?php endforeach; ?>
                    </select></div>
                    <div><label>تعداد</label><input type="number" name="invoiced_quantity" min="1" max="1000000000" required></div>
                </div>
                <button class="btn btn-primary" type="submit">ثبت تخصیص</button>
            </form>
        </details>
    <?php endif; ?>
</div>

<?php if ($itemAmendments): ?>
<div class="card" style="margin-top:16px">
    <h3>تاریخچه اصلاحیه تعداد</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>ردیف</th><th>قبل</th><th>بعد</th><th>دلیل</th><th>ثبت‌کننده</th><th>تاریخ</th></tr></thead>
        <tbody><?php foreach ($itemAmendments as $am): ?>
            <tr><td><?= e($am['item_title']) ?></td><td><?= e((string) $am['old_quantity']) ?></td>
                <td><?= e((string) $am['new_quantity']) ?></td><td><?= e($am['reason']) ?></td>
                <td><?= e($am['actor_name'] ?: 'کاربر حذف‌شده') ?></td><td><?= e(fa_datetime($am['created_at'])) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php endif; ?>
<?php endif; ?>
