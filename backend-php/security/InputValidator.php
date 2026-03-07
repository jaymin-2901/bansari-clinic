<?php
/**
 * ============================================================
 * MediConnect - Input Validator & Sanitizer
 * File: backend/security/InputValidator.php
 * ============================================================
 * Server-side validation and sanitization for all inputs
 * Prevents SQL injection, XSS, and ensures data integrity
 */

class InputValidator
{
    /**
     * Validate and sanitize email
     * 
     * @param string $email
     * @return array ['valid' => bool, 'value' => string|null, 'error' => string|null]
     */
    public static function email(string $email): array
    {
        $email = trim($email);
        
        if (empty($email)) {
            return ['valid' => false, 'value' => null, 'error' => 'Email is required'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'value' => null, 'error' => 'Invalid email format'];
        }

        // Sanitize to remove any non-standard characters
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        if (strlen($email) > 150) {
            return ['valid' => false, 'value' => null, 'error' => 'Email is too long'];
        }

        return ['valid' => true, 'value' => $email, 'error' => null];
    }

    /**
     * Validate and sanitize phone number (India format)
     * Accepts 10 digits, optional country code +91
     * 
     * @param string $phone
     * @return array ['valid' => bool, 'value' => string|null, 'error' => string|null]
     */
    public static function phoneNumber(string $phone): array
    {
        $phone = trim($phone);
        
        if (empty($phone)) {
            return ['valid' => false, 'value' => null, 'error' => 'Phone number is required'];
        }

        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[\s\-\(\)\.]/i', '', $phone);

        // Remove leading +91 or 91
        if (substr($phone, 0, 3) === '+91') {
            $phone = substr($phone, 3);
        } elseif (substr($phone, 0, 2) === '91') {
            $phone = substr($phone, 2);
        }

        // Must be exactly 10 digits
        if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
            return ['valid' => false, 'value' => null, 'error' => 'Invalid phone number format'];
        }

        // Add country code
        $phone = '+91' . $phone;

        return ['valid' => true, 'value' => $phone, 'error' => null];
    }

    /**
     * Validate date in YYYY-MM-DD format
     * 
     * @param string $date
     * @param bool $allowPast Allow past dates
     * @param bool $allowFuture Allow future dates
     * @return array ['valid' => bool, 'value' => string|null, 'error' => string|null]
     */
    public static function date(string $date, bool $allowPast = true, bool $allowFuture = true): array
    {
        $date = trim($date);
        
        if (empty($date)) {
            return ['valid' => false, 'value' => null, 'error' => 'Date is required'];
        }

        // Validate format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['valid' => false, 'value' => null, 'error' => 'Date must be in YYYY-MM-DD format'];
        }

        // Check if valid date
        $parts = explode('-', $date);
        if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            return ['valid' => false, 'value' => null, 'error' => 'Invalid date'];
        }

        $dateTime = new DateTime($date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        if (!$allowPast && $dateTime < $today) {
            return ['valid' => false, 'value' => null, 'error' => 'Date cannot be in the past'];
        }

        if (!$allowFuture && $dateTime > $today) {
            return ['valid' => false, 'value' => null, 'error' => 'Date cannot be in the future'];
        }

        return ['valid' => true, 'value' => $date, 'error' => null];
    }

    /**
     * Validate time in HH:MM format
     * 
     * @param string $time
     * @return array ['valid' => bool, 'value' => string|null, 'error' => string|null]
     */
    public static function time(string $time): array
    {
        $time = trim($time);
        
        if (empty($time)) {
            return ['valid' => false, 'value' => null, 'error' => 'Time is required'];
        }

        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return ['valid' => false, 'value' => null, 'error' => 'Time must be in HH:MM format (24-hour)'];
        }

        return ['valid' => true, 'value' => $time, 'error' => null];
    }

    /**
     * Validate and sanitize string input
     * Prevents HTML/JavaScript injection
     * 
     * @param string $input
     * @param int $maxLength
     * @param bool $allowNewlines
     * @return array ['valid' => bool, 'value' => string|null, 'error' => string|null]
     */
    public static function text(string $input, int $maxLength = 255, bool $allowNewlines = false): array
    {
        if (empty($input)) {
            return ['valid' => false, 'value' => null, 'error' => 'Input is required'];
        }

        if (!$allowNewlines) {
            $input = preg_replace('/[\r\n\t]+/', ' ', $input);
        }

        $input = trim($input);

        if (strlen($input) > $maxLength) {
            return ['valid' => false, 'value' => null, 'error' => "Input must be less than {$maxLength} characters"];
        }

        // Basic XSS prevention - while database stores safe content
        // This prevents some client-side attacks
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        return ['valid' => true, 'value' => $input, 'error' => null];
    }

    /**
     * Validate integer input
     * 
     * @param mixed $input
     * @param int|null $min
     * @param int|null $max
     * @return array ['valid' => bool, 'value' => int|null, 'error' => string|null]
     */
    public static function integer($input, ?int $min = null, ?int $max = null): array
    {
        if (!is_numeric($input) || (int)$input != $input) {
            return ['valid' => false, 'value' => null, 'error' => 'Invalid integer'];
        }

        $value = (int)$input;

        if ($min !== null && $value < $min) {
            return ['valid' => false, 'value' => null, 'error' => "Value must be at least {$min}"];
        }

        if ($max !== null && $value > $max) {
            return ['valid' => false, 'value' => null, 'error' => "Value must be at most {$max}"];
        }

        return ['valid' => true, 'value' => $value, 'error' => null];
    }

    /**
     * Validate from enum values
     * 
     * @param string $input
     * @param array $allowedValues
     * @return array ['valid' => bool, 'value' => string|null, 'error' => string|null]
     */
    public static function enum(string $input, array $allowedValues): array
    {
        $input = trim($input);

        if (empty($input)) {
            return ['valid' => false, 'value' => null, 'error' => 'Selection is required'];
        }

        if (!in_array($input, $allowedValues, true)) {
            return ['valid' => false, 'value' => null, 'error' => 'Invalid selection'];
        }

        return ['valid' => true, 'value' => $input, 'error' => null];
    }

    /**
     * Validate name (letters, spaces, hyphens)
     * 
     * @param string $name
     * @return array ['valid' => bool, 'value' => string|null, 'error' => string|null]
     */
    public static function name(string $name): array
    {
        $name = trim($name);
        
        if (empty($name)) {
            return ['valid' => false, 'value' => null, 'error' => 'Name is required'];
        }

        if (strlen($name) > 100) {
            return ['valid' => false, 'value' => null, 'error' => 'Name is too long'];
        }

        if (!preg_match("/^[a-zA-Z\s\-'\.]+$/", $name)) {
            return ['valid' => false, 'value' => null, 'error' => 'Name contains invalid characters'];
        }

        return ['valid' => true, 'value' => $name, 'error' => null];
    }

    /**
     * Validate and sanitize JSON input
     * 
     * @param string $json
     * @return array ['valid' => bool, 'value' => array|null, 'error' => string|null]
     */
    public static function json(string $json): array
    {
        $json = trim($json);
        
        if (empty($json)) {
            return ['valid' => false, 'value' => null, 'error' => 'JSON is required'];
        }

        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return ['valid' => false, 'value' => null, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
        }

        return ['valid' => true, 'value' => $decoded, 'error' => null];
    }

    /**
     * Batch validation of multiple fields
     * 
     * @param array $data
     * @param array $rules ['fieldName' => ['type' => 'email', 'required' => true, ...]]
     * @return array ['valid' => bool, 'errors' => array, 'data' => array]
     */
    public static function batch(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? '';
            $type = $rule['type'] ?? 'text';
            $required = $rule['required'] ?? true;

            if (empty($value) && $required) {
                $errors[$field] = ucfirst($field) . ' is required';
                continue;
            }

            if (empty($value)) {
                $validated[$field] = null;
                continue;
            }

            $result = match($type) {
                'email' => self::email($value),
                'phone' => self::phoneNumber($value),
                'date' => self::date($value, $rule['allowPast'] ?? true, $rule['allowFuture'] ?? true),
                'time' => self::time($value),
                'integer' => self::integer($value, $rule['min'] ?? null, $rule['max'] ?? null),
                'enum' => self::enum($value, $rule['values'] ?? []),
                'text' => self::text($value, $rule['maxLength'] ?? 255, $rule['newlines'] ?? false),
                'name' => self::name($value),
                'json' => self::json($value),
                default => ['valid' => false, 'value' => null, 'error' => 'Unknown validation type'],
            };

            if (!$result['valid']) {
                $errors[$field] = $result['error'];
            } else {
                $validated[$field] = $result['value'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        ];
    }
}
