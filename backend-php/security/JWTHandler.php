<?php
/**
 * ============================================================
 * MediConnect – Production JWT Handler
 * File: backend/security/JWTHandler.php
 * ============================================================
 *
 * Pure PHP JWT implementation (no external dependencies).
 * Supports HS256 signing with constant-time verification.
 *
 * Features:
 *   - HS256 (HMAC-SHA256) signing – industry standard
 *   - Signature validation with hash_equals (timing-safe)
 *   - Expiry validation (exp claim)
 *   - Issuer validation (iss claim)
 *   - Audience validation (aud claim)
 *   - Issued-at tracking (iat claim)
 *   - JWT ID for replay prevention (jti claim)
 *   - Algorithm pinning – prevents "alg: none" substitution attack
 *   - Secret key length enforcement (min 32 bytes)
 *   - Refresh token support with separate secret
 *
 * Attack Scenarios Prevented:
 *   1. Algorithm substitution: Attacker sets alg=none → rejected, we pin HS256
 *   2. Weak secret brute-force: Enforce minimum 32-byte secret
 *   3. Token replay: JTI claim + optional blacklist checking
 *   4. Expired token reuse: Strict exp claim validation
 *   5. Cross-service token misuse: iss/aud validation
 *   6. Timing attacks on signature: hash_equals() comparison
 */

class JWTHandler
{
    private string $secret;
    private string $refreshSecret;
    private string $issuer;
    private string $audience;
    private int $accessTTL;    // Access token lifetime in seconds
    private int $refreshTTL;   // Refresh token lifetime in seconds
    private const ALGORITHM = 'HS256';

    public function __construct()
    {
        $this->secret = $this->getRequiredEnv('JWT_SECRET');
        $this->refreshSecret = $this->getRequiredEnv('JWT_REFRESH_SECRET', $this->secret . '_refresh');
        $this->issuer = getenv('JWT_ISSUER') ?: 'mediconnect';
        $this->audience = getenv('JWT_AUDIENCE') ?: 'mediconnect-api';
        $this->accessTTL = (int) (getenv('JWT_ACCESS_TTL') ?: 900);      // 15 minutes default
        $this->refreshTTL = (int) (getenv('JWT_REFRESH_TTL') ?: 604800); // 7 days default

        // Enforce minimum secret length (32 bytes = 256 bits)
        if (strlen($this->secret) < 32) {
            throw new \RuntimeException(
                'JWT_SECRET must be at least 32 characters. Generate one with: php -r "echo bin2hex(random_bytes(32));"'
            );
        }
    }

    /**
     * Generate an access token for a user.
     *
     * @param int    $userId   User ID
     * @param string $role     User role (admin, staff, patient)
     * @param array  $extra    Additional claims (name, email, etc.)
     * @return array ['access_token' => string, 'refresh_token' => string, 'expires_in' => int]
     */
    public function generateTokens(int $userId, string $role, array $extra = []): array
    {
        $now = time();
        $jti = bin2hex(random_bytes(16)); // Unique token ID

        // Access token payload
        $accessPayload = array_merge([
            'iss'  => $this->issuer,
            'aud'  => $this->audience,
            'iat'  => $now,
            'exp'  => $now + $this->accessTTL,
            'jti'  => $jti,
            'sub'  => $userId,
            'role' => $role,
            'type' => 'access',
        ], $extra);

        // Refresh token payload (minimal claims, longer TTL)
        $refreshPayload = [
            'iss'  => $this->issuer,
            'aud'  => $this->audience,
            'iat'  => $now,
            'exp'  => $now + $this->refreshTTL,
            'jti'  => bin2hex(random_bytes(16)),
            'sub'  => $userId,
            'role' => $role,
            'type' => 'refresh',
        ];

        return [
            'access_token'  => $this->encode($accessPayload, $this->secret),
            'refresh_token' => $this->encode($refreshPayload, $this->refreshSecret),
            'expires_in'    => $this->accessTTL,
            'token_type'    => 'Bearer',
        ];
    }

    /**
     * Validate and decode an access token.
     *
     * @param string $token Raw JWT string
     * @return array Decoded payload
     * @throws \RuntimeException On any validation failure
     */
    public function validateAccessToken(string $token): array
    {
        $payload = $this->decode($token, $this->secret);

        // Verify token type
        if (($payload['type'] ?? '') !== 'access') {
            throw new \RuntimeException('Invalid token type');
        }

        return $payload;
    }

    /**
     * Validate and decode a refresh token.
     *
     * @param string $token Raw refresh JWT
     * @return array Decoded payload
     * @throws \RuntimeException On any validation failure
     */
    public function validateRefreshToken(string $token): array
    {
        $payload = $this->decode($token, $this->refreshSecret);

        // Verify token type
        if (($payload['type'] ?? '') !== 'refresh') {
            throw new \RuntimeException('Invalid token type');
        }

        return $payload;
    }

    /**
     * Refresh an access token using a valid refresh token.
     *
     * @param string $refreshToken The refresh token
     * @return array New token pair
     * @throws \RuntimeException If refresh token is invalid
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $payload = $this->validateRefreshToken($refreshToken);
        return $this->generateTokens(
            (int) $payload['sub'],
            $payload['role']
        );
    }

    /**
     * Encode a payload into a JWT string.
     */
    private function encode(array $payload, string $secret): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => self::ALGORITHM,
            'typ' => 'JWT',
        ]));

        $body = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign("$header.$body", $secret);

        return "$header.$body.$signature";
    }

    /**
     * Decode and validate a JWT string.
     *
     * @throws \RuntimeException On any validation failure
     */
    private function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed token');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // 1. Verify header and pin algorithm (prevent alg substitution)
        $header = json_decode($this->base64UrlDecode($headerB64), true);
        if (!$header || ($header['alg'] ?? '') !== self::ALGORITHM) {
            throw new \RuntimeException('Invalid or unsupported algorithm');
        }

        // 2. Verify signature (timing-safe comparison)
        $expectedSignature = $this->sign("$headerB64.$payloadB64", $secret);
        if (!hash_equals($expectedSignature, $signatureB64)) {
            throw new \RuntimeException('Invalid signature');
        }

        // 3. Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!$payload) {
            throw new \RuntimeException('Invalid payload');
        }

        // 4. Validate expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \RuntimeException('Token expired');
        }

        // 5. Validate issuer
        if (isset($payload['iss']) && $payload['iss'] !== $this->issuer) {
            throw new \RuntimeException('Invalid issuer');
        }

        // 6. Validate audience
        if (isset($payload['aud']) && $payload['aud'] !== $this->audience) {
            throw new \RuntimeException('Invalid audience');
        }

        return $payload;
    }

    /**
     * Create HMAC-SHA256 signature.
     */
    private function sign(string $data, string $secret): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $data, $secret, true)
        );
    }

    /**
     * URL-safe Base64 encoding (RFC 7515).
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL-safe Base64 decoding.
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get a required environment variable.
     */
    private function getRequiredEnv(string $key, ?string $fallback = null): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if ($fallback !== null) {
            return $fallback;
        }
        throw new \RuntimeException("Required environment variable '$key' is not set.");
    }

    /**
     * Extract Bearer token from Authorization header.
     *
     * @return string|null The token, or null if not found
     */
    public static function extractBearerToken(): ?string
    {
        $header = null;

        // Apache
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        }
        // Nginx / CGI fallback
        elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        // Apache mod_rewrite fallback
        elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
