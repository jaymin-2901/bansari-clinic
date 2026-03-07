<?php
/**
 * ============================================================
 * MediConnect - Secure Password Handler
 * File: backend/security/PasswordHandler.php
 * ============================================================
 * Provides secure password hashing, verification, and validation.
 * Handles password strength requirements and legacy password migration.
 */

class PasswordHandler
{
    // Password strength constants
    const MIN_LENGTH = 8;
    const REQUIRE_UPPERCASE = true;
    const REQUIRE_LOWERCASE = true;
    const REQUIRE_NUMBERS = true;
    const REQUIRE_SPECIAL = true;

    /**
     * Hash password using bcrypt
     * 
     * @param string $password
     * @return string Hashed password
     */
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12  // Iteration count (2^12 = 4096)
        ]);
    }

    /**
     * Verify password against hash
     * 
     * @param string $password Plain text password
     * @param string $hash Stored password hash
     * @return bool
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing (algorithm/cost changed)
     * 
     * @param string $hash Current hash
     * @return bool
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Validate password strength
     * 
     * @param string $password
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "Password must be at least " . self::MIN_LENGTH . " characters long";
        }

        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter (A-Z)";
        }

        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter (a-z)";
        }

        if (self::REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number (0-9)";
        }

        if (self::REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&*)";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Migrate legacy plain-text password to bcrypt
     * Called when legacy password is verified successfully
     * 
     * @param PDO $db
     * @param int $adminId
     * @param string $plainPassword
     * @return bool
     */
    public static function migrateLegacyPassword(PDO $db, int $adminId, string $plainPassword): bool
    {
        try {
            $hashedPassword = self::hash($plainPassword);
            $stmt = $db->prepare("UPDATE admins SET password = ? WHERE id = ?");
            return $stmt->execute([$hashedPassword, $adminId]);
        } catch (PDOException $e) {
            error_log("Password migration failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if hash is plain-text (legacy)
     * 
     * @param string $hash
     * @return bool
     */
    public static function isLegacyHash(string $hash): bool
    {
        // Bcrypt hash always starts with $2a$, $2b$, or $2y$
        return !preg_match('/^\$2[aby]\$/', $hash);
    }

    /**
     * Generate temporary password (for password reset)
     * 
     * @param int $length
     * @return string
     */
    public static function generateTemporaryPassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*';

        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Get password strength indicator text
     * 
     * @param string $password
     * @return string 'strong' | 'medium' | 'weak'
     */
    public static function getStrength(string $password): string
    {
        $strength = 0;

        if (strlen($password) >= 12) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) $strength++;

        if ($strength >= 5) return 'strong';
        if ($strength >= 3) return 'medium';
        return 'weak';
    }
}
