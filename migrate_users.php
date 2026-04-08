<?php
/**
 * Migrate Users from JSON to Database
 * Run this script once to migrate existing users from users.json to the database
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>User Migration</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;max-width:800px;margin:0 auto;}";
echo ".success{color:green;}.error{color:red;}.info{color:blue;}";
echo "pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow-x:auto;}";
echo "h1{border-bottom:2px solid #333;padding-bottom:10px;}</style></head><body>";

echo "<h1>User Migration: JSON → Database</h1>";

require_once 'db-config.php';
require_once 'user_manager.php';

// Check database connection
echo "<h2>Step 1: Check Database Connection</h2>";
if (isDatabaseAvailable()) {
    echo "<p class='success'>✓ Database connection successful!</p>";
} else {
    echo "<p class='error'>✗ Database connection failed. Please check db-config.php</p>";
    echo "</body></html>";
    exit();
}

// Check if users table exists
echo "<h2>Step 2: Check Users Table</h2>";
global $db_user_manager;
$result = $db_user_manager->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "<p class='success'>✓ Users table exists!</p>";
} else {
    echo "<p class='info'>Creating users table...</p>";
    $createTable = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user', 'guest') NOT NULL DEFAULT 'guest',
        department VARCHAR(100) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL DEFAULT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX idx_username (username),
        INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($db_user_manager->query($createTable)) {
        echo "<p class='success'>✓ Users table created successfully!</p>";
    } else {
        echo "<p class='error'>✗ Failed to create users table: " . $db_user_manager->error . "</p>";
    }
}

// Load users from JSON
echo "<h2>Step 3: Load Users from JSON</h2>";
$jsonUsers = loadUsersFromJson();
echo "<p class='info'>Found " . count($jsonUsers) . " users in users.json</p>";

if (count($jsonUsers) === 0) {
    echo "<p class='error'>No users found in JSON file!</p>";
    echo "</body></html>";
    exit();
}

// Display users to migrate
echo "<h2>Step 4: Users to Migrate</h2>";
echo "<pre>";
foreach ($jsonUsers as $index => $user) {
    echo ($index + 1) . ". " . $user['username'] . " (Role: " . $user['role'] . ", Department: " . (isset($user['department']) ? $user['department'] : 'N/A') . ")\n";
}
echo "</pre>";

// Check existing users in database
echo "<h2>Step 5: Check Existing Database Users</h2>";
$dbUsers = loadUsersFromDatabase();
echo "<p class='info'>Found " . count($dbUsers) . " users in database</p>";

$existingUsernames = array_column($dbUsers, 'username');

// Migrate users
echo "<h2>Step 6: Migration Process</h2>";
$migrated = 0;
$skipped = 0;
$errors = 0;

echo "<pre>";
foreach ($jsonUsers as $user) {
    $username = $user['username'];
    
    if (in_array($username, $existingUsernames)) {
        echo "⏭ SKIP: $username (already exists in database)\n";
        $skipped++;
        continue;
    }
    
    $result = saveUserToDatabase($user);
    
    if ($result !== false) {
        echo "✓ MIGRATED: $username (ID: $result)\n";
        $migrated++;
    } else {
        echo "✗ ERROR: $username (failed to save)\n";
        $errors++;
    }
}
echo "</pre>";

// Summary
echo "<h2>Migration Summary</h2>";
echo "<ul>";
echo "<li class='success'>Migrated: $migrated users</li>";
echo "<li class='info'>Skipped (already exist): $skipped users</li>";
echo "<li class='error'>Errors: $errors users</li>";
echo "</ul>";

// Final sync - update JSON backup from database
echo "<h2>Step 7: Sync JSON Backup</h2>";
$syncResult = syncUsersDatabaseToJson();
if ($syncResult['success']) {
    echo "<p class='success'>✓ JSON backup synced: " . $syncResult['message'] . "</p>";
} else {
    echo "<p class='error'>✗ JSON sync failed: " . $syncResult['message'] . "</p>";
}

// Show final database count
echo "<h2>Final Result</h2>";
$finalDbUsers = loadUsersFromDatabase();
echo "<p class='success'>Total users in database: " . count($finalDbUsers) . "</p>";

echo "<br><br>";
echo "<a href='login.php' style='display:inline-block;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;'>Go to Login Page</a>";

echo "</body></html>";
?>
