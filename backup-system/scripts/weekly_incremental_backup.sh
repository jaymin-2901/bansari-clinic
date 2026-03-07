#!/bin/bash
# ============================================================
# Bansari Homeopathy – Weekly Incremental Backup Script
# File: backup-system/scripts/weekly_incremental_backup.sh
# Schedule: 0 3 * * 0 bash /path/to/backup-system/scripts/weekly_incremental_backup.sh
# ============================================================
# Performs weekly rsync-based incremental backup of website files,
# keeps last 4 weekly backups, uses hard links for space efficiency.

set -euo pipefail

# ─── Configuration ───
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_ROOT="$(dirname "$SCRIPT_DIR")"
PROJECT_ROOT="$(dirname "$BACKUP_ROOT")"
BACKUP_DIR="${BACKUP_ROOT}/weekly_incremental"
LOG_FILE="${BACKUP_ROOT}/logs/backup_logs.txt"
MAX_BACKUPS=4

# ─── Timestamp ───
YEAR=$(date +"%Y")
WEEK=$(date +"%V")
DATE_STAMP=$(date +"%Y_%m_%d_%H_%M")
BACKUP_NAME="weekly_backup_${YEAR}_W${WEEK}"
CURRENT_BACKUP="${BACKUP_DIR}/${BACKUP_NAME}"

# ─── Directories to back up ───
INCLUDE_DIRS=(
    "${PROJECT_ROOT}/backend-php"
    "${PROJECT_ROOT}/clinic-admin-php"
    "${PROJECT_ROOT}/frontend-nextjs/src"
    "${PROJECT_ROOT}/frontend-nextjs/prisma"
    "${PROJECT_ROOT}/patient-form"
    "${PROJECT_ROOT}/config"
    "${PROJECT_ROOT}/database"
    "${PROJECT_ROOT}/uploads"
    "${PROJECT_ROOT}/public"
)

# ─── Logging ───
log_message() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "$msg" | tee -a "$LOG_FILE"
}

# ─── Ensure directories exist ───
mkdir -p "$BACKUP_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

log_message "========== Weekly Incremental Backup Started =========="

# ─── Skip if already backed up this week ───
if [ -d "$CURRENT_BACKUP" ]; then
    log_message "Weekly backup for ${YEAR}-W${WEEK} already exists, skipping"
    exit 0
fi

# ─── Find most recent backup for hard-linking (incremental) ───
LINK_DEST=""
LATEST=$(ls -1d "${BACKUP_DIR}"/weekly_backup_* 2>/dev/null | sort -r | head -1)
if [ -n "$LATEST" ] && [ -d "$LATEST" ]; then
    LINK_DEST="--link-dest=${LATEST}"
    log_message "Using link-dest for incremental from: $(basename "$LATEST")"
fi

# ─── Create backup directory ───
mkdir -p "${CURRENT_BACKUP}"

# ─── Rsync each directory ───
SUCCESS=true
for SRC_DIR in "${INCLUDE_DIRS[@]}"; do
    if [ ! -d "$SRC_DIR" ]; then
        log_message "NOTICE: Source directory does not exist, skipping: ${SRC_DIR}"
        continue
    fi

    DEST_NAME=$(basename "$SRC_DIR")
    mkdir -p "${CURRENT_BACKUP}/${DEST_NAME}"

    log_message "Syncing: ${SRC_DIR} -> ${CURRENT_BACKUP}/${DEST_NAME}"

    if rsync -a --delete \
        --exclude='node_modules' \
        --exclude='.next' \
        --exclude='vendor' \
        --exclude='*.log' \
        --exclude='.env' \
        --exclude='*.tmp' \
        ${LINK_DEST} \
        "${SRC_DIR}/" "${CURRENT_BACKUP}/${DEST_NAME}/" 2>>"${LOG_FILE}"; then
        log_message "  Synced: ${DEST_NAME}"
    else
        log_message "  ERROR syncing: ${DEST_NAME}"
        SUCCESS=false
    fi
done

# ─── Also copy important root-level files ───
for ROOT_FILE in composer.json package.json README.md; do
    if [ -f "${PROJECT_ROOT}/${ROOT_FILE}" ]; then
        cp -p "${PROJECT_ROOT}/${ROOT_FILE}" "${CURRENT_BACKUP}/" 2>/dev/null || true
    fi
done

# ─── Set permissions ───
chmod -R 700 "${CURRENT_BACKUP}"

# ─── Calculate size ───
TOTAL_SIZE=$(du -sh "${CURRENT_BACKUP}" | awk '{print $1}')

if [ "$SUCCESS" = true ]; then
    log_message "Weekly Backup Successful – ${BACKUP_NAME} (${TOTAL_SIZE})"
else
    log_message "WARNING: Weekly backup completed with errors – ${BACKUP_NAME} (${TOTAL_SIZE})"
fi

# ─── Cleanup: Keep only last N weekly backups ───
BACKUP_COUNT=$(ls -1d "${BACKUP_DIR}"/weekly_backup_* 2>/dev/null | wc -l)
if [ "$BACKUP_COUNT" -gt "$MAX_BACKUPS" ]; then
    DELETE_COUNT=$((BACKUP_COUNT - MAX_BACKUPS))
    ls -1dt "${BACKUP_DIR}"/weekly_backup_* | tail -n "$DELETE_COUNT" | while read -r old_dir; do
        rm -rf "$old_dir"
        log_message "Cleaned up old weekly backup: $(basename "$old_dir")"
    done
fi

log_message "========== Weekly Incremental Backup Completed =========="
exit 0
