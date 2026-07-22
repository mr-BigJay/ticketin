#!/bin/bash
# دیپلوی کامل تیکتین — همه فایل‌های اپلیکیشن از یک شاخه ثابت
# این اسکریپت جایگزین اجرای جداگانه deploy-ui / deploy-ticket-ui / deploy-push است
# تا ظاهر و منطق دوباره به نسخه قدیمی برنگردد.
set -euo pipefail

ROOT="${1:-/var/www/ticketin}"
BRANCH="${TICKETIN_DEPLOY_BRANCH:-cursor/unified-header-login-a1f4}"
BASE="https://raw.githubusercontent.com/mr-BigJay/ticketin/${BRANCH}"

if [ ! -d "$ROOT" ]; then
  echo "مسیر پروژه پیدا نشد: $ROOT"
  exit 1
fi

cd "$ROOT"

if [ -f includes/db.php ]; then
  cp -a includes/db.php "/root/ticketin-db.php.backup.$(date +%Y%m%d%H%M%S)" 2>/dev/null \
    || cp -a includes/db.php "./includes/db.php.backup.$(date +%Y%m%d%H%M%S)"
  echo "بکاپ db.php گرفته شد (فایل overwrite نمی‌شود)."
fi

fetch() {
  local path="$1"
  local dir
  dir="$(dirname "$path")"
  mkdir -p "$dir"
  echo "→ $path"
  curl -fsSL --retry 5 --retry-delay 8 -o "${path}.tmp" "${BASE}/${path}"
  mv "${path}.tmp" "$path"
}

echo "=== دیپلوی کامل از شاخه: ${BRANCH} ==="
echo ""

echo "--- هسته ---"
fetch includes/header.php
fetch includes/footer.php
fetch includes/jalali.php
fetch includes/jdatetime.class.php
fetch includes/security.php
fetch includes/user_helpers.php
fetch includes/admin_helpers.php
fetch includes/admin_auth.php
fetch includes/ticket_helpers.php
fetch includes/ticket_status_helpers.php
fetch includes/pagination_helpers.php
fetch includes/push_helpers.php
fetch includes/push_vapid.php
fetch includes/sms_helpers.php
fetch includes/upload_storage.php
fetch includes/admin_pwa_head.php
fetch includes/admin_pwa_foot.php

echo "--- صفحات کاربر ---"
fetch index.php
fetch login.php
fetch register.php
fetch dashboard.php
fetch tickets.php
fetch closed-tickets.php
fetch view-ticket.php
fetch new-ticket.php
fetch profile.php
fetch logout.php
fetch jay_controller.php

echo "--- صفحات ادمین ---"
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
fetch admin/organization/edit.php
fetch admin/reminders.php
fetch admin/job-titles.php
fetch admin/admins.php
fetch admin/announcements.php
fetch admin/announcement-list.php
fetch admin/announcement-create.php
fetch admin/announcement-view.php
fetch admin/announcement-archive.php
fetch admin/announcement-categories.php
fetch admin/sms-settings.php
fetch admin/upload-settings.php
fetch admin/push-test.php
fetch admin/push-subscribe.php
fetch admin/push-vapid-public.php
fetch admin/setup-vapid.php
fetch admin/sw.js
fetch admin/change-password.php
fetch admin/trainings.php
fetch admin/check-header.php

echo "--- کرون و اسکریپت‌ها ---"
fetch cron/send-reminders.php
fetch scripts/deploy-all.sh
fetch scripts/deploy-ui.sh
fetch scripts/deploy-ticket-ui.sh
fetch scripts/deploy-push.sh

echo "--- دارایی‌ها ---"
fetch assets/bg-pattern.svg || true
fetch assets/gums-logo.png || true

# هرگز db.php را از گیت overwrite نکن
chmod +x scripts/*.sh 2>/dev/null || true
chown -R www-data:www-data "$ROOT" 2>/dev/null || true

echo ""
echo "تمام شد."
echo "شاخه منبع: ${BRANCH}"
echo "includes/db.php دست نخورده است."
echo ""
echo "برای جلوگیری از برگشت UI قدیمی:"
echo "  فقط از scripts/deploy-all.sh استفاده کنید."
echo "  deploy-ui.sh / deploy-ticket-ui.sh / deploy-push.sh را جداگانه اجرا نکنید مگر برای تست محدود."
