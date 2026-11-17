#!/usr/bin/env bash
# Sao lưu MySQL: bản nhất quán (--single-transaction, không khóa bảng InnoDB), nén, giữ N ngày.
#   Cron: 15 1 * * *  /var/www/greenlife/deploy/backup-db.sh >> /var/log/greenlife-backup.log 2>&1
# Thông tin đăng nhập DB đọc từ ~/.my.cnf (KHÔNG truyền mật khẩu trên dòng lệnh: lộ qua `ps`).
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/greenlife}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/greenlife}"
KEEP_DAYS="${KEEP_DAYS:-14}"
DB_NAME="${DB_NAME:-$(grep -E '^DB_DATABASE=' "$APP_DIR/.env" | cut -d= -f2- | tr -d '"')}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
FILE="$BACKUP_DIR/${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql.gz"

# --routines/--triggers: phòng sau này có thêm; --hex-blob: an toàn dữ liệu nhị phân;
# --set-gtid-purged=OFF: khôi phục được sang server khác. CHECK constraint + cột sinh nằm trong CREATE TABLE.
mysqldump --single-transaction --quick --routines --triggers --hex-blob --set-gtid-purged=OFF \
    --default-character-set=utf8mb4 "$DB_NAME" | gzip -9 > "$FILE.part"
mv "$FILE.part" "$FILE"                                   # chỉ file hoàn chỉnh mới mang tên .sql.gz

gzip -t "$FILE"                                           # file nén hỏng -> báo lỗi ngay (exit != 0)
find "$BACKUP_DIR" -name "${DB_NAME}-*.sql.gz" -mtime +"$KEEP_DAYS" -delete
echo "$(date '+%F %T') OK $FILE ($(du -h "$FILE" | cut -f1))"
