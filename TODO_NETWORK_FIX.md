# TODO: Network Connectivity Fix

## Problem
- Frontend shows "Network error. Please check your connection."
- Vercel server-to-server proxy cannot connect to InfinityFree SSL
- Backend works when accessed directly from browser

## Solution
Update frontend to call InfinityFree backend directly from browser instead of through Vercel proxy

## Tasks
- [x] 1. Diagnose the issue (done - server-to-server SSL handshake failure)
- [x] 2. Update frontend-nextjs/src/lib/api.ts to use NEXT_PUBLIC_BACKEND_URL
- [x] 3. Update frontend-nextjs/src/app/admin/login/page.tsx for direct backend call
- [x] 4. Update frontend-nextjs/src/app/api/admin/login/route.ts env variable
- [x] 5. Add improved error handling and logging in api.ts
- [x] 6. Add health check endpoint (backend-php/api/clinic/health.php)
- [x] 7. Update login page to show detailed error messages
- [ ] 8. Commit changes to GitHub
- [ ] 9. Deploy frontend to Vercel
- [ ] 10. Upload health.php to InfinityFree backend
- [ ] 11. Test the connection using health endpoint

## Files Modified
1. frontend-nextjs/src/lib/api.ts - Added better URL handling, error logging, credentials support
2. frontend-nextjs/src/app/login/page.tsx - Improved error messages
3. backend-php/api/clinic/health.php - New health check endpoint

## Deployment Steps

### Step 1: Deploy Health Check to InfinityFree
Upload `backend-php/api/clinic/health.php` to your InfinityFree htdocs/backend-php/api/clinic/ folder.

### Step 2: Test Backend Connectivity
Visit: https://bansari-homeopathic-clinic.infinityfreeapp.com/api/clinic/health.php

You should see:
```json
{
    "success": true,
    "message": "Backend is reachable",
    "timestamp": ...,
    "backend_url": "https://bansari-homeopathic-clinic.infinityfreeapp.com",
    "frontend_url": "https://bansari-clinic.vercel.app"
}
```

### Step 3: Deploy Frontend to Vercel
Commit and push the changes, then deploy to Vercel.

### Step 4: Test Login
Visit https://bansari-clinic.vercel.app/login and check the browser console for detailed error messages.

## Environment Variables Required
Make sure these are set in Vercel:
- `NEXT_PUBLIC_BACKEND_URL` = `https://bansari-homeopathic-clinic.infinityfreeapp.com`

