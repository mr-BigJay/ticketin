#!/bin/bash
set -uo pipefail

ROOT="${1:-/var/www/ticketin}"
BRANCH="cursor/unified-header-login-a1f4"
REMOTE="${DEPLOY_REMOTE:-origin}"
BASE="https://raw.githubusercontent.com/mr-BigJay/ticketin/${BRANCH}"
FETCH_DELAY="${FETCH_DELAY:-2}"
MAX_ATTEMPTS="${MAX_ATTEMPTS:-8}"
FAILED_FILES=()

DEPLOY_PATHS=(
  includes/header.php
  includes/footer.php
  includes/jalali.php
  includes/jdatetime.class.php
  includes/admin_helpers.php
  includes/admin_auth.php
  includes/user_helpers.php
  includes/security.php
  assets/bg-pattern.svg
  assets/persian-datepicker/jquery.min.js
  assets/persian-datepicker/persian-date.min.js
  assets/persian-datepicker/persian-datepicker.min.js
  assets/persian-datepicker/persian-datepicker.min.css
  login.php
  register.php
  dashboard.php
  tickets.php
  closed-tickets.php
  view-ticket.php
  new-ticket.php
  admin/index.php
  admin/tickets.php
  admin/closed-tickets.php
  admin/view-ticket.php
  admin/users.php
  admin/user-edit.php
  admin/user-view.php
  admin/pending-users.php
  admin/departments.php
  admin/organization/index.php
  admin/organization/quick-add.php
  admin/reminders.php
  cron/send-reminders.php
  admin/job-titles.php
  admin/admins.php
  admin/check-header.php
  admin/sms-settings.php
  admin/upload-settings.php
  includes/pagination_helpers.php
  includes/push_helpers.php
  includes/ticket_helpers.php
  includes/ticket_status_helpers.php
)

if [ ! -d "$ROOT" ]; then
  echo "مسیر پروژه پیدا نشد: $ROOT"
  exit 1
fi

cd "$ROOT"

curl_auth_args=()
if [ -n "${GITHUB_TOKEN:-}" ]; then
  curl_auth_args=(-H "Authorization: Bearer ${GITHUB_TOKEN}")
fi

fetch_via_curl() {
  local path="$1"
  local url="${BASE}/${path}"
  local attempt=1
  local delay=8

  while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
    if curl -fsSL \
      "${curl_auth_args[@]}" \
      -H "User-Agent: ticketin-deploy/1.0" \
      --connect-timeout 20 \
      --max-time 120 \
      -o "$path" \
      "$url"; then
      return 0
    fi

    if [ "$attempt" -lt "$MAX_ATTEMPTS" ]; then
      echo "  ⚠ تلاش ${attempt} برای ${path} ناموفق؛ انتظار ${delay} ثانیه..."
      sleep "$delay"
      if [ "$delay" -lt 120 ]; then
        delay=$((delay * 2))
      fi
    fi

    attempt=$((attempt + 1))
  done

  return 1
}

fetch_via_git() {
  local path="$1"
  local ref="${REMOTE}/${BRANCH}"

  if ! git cat-file -e "${ref}:${path}" 2>/dev/null; then
    return 1
  fi

  git show "${ref}:${path}" > "$path"
}

fetch() {
  local path="$1"
  local dir
  dir="$(dirname "$path")"

  mkdir -p "$dir"
  echo "→ $path"

  if [ "${DEPLOY_METHOD:-auto}" != "curl" ] && [ -d .git ]; then
    if fetch_via_git "$path"; then
      sleep "$FETCH_DELAY"
      return 0
    fi
  fi

  if fetch_via_curl "$path"; then
    sleep "$FETCH_DELAY"
    return 0
  fi

  FAILED_FILES+=("$path")
  echo "  ✗ خطا در دریافت $path"
  return 1
}

deploy_via_git_fetch() {
  if [ ! -d .git ]; then
    return 1
  fi

  echo "=== به‌روزرسانی مخزن git (${REMOTE}/${BRANCH}) ==="

  if ! git remote get-url "$REMOTE" >/dev/null 2>&1; then
    echo "ریموت ${REMOTE} پیدا نشد؛ از curl استفاده می‌شود."
    return 1
  fi

  if ! git fetch "$REMOTE" "$BRANCH" --depth=1 2>/dev/null; then
    git fetch "$REMOTE" "$BRANCH"
  fi

  return 0
}

retry_failed_files() {
  if [ "${#FAILED_FILES[@]}" -eq 0 ]; then
    return 0
  fi

  echo ""
  echo "=== تلاش مجدد برای ${#FAILED_FILES[@]} فایل ==="

  local still_failed=()
  local path

  for path in "${FAILED_FILES[@]}"; do
    echo "↻ $path"
    sleep "$((FETCH_DELAY * 2))"

    if [ -d .git ] && fetch_via_git "$path"; then
      continue
    fi

    if fetch_via_curl "$path"; then
      continue
    fi

    still_failed+=("$path")
    echo "  ✗ باز هم ناموفق: $path"
  done

  FAILED_FILES=("${still_failed[@]}")
}

echo "=== دیپلوی UI (${BRANCH}) ==="

if [ "${DEPLOY_METHOD:-auto}" != "curl" ]; then
  deploy_via_git_fetch || true
fi

echo "=== دریافت فایل‌ها ==="
for path in "${DEPLOY_PATHS[@]}"; do
  fetch "$path" || true
done

retry_failed_files

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
if [ "${#FAILED_FILES[@]}" -gt 0 ]; then
  echo "دیپلوی ناقص بود. فایل‌های ناموفق:"
  printf '  - %s\n' "${FAILED_FILES[@]}"
  echo ""
  echo "راه‌حل‌ها:"
  echo "  1) چند دقیقه صبر کنید و دوباره اجرا کنید"
  echo "  2) اگر مخزن git دارید: DEPLOY_METHOD=auto bash scripts/deploy-ui.sh"
  echo "  3) با توکن GitHub: GITHUB_TOKEN=... bash scripts/deploy-ui.sh"
  exit 1
fi

echo "تمام شد."
echo "برای تست: head -5 includes/header.php | grep Design"
echo "باید عبارت Design B را ببینید."
