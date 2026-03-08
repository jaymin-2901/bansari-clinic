<?php
/**
 * ============================================================
 * MediConnect – Database Connection (PDO + MySQLi)
 * File: backend/config/database.php
 * ============================================================
 * 
 * Returns a PDO connection with:
 *  - Prepared statements by default
 *  - UTF-8 charset
 *  - Exception error mode
 *  - Persistent connections disabled (cleaner for cron)
 */

// Load production config if available (for InfinityFree deployment)
$prodConfig = __DIR__ . '/production_config.php';
if (file_exists($prodConfig)) {
    require_once $prodConfig;
} else {
    require_once __DIR__ . '/config.php';
}

/**
 * Get a PDO database connection
 * 
 * @return PDO
 * @throws PDOException on connection failure
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // Try MySQL first
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        $logFile = LOG_DIR . '/db_errors.log';
        $logMsg  = date('[Y-m-d H:i:s]') . ' DB Connection Failed: ' . $e->getMessage() . PHP_EOL;
        @file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);

        if (APP_ENV === 'development') {
            throw $e;
        }
        die('Database connection error. Please try again later.');
    }
}

/**
 * Helper: Write to a log file
 * 
 * @param string $filename  Log filename (relative to LOG_DIR)
 * @param string $message   Message to log
 */
function writeLog(string $filename, string $message): void
{
    $logFile = LOG_DIR . '/' . $filename;
    $logMsg  = date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL;
    @file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
}

