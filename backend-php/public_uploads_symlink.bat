@echo off
cd /d "d:\bansari-homeopathy\backend-php"
if not exist "public" mkdir public
cd public
rmdir /s /q uploads >nul 2>&1
mklink /J uploads ..\..\uploads
echo ✅ Symlink created: backend-php\public\uploads → d:\bansari-homeopathy\uploads
echo Test: curl http://localhost:8080/uploads/testimonials/before_cropped_1772891495_5c8e6c8e.jpg
pause
