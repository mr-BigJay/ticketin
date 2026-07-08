#!/bin/bash
set -euo pipefail

ROOT="${1:-/var/www/ticketin}"
BRANCH="cursor/unified-header-login-a1f4"
BASE="https://raw.githubusercontent.com/mr-BigJay/ticketin/${BRANCH}"

if [ ! -d "$ROOT" ]; then
  echo "مسیر پروژه پیدا نشد: $ROOT"
  exit 1
fi

cd "$ROOT"

fetch() {
  local path="$1"
  local dir
  dir="$(dirname "$path")"
  mkdir -p "$dir"
  echo "→ $path"
  curl -fsSL --retry 3 --retry-delay 5 \
    -o "$path" \
    "${BASE}/${path}"
}

echo "=== دیپلوی هدر و فوتر یکپارچه ==="
fetch includes/header.php
fetch includes/footer.php
fetch includes/jalali.php
fetch includes/jdatetime.class.php
fetch includes/admin_helpers.php
fetch includes/admin_auth.php
fetch includes/user_helpers.php
fetch includes/security.php
fetch assets/bg-pattern.svg

echo "=== صفحات اصلی کاربر ==="
fetch login.php
fetch register.php
fetch dashboard.php
fetch tickets.php
fetch closed-tickets.php
fetch view-ticket.php
fetch new-ticket.php

echo "=== صفحات اصلی ادمین ==="
fetch admin/index.php
fetch admin/tickets.php
fetch admin/closed-tickets.php
fetch admin/view-ticket.php
fetch admin/users.php
fetch admin/user-edit.php
fetch admin/user-view.php
fetch admin/pending-users.php
fetch admin/departments.php
fetch admin/organization/index.php
fetch admin/organization/quick-add.php
fetch admin/reminders.php
fetch cron/send-reminders.php
fetch admin/job-titles.php
fetch admin/admins.php
fetch admin/check-header.php
fetch admin/sms-settings.php
fetch admin/upload-settings.php
fetch includes/pagination_helpers.php
fetch includes/push_helpers.php

echo "=== حذف فایل‌های قدیمی هدر (اگر وجود داشته باشند) ==="
rm -f \
  includes/header_old.php \
  includes/header-old.php \
  includes/header.backup.php \
  includes/header.bak \
  includes/header_new.php \
  includes/header-new.php \
  includes/old_header.php \
  includes/footer_old.php \
  includes/footer-old.php \
  header.php \
  footer.php

if [ -f includes/db.php ]; then
  cp includes/db.php /root/ticketin-db.php.backup
  echo "بکاپ db.php در /root/ticketin-db.php.backup"
fi

chown -R www-data:www-data "$ROOT"

echo ""
echo "تمام شد."
echo "برای تست: head -5 includes/header.php | grep Design"
echo "باید عبارت Design B را ببینید."
