# Implementation Plan

## Task 1: Separate Hero Background Image for Mobile

### 1.1 Admin Panel - Add Mobile Hero Image Field
- **File:** `clinic-admin-php/settings.php`
- Add new file input field for `home_hero_image_mobile` in the Home Page tab
- Handle upload in the POST handler section

### 1.2 Backend API - Settings Endpoint (if needed)
- **File:** `backend-php/api/clinic/settings.php`
- Already handles image paths correctly, no changes needed

### 1.3 Frontend - Hero Section with Responsive Images
- **File:** `frontend-nextjs/src/app/page.tsx`
- Add state to track screen size (useWindowSize or media query)
- Conditionally show mobile vs desktop hero image
- Add fallback to desktop image if mobile not uploaded

## Task 2: Fix Legal Pages Not Showing

### 2.1 Fix Privacy Policy Frontend API URL
- **File:** `frontend-nextjs/src/app/privacy-policy/page.tsx`
- Change from `${BACKEND_URL}/api/clinic/legal_page.php` to `/api/clinic/legal_page.php`

### 2.2 Fix Terms & Conditions Frontend API URL
- **File:** `frontend-nextjs/src/app/terms-conditions/page.tsx`
- Same fix as privacy policy

### 2.3 Add Refund Policy Support (Optional Enhancement)
- Add to legal_pages admin panel
- Create frontend page for refund-policy

## Task 3: Testing & Verification
- Test hero image on mobile viewport
- Test legal pages content display
- Verify API caching is disabled (already using `cache: 'no-store'`)

