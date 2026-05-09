<?php
// Run: php backend-php/sql/run_activate_testimonials.php
require_once '../config/clinic_db.php';

$db = getClinicDB();

$updated = $db->exec("
    UPDATE testimonials 
    SET display_status = 'active', 
        sort_order = (
            SELECT @row_number := @row_number + 1 
            FROM (SELECT @row_number := 0) AS t
            ORDER BY id DESC
        )
    WHERE display_status != 'active' OR display_status IS NULL
");

echo "✅ Activated $updated testimonials\n";

$active = $db->query("SELECT COUNT(*) FROM testimonials WHERE display_status = 'active'")->fetchColumn();
echo "📊 Active testimonials: $active\n";

print_r($db->query("
    SELECT id, patient_name, before_image, after_image, sort_order 
    FROM testimonials 
    WHERE display_status = 'active' 
    ORDER BY sort_order DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC));

