<?php
/* =========================================
   RIPPLE – api/db.php
   Database connection using MySQLi
   =========================================
   Configure these values to match your
   XAMPP MySQL setup.
   ========================================= */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Default XAMPP MySQL user
define('DB_PASS', '');          // Default XAMPP has no password
define('DB_NAME', 'ripple');

/**
 * Create and return a MySQLi connection.
 * Exits with a JSON error if connection fails.
 *
 * @return mysqli
 */
function getConnection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // Return JSON error and stop execution
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]);
        exit;
    }

    // Use UTF-8 for full Unicode support
    $conn->set_charset('utf8mb4');
    return $conn;
}
?>