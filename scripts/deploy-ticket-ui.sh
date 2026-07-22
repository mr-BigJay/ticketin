#!/bin/bash
# این اسکریپت فقط برای سازگاری نگه داشته شده.
# برای دیپلوی واقعی از scripts/deploy-all.sh استفاده کنید.
set -euo pipefail

ROOT="${1:-/var/www/ticketin}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "توجه: deploy-ticket-ui.sh منسوخ شده است."
echo "در حال اجرای deploy-all.sh برای جلوگیری از برگشت ظاهر قدیمی..."
echo ""

exec bash "${SCRIPT_DIR}/deploy-all.sh" "$ROOT"
