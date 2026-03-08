# 🚀 InfinityFree + Vercel Deployment Guide

This guide explains how to deploy your Bansari Homeopathy Clinic backend on InfinityFree and connect it with Vercel frontend.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      INTERNET                               │
└─────────────────────────────────────────────────────────────┘
                           │
         ┌─────────────────┴─────────────────┐
         │                                   │
         ▼                                   ▼
┌─────────────────────┐           ┌─────────────────────┐
│   Vercel (Frontend)│           │  InfinityFree       │
│                     │           │  (Backend PHP)      │
│ https://your-       │  ───────► │                     │
│   vercel.app        │   API     │ https://bansari-   │
│                     │           │   homeopathic-     │
│  Next.js App        │           │   clinic.infinity- │
│                     │           │   freeapp.com      │
└─────────────────────┘           └─────────────────────┘
```

---

## Step 1: Configure Backend Database (InfinityFree)

### 1.1 Get MySQL Credentials
1. Login to InfinityFree Control Panel
2. Go to **MySQL Databases**
3. Note down:
   - **MySQL Host** (e.g., `sql313.infinityfree.com`)
   - **MySQL Port** (usually `3306`)
   - **Database Name** (e.g., `if0_12345678_clinic`)
   - **Username** (e.g., `if0_12345678`)
   - **Password** (your MySQL password)

### 1.2 Update Production Config
Edit `backend-php/config/production_config.php` and replace the placeholder values:

```php
define('DB_HOST', 'sqlXXX.infinityfree.com');  // Your actual MySQL host
define('DB_PORT', '3306');
define('DB_NAME', 'if0_XXXXXXXX_clinic');       // Your actual database name
define('DB_USER', 'if0_XXXXXXXX');              // Your actual username
define('DB_PASS', 'your_password_here');       // Your actual password
```

---

## Step 2: Upload Backend to InfinityFree

### 2.1 Files to Upload
Upload the following folders to your InfinityFree **htdocs** folder:
```
htdocs/
├── backend-php/
│   ├── api/
│   ├── config/
│   ├── security/
│   ├── router.php
│   └── .htaccess
├── public/
│   └── uploads/
└── clinic-admin-php/  (optional - for admin panel)
```

### 2.2 Create .htaccess in htdocs
Create or update `htdocs/.htaccess`:

```apache
# Rewrite rules for API routing
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # API requests to backend-php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^api/(.*)$ backend-php/api/clinic/$1 [L,QSA]
    
    # Uploads
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^uploads/(.*)$ public/uploads/$1 [L,QSA]
</IfModule>

# Pass Authorization header
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]
```

---

## Step 3: Setup Database

### 3.1 Import Database Schema
1. Go to **MySQL Databases** in InfinityFree Control Panel
2. Click **phpMyAdmin**
3. Select your database
4. Import `database/schema.sql`

---

## Step 4: Configure Vercel Frontend

### 4.1 Set Environment Variables
In Vercel Dashboard:
1. Go to **Settings** → **Environment Variables**
2. Add:
   - `PHP_BACKEND_URL` = `https://bansari-homeopathic-clinic.infinityfreeapp.com`

### 4.2 Deploy/Redeploy
1. Go to **Deployments**
2. Click **Redeploy** on your latest deployment

---

## Step 5: Test the Connection

### 5.1 Test Backend API
Visit: `https://bansari-homeopathic-clinic.infinityfreeapp.com/api/clinic/settings.php?group=general`

Expected JSON response:
```json
{
  "success": true,
  "data": { ... }
}
```

### 5.2 Test Frontend
Visit your Vercel URL and check if:
- Homepage loads
- Booking form works
- Login/signup works

---

## Troubleshooting

### CORS Errors?
Make sure your CORS handler allows your Vercel domain. Update in `backend-php/config/production_config.php`:
```php
define('ALLOWED_ORIGINS', 'https://your-vercel-project.vercel.app');
```

### Database Connection Failed?
1. Verify MySQL credentials in InfinityFree Control Panel
2. Check database name is correct
3. Ensure database user has privileges

### 500 Internal Server Error?
1. Check InfinityFree error logs
2. Verify `.htaccess` syntax
3. Ensure PHP version is 8.0+

---

## File Structure on InfinityFree

```
htdocs/
├── .htaccess                    # Main routing rules
├── api/                         # API alias (via .htaccess)
│   └── clinic/
├── backend-php/
│   ├── api/
│   │   └── clinic/
│   │       ├── settings.php
│   │       ├── appointments.php
│   │       └── ...
│   ├── config/
│   │   ├── production_config.php  # UPDATE THIS WITH YOUR DB CREDS
│   │   ├── config.php
│   │   └── database.php
│   ├── security/
│   │   ├── CORSHandler.php
│   │   └── ...
│   ├── router.php
│   └── .htaccess
├── public/
│   └── uploads/
│       ├── about/
│       ├── clinic-images/
│       └── testimonials/
└── clinic-admin-php/            # Admin panel (optional)
```

---

## Important Notes

1. **HTTPS is required** - InfinityFree provides free SSL
2. **PHP 8.0+** - Ensure your hosting uses PHP 8.0 or higher
3. **Database Port** - InfinityFree uses port 3306 (not 3307 like local XAMPP)
4. **File Permissions** - Ensure uploads folder is writable (755 or 775)

---

## API Endpoints Available

After deployment, these endpoints will be available:

| Endpoint | Description |
|----------|-------------|
| `/api/clinic/settings.php` | Get clinic settings |
| `/api/clinic/appointments.php` | Book appointments |
| `/api/clinic/login.php` | Patient login |
| `/api/clinic/signup.php` | Patient signup |
| `/api/clinic/testimonials.php` | Get testimonials |
| `/api/clinic/contact.php` | Contact form |
| `/api/clinic/slots.php` | Available time slots |
| `/uploads/` | Image uploads |

