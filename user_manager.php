<?php
/**
 * User Manager - Database Integration with JSON Backup
 * Primary storage: MySQL database
 * Backup storage: users.json (for offline fallback)
 */

// Graceful db-config include - don't fail if database is unavailable
$USERS_FILE = __DIR__ . '/users.json';
$db_user_manager = null;

// Check if db is already available from db-config.php
if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli && !$GLOBALS['db']->connect_error) {
    $db_user_manager = $GLOBALS['db'];
} else {
    // Try to establish our own connection
    if (!defined('DB_HOST')) {
        define('DB_HOST', 'localhost');
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', 'bom_db');
    }
    if (!defined('DB_USER')) {
        define('DB_USER', 'bom_user');
    }
    if (!defined('DB_PASSWORD')) {
        define('DB_PASSWORD', '');
    }
    
    try {
        @$db_user_manager = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        if ($db_user_manager->connect_error) {
            error_log('User Manager: Database connection failed - ' . $db_user_manager->connect_error);
            $db_user_manager = null;
        } else {
            $db_user_manager->set_charset('utf8mb4');
        }
    } catch (Exception $e) {
        error_log('User Manager: Database exception - ' . $e->getMessage());
        $db_user_manager = null;
    }
}

/**
 * Check if database connection is available
 * @return bool True if connected
 */
function isDatabaseAvailable() {
    global $db_user_manager;
    if (!isset($db_user_manager) || !$db_user_manager || $db_user_manager->connect_error) {
        return false;
    }
    // Use a lightweight query instead of deprecated ping()
    try {
        $result = @$db_user_manager->query("SELECT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Load users from database
 * @return array Array of users
 */
function loadUsersFromDatabase() {
    global $db_user_manager;
    
    if (!isDatabaseAvailable()) {
        return [];
    }
    
    if (!($db_user_manager instanceof mysqli)) {
        return [];
    }
    
    $result = $db_user_manager->query("SELECT id, username, password, role, department, email, created_at, updated_at, last_login, is_active FROM users ORDER BY id ASC");
    
    if (!$result) {
        return [];
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'password' => $row['password'],
            'role' => $row['role'],
            'department' => $row['department'] ?? '',
            'email' => $row['email'] ?? '',
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'last_login' => $row['last_login'],
            'is_active' => $row['is_active']
        ];
    }
    
    return $users;
}

/**
 * Load users from JSON file (backup)
 * @return array Array of users
 */
function loadUsersFromJson() {
    global $USERS_FILE;
    
    if (file_exists($USERS_FILE)) {
        $content = file_get_contents($USERS_FILE);
        $users = json_decode($content, true);
        return is_array($users) ? $users : [];
    }
    
    return [];
}

/**
 * Load users - tries database first, falls back to JSON
 * @return array Array of users
 */
function load_users() {
    // Try database first
    if (isDatabaseAvailable()) {
        $users = loadUsersFromDatabase();
        if (!empty($users)) {
            return $users;
        }
    }
    
    // Fall back to JSON file
    return loadUsersFromJson();
}

/**
 * Save user to database
 * @param array $user User data
 * @return bool|int User ID on success, false on failure
 */
function saveUserToDatabase($user) {
    global $db_user_manager;
    
    if (!isDatabaseAvailable() || !($db_user_manager instanceof mysqli)) {
        return false;
    }
    
    // Check if user already exists
    $stmt = $db_user_manager->prepare("SELECT id FROM users WHERE username = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $user['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing user
        $row = $result->fetch_assoc();
        $stmt = $db_user_manager->prepare("UPDATE users SET password = ?, role = ?, department = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $department = isset($user['department']) ? $user['department'] : '';
        $stmt->bind_param('sssi', $user['password'], $user['role'], $department, $row['id']);
        return $stmt->execute() ? $row['id'] : false;
    } else {
        // Insert new user
        $stmt = $db_user_manager->prepare("INSERT INTO users (username, password, role, department, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            return false;
        }
        $department = isset($user['department']) ? $user['department'] : '';
        $createdAt = isset($user['created_at']) ? $user['created_at'] : date('Y-m-d H:i:s');
        $stmt->bind_param('sssss', $user['username'], $user['password'], $user['role'], $department, $createdAt);
        
        if ($stmt->execute()) {
            return $db_user_manager->insert_id;
        }
        return false;
    }
}

/**
 * Save users to JSON file (backup)
 * @param array $users Array of users
 * @return bool Success status
 */
function saveUsersToJson($users) {
    global $USERS_FILE;
    
    // Convert to JSON-safe format (remove db-specific fields)
    $jsonUsers = array_map(function($user) {
        return [
            'username' => $user['username'],
            'password' => $user['password'],
            'role' => $user['role'],
            'department' => isset($user['department']) ? $user['department'] : '',
            'created_at' => isset($user['created_at']) ? $user['created_at'] : date('Y-m-d H:i:s')
        ];
    }, $users);
    
    return file_put_contents($USERS_FILE, json_encode($jsonUsers, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Save users - saves to both database and JSON
 * @param array $users Array of users
 * @return bool Success status
 */
function save_users($users) {
    $dbSuccess = true;
    
    // Database is the primary store
    if (isDatabaseAvailable()) {
        foreach ($users as $user) {
            if (saveUserToDatabase($user) === false) {
                $dbSuccess = false;
            }
        }
        
        // After DB save, refresh JSON backup from database
        $dbUsers = loadUsersFromDatabase();
        if (!empty($dbUsers)) {
            $jsonSuccess = saveUsersToJson($dbUsers);
        } else {
            $jsonSuccess = saveUsersToJson($users);
        }
    } else {
        // DB unavailable — save to JSON as emergency fallback
        $jsonSuccess = saveUsersToJson($users);
    }
    
    return $dbSuccess && $jsonSuccess;
}

/**
 * Find user by username - checks database first, then JSON
 * @param string $username Username to find
 * @return array|null User data or null if not found
 */
function find_user($username) {
    // Try database first
    if (isDatabaseAvailable()) {
        $user = findUserInDatabase($username);
        if ($user) {
            return $user;
        }
    }
    
    // Fall back to JSON
    return findUserInJson($username);
}

/**
 * Find user in database
 * @param string $username Username to find
 * @return array|null User data or null if not found
 */
function findUserInDatabase($username) {
    global $db_user_manager;
    
    if (!isDatabaseAvailable() || !($db_user_manager instanceof mysqli)) {
        return null;
    }
    
    $stmt = $db_user_manager->prepare("SELECT id, username, password, role, department, email, created_at, last_login, is_active FROM users WHERE username = ? AND is_active = 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'id' => $row['id'],
            'username' => $row['username'],
            'password' => $row['password'],
            'role' => $row['role'],
            'department' => $row['department'] ?? '',
            'email' => $row['email'] ?? '',
            'created_at' => $row['created_at'],
            'last_login' => $row['last_login'],
            'is_active' => $row['is_active']
        ];
    }
    
    return null;
}

/**
 * Find user in JSON file
 * @param string $username Username to find
 * @return array|null User data or null if not found
 */
function findUserInJson($username) {
    $users = loadUsersFromJson();
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return $user;
        }
    }
    return null;
}

/**
 * Create new user - saves to database and JSON
 * @param string $username Username
 * @param string $password Plain text password (will be hashed)
 * @param string $role User role
 * @param string $department User department
 * @return array Result with success status and message
 */
function createUser($username, $password, $role, $department = '') {
    // Check if username already exists
    if (find_user($username)) {
        return ['success' => false, 'message' => 'Username already exists'];
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $createdAt = date('Y-m-d H:i:s');
    
    $newUser = [
        'username' => $username,
        'password' => $hashedPassword,
        'role' => $role,
        'department' => $department,
        'created_at' => $createdAt
    ];
    
    // Database is the primary store — must succeed
    if (!isDatabaseAvailable()) {
        error_log('createUser: Database not available for user ' . $username);
        return ['success' => false, 'message' => 'Database is unavailable. Please try again later.'];
    }
    
    $userId = saveUserToDatabase($newUser);
    if ($userId === false) {
        error_log('createUser: Database insert failed for user ' . $username);
        return ['success' => false, 'message' => 'Failed to save user to database. Please try again.'];
    }
    
    // DB write succeeded — update users.json as a backup copy
    $jsonSuccess = false;
    try {
        $allDbUsers = loadUsersFromDatabase();
        if (!empty($allDbUsers)) {
            $jsonSuccess = saveUsersToJson($allDbUsers);
        } else {
            // Fallback: append only the new user
            $jsonUsers = loadUsersFromJson();
            $jsonUsers[] = $newUser;
            $jsonSuccess = saveUsersToJson($jsonUsers);
        }
    } catch (Exception $e) {
        error_log('createUser: JSON backup failed - ' . $e->getMessage());
    }
    
    return [
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => $userId,
        'db_synced' => true,
        'json_synced' => $jsonSuccess
    ];
}

/**
 * Update user's last login time
 * @param string $username Username
 * @return bool Success status
 */
function updateLastLogin($username) {
    global $db_user_manager;
    
    if (!isDatabaseAvailable() || !($db_user_manager instanceof mysqli)) {
        return false;
    }
    
    $stmt = $db_user_manager->prepare("UPDATE users SET last_login = NOW() WHERE username = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $username);
    return $stmt->execute();
}

/**
 * Sync users from JSON to database
 * @return array Result with sync statistics
 */
function syncUsersJsonToDatabase() {
    if (!isDatabaseAvailable()) {
        return ['success' => false, 'message' => 'Database not available'];
    }
    
    $jsonUsers = loadUsersFromJson();
    $synced = 0;
    $errors = 0;
    
    foreach ($jsonUsers as $user) {
        $result = saveUserToDatabase($user);
        if ($result !== false) {
            $synced++;
        } else {
            $errors++;
        }
    }
    
    return [
        'success' => $errors === 0,
        'message' => "Synced $synced users, $errors errors",
        'synced' => $synced,
        'errors' => $errors
    ];
}

/**
 * Sync users from database to JSON
 * @return array Result with sync statistics
 */
function syncUsersDatabaseToJson() {
    if (!isDatabaseAvailable()) {
        return ['success' => false, 'message' => 'Database not available'];
    }
    
    $dbUsers = loadUsersFromDatabase();
    
    if (empty($dbUsers)) {
        return ['success' => false, 'message' => 'No users in database'];
    }
    
    $success = saveUsersToJson($dbUsers);
    
    return [
        'success' => $success,
        'message' => $success ? 'Synced ' . count($dbUsers) . ' users to JSON' : 'Failed to save JSON file',
        'count' => count($dbUsers)
    ];
}

/**
 * Full two-way sync between database and JSON
 * Database is the primary source, JSON is backup
 * @return array Result with sync statistics
 */
function syncUsers() {
    if (!isDatabaseAvailable()) {
        return ['success' => false, 'message' => 'Database not available', 'source' => 'json'];
    }
    
    $dbUsers = loadUsersFromDatabase();
    $jsonUsers = loadUsersFromJson();
    
    // Create username lookup maps
    $dbUsernames = array_column($dbUsers, 'username');
    $jsonUsernames = array_column($jsonUsers, 'username');
    
    $addedToDb = 0;
    $addedToJson = 0;
    
    // Add JSON-only users to database
    foreach ($jsonUsers as $jsonUser) {
        if (!in_array($jsonUser['username'], $dbUsernames)) {
            if (saveUserToDatabase($jsonUser) !== false) {
                $addedToDb++;
            }
        }
    }
    
    // Reload database users after adding new ones
    $dbUsers = loadUsersFromDatabase();
    
    // Save all database users to JSON (database is primary)
    $jsonSuccess = saveUsersToJson($dbUsers);
    
    return [
        'success' => true,
        'message' => "Sync complete. Added $addedToDb users to database. JSON backup updated.",
        'added_to_db' => $addedToDb,
        'total_users' => count($dbUsers),
        'json_updated' => $jsonSuccess
    ];
}

/**
 * Get all users (for admin panel)
 * @return array Array of users (without passwords)
 */
function getAllUsers() {
    $users = load_users();
    
    // Remove passwords for security
    return array_map(function($user) {
        return [
            'id' => isset($user['id']) ? $user['id'] : null,
            'username' => $user['username'],
            'role' => $user['role'],
            'department' => isset($user['department']) ? $user['department'] : '',
            'created_at' => isset($user['created_at']) ? $user['created_at'] : '',
            'last_login' => isset($user['last_login']) ? $user['last_login'] : null
        ];
    }, $users);
}

/**
 * Delete user by username
 * @param string $username Username to delete
 * @return array Result with success status
 */
function deleteUser($username) {
    global $db_user_manager;
    
    $dbSuccess = false;
    
    // Delete from database if available
    if (isDatabaseAvailable() && ($db_user_manager instanceof mysqli)) {
        $stmt = $db_user_manager->prepare("DELETE FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $dbSuccess = $stmt->execute();
        }
    }
    
    // Delete from JSON
    $users = loadUsersFromJson();
    $users = array_filter($users, function($user) use ($username) {
        return $user['username'] !== $username;
    });
    $users = array_values($users); // Re-index array
    $jsonSuccess = saveUsersToJson($users);
    
    return [
        'success' => $dbSuccess || $jsonSuccess,
        'message' => ($dbSuccess || $jsonSuccess) ? 'User deleted successfully' : 'Failed to delete user',
        'db_deleted' => $dbSuccess,
        'json_deleted' => $jsonSuccess
    ];
}

/**
 * Initialize default users if none exist
 */
function initializeDefaultUsers() {
    $users = load_users();
    
    if (empty($users)) {
        $defaultUsers = [
            [
                'username' => 'Admin',
                'password' => password_hash('T0ms1234', PASSWORD_DEFAULT),
                'role' => 'admin',
                'department' => 'Admin',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'Testuser',
                'password' => password_hash('test123', PASSWORD_DEFAULT),
                'role' => 'user',
                'department' => '',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'Testguest',
                'password' => password_hash('guest123', PASSWORD_DEFAULT),
                'role' => 'guest',
                'department' => '',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        foreach ($defaultUsers as $user) {
            saveUserToDatabase($user);
        }
        
        saveUsersToJson($defaultUsers);
    }
}
?>
