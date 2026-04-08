<?php
/**
 * Database Connection Test
 * Run this to verify your database setup is working
 * Delete this file after testing!
 */

echo "<!DOCTYPE html>";
echo "<html><head><title>Database Test</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo ".success{color:green;} .error{color:red;} .info{color:blue;}</style>";
echo "</head><body><div class='box'>";

echo "<h1>🔍 Database Connection Test</h1>";

// Test 1: Check if db-config.php exists
echo "<h2>Test 1: Configuration File</h2>";
if (file_exists('db-config.php')) {
    echo "<p class='success'>✓ db-config.php found</p>";
    require_once 'db-config.php';
} else {
    echo "<p class='error'>✗ db-config.php not found!</p>";
    exit;
}

// Test 2: Database connection
echo "<h2>Test 2: Database Connection</h2>";
if (isset($db) && $db->ping()) {
    echo "<p class='success'>✓ Connected to MySQL successfully</p>";
    echo "<p class='info'>Server: " . $db->host_info . "</p>";
    echo "<p class='info'>Database: " . DB_NAME . "</p>";
} else {
    echo "<p class='error'>✗ Failed to connect to database</p>";
    echo "<p class='error'>Error: " . $db->connect_error . "</p>";
    exit;
}

// Test 3: Check tables
echo "<h2>Test 3: Database Tables</h2>";
$tables = ['users', 'documents', 'activity_logs'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<p class='success'>✓ Table '$table' exists</p>";
        
        // Count records
        $count_result = $db->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_result->fetch_assoc()['count'];
        echo "<p class='info'>  → Records: $count</p>";
    } else {
        echo "<p class='error'>✗ Table '$table' not found</p>";
    }
}

// Test 4: Check activity logger
echo "<h2>Test 4: Activity Logger</h2>";
if (file_exists('activity_logger_db.php')) {
    echo "<p class='success'>✓ activity_logger_db.php found</p>";
    require_once 'activity_logger_db.php';
    
    // Try to get logs
    try {
        $logs = getActivityLogs(5);
        echo "<p class='success'>✓ Activity logger working</p>";
        echo "<p class='info'>  → Found " . count($logs) . " recent logs</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ Activity logger error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>✗ activity_logger_db.php not found</p>";
}

// Test 5: Check file permissions (for production)
echo "<h2>Test 5: File Checks</h2>";
$files_to_check = [
    'users.json' => 'Should exist for migration',
    'user_activity_logs.json' => 'Should exist for migration',
    'migrate_to_db.php' => 'Migration script',
    'login.php' => 'Login page',
];

foreach ($files_to_check as $file => $desc) {
    if (file_exists($file)) {
        echo "<p class='success'>✓ $file - $desc</p>";
    } else {
        echo "<p class='info'>→ $file - Not found (may be normal)</p>";
    }
}

// Summary
echo "<h2>📊 Summary</h2>";
echo "<p><strong>Database Status:</strong> <span class='success'>✓ Ready</span></p>";
echo "<p><strong>Next Step:</strong> Run <a href='migrate_to_db.php'>migrate_to_db.php</a> to migrate data</p>";
echo "<p><strong>After Migration:</strong> Test <a href='login.php'>login.php</a></p>";
echo "<p class='error'><strong>Security:</strong> Delete this test file after verification!</p>";

echo "</div></body></html>";
?>
