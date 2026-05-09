<?php
/**
 * Hero Image Actions
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../backend-php/config/clinic_config.php';
require_once __DIR__ . '/../../backend-php/config/clinic_db.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify admin session
if (!getAdminID()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$db = getClinicDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        setFlash('danger', 'Invalid security token.');
        header('Location: ../hero_management.php');
        exit;
    }

    if ($action === 'add') {
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        
        $desktopImage = null;
        $mobileImage = null;

        // Upload Desktop Image (Required)
        if (isset($_FILES['desktop_image']) && $_FILES['desktop_image']['error'] === UPLOAD_ERR_OK) {
            $desktopImage = uploadImage($_FILES['desktop_image'], HERO_UPLOAD_PATH, 'hero_d');
        }

        // Upload Mobile Image (Optional)
        if (isset($_FILES['mobile_image']) && $_FILES['mobile_image']['error'] === UPLOAD_ERR_OK) {
            $mobileImage = uploadImage($_FILES['mobile_image'], HERO_UPLOAD_PATH, 'hero_m');
        }

        if ($desktopImage) {
            try {
                $stmt = $db->prepare("INSERT INTO hero_images (desktop_image, mobile_image, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$desktopImage, $mobileImage, $sortOrder]);
                setFlash('success', 'Hero image added successfully.');
            } catch (Exception $e) {
                setFlash('danger', 'Database error: ' . $e->getMessage());
            }
        } else {
            setFlash('danger', 'Failed to upload desktop image.');
        }
        header('Location: ../hero_management.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            // Get filenames first
            $stmt = $db->prepare("SELECT desktop_image, mobile_image FROM hero_images WHERE id = ?");
            $stmt->execute([$id]);
            $hero = $stmt->fetch();

            if ($hero) {
                // Delete files
                deleteImage(HERO_UPLOAD_PATH . '/' . $hero['desktop_image']);
                if ($hero['mobile_image']) {
                    deleteImage(HERO_UPLOAD_PATH . '/' . $hero['mobile_image']);
                }

                // Delete record
                $stmt = $db->prepare("DELETE FROM hero_images WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Hero image deleted.');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        header('Location: ../hero_management.php');
        exit;
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);
        try {
            $stmt = $db->prepare("UPDATE hero_images SET is_active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            setFlash('success', 'Status updated.');
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        header('Location: ../hero_management.php');
        exit;
    }

    if ($action === 'update_order') {
        $orders = $_POST['order'] ?? [];
        try {
            $stmt = $db->prepare("UPDATE hero_images SET sort_order = ? WHERE id = ?");
            foreach ($orders as $id => $val) {
                $stmt->execute([(int)$val, (int)$id]);
            }
            setFlash('success', 'Sort order updated.');
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        header('Location: ../hero_management.php');
        exit;
    }
}

header('Location: ../hero_management.php');
exit;
