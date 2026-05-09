<?php
/**
 * Environment Variable Loader
 * Loads environment variables and provides fallback values
 * 
 * For local development: uses clinic_config.php (localhost database)
 * For production (InfinityFree): uses production_config.php
 * 
 * Set environment variable USE_PRODUCTION=true to force production config
 */

// Check if we should use production config
$useProduction = getenv('USE_PRODUCTION') === 'true';

if ($useProduction && file_exists(__DIR__ . '/production_config.php')) {
    require_once __DIR__ . '/production_config.php';
} elseif (file_exists(__DIR__ . '/clinic_config.php')) {
    // Load clinic_config for local development (localhost database)
    require_once __DIR__ . '/clinic_config.php';
} else {
    // Fallback to main config
    if (file_exists(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    }
}

// Function to get environment variable with fallback support
function getEnvVar(string $key, $default = false) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    return $value;
}
