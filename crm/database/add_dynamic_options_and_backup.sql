SET NAMES utf8mb4;
USE simple_crm;

ALTER TABLE customers
  MODIFY customer_type VARCHAR(80) NOT NULL DEFAULT 'Other',
  MODIFY interested_product VARCHAR(120) NOT NULL DEFAULT 'Other',
  MODIFY sales_status VARCHAR(80) NOT NULL DEFAULT 'New';

ALTER TABLE deals
  MODIFY product VARCHAR(120) NOT NULL DEFAULT 'Other',
  MODIFY deal_stage VARCHAR(80) NOT NULL DEFAULT 'Lead';

ALTER TABLE activities
  MODIFY activity_type VARCHAR(80) NOT NULL DEFAULT 'Follow-up',
  MODIFY status VARCHAR(80) NOT NULL DEFAULT 'Open';

ALTER TABLE tickets
  MODIFY category VARCHAR(80) NOT NULL DEFAULT 'Support',
  MODIFY priority VARCHAR(80) NOT NULL DEFAULT 'Normal',
  MODIFY status VARCHAR(80) NOT NULL DEFAULT 'Open';

INSERT INTO app_settings (setting_key, setting_value) VALUES
('currency_unit', 'ریال'),
('customer_code_mode', 'manual'),
('customer_code_format', 'CUS-{YYYY}-{SEQ4}'),
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
Support|پشتیبانی
Other|سایر'),
('options_activity_statuses', 'Open|باز
Done|انجام شده
Cancelled|لغو شده
Waiting|در انتظار'),
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
Other|سایر')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
