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
  curl -fsSL --retry 5 --retry-delay 10 \
    -o "$path" \
    "${BASE}/${path}"
  sleep 1
}

echo "=== دیپلوی کامل UI تیکت (ادمین + کاربر) ==="
echo "شاخه: ${BRANCH}"
echo ""

echo "--- هسته مشترک ---"
fetch includes/ticket_helpers.php
fetch includes/ticket_status_helpers.php

echo "--- ادمین ---"
fetch admin/tickets.php
fetch admin/closed-tickets.php
fetch admin/view-ticket.php

echo "--- کاربر ---"
fetch view-ticket.php
fetch tickets.php
fetch closed-tickets.php
fetch new-ticket.php

if [ -f includes/db.php ]; then
  cp includes/db.php /root/ticketin-db.php.backup
  echo "بکاپ db.php در /root/ticketin-db.php.backup"
fi

chown -R www-data:www-data "$ROOT"

echo ""
echo "تمام شد."
echo "توجه: includes/db.php تغییر نکرد."
echo "برای جلوگیری از برگشت UI قدیمی، deploy-ui.sh را بدون هماهنگی اجرا نکنید."
