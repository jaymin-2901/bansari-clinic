<?php
/**
 * Admin Authentication Helper
 * Secure session with HttpOnly, Secure, SameSite cookies
 */

// Load secure session configuration before starting session
require_once __DIR__ . '/../../backend-php/security/session_config.php';

session_start();

function requireAdmin(): void
{
    if (!isset($_SESSION['clinic_admin']) || empty($_SESSION['clinic_admin']['id'])) {
        header('Location: login.php');
        exit;
    }
    // Session timeout: 2 hours
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function isAdmin(): bool
{
    return isset($_SESSION['clinic_admin']) && !empty($_SESSION['clinic_admin']['id']);
}

function getAdminName(): string
{
    return $_SESSION['clinic_admin']['name'] ?? 'Admin';
}

function getAdminId(): int
{
    return (int)($_SESSION['clinic_admin']['id'] ?? 0);
}

function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
