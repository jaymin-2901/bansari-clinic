# 🚀 Deployment Guide - Bansari Homeopathy Clinic

## Overview

This project has two parts:
1. **Frontend** (Next.js) - Hosted on Vercel: `https://bansari-clinic.vercel.app`
2. **Backend** (PHP) - Needs to be hosted separately (not on Vercel)

The frontend makes API calls to the backend. Currently, the backend is not deployed, causing network errors.

---

## Quick Start

### Step 1: Deploy PHP Backend

Deploy the `backend-php/` folder to any PHP hosting service:

| Provider | Free Tier | URL |
|----------|-----------|-----|
| **Render** | Yes | render.com |
| **Railway** | Yes | railway.app |
| **Cyclic** | Yes | cyclic.sh |
| **Fly.io** | Yes | fly.io |
| **Your cPanel** | Yes | your hosting |

### Step 2: Get Your Backend URL

After deployment, you'll get a URL like:
- `https://your-backend-name.onrender.com`
- `https://bansari-api.railway.app`

### Step 3: Update Vercel Environment Variables

Go to [Vercel Dashboard](https://vercel.com/dashboard) → Your Project → Settings → Environment Variables

Add:
```
PHP_BACKEND_URL = https://your-backend-url.com
```

### Step 4: Rebuild

Go to Vercel Dashboard → Deployments → Redeploy

---

## Detailed Deployment Instructions

### Option A: Deploy to Render (Recommended)

1. **Push code to GitHub**
   ```bash
   git add .
   git commit -m "Prepare for deployment"
   git push origin main
   ```

2. **Create Render Account**
   - Go to [render.com](https://render.com)
   - Connect your GitHub account

3. **Create Web Service**
   - Click "New +" → "Web Service"
   - Select your repository
   - Configure:
     - **Name**: `bansari-backend`
     - **Root Directory**: `backend-php`
     - **Build Command**: (leave empty)
     - **Start Command**: `php -S 0.0.0.0:$PORT router.php`
   - Click "Create Web Service"

4. **Get Your Backend URL**
   - Wait for deployment to complete
   - Copy the URL (e.g., `https://bansari-backend.onrender.com`)

### Option B: Deploy to Railway

1. **Install Railway CLI**
   ```bash
   npm install -g @railway/cli
   ```

2. **Login and Initialize**
   ```bash
   railway login
   railway init
   ```

3. **Deploy**
   ```bash
   cd backend-php
   railway up
   ```

### Option C: Deploy to cPanel/Shared Hosting

1. Upload `backend-php/` folder to your hosting
2. Point domain/subdomain to `backend-php/`
3. Ensure PHP version is 8.0+

---

## Database Setup

Your backend needs access to the MySQL database. Update the environment variables in your hosting:

| Variable | Value |
|----------|-------|
| `DB_HOST` | Your MySQL host |
| `DB_PORT` | 3307 |
| `DB_NAME` | bansari_clinic |
| `DB_USER` | Your database username |
| `DB_PASS` | Your database password |

---

## Frontend Configuration

### Option 1: Environment Variable (Recommended)

In Vercel, set:
```
PHP_BACKEND_URL = https://your-backend-url.com
```

### Option 2: Manual Configuration

Edit `frontend-nextjs/next.config.js`:

```javascript
async rewrites() {
  return [
    {
      source: '/api/clinic/:path*',
      destination: 'https://YOUR_BACKEND_URL/api/clinic/:path*',
    },
    {
      source: '/uploads/:path*',
      destination: 'https://YOUR_BACKEND_URL/uploads/:path*',
    },
  ];
},
```

---

## Troubleshooting

### Network Error Persists?
1. Check browser console for specific errors
2. Verify backend is responding: Visit `https://YOUR_BACKEND_URL/api/clinic/settings.php?group=general`
3. Check Vercel environment variables are set correctly

### CORS Errors?
The backend CORS is configured to allow:
- `https://bansari-clinic.vercel.app` (production)
- `http://localhost:3000` (development)

### Database Connection Failed?
1. Verify database credentials
2. Ensure database server allows remote connections
3. Check database `bansari_clinic` exists

---

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
│   Vercel (Frontend)│           │  Render/Railway     │
│                     │           │  (Backend PHP)      │
│ bansari-clinic.    │  ───────► │                     │
│    vercel.app      │   API     │ your-backend.       │
│                     │           │   onrender.com     │
│  Next.js App        │           │  PHP + MySQL        │
└─────────────────────┘           └─────────────────────┘
```

---

## File Structure

```
bansari-homeopathy/
├── frontend-nextjs/          # Next.js frontend (Vercel)
│   ├── src/
│   │   ├── app/             # Pages
│   │   ├── components/      # React components
│   │   └── lib/             # API utilities
│   ├── next.config.js       # API routing
│   └── package.json
│
├── backend-php/             # PHP backend (Deploy this separately)
│   ├── api/
│   │   └── clinic/          # API endpoints
│   ├── config/              # Database & settings
│   ├── security/            # CORS, Auth, etc.
│   ├── router.php           # Request routing
│   └── public/
│       └── uploads/         # Image uploads
│
├── clinic-admin-php/        # Admin panel (Deploy with backend)
│   ├── index.php
│   ├── appointments.php
│   └── ...
│
└── public/                  # Static files (Deploy with backend)
    └── uploads/
```

---

## Need Help?

1. Check the browser console (F12) for error messages
2. Verify backend is live: Visit your backend URL directly
3. Check Vercel deployment logs for errors

