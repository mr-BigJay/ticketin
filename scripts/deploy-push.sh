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

mkdir -p scripts

if [ ! -f "scripts/deploy-push.sh" ]; then
  echo "→ scripts/deploy-push.sh"
  curl -fsSL --retry 5 --retry-delay 10 -o "scripts/deploy-push.sh" "${BASE}/scripts/deploy-push.sh"
  chmod +x "scripts/deploy-push.sh"
fi

fetch() {
  local path="$1"
  local dir
  dir="$(dirname "$path")"
  mkdir -p "$dir"
  echo "→ $path"
  curl -fsSL --retry 5 --retry-delay 10 -o "$path" "${BASE}/${path}"
  sleep 2
}

echo "=== دیپلوی فایل‌های Push / PWA ادمین ==="
fetch includes/push_helpers.php
fetch includes/push_vapid.php
fetch includes/admin_pwa_head.php
fetch includes/admin_pwa_foot.php
fetch includes/admin_auth.php
fetch admin/sw.js
fetch admin/push-subscribe.php
fetch admin/push-vapid-public.php
fetch admin/push-test.php
fetch admin/setup-vapid.php
fetch cron/send-reminders.php
fetch register.php
fetch view-ticket.php

chown -R www-data:www-data "$ROOT"

echo ""
echo "تمام شد."
echo "کرون یادآوری (پیشنهادی):"
echo "0 8,10,12 * * * /usr/bin/php $ROOT/cron/send-reminders.php >> /var/log/ticketin-reminders.log 2>&1"
