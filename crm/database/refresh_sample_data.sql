SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;
USE mammut_connect_crm;

UPDATE users
SET name = 'مدیر سیستم'
WHERE id = 1 AND email = 'admin@mammutconnect.local';

UPDATE customers SET
  customer_name = 'ناوگان لجستیک آریا ترابر',
  industry = 'حمل و نقل و پخش',
  city = 'تهران',
  lead_source = 'نمایشگاه حمل و نقل و لجستیک',
  notes = 'نیاز اصلی مشتری پایش لحظه‌ای ناوگان، کنترل مصرف سوخت، هشدار توقف غیرمجاز و گزارش رفتار راننده است.'
WHERE customer_code = 'MC-1001';

UPDATE customers SET
  customer_name = 'گروه خودروسازی البرز موتور',
  industry = 'خودروسازی',
  city = 'کرج',
  lead_source = 'معرفی از واحد توسعه محصول',
  notes = 'در حال بررسی معماری پلتفرم خودروی متصل، نصب TBox و اتصال API به سامانه‌های داخلی خودروساز.'
WHERE customer_code = 'MC-1002';

UPDATE customers SET
  customer_name = 'نمایندگی مرکزی شرق خودرو',
  industry = 'فروش و خدمات پس از فروش',
  city = 'مشهد',
  lead_source = 'فرم درخواست وب‌سایت',
  notes = 'مشتری به اپلیکیشن مالک، اعلان وضعیت خودرو و سرویس‌های ارزش افزوده برای خریداران خودرو علاقه‌مند است.'
WHERE customer_code = 'MC-1003';

UPDATE customers SET
  customer_name = 'همراه داده هوشمند ایرانیان',
  industry = 'فناوری اطلاعات و تحلیل داده',
  city = 'تهران',
  lead_source = 'شبکه همکاران راهبردی',
  notes = 'پتانسیل همکاری در یکپارچه‌سازی داده‌های تلماتیک، داشبورد BI و ارائه API به مشتریان سازمانی.'
WHERE customer_code = 'MC-1004';

UPDATE contacts SET position = 'مدیر عملیات ناوگان', notes = 'تصمیم‌گیر اصلی برای نیازهای عملیاتی ناوگان.' WHERE customer_id = 1;
UPDATE contacts SET position = 'مدیر محصول خودروهای متصل', notes = 'هماهنگ‌کننده جلسه‌های فنی با تیم نرم‌افزار و سخت‌افزار.' WHERE customer_id = 2;
UPDATE contacts SET position = 'مدیر فروش نمایندگی', notes = 'پیگیر سرویس‌های قابل فروش به مشتری نهایی.' WHERE customer_id = 3;
UPDATE contacts SET position = 'مدیر توسعه کسب‌وکار', notes = 'مسئول مذاکره چارچوب همکاری و مدل درآمدی.' WHERE customer_id = 4;

UPDATE deals SET deal_name = 'استقرار سامانه FMS برای ناوگان آریا', notes = 'پیشنهاد onCloud همراه با داشبورد مدیر ناوگان و گزارش مصرف سوخت ارائه شده است.' WHERE id = 1;
UPDATE deals SET deal_name = 'پلتفرم خودروی متصل برای البرز موتور', notes = 'پیشنهاد شامل TBox، سرویس API، پنل پایش و لایه تحلیل داده است.' WHERE id = 2;
UPDATE deals SET deal_name = 'راه‌اندازی اپلیکیشن مالک برای نمایندگی شرق', notes = 'مرحله بعدی ارائه دمو به تیم فروش و خدمات پس از فروش نمایندگی است.' WHERE id = 3;
UPDATE deals SET deal_name = 'یکپارچه‌سازی API با همراه داده', notes = 'در حال بررسی مدل همکاری و دسترسی کنترل‌شده به داده‌های تلماتیک.' WHERE id = 4;

UPDATE activities SET
  summary = 'جلسه کشف نیاز با مدیر عملیات ناوگان برگزار شد.',
  next_action = 'ارسال نسخه نهایی پیشنهاد مالی و زمان‌بندی پایلوت',
  notes = 'مشتری روی گزارش مصرف سوخت و هشدار توقف غیرمجاز حساس است.'
WHERE id = 1;

UPDATE activities SET
  summary = 'پیشنهاد اولیه پلتفرم خودروی متصل ارسال شد.',
  next_action = 'هماهنگی دمو فنی برای تیم محصول و IT',
  notes = 'در جلسه بعدی باید سناریوی تبادل داده API مرور شود.'
WHERE id = 2;

UPDATE activities SET
  summary = 'تماس اولیه با مدیر فروش نمایندگی انجام شد.',
  next_action = 'ارسال معرفی اپ مالک و بسته پیشنهادی فروش',
  notes = 'مشتری نمونه صفحه وضعیت خودرو و اعلان سرویس دوره‌ای می‌خواهد.'
WHERE id = 3;

UPDATE activities SET
  summary = 'معرفی API، داشبورد BI و مدل همکاری ارسال شد.',
  next_action = 'برگزاری جلسه مشترک فنی و تجاری',
  notes = 'احتمال همکاری در پروژه‌های سازمانی مشترک وجود دارد.'
WHERE id = 4;
