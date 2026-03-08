<?php
/**
 * Environment Variable Loader
 * Loads environment variables and provides fallback values
 */

// Load production config for production deployments (database, CORS, etc.)
if (file_exists(__DIR__ . '/production_config.php')) {
    require_once __DIR__ . '/production_config.php';
} elseif (file_exists(__DIR__ . '/clinic_config.php')) {
    // Fallback to clinic_config for development
    require_once __DIR__ . '/clinic_config.php';
}

// Load main config for database settings
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Function to get environment variable with fallback support
function getEnvVar(string $key, $default = false) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    return $value;
}

