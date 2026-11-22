#!/usr/bin/env bash
# Triển khai bản mới lên server (chạy bằng user sở hữu code, KHÔNG phải root).
#   APP_DIR=/var/www/greenlife BRANCH=main ./deploy/deploy.sh
# Dừng ngay ở bước lỗi đầu tiên; site để ở chế độ bảo trì cho tới khi xong.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/greenlife}"
BRANCH="${BRANCH:-main}"
PHP="${PHP:-php}"
cd "$APP_DIR"

echo "==> 0. Sao lưu DB trước khi migrate"
"$APP_DIR/deploy/backup-db.sh"

echo "==> 1. Bật chế độ bảo trì (trang 503 tiếng Việt, khách được báo thử lại sau 60s)"
$PHP artisan down --retry=60 || true
# Lỗi giữa chừng: GIỮ site ở bảo trì (code mới + schema dở dang nguy hiểm hơn site tạm đóng).
trap 'echo "!! DEPLOY LỖI - site vẫn ở chế độ bảo trì. Xem docs/H7-van-hanh.md mục Rollback." >&2' ERR

echo "==> 2. Lấy code"
git fetch --prune origin "$BRANCH"
git checkout -q "$BRANCH"
git merge --ff-only "origin/$BRANCH"

echo "==> 3. Thư viện (không cài gói dev)"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> 4. Migrate (KHÔNG --seed: dữ liệu mẫu bị chặn ở production)"
$PHP artisan migrate --force

echo "==> 4b. Link public/storage -> storage/app/public (ảnh tin tức tải lên); đã có thì bỏ qua"
[ -L public/storage ] || $PHP artisan storage:link

echo "==> 5. Cache cấu hình / route / view / event"
$PHP artisan optimize

echo "==> 6. Khởi động lại queue worker để nạp code mới"
$PHP artisan queue:restart

echo "==> 7. Đối soát dữ liệu sau deploy"
$PHP artisan shop:reconcile

$PHP artisan up
echo "==> Xong. Kiểm tra: curl -fsS \"\$(grep ^APP_URL .env | cut -d= -f2)/up\""
