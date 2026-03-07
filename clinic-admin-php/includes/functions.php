<?php
/**
 * Upload helper functions
 */

function uploadImage(array $file, string $destination, string $prefix = 'img'): ?string
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > $maxSize) return null;
    if (!in_array($file['type'], $allowedTypes)) return null;

    // Create destination directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $filepath = rtrim($destination, '/') . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }

    return null;
}

function deleteImage(string $filepath): bool
{
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Paginate query helper
 */
function getPagination(int $totalRecords, int $perPage = 15, int $currentPage = 1): array
{
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $totalRecords,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

/**
 * Format date for display
 */
function formatDate(string $date, string $format = 'd M Y'): string
{
    return date($format, strtotime($date));
}

/**
 * Sanitize input
 */
function clean(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
