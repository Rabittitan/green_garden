#!/usr/bin/env bash
# Khôi phục từ bản sao lưu VÀO MỘT DB KHÁC để kiểm tra (mặc định), không ghi đè DB đang chạy.
#   ./deploy/restore-db.sh /var/backups/greenlife/green_life-20261117-011500.sql.gz green_life_restore_check
# Diễn tập mỗi tháng: khôi phục xong chạy đối soát trên DB đó:
#   DB_DATABASE=green_life_restore_check php artisan shop:reconcile
set -euo pipefail
FILE="${1:?Cần đường dẫn file .sql.gz}"
TARGET="${2:?Cần tên DB đích (nên là DB tạm, không phải DB production)}"

mysql -e "CREATE DATABASE IF NOT EXISTS \`$TARGET\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c "$FILE" | mysql "$TARGET"
echo "Đã khôi phục $FILE vào $TARGET"
