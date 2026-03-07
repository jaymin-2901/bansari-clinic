# 3-Layer Backup System – Setup Guide

## Overview

The backup system has three automated layers:

| Layer | Schedule | Retention | Storage |
|-------|----------|-----------|---------|
| **Daily Database** | Every day at 2 AM | Last 7 backups | `backup-system/daily_database/` |
| **Weekly Incremental** | Sundays at 3 AM | Last 4 backups | `backup-system/weekly_incremental/` |
| **Monthly Snapshot** | 1st of month at 4 AM | Last 3 backups | `backup-system/monthly_snapshot/` |

## Directory Structure

```
backup-system/
├── .htaccess              # Denies all direct web access
├── daily_database/        # Daily mysqldump .sql files
├── weekly_incremental/    # Weekly rsync directories
├── monthly_snapshot/      # Monthly tar.gz archives
├── logs/
│   └── backup_logs.txt    # Unified log file
└── scripts/
    ├── daily_db_backup.sh
    ├── weekly_incremental_backup.sh
    └── monthly_snapshot.sh
```

## Cron Setup

Add these lines to your crontab (`crontab -e`):

```cron
# Bansari Homeopathy – 3-Layer Backup System
0 2 * * *   DB_HOST=localhost DB_PORT=3306 DB_USER=root DB_NAME=bansari_clinic bash /path/to/backup-system/scripts/daily_db_backup.sh
0 3 * * 0   bash /path/to/backup-system/scripts/weekly_incremental_backup.sh
0 4 1 * *   DB_HOST=localhost DB_PORT=3306 DB_USER=root DB_NAME=bansari_clinic bash /path/to/backup-system/scripts/monthly_snapshot.sh
```

Replace `/path/to/` with the actual absolute path to your project.

## Environment Variables

The daily and monthly scripts use these env vars (with defaults):

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `localhost` | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_USER` | `root` | MySQL user |
| `DB_PASS` | *(empty)* | MySQL password |
| `DB_NAME` | `bansari_clinic` | Database name |

## Admin Dashboard

The backup management page is at `clinic-admin-php/backups.php` and provides:

- **Stats cards** – counts for each backup layer + total storage
- **Quick action buttons** – trigger any backup type manually
- **Backup table** – filterable list of all backups with download/restore/delete
- **Restore system** – type RESTORE to confirm, SQL file restore only
- **Log viewer** – shows last 30 log entries with color coding
- **Cron reference** – copy-paste cron commands

## Security

- `.htaccess` blocks direct web access to all backup files
- Backup files are created with `chmod 600` (owner-read-write only)
- SHA256 checksums verified before download and restore
- CSRF tokens required for all write operations
- Admin authentication required for all API endpoints
- 7-day deletion protection on recent backups
- Directory traversal prevention on all file operations

## Manual Testing

```bash
# Test daily backup
bash backup-system/scripts/daily_db_backup.sh

# Test weekly backup
bash backup-system/scripts/weekly_incremental_backup.sh

# Test monthly backup
bash backup-system/scripts/monthly_snapshot.sh

# Check logs
tail -20 backup-system/logs/backup_logs.txt
```
