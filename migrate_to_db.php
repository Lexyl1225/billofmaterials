<?php
/**
 * One-time Migration Script
 * Migrates data from JSON files to MySQL database
 * Run this ONCE via browser: http://localhost:8000/migrate_to_db.php
 */

require_once 'db-config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
        code { background: #eee; padding: 2px 5px; border-radius: 3px; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Database Migration Script</h1>
        <p>This script will migrate your data from JSON files to MySQL database.</p>
        
        <?php
        echo "<h2>📋 Migration Process</h2>";
        
        // Step 1: Migrate Users
        echo "<h3>1. Migrating Users from users.json</h3>";
        if (file_exists('users.json')) {
            $users_json = json_decode(file_get_contents('users.json'), true);
            $migrated = 0;
            $skipped = 0;
            
            foreach ($users_json as $user) {
                $username = $user['username'];
                $password = $user['password'];
                $role = $user['role'];
                $department = isset($user['department']) ? $user['department'] : null;
                
                // Check if user already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    // Insert new user
                    $stmt = $db->prepare("INSERT INTO users (username, password, role, department) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('ssss', $username, $password, $role, $department);
                    if ($stmt->execute()) {
                        echo "<p class='success'>✓ Migrated user: <strong>$username</strong> (Role: $role)</p>";
                        $migrated++;
                    } else {
                        echo "<p class='error'>✗ Failed to migrate user: <strong>$username</strong></p>";
                    }
                } else {
                    echo "<p class='info'>→ User already exists: <strong>$username</strong></p>";
                    $skipped++;
                }
                $stmt->close();
            }
            echo "<p><strong>Users Summary:</strong> $migrated migrated, $skipped skipped</p>";
        } else {
            echo "<p class='warning'>⚠ users.json not found - creating default admin account</p>";
            
            // Create default admin
            $admin_user = 'Admin';
            $admin_pass = password_hash('T0ms1234', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin') ON DUPLICATE KEY UPDATE username=username");
            $stmt->bind_param('ss', $admin_user, $admin_pass);
            $stmt->execute();
            $stmt->close();
            echo "<p class='success'>✓ Created default admin account (Username: Admin, Password: T0ms1234)</p>";
        }
        
        // Step 2: Migrate Activity Logs
        echo "<h3>2. Migrating Activity Logs from user_activity_logs.json</h3>";
        if (file_exists('user_activity_logs.json')) {
            $logs_json = json_decode(file_get_contents('user_activity_logs.json'), true);
            $count = 0;
            $errors = 0;
            
            foreach ($logs_json as $log) {
                $log_id = $log['id'];
                $timestamp = $log['timestamp'];
                $username = $log['username'];
                $role = $log['role'];
                $department = isset($log['department']) ? $log['department'] : null;
                $action = $log['action'];
                $details = isset($log['details']) ? $log['details'] : null;
                $page = isset($log['page']) ? $log['page'] : null;
                $ip = isset($log['ip_address']) ? $log['ip_address'] : null;
                $agent = isset($log['user_agent']) ? $log['user_agent'] : null;
                
                // Get user ID
                $user_id = null;
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $user_id = $row['id'];
                }
                $stmt->close();
                
                // Insert log
                $stmt = $db->prepare("INSERT INTO activity_logs (log_id, timestamp, username, user_id, role, department, action, details, page, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE log_id=log_id");
                $stmt->bind_param('sssssssssss', $log_id, $timestamp, $username, $user_id, $role, $department, $action, $details, $page, $ip, $agent);
                if ($stmt->execute()) {
                    $count++;
                } else {
                    $errors++;
                }
                $stmt->close();
            }
            echo "<p class='success'>✓ Migrated <strong>$count</strong> activity logs</p>";
            if ($errors > 0) {
                echo "<p class='error'>✗ $errors logs failed to migrate</p>";
            }
        } else {
            echo "<p class='warning'>⚠ user_activity_logs.json not found - no activity logs to migrate</p>";
        }
        
        // Step 3: Information about documents
        echo "<h3>3. Documents Migration</h3>";
        echo "<p class='warning'>⚠ <strong>Important:</strong> Documents are currently stored in browser localStorage.</p>";
        echo "<p>These cannot be automatically migrated. Options:</p>";
        echo "<ul>";
        echo "<li><strong>Option A:</strong> Users re-create their documents after migration</li>";
        echo "<li><strong>Option B:</strong> Export documents manually from browser console and import to database</li>";
        echo "<li><strong>Option C:</strong> Keep using localStorage alongside database (hybrid approach)</li>";
        echo "</ul>";
        
        // Final summary
        echo "<h2>✅ Migration Complete!</h2>";
        echo "<p>Your system is now configured to use MySQL database for users and activity logs.</p>";
        
        echo "<h3>Next Steps:</h3>";
        echo "<ol>";
        echo "<li>Test the login system: <a href='login.php' class='btn'>Go to Login</a></li>";
        echo "<li>Update your nginx configuration for production deployment</li>";
        echo "<li>Update database credentials in <code>db-config.php</code> for production</li>";
        echo "<li>Change the SALT value in <code>db-config.php</code></li>";
        echo "<li><strong>Delete or rename this migration file for security</strong></li>";
        echo "</ol>";
        
        echo "<p class='warning'>⚠ <strong>Security Warning:</strong> Delete this file after migration is complete!</p>";
        ?>
        
        <p><a href="login.php" class="btn">Go to Login Page</a></p>
    </div>
</body>
</html>
