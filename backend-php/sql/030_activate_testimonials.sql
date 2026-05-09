-- Activate all existing testimonials for public display
-- Run this via php backend-php/sql/run_setup.php or phpMyAdmin

UPDATE testimonials 
SET display_status = 'active', 
    sort_order = (
        SELECT @row_number := @row_number + 1 
        FROM (SELECT @row_number := 0) AS t
        ORDER BY id DESC
    )
WHERE display_status != 'active' OR display_status IS NULL;

-- Verify results
SELECT id, patient_name, display_status, sort_order, created_at 
FROM testimonials 
ORDER BY sort_order DESC, created_at DESC 
LIMIT 10;

-- Run activation script
echo "Activated $(mysql -u[USER] -p[PASS] [DB] -e \"UPDATE testimonials SET display_status='active'\") rows";

