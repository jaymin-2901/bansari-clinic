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
- [ ] 5. Commit changes to GitHub
- [ ] 6. Deploy to Vercel
- [ ] 7. Test the connection

## Files Modified
1. frontend-nextjs/src/lib/api.ts
2. frontend-nextjs/src/app/admin/login/page.tsx
3. frontend-nextjs/src/app/api/admin/login/route.ts

