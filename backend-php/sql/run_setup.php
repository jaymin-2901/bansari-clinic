<?php
/**
 * =================================================================
 * Database Setup/Migration Script
 * =================================================================
 * Run this script to:
 * 1. Create the mediconnect database (if not exists)
 * 2. Create all required tables
 * 3. Seed sample data
 * 
 * Usage: php run_setup.php
 * Or: Visit http://localhost/mediconnect/backend/sql/run_setup.php in browser
 */

set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Suppress output buffering
ob_implicit_flush(true);

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'mediconnect';

try {
    // Step 1: Connect to MySQL without database
    echo "✓ Connecting to MySQL...\n";
    $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Step 2: Create database
    echo "✓ Creating database (if not exists)...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Step 3: Select database
    echo "✓ Selecting database...\n";
    $pdo->exec("USE `$dbName`");
    
    // Step 4: Read and execute setup schema
    echo "✓ Running database schema...\n";
    $schemaFile = __DIR__ . '/setup_full.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }
    
    $sql = file_get_contents($schemaFile);
    // Split by CREATE or INSERT statements to handle multiple statements
    $statements = preg_split('/;[\s\n]+/', $sql, -1, PREG_SPLIT_NO_EMPTY);
    
    $count = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $count++;
            } catch (PDOException $e) {
                // Skip duplicate key/table errors
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    echo "  ⚠ Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "✓ Executed $count SQL statements\n";
    
    // Step 5: Verify tables exist
    echo "✓ Verifying tables...\n";
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\n✅ DATABASE SETUP COMPLETE!\n";
    echo "\nTables created: " . count($tables) . "\n";
    echo implode(", ", $tables) . "\n";
    
    echo "\n📝 Admin Login Credentials:\n";
    echo "   Email: admin@mediconnect.in\n";
    echo "   Password: password\n";
    
    echo "\n✓ Database is ready! You can now:\n";
    echo "   1. Start MySQL (if not running)\n";
    echo "   2. Access clinic admin at: http://localhost/clinic-admin/login.php\n";
    echo "   3. Check logs at: backend/logs/\n";
    
} catch (PDOException $e) {
    echo "\n❌ DATABASE ERROR:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "⚠ MySQL might not be running. Please:\n";
    echo "   1. Start MySQL (XAMPP/WAMP/MySQL Server)\n";
    echo "   2. Verify connection details in this script\n";
    echo "   3. Then run this script again\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
?>
