<?php
/**
 * ============================================================
 * MediConnect – Secure Session Configuration
 * File: backend/security/session_config.php
 * ============================================================
 * Centralised session hardening for all admin panels.
 * Configures secure cookie settings, strict mode, and entropy.
 * Include this BEFORE session_start() in auth files.
 *
 * Security measures:
 *  - HttpOnly cookies (prevent JS access)
 *  - Secure flag (HTTPS only in production)
 *  - SameSite=Lax (CSRF protection)
 *  - Strict mode (reject uninitialized session IDs)
 *  - Custom session name (no default "PHPSESSID" fingerprint)
 *  - Short cookie lifetime (browser session)
 *  - Entropy source for strong session IDs
 */

// Only configure if session hasn't started yet
if (session_status() === PHP_SESSION_NONE) {
    // Detect production environment
    $isProduction = (
        getenv('APP_ENV') === 'production' ||
        (defined('APP_ENV') && APP_ENV === 'production') ||
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    );

    // ── Cookie security settings ──
    ini_set('session.cookie_httponly', '1');        // Prevent JavaScript access
    ini_set('session.cookie_secure', $isProduction ? '1' : '0'); // HTTPS only in production
    ini_set('session.cookie_samesite', 'Strict');   // Strict CSRF protection for admin panel
    ini_set('session.use_strict_mode', '1');        // Reject uninitialized IDs
    ini_set('session.use_only_cookies', '1');       // No session ID in URLs
    ini_set('session.use_trans_sid', '0');           // No URL-based session ID
    ini_set('session.cookie_lifetime', '0');         // Session cookie (browser close)
    ini_set('session.gc_maxlifetime', '7200');       // 2 hour server-side expiry
    ini_set('session.sid_length', '48');             // Longer session ID
    ini_set('session.sid_bits_per_character', '6'); // More entropy per character

    // Custom session name (avoid default PHPSESSID fingerprint)
    session_name('MCADMIN_SID');
}
