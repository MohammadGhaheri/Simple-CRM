<?php
$services = ['ifms'=>'iFMS','iotruck'=>'IoTruck','iotrailer'=>'IoTrailer','dsp'=>'DSP','tracking'=>'ردیابی','consultation'=>'مشاوره','other'=>'سایر'];
?>
<?php if (!empty($errors)): ?>
<div class="card" style="border-inline-start:4px solid #dc2626;margin-bottom:16px">
    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($action === 'show' && !empty($lead)): ?>
    <div class="card">
        <p><a href="<?= e(url('website_leads')) ?>">بازگشت به درخواست‌های وب‌سایت</a></p>
        <h2>درخواست شماره <?= (int)$lead['id'] ?> — <?= e($lead['contact_name']) ?></h2>
        <div class="grid grid-2">
            <p><b>شرکت:</b> <?= e($lead['company'] ?: '—') ?></p>
            <p><b>موبایل:</b> <?= e($lead['mobile']) ?></p>
            <p><b>ایمیل:</b> <?= e($lead['email'] ?: '—') ?></p>
            <p><b>منبع:</b> <?= e($lead['source']) ?></p>
            <p><b>محصول:</b> <?= e($services[$lead['service_code']] ?? $lead['service_code']) ?> (<?= e($lead['service_label']) ?>)</p>
            <p><b>ناوگان:</b> <?= e($lead['fleet_type_label'] ?: $lead['fleet_type']) ?></p>
            <p><b>تعداد خودرو:</b> <?= (int) $lead['vehicle_count'] ?></p>
            <p><b>تاریخ ثبت:</b> <?= e($lead['created_at']) ?> (UTC)</p>
            <p><b>صفحه:</b> <a target="_blank" rel="noopener" href="<?= e($lead['page_url']) ?>"><?= e($lead['page_url']) ?></a></p>
            <p><b>کمپین:</b> <?= e(implode(' / ', array_filter([$lead['utm_source'], $lead['utm_medium'], $lead['utm_campaign']]))) ?></p>
        </div>
        <p><strong>متن درخواست:</strong></p>
        <p style="white-space:pre-wrap"><?= e($lead['message'] ?: '—') ?></p>
        <p><small>شناسه ارسال: <?= e($lead['request_id']) ?></small></p>
        <?php if ($lead['status'] === 'converted'): ?>
            <p>این درخواست قبلاً تبدیل شده است. مشتری #<?= (int)$lead['converted_customer_id'] ?>
            <?php if ($lead['converted_deal_id']): ?> — فرصت #<?= (int)$lead['converted_deal_id'] ?><?php endif; ?></p>
        <?php else: ?>
            <h3>پیگیری و ارجاع</h3>
            <form method="post" action="<?= e(url('website_leads', ['action'=>'save','id'=>$lead['id']])) ?>">
                <?= csrf_field() ?>
                <div class="grid grid-2">
                    <label>وضعیت
                        <select name="status">
                            <?php foreach (WebsiteLead::STATUSES as $code=>$label): ?>
                                <?php if ($code === 'converted') continue; ?>
                                <option value="<?= e($code) ?>" <?= $lead['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>مسئول پیگیری
                        <select name="assigned_user_id">
                            <?php if (is_admin()): ?><option value="0">بدون مسئول</option><?php endif; ?>
                            <?php foreach ($salesUsers as $person): ?>
                                <?php if (!is_admin() && (int)$person['id'] !== current_user_id()) continue; ?>
                                <option value="<?= (int)$person['id'] ?>" <?= (int)$lead['assigned_user_id']===(int)$person['id'] ? 'selected' : '' ?>><?= e($person['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>زمان پیگیری بعدی (زمان محلی سرور)
                        <input type="datetime-local" name="next_followup_at"
                          value="<?= e($lead['next_followup_at'] ? str_replace(' ', 'T', substr($lead['next_followup_at'],0,16)) : '') ?>">
                    </label>
                </div>
                <p><label>نتیجه تماس
                    <textarea name="call_result" rows="4"><?= e($lead['call_result'] ?? '') ?></textarea>
                </label></p>
                <button class="btn btn-primary" type="submit">ثبت پیگیری</button>
            </form>
            <hr>
            <h3>تبدیل سرنخ به مشتری / فرصت</h3>
            <p>سیستم مشتریان احتمالی را بر اساس نام شرکت، موبایل و ایمیل پیشنهاد می‌دهد. در صورت وجود مشابه، از ساخت مشتری تکراری جلوگیری می‌شود.</p>
            <form method="post" action="<?= e(url('website_leads', ['action'=>'convert','id'=>$lead['id']])) ?>">
                <?= csrf_field() ?>
                <label>مشتری مقصد
                    <select name="customer_id">
                        <option value="0">ایجاد مشتری جدید (در صورت نبودن مشابه)</option>
                        <?php foreach ($possibleCustomers as $candidate): ?>
                            <option value="<?= (int)$candidate['id'] ?>"><?= e($candidate['customer_name']) ?> (#<?= (int)$candidate['id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p><label><input type="checkbox" name="create_deal" value="1" checked> ایجاد فرصت فروش با مرحله Lead</label></p>
                <button class="btn btn-primary" type="submit">تأیید و تبدیل</button>
            </form>
            <?php if (empty($possibleCustomers)): ?><p class="muted">مشتری مشابه قابل‌نمایشی برای این کاربر پیدا نشد.</p><?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="grid grid-2">
        <div class="card stat"><span>درخواست‌های جدید</span><strong><?= (int)$leadCounts['new'] ?></strong></div>
        <div class="card stat"><span>نیازمند پیگیری</span><strong><?= (int)$leadCounts['due'] ?></strong></div>
    </div>
    <div class="card" style="margin-top:16px">
        <h3>جست‌وجو و فیلتر</h3>
        <form method="get" action="index.php">
            <input type="hidden" name="page" value="website_leads">
            <div class="grid grid-4">
                <label>نام شرکت / شماره موبایل<input name="q" value="<?= e((string)($filters['q']??'')) ?>"></label>
                <label>وضعیت<select name="status"><option value="">همه</option>
                    <?php foreach (WebsiteLead::STATUSES as $code=>$label): ?>
                    <option value="<?= e($code) ?>" <?= ($filters['status']??'')===$code?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></label>
                <label>محصول<select name="service"><option value="">همه</option>
                    <?php foreach ($services as $code=>$label): ?>
                    <option value="<?= e($code) ?>" <?= ($filters['service']??'')===$code?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></label>
                <label>از تاریخ میلادی<input type="date" name="date_from" value="<?= e((string)($filters['date_from']??'')) ?>"></label>
                <label>تا تاریخ میلادی<input type="date" name="date_to" value="<?= e((string)($filters['date_to']??'')) ?>"></label>
            </div>
            <p><button class="btn btn-primary" type="submit">اعمال فیلتر</button></p>
        </form>
    </div>
    <div class="card" style="margin-top:16px;overflow-x:auto">
        <h3>درخواست‌های وب‌سایت</h3>
        <table>
            <thead><tr><th>تاریخ</th><th>نام / شرکت</th><th>موبایل</th><th>محصول</th><th>وضعیت</th><th>مسئول</th><th>پیگیری بعدی</th><th>منبع</th></tr></thead>
            <tbody>
            <?php foreach ($leads as $w): ?>
                <tr>
                    <td><?= e($w['created_at']) ?></td>
                    <td><a href="<?= e(url('website_leads',['action'=>'show','id'=>$w['id']])) ?>"><?= e($w['contact_name']) ?></a><br><?= e($w['company'] ?? '') ?></td>
                    <td><?= e($w['mobile']) ?></td>
                    <td><?= e($services[$w['service_code']] ?? $w['service_code']) ?></td>
                    <td><?= e(WebsiteLead::STATUSES[$w['status']] ?? $w['status']) ?></td>
                    <td><?= e($w['assigned_name'] ?: 'تخصیص‌نیافته') ?></td>
                    <td><?= e($w['next_followup_at'] ?: '—') ?></td>
                    <td><?= e($w['source']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$leads): ?><p class="muted">درخواستی با این فیلترها یافت نشد.</p><?php endif; ?>
    </div>
    <?php if (is_admin()): ?>
        <div class="card" style="margin-top:16px">
            <h3>تخصیص اولیه خودکار</h3>
            <p>درخواست‌های جدید در صورت انتخاب کارشناس فعال به‌طور خودکار به او تخصیص داده می‌شوند؛ در غیر این صورت در صف بدون مسئول باقی می‌مانند.</p>
            <form method="post" action="<?= e(url('website_leads',['action'=>'default_owner'])) ?>">
                <?= csrf_field() ?>
                <select name="default_owner_user_id"><option value="0">بدون مسئول (صف مشترک فروش)</option>
                    <?php foreach ($salesUsers as $person): ?>
                        <?php if ($person['role'] !== 'sales') continue; ?>
                        <option value="<?= (int)$person['id'] ?>" <?= (int)$defaultOwner===(int)$person['id']?'selected':'' ?>><?= e($person['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">ذخیره قانون تخصیص</button>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>
