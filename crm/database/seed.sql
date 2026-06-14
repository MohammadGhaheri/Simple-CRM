SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;
USE simple_crm;

INSERT INTO users (id, name, email, password_hash, role, is_active) VALUES
(1, 'مدیر سیستم', 'admin@simple-crm.local', '$2y$10$MA2Q2PucE9mu9SvnsuoY.e96PJM.qzV/LHTxwMuFRJHqHX.xAAE3S', 'admin', 1);

INSERT INTO app_settings (setting_key, setting_value) VALUES
('app_title', 'Elm Simple CRM'),
('app_subtitle', 'مدیریت مشتریان، فرصت‌ها و پیگیری‌های فروش'),
('primary_color', '#155eef'),
('sidebar_color', '#111827'),
('app_icon', ''),
('home_title', 'Elm Simple CRM'),
('home_text', 'یک سامانه سبک برای مدیریت مشتریان، مخاطبین، فرصت‌های فروش، پیگیری‌ها و تیکت‌های مشتریان.'),
('show_about_page', '1'),
('currency_unit', 'ریال'),
('contract_renewal_reminder_days', '30'),
('customer_code_mode', 'manual'),
('customer_code_format', 'CUS-{YYYY}-{SEQ4}'),
('contact_invite_message_template', 'سلام، شما به عنوان همکار {customer_name} برای ایجاد حساب کاربری در سامانه {app_title} دعوت شده‌اید.
لینک اختصاصی ثبت‌نام:
{invite_url}'),
('contact_activation_auto_reply_enabled', '1'),
('contact_activation_auto_reply_template', 'با سلام، احتراما پروفایل شما تایید و فعال شد
با سپاس {support_name}'),
('options_customer_types', 'B2B Fleet|ناوگان سازمانی
B2C Owner|مالک شخصی
B2D Dealer|نمایندگی
OEM|خودروساز
Strategic Partner|شریک راهبردی
Other|سایر'),
('options_sales_statuses', 'New|جدید
Contacted|تماس گرفته شده
Meeting Scheduled|جلسه تنظیم شده
Proposal Sent|پیشنهاد ارسال شده
Negotiation|مذاکره
Won|برنده
Lost|از دست رفته
Inactive|غیرفعال'),
('options_products', 'FMS|FMS
TBox|TBox
Connected Vehicle Platform|Connected Vehicle Platform
Owner App|Owner App
API Integration|API Integration
Dashboard / BI|Dashboard / BI
onCloud|onCloud
onPremises|onPremises
Other|سایر'),
('options_deal_stages', 'Lead|سرنخ
Qualified|تایید شده
Proposal|پیشنهاد
Negotiation|مذاکره
Won|برنده
Lost|از دست رفته'),
('options_activity_types', 'Call|تماس
Meeting|جلسه
WhatsApp / Message|پیام
Email|ایمیل
Proposal Sent|پیشنهاد ارسال شده
Demo|دمو
Follow-up|پیگیری
Contract|قرارداد
Contract Renewal|تمدید قرارداد
Support|پشتیبانی
Other|سایر'),
('options_activity_statuses', 'Open|باز
Done|انجام شده
Cancelled|لغو شده
Waiting|در انتظار'),
('options_contract_statuses', 'Active|فعال
Renewal Due|نیازمند تمدید
Renewed|تمدید شده
Expired|منقضی شده
Cancelled|لغو شده'),
('options_ticket_statuses', 'Open|باز
In Progress|در حال بررسی
Waiting Customer|در انتظار مشتری
Resolved|حل شده
Closed|بسته'),
('options_ticket_priorities', 'Low|کم
Normal|عادی
High|زیاد
Urgent|فوری'),
('options_ticket_categories', 'Support|پشتیبانی
Request|درخواست
Bug|خطا
Training|آموزش
Billing|مالی
Other|سایر'),
('sms_enabled', '0'),
('sms_ticket_created_enabled', '0'),
('sms_ticket_answered_enabled', '0'),
('sms_portal_credentials_enabled', '1'),
('sms_portal_credentials_template', 'سلام {contact_name}
دسترسی شما به پرتال مشتری {app_title} فعال شد.
نام کاربری: {email}
رمز عبور: {password}
ورود: {portal_url}'),
('sms_daily_summary_enabled', '0'),
('sms_api_key', ''),
('sms_line_number', ''),
('sms_admin_mobile', ''),
('sms_default_assigned_user_id', ''),
('portal_public_url', ''),
('email_enabled', '0'),
('email_transport', 'mail'),
('email_from_name', 'Elm Simple CRM'),
('email_from_address', ''),
('email_smtp_host', ''),
('email_smtp_port', '587'),
('email_smtp_username', ''),
('email_smtp_password', ''),
('email_smtp_encryption', 'tls'),
('email_test_recipient', ''),
('email_portal_credentials_enabled', '1'),
('email_ticket_answered_enabled', '1'),
('email_activity_reminder_enabled', '1'),
('email_portal_credentials_subject', 'اطلاعات ورود پرتال مشتری'),
('email_portal_credentials_template', 'سلام {contact_name}
دسترسی شما به پرتال مشتری {app_title} فعال شد.
نام کاربری: {email}
رمز عبور: {password}
ورود: {portal_url}');

INSERT INTO customers (id, customer_code, customer_name, customer_type, industry, city, lead_source, interested_product, vehicle_count, estimated_contract_value, sales_status, owner_user_id, last_followup_date, next_followup_date, notes) VALUES
(1, 'SC-1001', 'ناوگان لجستیک آریا ترابر', 'B2B Fleet', 'حمل و نقل و پخش', 'تهران', 'نمایشگاه حمل و نقل و لجستیک', 'FMS', 180, 12500000000, 'Negotiation', 1, '2026-05-20', '2026-06-03', 'نیاز اصلی مشتری پایش لحظه‌ای ناوگان، کنترل مصرف سوخت، هشدار توقف غیرمجاز و گزارش رفتار راننده است.'),
(2, 'SC-1002', 'گروه خودروسازی البرز موتور', 'OEM', 'خودروسازی', 'کرج', 'معرفی از واحد توسعه محصول', 'Connected Vehicle Platform', 5000, 85000000000, 'Proposal Sent', 1, '2026-05-18', '2026-06-05', 'در حال بررسی معماری پلتفرم خودروی متصل، نصب TBox و اتصال API به سامانه‌های داخلی خودروساز.'),
(3, 'SC-1003', 'نمایندگی مرکزی شرق خودرو', 'B2D Dealer', 'فروش و خدمات پس از فروش', 'مشهد', 'فرم درخواست وب‌سایت', 'Owner App', 320, 6800000000, 'Contacted', 1, '2026-05-23', '2026-06-02', 'مشتری به اپلیکیشن مالک، اعلان وضعیت خودرو و سرویس‌های ارزش افزوده برای خریداران خودرو علاقه‌مند است.'),
(4, 'SC-1004', 'همراه داده هوشمند ایرانیان', 'Strategic Partner', 'فناوری اطلاعات و تحلیل داده', 'تهران', 'شبکه همکاران راهبردی', 'API Integration', 0, 18000000000, 'Meeting Scheduled', 1, '2026-05-25', '2026-06-01', 'پتانسیل همکاری در یکپارچه‌سازی داده‌های تلماتیک، داشبورد BI و ارائه API به مشتریان سازمانی.');

INSERT INTO contacts (customer_id, contact_name, position, mobile, phone, email, portal_enabled, password_hash, is_primary, notes) VALUES
(1, 'رضا محمدی', 'مدیر عملیات ناوگان', '09120000001', '02144000001', 'reza.mohammadi@example.com', 1, '$2y$10$mnF.X3Ttd5vSqxYfNny61ePYeI9Ox30SCuUD69XkszHS8Z/jGteWi', 1, 'تصمیم‌گیر اصلی برای نیازهای عملیاتی ناوگان.'),
(2, 'سارا احمدی', 'مدیر محصول خودروهای متصل', '09120000002', '02634000002', 'sara.ahmadi@example.com', 1, '$2y$10$mnF.X3Ttd5vSqxYfNny61ePYeI9Ox30SCuUD69XkszHS8Z/jGteWi', 1, 'هماهنگ‌کننده جلسه‌های فنی با تیم نرم‌افزار و سخت‌افزار.'),
(3, 'مهدی کریمی', 'مدیر فروش نمایندگی', '09120000003', '05137000003', 'mahdi.karimi@example.com', 0, NULL, 1, 'پیگیر سرویس‌های قابل فروش به مشتری نهایی.'),
(4, 'نسترن رضایی', 'مدیر توسعه کسب‌وکار', '09120000004', '02188000004', 'nastaran.rezaei@example.com', 0, NULL, 1, 'مسئول مذاکره چارچوب همکاری و مدل درآمدی.');

UPDATE customers SET is_vip = 1 WHERE id = 1;
UPDATE contacts SET default_support_user_id = 1 WHERE id IN (1, 2);

INSERT INTO deals (id, deal_name, customer_id, product, vehicle_count, estimated_amount, probability, weighted_amount, deal_stage, expected_close_date, owner_user_id, win_loss_reason, notes) VALUES
(1, 'استقرار سامانه FMS برای ناوگان آریا', 1, 'FMS', 180, 12500000000, 65, 8125000000, 'Negotiation', '2026-06-25', 1, '', 'پیشنهاد onCloud همراه با داشبورد مدیر ناوگان و گزارش مصرف سوخت ارائه شده است.'),
(2, 'پلتفرم خودروی متصل برای البرز موتور', 2, 'Connected Vehicle Platform', 5000, 85000000000, 45, 38250000000, 'Proposal', '2026-07-15', 1, '', 'پیشنهاد شامل TBox، سرویس API، پنل پایش و لایه تحلیل داده است.'),
(3, 'راه‌اندازی اپلیکیشن مالک برای نمایندگی شرق', 3, 'Owner App', 320, 6800000000, 30, 2040000000, 'Qualified', '2026-06-30', 1, '', 'مرحله بعدی ارائه دمو به تیم فروش و خدمات پس از فروش نمایندگی است.'),
(4, 'یکپارچه‌سازی API با همراه داده', 4, 'API Integration', 0, 18000000000, 50, 9000000000, 'Lead', '2026-07-05', 1, '', 'در حال بررسی مدل همکاری و دسترسی کنترل‌شده به داده‌های تلماتیک.');

INSERT INTO activities (customer_id, deal_id, activity_date, activity_type, summary, next_action, next_followup_date, owner_user_id, status, notes) VALUES
(1, 1, '2026-05-20', 'Meeting', 'جلسه کشف نیاز با مدیر عملیات ناوگان برگزار شد.', 'ارسال نسخه نهایی پیشنهاد مالی و زمان‌بندی پایلوت', '2026-06-03', 1, 'Open', 'مشتری روی گزارش مصرف سوخت و هشدار توقف غیرمجاز حساس است.'),
(2, 2, '2026-05-18', 'Proposal Sent', 'پیشنهاد اولیه پلتفرم خودروی متصل ارسال شد.', 'هماهنگی دمو فنی برای تیم محصول و IT', '2026-06-05', 1, 'Waiting', 'در جلسه بعدی باید سناریوی تبادل داده API مرور شود.'),
(3, 3, '2026-05-23', 'Call', 'تماس اولیه با مدیر فروش نمایندگی انجام شد.', 'ارسال معرفی اپ مالک و بسته پیشنهادی فروش', '2026-06-02', 1, 'Open', 'مشتری نمونه صفحه وضعیت خودرو و اعلان سرویس دوره‌ای می‌خواهد.'),
(4, 4, '2026-05-25', 'Email', 'معرفی API، داشبورد BI و مدل همکاری ارسال شد.', 'برگزاری جلسه مشترک فنی و تجاری', '2026-06-01', 1, 'Open', 'احتمال همکاری در پروژه‌های سازمانی مشترک وجود دارد.');

INSERT INTO tickets (ticket_code, customer_id, contact_id, subject, category, priority, status, description, assigned_user_id) VALUES
('TCK-1001', 1, 1, 'درخواست بررسی گزارش مصرف سوخت', 'Support', 'Normal', 'Open', 'در گزارش مصرف سوخت برخی خودروها داده روز گذشته دیده نمی‌شود. لطفا بررسی شود.', 1),
('TCK-1002', 2, 2, 'درخواست جلسه آموزشی API', 'Training', 'High', 'In Progress', 'برای تیم فنی نیاز به یک جلسه آموزشی درباره endpointهای اصلی API داریم.', 1);

INSERT INTO ticket_messages (ticket_id, sender_type, sender_contact_id, message, created_at) VALUES
(1, 'contact', 1, 'در گزارش مصرف سوخت برخی خودروها داده روز گذشته دیده نمی‌شود. لطفا بررسی شود.', NOW()),
(2, 'contact', 2, 'برای تیم فنی نیاز به یک جلسه آموزشی درباره endpointهای اصلی API داریم.', NOW());
