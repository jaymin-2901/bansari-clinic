# Production Deployment Checklist

## Pre-Deployment Steps

### 1. Database Setup
- [ ] Create production database on your hosting provider
- [ ] Run SQL migrations in order:
  ```
  backend-php/sql/setup_full.sql
  ```
- [ ] Configure database credentials in hosting environment variables

### 2. Environment Variables (Production)
Set these in your hosting control panel:

| Variable | Value |
|----------|-------|
| `DB_HOST` | Your MySQL host |
| `DB_PORT` | 3306 (or provider default) |
| `DB_NAME` | bansari_clinic |
| `DB_USER` | Your database username |
| `DB_PASS` | Your database password |
| `APP_ENV` | production |
| `JWT_SECRET` | Generate a secure key (min 32 chars) |
| `JWT_REFRESH_SECRET` | Generate a separate key |

### 3. Security Hardening (IMPORTANT)
Run these SQL commands to secure the database:

```sql
-- Remove plain_password column (SECURITY RISK)
ALTER TABLE patients DROP COLUMN IF EXISTS plain_password;

-- Enable rate limiting
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    request_count INT DEFAULT 0,
    window_start INT NOT NULL,
    window_reset INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier)
) ENGINE=InnoDB;
```

### 4. Clean Up (Already Done)
- [x] Removed temporary/test files
- [x] Updated .gitignore

---

## Deployment Options

### Option A: InfinityFree
1. Upload `backend-php/` and `clinic-admin-php/` folders via File Manager
2. Create MySQL database in InfinityFree panel
3. Import database schema
4. Update database credentials in `clinic-admin-php/config/db.php`

### Option B: Render.com
1. Connect GitHub repository
2. Create web service with:
   - Root Directory: `backend-php`
   - Build Command: (empty)
   - Start Command: `php -S 0.0.0.0:$PORT router.php`
3. Add environment variables

### Option C: Shared Hosting (cPanel)
1. Upload files via FTP/cPanel File Manager
2. Point subdomain to `backend-php/`
3. Create database and import schema

---

## Post-Deployment Verification

### Test These Endpoints:
- [ ] `https://your-backend/api/clinic/settings.php?group=general`
- [ ] `https://your-backend/api/clinic/login.php` (POST test)
- [ ] Admin panel login at `https://your-backend/clinic-admin-php/login.php`

### Check:
- [ ] Database connection working
- [ ] File uploads working
- [ ] JSON responses formatted correctly
- [ ] No PHP errors in logs

---

## Troubleshooting

### Common Issues:
1. **Blank page**: Check PHP error logs
2. **Database connection failed**: Verify credentials
3. **API 404 errors**: Check .htaccess and routing
4. **CORS errors**: Verify FRONTEND_URL environment variable

---

## Support
For issues, check:
- `backend-php/logs/` directory for error logs
- Hosting provider error logs
- Browser developer console for network errors

