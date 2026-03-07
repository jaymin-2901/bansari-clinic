<?php
/**
 * ============================================================
 * MediConnect - Legal Pages Manager API
 * File: clinic-admin/api/legal_pages.php
 * ============================================================
 * Manage Privacy Policy and Terms & Conditions
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';

requireAdmin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;

// ─── GET all legal pages ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    try {
        $db = getClinicDB();
        $pages = $db->query("
            SELECT id, title, slug, content, updated_at, updated_by 
            FROM legal_pages 
            ORDER BY created_at
        ")->fetchAll();

        echo json_encode(['success' => true, 'data' => $pages]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// ─── GET single page ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'view') {
    $slug = $_GET['slug'] ?? null;
    
    if (!$slug) {
        echo json_encode(['success' => false, 'message' => 'Page not specified']);
        exit;
    }

    try {
        $db = getClinicDB();
        $stmt = $db->prepare("
            SELECT id, title, slug, content, updated_at, updated_by 
            FROM legal_pages 
            WHERE slug = ?
        ");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();

        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $page]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// ─── UPDATE legal page ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $slug = $_POST['slug'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? ''; // HTML content allowed

    if (!$slug || !$title || !$content) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        exit;
    }

    // Sanitize HTML (allow basic formatting, prevent XSS)
    $allowedTags = '<p><div><span><br><strong><em><u><li><ul><ol><h1><h2><h3><h4><h5><h6><a><img>';
    $content = strip_tags($content, $allowedTags);

    try {
        $db = getClinicDB();
        
        // Check if page exists
        $stmt = $db->prepare("SELECT id FROM legal_pages WHERE slug = ?");
        $stmt->execute([$slug]);
        $exists = $stmt->fetch();

        if ($exists) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE legal_pages 
                SET title = ?, content = ?, updated_by = ?, updated_at = NOW()
                WHERE slug = ?
            ");
            $stmt->execute([$title, $content, getAdminId(), $slug]);
            
            echo json_encode(['success' => true, 'message' => 'Page updated successfully']);
        } else {
            // Create new
            $stmt = $db->prepare("
                INSERT INTO legal_pages (title, slug, content, updated_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$title, $slug, $content, getAdminId()]);
            
            echo json_encode(['success' => true, 'message' => 'Page created successfully']);
        }
    } catch (PDOException $e) {
        error_log("Legal page update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update page']);
    }
    exit;
}

// ─── DELETE legal page (admin only) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $slug = $_POST['slug'] ?? null;

    if (!$slug) {
        echo json_encode(['success' => false, 'message' => 'Page not specified']);
        exit;
    }

    // Prevent deletion of core pages
    if (in_array($slug, ['privacy-policy', 'terms-conditions'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete core legal pages']);
        exit;
    }

    try {
        $db = getClinicDB();
        $stmt = $db->prepare("DELETE FROM legal_pages WHERE slug = ?");
        $stmt->execute([$slug]);

        echo json_encode(['success' => true, 'message' => 'Page deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete page']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
