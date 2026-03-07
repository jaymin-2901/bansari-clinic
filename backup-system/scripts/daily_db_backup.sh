#!/bin/bash
# ============================================================
# Bansari Homeopathy – Daily Database Backup Script
# File: backup-system/scripts/daily_db_backup.sh
# Schedule: 0 2 * * * bash /path/to/backup-system/scripts/daily_db_backup.sh
# ============================================================
# Performs daily mysqldump, keeps last 7 daily backups,
# generates SHA256 checksum for integrity verification.

set -euo pipefail

# ─── Configuration ───
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${BACKUP_ROOT}/daily_database"
LOG_FILE="${BACKUP_ROOT}/logs/backup_logs.txt"
MAX_BACKUPS=7

# Database credentials (override via environment or edit here)
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-bansari_clinic}"

# ─── Timestamp ───
TIMESTAMP=$(date +"%Y_%m_%d_%H_%M")
DATE_ONLY=$(date +"%Y_%m_%d")
BACKUP_FILE="${BACKUP_DIR}/db_backup_${DATE_ONLY}.sql"
CHECKSUM_FILE="${BACKUP_FILE}.sha256"

# ─── Logging ───
log_message() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "$msg" | tee -a "$LOG_FILE"
}

# ─── Ensure directories exist ───
mkdir -p "$BACKUP_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

log_message "========== Daily Database Backup Started =========="

# ─── Skip if already backed up today ───
if [ -f "$BACKUP_FILE" ]; then
    log_message "Daily backup for ${DATE_ONLY} already exists, skipping"
    exit 0
fi

# ─── Perform mysqldump ───
log_message "Creating database dump: ${DB_NAME} -> ${BACKUP_FILE}"

DUMP_ARGS="-h${DB_HOST} -P${DB_PORT} -u${DB_USER}"
if [ -n "$DB_PASS" ]; then
    DUMP_ARGS="${DUMP_ARGS} -p${DB_PASS}"
fi

if mysqldump ${DUMP_ARGS} \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --add-drop-table \
    --complete-insert \
    "${DB_NAME}" > "${BACKUP_FILE}" 2>>"${LOG_FILE}"; then
    
    # Set restrictive permissions
    chmod 600 "$BACKUP_FILE"
    
    # Generate checksum
    sha256sum "$BACKUP_FILE" | awk '{print $1}' > "$CHECKSUM_FILE"
    chmod 600 "$CHECKSUM_FILE"
    
    # Get file size
    FILE_SIZE=$(du -h "$BACKUP_FILE" | awk '{print $1}')
    
    log_message "Daily Backup Successful – ${BACKUP_FILE} (${FILE_SIZE})"
else
    log_message "ERROR: Daily database backup FAILED"
    rm -f "$BACKUP_FILE"
    exit 1
fi

# ─── Cleanup: Keep only last N daily backups ───
BACKUP_COUNT=$(ls -1 "${BACKUP_DIR}"/db_backup_*.sql 2>/dev/null | wc -l)
if [ "$BACKUP_COUNT" -gt "$MAX_BACKUPS" ]; then
    DELETE_COUNT=$((BACKUP_COUNT - MAX_BACKUPS))
    ls -1t "${BACKUP_DIR}"/db_backup_*.sql | tail -n "$DELETE_COUNT" | while read -r old_file; do
        rm -f "$old_file" "${old_file}.sha256"
        log_message "Cleaned up old daily backup: $(basename "$old_file")"
    done
fi

log_message "========== Daily Database Backup Completed =========="
exit 0
