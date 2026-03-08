# Render.com Deployment Guide

## Overview
This guide helps you deploy the Bansari Homeopathy backend on Render.com.

**Note:** This is a PHP-based backend, not Node.js.

---

## Files Created/Modified

1. **`.env.example`** - Environment variables template
2. **`router.php`** (root) - Added health check endpoint and PORT support
3. **`backend-php/router.php`** - Added health check endpoint and PORT support
4. **`render.yaml`** - Render.com deployment configuration

---

## Final Backend Folder Structure

```
bansari-homeopathy/
├── .env.example          # Environment variables template
├── .gitignore            # Git ignore rules
├── composer.json         # PHP dependencies
├── render.yaml           # Render.com deployment config
├── router.php            # Main PHP router
│
├── backend-php/
│   ├── router.php        # Backend router with health check
│   ├── config/
│   │   ├── config.php
│   │   ├── clinic_config.php
│   │   ├── database.php
│   │   └── env_loader.php
│   ├── api/
│   │   ├── clinic/       # Clinic APIs
│   │   └── auth/         # Auth APIs
│   ├── security/
│   │   ├── CORSHandler.php
│   │   ├── JWTHandler.php
│   │   └── ...
│   ├── email/
│   ├── sms/
│   └── logs/
│
├── clinic-admin-php/      # Admin panel
├── public/
│   └── uploads/          # Uploaded files
└── database/
    └── schema.sql
```

---

## Environment Variables

Copy `.env.example` to `.env` and configure:

```bash
# Application
APP_ENV=production

# Database (MySQL)
DB_HOST=your-mysql-host.render.io
DB_PORT=3306
DB_NAME=bansari_clinic
DB_USER=your_db_user
DB_PASS=your_db_password

# Frontend URL (for CORS - Vercel)
FRONTEND_URL=https://your-app.vercel.app

# Security
JWT_SECRET=your-secure-random-string
ADMIN_API_KEY=your-admin-api-key
```

---

## Health Check Endpoint

Added health check at:
- `GET /api/health`
- `GET /health`

Response:
```json
{
  "status": "Backend running successfully",
  "timestamp": 1234567890,
  "environment": "production"
}
```

---

## CORS Configuration

CORS is already configured via `CORSHandler.php`. To allow your Vercel frontend:

1. Set `FRONTEND_URL` environment variable to your Vercel URL
2. Set `APP_ENV=production`

The CORS handler will automatically add your frontend URL to the allowed origins.

---

## Render Deployment Steps

### Step 1: Push Code to GitHub

Make sure your code is pushed to your GitHub repository:

```bash
git add .
git commit -m "Configure for Render.com deployment"
git push origin main
```

### Step 2: Create Render Account

1. Go to [Render.com](https://render.com)
2. Sign up with your GitHub account

### Step 3: Create MySQL Database (if not using external DB)

1. In Render Dashboard, click **New** → **PostgreSQL** (or MySQL)
2. Configure:
   - Name: `bansari-mysql`
   - Database Name: `bansari_clinic`
   - User: `bansari_user`
3. Click **Create Database**
4. Copy the **Internal Database URL** for later use

### Step 4: Create Web Service

1. In Render Dashboard, click **New** → **Web Service**
2. Connect your GitHub repository
3. Configure:
   - **Name**: `bansari-backend`
   - **Environment**: `PHP`
   - **Build Command**: (leave empty - uses render.yaml)
   - **Start Command**: (leave empty - uses render.yaml)
   - **Plan**: `Free`

### Step 5: Configure Environment Variables

In the Render web service settings, add these environment variables:

| Key | Value |
|-----|-------|
| APP_ENV | `production` |
| DB_HOST | Your MySQL host (from Step 3 or external DB) |
| DB_PORT | `3306` |
| DB_NAME | `bansari_clinic` |
| DB_USER | Your database user |
| DB_PASS | Your database password (mark as secret) |
| FRONTEND_URL | Your Vercel frontend URL |
| JWT_SECRET | A secure random string |
| ADMIN_API_KEY | A secure random string |

### Step 6: Deploy

1. Click **Deploy Changes**
2. Wait for build to complete
3. Check the health endpoint: `https://bansari-backend.onrender.com/api/health`

---

## Important Notes for Free Plan

1. **Sleeping**: Free services sleep after 15 minutes of inactivity. First request after sleep may take 30+ seconds.

2. **Health Check**: Render uses `/` for health checks. Our router handles this by redirecting to admin login.

3. **Database**: If using external MySQL (e.g., from Cloudways, Hostinger, etc.), make sure to allow connections from Render's IP addresses.

4. **HTTPS**: Render provides free SSL certificates automatically.

---

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check DB_HOST, DB_PORT, DB_USER, DB_PASS
   - Ensure MySQL allows remote connections from Render

2. **CORS Errors**
   - Verify FRONTEND_URL is set correctly
   - Check that APP_ENV=production

3. **504 Timeout**
   - Free tier has 60-second timeout
   - Check if database query is hanging

### Check Logs

In Render Dashboard, go to **Logs** tab to see application logs.

---

## Next Steps

After deployment:
1. Update your Next.js frontend `.env.local` with the new backend URL
2. Redeploy frontend on Vercel
3. Test the application end-to-end

