-- Fix logo path in DB - remove 'general/' prefix
UPDATE website_settings 
SET setting_value = 'logo_1773326104_22b54618.png'
WHERE setting_key = 'clinic_logo';

-- Verify
SELECT setting_key, setting_value FROM website_settings WHERE setting_key = 'clinic_logo';

