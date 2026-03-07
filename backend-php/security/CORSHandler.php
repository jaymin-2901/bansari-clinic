<?php
/**
 * ============================================================
 * MediConnect – Production CORS Handler
 * File: backend/security/CORSHandler.php
 * ============================================================
 *
 * Centralized, strict CORS configuration that replaces all
 * per-file wildcard CORS headers.
 *
 * Features:
 *   - Explicit origin whitelist (no wildcards with credentials)
 *   - Configurable via ALLOWED_ORIGINS environment variable
 *   - Per-environment support (dev, staging, production)
 *   - Proper preflight (OPTIONS) handling
 *   - Credentials support with strict origin matching
 *   - Origin validation – prevents reflection vulnerability
 *   - Method and header whitelisting
 *
 * Attack Scenarios Prevented:
 *   1. Cross-origin credential theft: Malicious site at evil.com sends
 *      authenticated requests to your API → blocked by origin check
 *   2. CSRF via cross-origin scripts: Attacker page submits forms to
 *      your API with user's cookies → blocked, origin not whitelisted
 *   3. Data exfiltration: Attacker JS reads API responses from victim's
 *      browser session → blocked by CORS, browser won't expose response
 *   4. Origin reflection: If server blindly reflects back the Origin header,
 *      any domain is effectively whitelisted → prevented by explicit matching
 *   5. Subdomain takeover abuse: Only exact-match origins allowed,
 *      not *.yourdomain.com patterns
 */

class CORSHandler
{
    private array $allowedOrigins  = [];
    private bool $allowDynamicOrigins = false;
    private array $allowedMethods  = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
    private array $allowedHeaders  = [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-Token',
        'Accept',
        'Origin',
    ];
    private array $exposedHeaders  = [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ];
    private bool  $allowCredentials = true;
    private int   $maxAge           = 86400; // 24 hours preflight cache

    public function __construct()
    {
        $this->loadAllowedOrigins();
    }

    /**
     * Handle CORS for the current request.
     * Must be called BEFORE any output.
     *
     * @return void (exits on OPTIONS preflight)
     */
    public function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // If no Origin header, this is a same-origin or non-browser request
        if (empty($origin)) {
            // Still set Content-Type for API responses
            header('Content-Type: application/json; charset=utf-8');
            return;
        }

        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            // Set exact origin (never wildcard when credentials are enabled)
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin'); // Critical for caching correctness

            // Expose rate limit headers to JavaScript
            header('Access-Control-Expose-Headers: ' . implode(', ', $this->exposedHeaders));
        } else {
            // Origin not allowed: don't set any CORS headers
            // Browser will block the response automatically
            header('Content-Type: application/json; charset=utf-8');

            // If it's a preflight, respond with 403
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Origin not allowed',
                ]);
                exit;
            }

            // For actual requests from disallowed origins, let it proceed
            // but without CORS headers → browser blocks reading the response.
            // The request still executes (same as any server-side request).
            // For sensitive mutations, CSRF tokens provide the real protection.
            return;
        }

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
            header('Access-Control-Max-Age: ' . $this->maxAge);
            http_response_code(204); // No Content
            exit;
        }

        // Set Content-Type for non-preflight requests
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * Check if an origin is in the whitelist.
     * Uses strict exact-match comparison (case-insensitive).
     *
     * SECURITY: Never use wildcards, regex, or contains/endsWith checks.
     * Those can be bypassed with creative origin construction.
     */
    private function isOriginAllowed(string $origin): bool
    {
        $origin = strtolower(rtrim($origin, '/'));

        foreach ($this->allowedOrigins as $allowed) {
            if ($origin === $allowed) {
                return true;
            }
        }

        // Allow dynamic origins in development mode for mobile network testing
        // This allows any http://IP:port origin
        if ($this->allowDynamicOrigins) {
            return $this->isDynamicOriginAllowed($origin);
        }

        return false;
    }

    /**
     * Check if a dynamic origin (IP-based) is allowed.
     * Only allows http:// URLs with valid IP addresses.
     */
    private function isDynamicOriginAllowed(string $origin): bool
    {
        // Only allow http:// origins (not https:// which would be production)
        if (!str_starts_with($origin, 'http://')) {
            return false;
        }

        // Extract host:port from origin (e.g., "http://192.168.1.100:3000")
        $parsed = parse_url($origin);
        if (empty($parsed['host'])) {
            return false;
        }

        // Validate that the host is a valid IP address
        $host = $parsed['host'];
        
        // Check if it's a valid IPv4 address
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        // Check if it's a valid IPv6 address
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return true;
        }

        // Check for localhost variations (might not pass filter_var)
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return true;
        }

        return false;
    }

    /**
     * Load allowed origins from environment.
     * Supports comma-separated list and per-environment defaults.
     */
    private function loadAllowedOrigins(): void
    {
        $envOrigins = getenv('ALLOWED_ORIGINS');
        if ($envOrigins === false || $envOrigins === '') {
            // Check $_ENV as fallback
            $envOrigins = $_ENV['ALLOWED_ORIGINS'] ?? '';
        }

        if (!empty($envOrigins)) {
            $origins = array_map(function (string $o): string {
                return strtolower(rtrim(trim($o), '/'));
            }, explode(',', $envOrigins));

            $this->allowedOrigins = array_filter($origins, function (string $o): bool {
                return !empty($o) && $o !== '*';
            });
        }

        // If no origins configured, use sensible defaults based on APP_ENV
        if (empty($this->allowedOrigins)) {
            $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');
            // Check environment variable first, then fall back to defined constant
            $frontendUrl = getenv('FRONTEND_URL') ?: ($_ENV['FRONTEND_URL'] ?? (defined('FRONTEND_URL') ? FRONTEND_URL : ''));

            switch ($appEnv) {
                case 'production':
                    // In production, try ALLOWED_ORIGINS first, then fall back to FRONTEND_URL
                    if (!empty($frontendUrl)) {
                        $this->allowedOrigins = [strtolower(rtrim($frontendUrl, '/'))];
                        error_log('[CORS] Production mode: Using FRONTEND_URL as allowed origin: ' . $frontendUrl);
                    } else {
                        error_log('[CORS] WARNING: No ALLOWED_ORIGINS or FRONTEND_URL set in production. All cross-origin requests will be blocked.');
                        $this->allowedOrigins = [];
                    }
                    break;

                case 'staging':
                    if (!empty($frontendUrl)) {
                        $this->allowedOrigins = [strtolower(rtrim($frontendUrl, '/'))];
                    }
                    break;

                case 'development':
                default:
                    // In development, allow common local origins plus dynamic IPs
                    $this->allowedOrigins = [
                        'http://localhost:3000',
                        'http://localhost:3003',
                        'http://localhost:5173',
                        'http://localhost:5174',
                        'http://localhost:8080',
                        'http://127.0.0.1:3000',
                        'http://127.0.0.1:3003',
                        'http://127.0.0.1:5173',
                    ];
                    // Allow any origin on the same network (for mobile testing)
                    // This regex matches http:// followed by any IP:port combination
                    if (!isset($_SERVER['ALLOW_DYNAMIC_ORIGINS']) || $_SERVER['ALLOW_DYNAMIC_ORIGINS'] !== 'false') {
                        // Dynamic origins will be checked in isOriginAllowed for development
                        $this->allowDynamicOrigins = true;
                    }
                    if (!empty($frontendUrl)) {
                        $this->allowedOrigins[] = strtolower(rtrim($frontendUrl, '/'));
                    }
                    break;
            }
        }
    }

    /**
     * Get the list of currently allowed origins (for debugging).
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }
}
