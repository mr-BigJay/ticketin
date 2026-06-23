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

---

## دیتابیس جدید (از صفر)

اگر می‌خواهید دیتابیس تازه داشته باشید (بدون داده قبلی):

### ۱. ساخت دیتابیس در MySQL

```bash
mysql -u root -p
```

```sql
CREATE DATABASE ticketin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ticketuser'@'localhost' IDENTIFIED BY 'رمز-قوی-شما';
GRANT ALL PRIVILEGES ON ticketin.* TO 'ticketuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### ۲. تنظیم اتصال

```bash
cd /var/www/ticketin
cp includes/config.local.php.example includes/config.local.php
nano includes/config.local.php
```

مقادیر `dbname`, `username`, `password` را مطابق دیتابیس جدید وارد کنید.

### ۳. نصب جداول و ادمین اصلی

**روش آسان (مرورگر):**

```
https://your-domain/install.php
```

فرم را پر کنید → جداول ساخته می‌شود + یک ادمین اصلی (super) ایجاد می‌شود.

**روش دستی (ترمینال):**

```bash
mysql -u ticketuser -p ticketin < /var/www/ticketin/database/schema.sql
```

سپس از `install.php` فقط برای ساخت ادمین استفاده کنید، یا ادمین را دستی در جدول `users` بسازید.

### ۴. بعد از نصب

- ورود ادمین: `/jay_controller.php` با نام کاربری و رمزی که در install وارد کردید
- حذف کنید: `install.php`, `setup-check.php`
- از پنل ادمین: ساختار سازمانی، دسته‌بندی تیکت، پست سازمانی را تنظیم کنید

---

## نکته مهم بعد از جایگزینی پوشه

اگر فقط فایل‌های پروژه را عوض کردید ولی `includes/config.local.php` و پوشه `uploads/` را کپی نکردید، سایت معمولاً با خطای **Database Error** یا مشکل آپلود فایل از کار می‌افتد.

از نسخه قبلی این موارد را حفظ کنید:

- `includes/config.local.php` (یا حداقل تنظیمات `includes/db.php` قدیمی)
- پوشه `uploads/` (فایل‌های آپلود شده)
