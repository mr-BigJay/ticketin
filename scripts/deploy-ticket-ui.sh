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
  sleep 2
}

echo "=== دیپلوی UI تیکت (فقط فایل‌های تغییرکرده) ==="
fetch admin/view-ticket.php
fetch admin/closed-tickets.php
fetch new-ticket.php
fetch includes/ticket_helpers.php
fetch includes/ticket_status_helpers.php

chown -R www-data:www-data "$ROOT"

echo ""
echo "تمام شد."
