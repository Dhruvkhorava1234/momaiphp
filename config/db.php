<?php
/**
 * Database Configuration & PDO Connection
 * MOMAI PLYWOOD - Core PHP
 */

// Hostinger / Localhost Database Credentials
// Update these when uploading to Hostinger cPanel / hPanel
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'momai'));
define('DB_USER', getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'root'));
define('DB_PASS', getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''));
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO instance
 *
 * @return PDO
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly error handling without revealing credentials
            http_response_code(500);
            die("Database Connection Error: Could not connect to the database. Please check your credentials in config/db.php. (Details: " . htmlspecialchars($e->getMessage()) . ")");
        }
    }

    return $pdo;
}
