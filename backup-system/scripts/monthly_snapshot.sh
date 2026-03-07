#!/bin/bash
# ============================================================
# Bansari Homeopathy – Monthly Full Snapshot Script
# File: backup-system/scripts/monthly_snapshot.sh
# Schedule: 0 4 1 * * bash /path/to/backup-system/scripts/monthly_snapshot.sh
# ============================================================
# Creates a full tar.gz snapshot of the entire project including
# a fresh DB dump, keeps last 3 monthly snapshots.

set -euo pipefail

# ─── Configuration ───
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_ROOT="$(dirname "$SCRIPT_DIR")"
PROJECT_ROOT="$(dirname "$BACKUP_ROOT")"
BACKUP_DIR="${BACKUP_ROOT}/monthly_snapshot"
LOG_FILE="${BACKUP_ROOT}/logs/backup_logs.txt"
MAX_BACKUPS=3

# Database credentials
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-bansari_clinic}"

# ─── Timestamp ───
MONTH_STAMP=$(date +"%Y_%m")
DATE_STAMP=$(date +"%Y_%m_%d_%H_%M")
SNAPSHOT_NAME="monthly_snapshot_${MONTH_STAMP}"
SNAPSHOT_FILE="${BACKUP_DIR}/${SNAPSHOT_NAME}.tar.gz"
CHECKSUM_FILE="${SNAPSHOT_FILE}.sha256"
TEMP_DIR=$(mktemp -d)

# ─── Logging ───
log_message() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "$msg" | tee -a "$LOG_FILE"
}

# ─── Cleanup temp on exit ───
cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

# ─── Ensure directories exist ───
mkdir -p "$BACKUP_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

log_message "========== Monthly Full Snapshot Started =========="

# ─── Skip if already backed up this month ───
if [ -f "$SNAPSHOT_FILE" ]; then
    log_message "Monthly snapshot for ${MONTH_STAMP} already exists, skipping"
    exit 0
fi

# ─── Step 1: Fresh database dump ───
log_message "Creating fresh database dump for monthly snapshot..."
DB_DUMP_FILE="${TEMP_DIR}/database_${DATE_STAMP}.sql"

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
    "${DB_NAME}" > "${DB_DUMP_FILE}" 2>>"${LOG_FILE}"; then
    log_message "Database dump created for snapshot"
else
    log_message "ERROR: Database dump failed for monthly snapshot"
    exit 1
fi

# ─── Step 2: Create archive ───
log_message "Creating tar.gz snapshot of project..."

if tar czf "${SNAPSHOT_FILE}" \
    --directory="${PROJECT_ROOT}" \
    --exclude='node_modules' \
    --exclude='.next' \
    --exclude='vendor' \
    --exclude='backup-system/daily_database' \
    --exclude='backup-system/weekly_incremental' \
    --exclude='backup-system/monthly_snapshot' \
    --exclude='*.log' \
    --exclude='.env' \
    --exclude='*.tmp' \
    backend-php \
    clinic-admin-php \
    frontend-nextjs/src \
    frontend-nextjs/prisma \
    frontend-nextjs/package.json \
    frontend-nextjs/next.config.js \
    frontend-nextjs/tsconfig.json \
    frontend-nextjs/tailwind.config.ts \
    frontend-nextjs/postcss.config.js \
    patient-form \
    config \
    database \
    uploads \
    public \
    composer.json \
    README.md \
    --directory="${TEMP_DIR}" \
    "$(basename "$DB_DUMP_FILE")" \
    2>>"${LOG_FILE}"; then

    # Set restrictive permissions
    chmod 600 "$SNAPSHOT_FILE"

    # Generate checksum
    sha256sum "$SNAPSHOT_FILE" | awk '{print $1}' > "$CHECKSUM_FILE"
    chmod 600 "$CHECKSUM_FILE"

    FILE_SIZE=$(du -h "$SNAPSHOT_FILE" | awk '{print $1}')
    log_message "Monthly Snapshot Successful – ${SNAPSHOT_NAME}.tar.gz (${FILE_SIZE})"
else
    log_message "ERROR: Monthly snapshot archive FAILED"
    rm -f "$SNAPSHOT_FILE"
    exit 1
fi

# ─── Cleanup: Keep only last N monthly snapshots ───
BACKUP_COUNT=$(ls -1 "${BACKUP_DIR}"/monthly_snapshot_*.tar.gz 2>/dev/null | wc -l)
if [ "$BACKUP_COUNT" -gt "$MAX_BACKUPS" ]; then
    DELETE_COUNT=$((BACKUP_COUNT - MAX_BACKUPS))
    ls -1t "${BACKUP_DIR}"/monthly_snapshot_*.tar.gz | tail -n "$DELETE_COUNT" | while read -r old_file; do
        rm -f "$old_file" "${old_file}.sha256"
        log_message "Cleaned up old monthly snapshot: $(basename "$old_file")"
    done
fi

log_message "========== Monthly Full Snapshot Completed =========="
exit 0
