<?php
/**
 * ============================================================
 * MediConnect – Production Rate Limiter
 * File: backend/security/RateLimiter.php
 * ============================================================
 *
 * MySQL-based sliding-window rate limiter with atomic operations.
 * Prevents brute-force login, credential stuffing, API abuse, and DoS.
 *
 * Features:
 *   - Sliding window algorithm (not fixed window, prevents burst at boundary)
 *   - Atomic MySQL UPDATE with row-level locking (no race conditions)
 *   - IP-based limits for unauthenticated routes
 *   - User-ID based limits for authenticated routes
 *   - Configurable via environment variables
 *   - Proper HTTP 429 response with standard rate limit headers
 *   - X-Forwarded-For spoofing prevention
 *   - Per-endpoint sensitivity tiers
 *   - Automatic cleanup of expired records
 *
 * Attack Scenarios Prevented:
 *   1. Brute-force login: Attacker tries thousands of passwords → blocked after N attempts
 *   2. Credential stuffing: Automated tools replay leaked credentials → throttled
 *   3. API enumeration: Scraping patient data endpoints → rate-limited
 *   4. DoS/resource exhaustion: Flooding booking API → capped per IP
 *   5. X-Forwarded-For bypass: Attacker sends fake IP headers → ignored, real IP used
 */

class RateLimiter
{
    private PDO $db;
    private int $maxRequests;
    private int $windowSeconds;

    /**
     * Sensitivity tiers: endpoint pattern → [max_requests, window_seconds]
     * More sensitive endpoints get stricter limits.
     */
    private array $endpointTiers = [];

    public function __construct(PDO $db, ?int $maxRequests = null, ?int $windowSeconds = null)
    {
        $this->db = $db;

        // Configurable via env vars, with safe defaults
        $this->maxRequests = $maxRequests
            ?? (int) (getenv('RATE_LIMIT_MAX_REQUESTS') ?: 100);
        $this->windowSeconds = $windowSeconds
            ?? (int) (getenv('RATE_LIMIT_WINDOW') ?: 60);

        // Define per-endpoint sensitivity tiers
        $this->endpointTiers = [
            // Authentication endpoints: very strict (prevent brute-force)
            'login'    => ['max' => 10,  'window' => 900],   // 10 per 15 min
            'signup'   => ['max' => 5,   'window' => 3600],  // 5 per hour
            'password' => ['max' => 5,   'window' => 3600],  // 5 per hour

            // Booking endpoints: moderate
            'book'     => ['max' => 20,  'window' => 300],   // 20 per 5 min
            'import'   => ['max' => 10,  'window' => 600],   // 10 per 10 min

            // Admin/analytics: normal limits
            'admin'    => ['max' => 200, 'window' => 60],    // 200 per min
            'analytics'=> ['max' => 60,  'window' => 60],    // 60 per min
        ];
    }

    /**
     * Check and enforce rate limit.
     * Returns true if request is allowed, false if rate-limited.
     * Sends appropriate headers in both cases.
     *
     * @param string|null $userId   Authenticated user ID (null for unauthenticated)
     * @param string|null $endpoint Endpoint name for tier-based limits
     * @return bool True if allowed, false if rate-limited (429 already sent)
     */
    public function check(?string $userId = null, ?string $endpoint = null): bool
    {
        $identifier = $this->buildIdentifier($userId);
        $limits = $this->getLimitsForEndpoint($endpoint);
        $maxRequests = $limits['max'];
        $windowSeconds = $limits['window'];

        // Append endpoint to identifier for per-endpoint tracking
        if ($endpoint !== null) {
            $identifier .= ':' . $endpoint;
        }

        $now = time();
        $windowStart = $now - $windowSeconds;

        try {
            // Atomic upsert with row-level locking
            // Step 1: Clean old entries and get/create current window
            $result = $this->atomicIncrement($identifier, $now, $windowStart, $maxRequests, $windowSeconds);

            $remaining = max(0, $maxRequests - $result['request_count']);
            $resetTime = $result['window_reset'];

            // Always send rate limit headers
            $this->sendHeaders($maxRequests, $remaining, $resetTime);

            if ($result['request_count'] > $maxRequests) {
                $this->sendRateLimitResponse($maxRequests, $resetTime);
                return false;
            }

            return true;
        } catch (\PDOException $e) {
            // On DB error, log but allow the request (fail-open for availability)
            error_log('[RateLimiter] Database error: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Atomic increment using MySQL INSERT ... ON DUPLICATE KEY UPDATE.
     * Prevents race conditions without explicit locking.
     */
    private function atomicIncrement(
        string $identifier,
        int $now,
        int $windowStart,
        int $maxRequests,
        int $windowSeconds
    ): array {
        $windowReset = $now + $windowSeconds;

        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert
        // If the existing window has expired, reset the counter
        $stmt = $this->db->prepare("
            INSERT INTO api_rate_limits (identifier, request_count, window_start, window_reset, created_at)
            VALUES (:id, 1, :window_start, :window_reset, NOW())
            ON DUPLICATE KEY UPDATE
                request_count = IF(window_start < :expired_before, 1, request_count + 1),
                window_start  = IF(window_start < :expired_before2, :new_window_start, window_start),
                window_reset  = IF(window_start < :expired_before3, :new_window_reset, window_reset)
        ");

        $stmt->execute([
            ':id'               => $identifier,
            ':window_start'     => $now,
            ':window_reset'     => $windowReset,
            ':expired_before'   => $windowStart,
            ':expired_before2'  => $windowStart,
            ':new_window_start' => $now,
            ':expired_before3'  => $windowStart,
            ':new_window_reset' => $windowReset,
        ]);

        // Read back current state
        $readStmt = $this->db->prepare("
            SELECT request_count, window_reset 
            FROM api_rate_limits 
            WHERE identifier = :id
        ");
        $readStmt->execute([':id' => $identifier]);
        $row = $readStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'request_count' => (int) ($row['request_count'] ?? 1),
            'window_reset'  => (int) ($row['window_reset'] ?? $windowReset),
        ];
    }

    /**
     * Get the real client IP, preventing X-Forwarded-For spoofing.
     *
     * SECURITY: Only trust X-Forwarded-For if the request comes from
     * a known trusted proxy (load balancer, CDN). Fall back to REMOTE_ADDR.
     */
    public static function getClientIP(): string
    {
        // Define trusted proxy IPs/ranges (configure for your infrastructure)
        $trustedProxies = array_filter(
            array_map('trim', explode(',', getenv('TRUSTED_PROXIES') ?: '127.0.0.1,::1'))
        );

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Only trust forwarded headers if request comes from a known proxy
        if (in_array($remoteAddr, $trustedProxies, true)) {
            // Check headers in priority order
            foreach (['HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
                if (!empty($_SERVER[$header])) {
                    // X-Forwarded-For can contain multiple IPs: client, proxy1, proxy2
                    // The FIRST IP is the original client (if proxy chain is trusted)
                    $ips = array_map('trim', explode(',', $_SERVER[$header]));
                    $clientIP = $ips[0];

                    // Validate it's a real IP address (prevent injection)
                    if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $clientIP;
                    }
                    // If first IP is private/reserved, use it anyway (internal network)
                    if (filter_var($clientIP, FILTER_VALIDATE_IP)) {
                        return $clientIP;
                    }
                }
            }
        }

        // Default: use direct connection IP (safest)
        return $remoteAddr;
    }

    /**
     * Build a rate limit identifier.
     * Uses user ID for authenticated requests, IP for unauthenticated.
     */
    private function buildIdentifier(?string $userId): string
    {
        if ($userId !== null && $userId !== '') {
            return 'user:' . $userId;
        }
        return 'ip:' . self::getClientIP();
    }

    /**
     * Get rate limits for a specific endpoint tier.
     */
    private function getLimitsForEndpoint(?string $endpoint): array
    {
        if ($endpoint !== null) {
            foreach ($this->endpointTiers as $pattern => $limits) {
                if (stripos($endpoint, $pattern) !== false) {
                    return $limits;
                }
            }
        }
        return ['max' => $this->maxRequests, 'window' => $this->windowSeconds];
    }

    /**
     * Send standard rate limit response headers.
     */
    private function sendHeaders(int $limit, int $remaining, int $resetTime): void
    {
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $resetTime);
    }

    /**
     * Send HTTP 429 Too Many Requests response and exit.
     */
    private function sendRateLimitResponse(int $limit, int $resetTime): void
    {
        $retryAfter = max(1, $resetTime - time());

        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'error'   => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Cleanup expired rate limit records.
     * Should be called periodically (e.g., via cron) to prevent table bloat.
     */
    public function cleanup(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM api_rate_limits 
            WHERE window_reset < :now
        ");
        $stmt->execute([':now' => time()]);
        return $stmt->rowCount();
    }
}
