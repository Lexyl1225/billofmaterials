<?php
// Database configuration for MySQL
define('DB_NAME', 'your_db_name');
define('DB_USER', 'bom_user');
define('DB_PASSWORD', 'your_password'); // Update for production server
define('DB_HOST', 'localhost');

// Security salt for password hashing
define('SALT', 'aB3$xY9!mK7@pL2#qR8%vN4&wT6*zF1+jH5-gC0=dS9@kM3#pL7$wQ2!vB6&nH4');

// Create database connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($db->connect_error) {
    error_log('Database connection failed: ' . $db->connect_error);
    // Check if this is an API request (Content-Type already set to JSON)
    $headers = headers_list();
    $isJson = false;
    foreach ($headers as $header) {
        if (stripos($header, 'application/json') !== false) {
            $isJson = true;
            break;
        }
    }
    if ($isJson) {
        echo json_encode(['success' => false, 'message' => 'Database connection error']);
        exit();
    } else {
        die('Database connection error. Please contact administrator.');
    }
}

// Set charset to UTF-8
$db->set_charset('utf8mb4');

// Helper function to escape strings
function db_escape($str) {
    global $db;
    return $db->real_escape_string($str);
}

// Helper function for prepared statements
function db_query($query, $params = [], $types = '') {
    global $db;
    $stmt = $db->prepare($query);
    if (!$stmt) {
        error_log('SQL Error: ' . $db->error);
        return false;
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}
?>
