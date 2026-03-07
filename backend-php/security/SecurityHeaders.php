<?php
/**
 * ============================================================
 * MediConnect – Security Headers Middleware
 * File: backend/security/SecurityHeaders.php
 * ============================================================
 *
 * Adds defense-in-depth HTTP security headers to every response.
 * These headers instruct browsers to enable built-in security features.
 *
 * Headers applied:
 *   - X-Content-Type-Options: nosniff        → Prevents MIME type sniffing
 *   - X-Frame-Options: DENY                  → Prevents clickjacking
 *   - X-XSS-Protection: 0                    → Defer to CSP (legacy header)
 *   - Referrer-Policy: strict-origin-when-cross-origin → Limits referrer leakage
 *   - Content-Security-Policy                → Restricts resource loading
 *   - Strict-Transport-Security              → Forces HTTPS
 *   - Permissions-Policy                     → Restricts browser features
 *   - Cache-Control                          → Prevents caching sensitive data
 */

class SecurityHeaders
{
    /**
     * Apply all security headers.
     * Call before any output.
     */
    public static function apply(): void
    {
        // Prevent MIME type sniffing (e.g., treating JSON as HTML)
        header('X-Content-Type-Options: nosniff');

        // Prevent clickjacking by disallowing framing
        header('X-Frame-Options: DENY');

        // Disable legacy XSS filter (CSP is the modern replacement)
        // Setting to 0 avoids XSS filter bugs that can INTRODUCE vulnerabilities
        header('X-XSS-Protection: 0');

        // Control referrer information sent with requests
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Restrict browser feature access (camera, mic, geolocation, etc.)
        header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()");

        // Prevent caching of API responses containing sensitive data
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');

        // HSTS: Force HTTPS (only in production)
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');
        if ($appEnv === 'production') {
            // max-age=31536000 = 1 year, includeSubDomains for full coverage
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Content Security Policy for API responses
        // APIs return JSON, so restrict everything
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    }
}
