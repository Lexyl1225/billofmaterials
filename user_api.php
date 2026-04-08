<?php
/**
 * User API - Server-side user management API
 * Handles user CRUD operations and sync between database and JSON
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db-config.php';
require_once 'user_manager.php';
require_once 'activity_logger.php';

// Check if user is logged in and is admin for certain operations
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isLoggedIn() {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

// Get current user
function getCurrentUser() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Get action from request
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

try {
    switch ($action) {
        case 'status':
            // Get database connection status
            echo json_encode([
                'success' => true,
                'database_available' => isDatabaseAvailable(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'sync':
            // Sync users between database and JSON
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $result = syncUsers();
            logActivity('user_sync', 'Users synchronized: ' . $result['message'], 'user_api.php');
            echo json_encode($result);
            break;
            
        case 'sync_to_db':
            // Sync JSON users to database
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $result = syncUsersJsonToDatabase();
            logActivity('user_sync_to_db', 'Users synced to database: ' . $result['message'], 'user_api.php');
            echo json_encode($result);
            break;
            
        case 'sync_to_json':
            // Sync database users to JSON
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $result = syncUsersDatabaseToJson();
            logActivity('user_sync_to_json', 'Users synced to JSON: ' . $result['message'], 'user_api.php');
            echo json_encode($result);
            break;
            
        case 'list':
            // List all users (admin only)
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $users = getAllUsers();
            echo json_encode([
                'success' => true,
                'users' => $users,
                'count' => count($users),
                'source' => isDatabaseAvailable() ? 'database' : 'json'
            ]);
            break;
            
        case 'get':
            // Get single user by username
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Login required']);
                break;
            }
            
            $username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
            
            // Non-admins can only get their own info
            if (!isAdmin() && $username !== getCurrentUser()) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                break;
            }
            
            $user = find_user($username);
            if ($user) {
                // Remove password from response
                unset($user['password']);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            break;
            
        case 'create':
            // Create new user (admin only for non-guest roles)
            $data = json_decode(file_get_contents('php://input'), true);
            
            $username = isset($data['username']) ? trim($data['username']) : '';
            $password = isset($data['password']) ? $data['password'] : '';
            $role = isset($data['role']) ? $data['role'] : 'guest';
            $department = isset($data['department']) ? $data['department'] : '';
            
            if (empty($username) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Username and password are required']);
                break;
            }
            
            // Only admins can create admin/user accounts
            if (($role === 'admin' || $role === 'user') && !isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Admin access required to create this role']);
                break;
            }
            
            $result = createUser($username, $password, $role, $department);
            
            if ($result['success']) {
                logActivity('user_create', 'User created via API: ' . $username . ' (Role: ' . $role . ')', 'user_api.php');
            }
            
            echo json_encode($result);
            break;
            
        case 'update':
            // Update user (admin only, or user updating their own password)
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Login required']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $username = isset($data['username']) ? trim($data['username']) : '';
            
            // Non-admins can only update their own account
            if (!isAdmin() && $username !== getCurrentUser()) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                break;
            }
            
            $user = find_user($username);
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                break;
            }
            
            // Update allowed fields
            if (isset($data['password']) && !empty($data['password'])) {
                $user['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if (isAdmin()) {
                if (isset($data['role'])) {
                    $user['role'] = $data['role'];
                }
                if (isset($data['department'])) {
                    $user['department'] = $data['department'];
                }
            }
            
            // Save to database
            $dbSuccess = saveUserToDatabase($user);
            
            // Update JSON backup
            $users = loadUsersFromJson();
            $updated = false;
            foreach ($users as &$u) {
                if ($u['username'] === $username) {
                    $u = array_merge($u, [
                        'password' => $user['password'],
                        'role' => $user['role'],
                        'department' => isset($user['department']) ? $user['department'] : ''
                    ]);
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                saveUsersToJson($users);
            }
            
            logActivity('user_update', 'User updated via API: ' . $username, 'user_api.php');
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            break;
            
        case 'delete':
            // Delete user (admin only)
            if (!isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
            
            if (empty($username)) {
                echo json_encode(['success' => false, 'message' => 'Username is required']);
                break;
            }
            
            // Prevent deleting yourself
            if ($username === getCurrentUser()) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                break;
            }
            
            $result = deleteUser($username);
            
            if ($result['success']) {
                logActivity('user_delete', 'User deleted via API: ' . $username, 'user_api.php');
            }
            
            echo json_encode($result);
            break;
            
        case 'verify':
            // Verify user credentials (for registration auth)
            $data = json_decode(file_get_contents('php://input'), true);
            $username = isset($data['username']) ? trim($data['username']) : '';
            $password = isset($data['password']) ? $data['password'] : '';
            $requiredRole = isset($data['required_role']) ? $data['required_role'] : null;
            
            $user = find_user($username);
            
            if ($user && password_verify($password, $user['password'])) {
                if ($requiredRole && $user['role'] !== $requiredRole && $user['role'] !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Insufficient privileges']);
                } else {
                    echo json_encode(['success' => true, 'message' => 'Credentials verified', 'role' => $user['role']]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("User API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
