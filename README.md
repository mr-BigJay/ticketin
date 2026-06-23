# ticketin — سامانه پشتیبانی و ثبت تیکت

## نصب روی سرور (مسیر: `/var/www/ticketin/`)

### ۱. تنظیم دیتابیس

بعد از آپلود فایل‌ها، حتماً فایل تنظیمات محلی بسازید (این فایل روی سرور می‌ماند و با آپدیت پاک نمی‌شود اگر جدا نگه دارید):

```bash
cd /var/www/ticketin
cp includes/config.local.php.example includes/config.local.php
nano includes/config.local.php
```

مقادیر `host`, `dbname`, `username`, `password` را مطابق دیتابیس قبلی خود وارد کنید.

### ۲. دسترسی پوشه‌ها

```bash
chown -R www-data:www-data /var/www/ticketin
chmod -R 755 /var/www/ticketin
chmod -R 775 /var/www/ticketin/uploads
```

### ۳. DocumentRoot

در Apache یا Nginx، ریشه سایت باید `/var/www/ticketin` باشد — نه `/var/www/html`.

### ۴. بررسی نصب

در مرورگر باز کنید:

```
https://your-domain/setup-check.php
```

موارد قرمز را رفع کنید، سپس `setup-check.php` را حذف کنید.

### ۵. ورود

| نقش | آدرس |
|-----|------|
| کاربر | `/login.php` |
| ادمین | `/jay_controller.php` |

ادمین‌های قدیمی بدون نام کاربری: `admin001`, `admin002`, ...

رمز ادمین پشتیبانی جدید پیش‌فرض: `1` (باید در اولین ورود تغییر داده شود)

## نکته مهم بعد از جایگزینی پوشه

اگر فقط فایل‌های پروژه را عوض کردید ولی `includes/config.local.php` و پوشه `uploads/` را کپی نکردید، سایت معمولاً با خطای **Database Error** یا مشکل آپلود فایل از کار می‌افتد.

از نسخه قبلی این موارد را حفظ کنید:

- `includes/config.local.php` (یا حداقل تنظیمات `includes/db.php` قدیمی)
- پوشه `uploads/` (فایل‌های آپلود شده)
