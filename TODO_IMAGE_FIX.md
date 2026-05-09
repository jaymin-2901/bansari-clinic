# TODO: Fix Image Serving (Logo/Testimonials Placeholders)

## Current Status
- Servers running: Frontend 3000, Backend 8000, Admin 8080
- API returns correct image paths
- Files exist in uploads/general/, backend-php/public/uploads/general/
- router.php symlink path realpath/readfile fails (Windows issue) → 404

## Steps
1. [ ] Edit backend-php/router.php: Add fallback root uploads/ for /uploads/ requests
2. [ ] Restart backend server (Ctrl+C, rerun php -S localhost:8000 router.php)
3. [ ] Test http://localhost:8000/uploads/general/logo_1773331326_62c0137d.png → 200
4. [ ] Frontend auto-updates (polling), hard refresh
5. [ ] Verify logo in Navbar, testimonials images no placeholder

## Progress
0/5

## Edit router.php Diff
In uploads block, after $filePath construction:
```
if (!file_exists($filePath) || !is_file($filePath)) {
  $filePath = $rootDir . DIRECTORY_SEPARATOR
