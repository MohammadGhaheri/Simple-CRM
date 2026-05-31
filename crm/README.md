# Simple CRM

یک CRM سبک و ساده برای مدیریت مشتریان، مخاطب‌ها، فرصت‌های فروش، فعالیت‌های پیگیری و داشبورد فروش شرکت‌های خودروی متصل / تلماتیک.

## تکنولوژی‌ها

- PHP 8+
- MySQL / MariaDB
- PDO و Prepared Statements
- HTML, CSS, Vanilla JavaScript
- رابط فارسی و RTL
- نمایش تاریخ‌ها به صورت شمسی در رابط کاربری
- ذخیره تاریخ‌ها به صورت میلادی در MySQL
- بدون Laravel یا فریم‌ورک سنگین

## نصب با XAMPP یا Laragon

1. پوشه `crm` را داخل مسیر وب‌سرور قرار دهید.
   - XAMPP: `C:\xampp\htdocs\crm`
   - Laragon: `C:\laragon\www\crm`

2. MySQL را اجرا کنید و فایل‌های دیتابیس را وارد کنید:

```sql
SOURCE C:/xampp/htdocs/crm/database/schema.sql;
SOURCE C:/xampp/htdocs/crm/database/seed.sql;
```

یا از phpMyAdmin:

1. وارد phpMyAdmin شوید.
2. فایل `database/schema.sql` را Import کنید.
3. سپس فایل `database/seed.sql` را Import کنید.

3. اگر نام کاربری یا رمز MySQL شما متفاوت است، فایل زیر را تغییر دهید:

```text
app/config/database.php
```

تنظیم پیش‌فرض:

```php
'host' => '127.0.0.1',
'dbname' => 'simple_crm',
'username' => 'root',
'password' => '',
```

4. پروژه را باز کنید:

```text
http://localhost/crm/public/login.php
```

## ورود پیش‌فرض

- Email: `admin@simple-crm.local`
- Password: `Admin@12345`

رمز در `seed.sql` فقط به صورت hash شده ذخیره شده است.

## ساختار پروژه

```text
crm/
  app/
    config/database.php
    core/auth.php
    core/helpers.php
    core/csrf.php
    models/
    views/
  public/
    index.php
    login.php
    logout.php
    assets/css/style.css
    assets/js/app.js
  database/
    schema.sql
    seed.sql
```

## امکانات

- ورود و خروج با Session
- محافظت صفحات داخلی از کاربران مهمان
- مدیریت کاربران توسط مدیر سیستم شامل تعریف کاربر، نقش، رمز عبور و فعال/غیرفعال‌سازی
- CRUD مشتریان
- مخاطب‌های چندگانه برای هر مشتری
- CRUD فرصت‌های فروش با محاسبه `weighted_amount`
- CRUD فعالیت‌ها و پیگیری‌ها
- صفحه «برنامه کاری من» برای نمایش دستور کار هر اپراتور فروش و ثبت نتیجه پیگیری‌ها
- داشبورد فروش شامل KPI، پایپ‌لاین، فعالیت‌های اخیر و پیگیری‌های آینده
- فیلتر و جستجو برای مشتریان، فرصت‌ها و فعالیت‌ها
- CSRF token برای فرم‌های ایجاد، ویرایش و حذف
- Escape خروجی‌ها با `htmlspecialchars`

## تاریخ شمسی و میلادی

تمام ستون‌های تاریخ در دیتابیس با فرمت میلادی `YYYY-MM-DD` ذخیره می‌شوند. در رابط کاربری، تاریخ‌ها به صورت شمسی نمایش داده می‌شوند و کاربر می‌تواند تاریخ را با فرمت زیر وارد کند:

```text
1405/03/12
```

برنامه قبل از ذخیره، تاریخ شمسی را به میلادی تبدیل می‌کند.

## نکات توسعه

- نام جدول‌ها و شناسه‌های کد انگلیسی هستند.
- متن رابط کاربری فارسی است.
- برای افزودن نقش‌های کاربری، می‌توانید جدول `users` را با ستون‌هایی مثل `role` توسعه دهید.
- برای محیط production مقدار `display_errors` را خاموش کنید و اطلاعات دیتابیس را خارج از مسیر public نگه دارید.
