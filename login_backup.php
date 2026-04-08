<?php
// Login Form with User Registration
session_start();

// Include activity logger
require_once 'activity_logger.php';

// File to store user accounts
$USERS_FILE = __DIR__ . '/users.json';

// Initialize users.json with default admin account if it doesn't exist
if (!file_exists($USERS_FILE)) {
    $default_users = array(
        array(
            'username' => 'Admin',
            'password' => password_hash('T0ms1234', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s')
        ),
        array(
            'username' => 'Testuser',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'role' => 'user',
            'created_at' => date('Y-m-d H:i:s')
        ),
        array(
            'username' => 'Testguest',
            'password' => password_hash('guest123', PASSWORD_DEFAULT),
            'role' => 'guest',
            'created_at' => date('Y-m-d H:i:s')
        )
    );
    file_put_contents($USERS_FILE, json_encode($default_users, JSON_PRETTY_PRINT));
}

// Check if user is already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: save_bomboq.php');
    exit();
}

$error_message = '';
$success_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'login';

// Load users from file
function load_users() {
    global $USERS_FILE;
    if (file_exists($USERS_FILE)) {
        return json_decode(file_get_contents($USERS_FILE), true);
    }
    return array();
}

// Save users to file
function save_users($users) {
    global $USERS_FILE;
    file_put_contents($USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// Find user by username
function find_user($username) {
    $users = load_users();
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return $user;
        }
    }
    return null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_type'])) {
        if ($_POST['form_type'] === 'login') {
            // Login Form
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            $user = find_user($username);
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $username;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_department'] = isset($user['department']) ? $user['department'] : '';
                $_SESSION['login_time'] = date('Y-m-d H:i:s');

                // Log login activity
                logActivity('login', 'User logged in successfully', 'login.php');

                // Redirect to main page
                header('Location: save_bomboq.php');
                exit();
            } else {
                $error_message = 'Invalid username or password. Please try again.';
            }
        } elseif ($_POST['form_type'] === 'register') {
            // Register Form
            $new_username = isset($_POST['new_username']) ? trim($_POST['new_username']) : '';
            $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
            $role = isset($_POST['role']) ? $_POST['role'] : 'guest';
            $department = isset($_POST['department']) ? $_POST['department'] : '';
            $admin_auth_username = isset($_POST['admin_auth_username']) ? trim($_POST['admin_auth_username']) : '';
            $admin_auth_password = isset($_POST['admin_auth_password']) ? $_POST['admin_auth_password'] : '';

            // Validation
            if (empty($new_username) || empty($new_password)) {
                $error_message = 'Username and password are required.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'Passwords do not match.';
            } elseif (strlen($new_password) < 6) {
                $error_message = 'Password must be at least 6 characters long.';
            } elseif (find_user($new_username)) {
                $error_message = 'Username already exists. Please choose another.';
            } elseif ($role === 'admin') {
                // Verify admin credentials for creating admin account
                if (empty($admin_auth_username) || empty($admin_auth_password)) {
                    $error_message = 'Admin credentials are required to create an Admin account.';
                } else {
                    $auth_admin = find_user($admin_auth_username);
                    if (!$auth_admin || $auth_admin['role'] !== 'admin' || !password_verify($admin_auth_password, $auth_admin['password'])) {
                        $error_message = 'Invalid admin credentials. Only existing admins can create admin accounts.';
                    } else {
                        // Admin verified, create admin account
                        $users = load_users();
                        $users[] = array(
                            'username' => $new_username,
                            'password' => password_hash($new_password, PASSWORD_DEFAULT),
                            'role' => $role,
                            'created_at' => date('Y-m-d H:i:s')
                        );
                        save_users($users);
                        logActivity('create_account', 'Admin account created: ' . $new_username, 'login.php');
                        $success_message = 'Admin account created successfully! You can now login.';
                        $action = 'login';
                    }
                }
            } else {
                // Create new user (non-admin)
                $users = load_users();
                $users[] = array(
                    'username' => $new_username,
                    'password' => password_hash($new_password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'department' => $department,
                    'created_at' => date('Y-m-d H:i:s')
                );
                save_users($users);
                logActivity('create_account', 'User account created: ' . $new_username . ' (Role: ' . $role . ')', 'login.php');
                $success_message = 'Account created successfully! You can now login.';
                $action = 'login';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'register' ? 'Register' : 'Login'; ?> - BOM/BOQ System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus {
            border-color: #667eea;
            outline: none;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .error {
            background: #ffe6e6;
            color: #cc0000;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success {
            background: #e6ffe6;
            color: #006600;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .switch-form {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .switch-form a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        .switch-form a:hover {
            text-decoration: underline;
        }
        .admin-auth {
            display: none;
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .admin-auth.show {
            display: block;
        }
        .admin-auth h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .department-field {
            display: none;
        }
        .department-field.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($action === 'register'): ?>
            <h1>Create Account</h1>
            <?php if ($error_message): ?>
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <form method="POST" action="login.php?action=register">
                <input type="hidden" name="form_type" value="register">
                <div class="form-group">
                    <label for="new_username">Username</label>
                    <input type="text" id="new_username" name="new_username" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" onchange="toggleAdminAuth(); toggleDepartment();">
                        <option value="guest">Guest (View Only)</option>
                        <option value="user">User (Can Edit)</option>
                        <option value="admin">Admin (Full Access)</option>
                        <option value="purchasing">Purchasing Department</option>
                    </select>
                </div>
                
                <div class="form-group department-field" id="departmentField">
                    <label for="department">Department</label>
                    <select id="department" name="department">
                        <option value="">-- Select Department --</option>
                        <option value="Purchasing">Purchasing</option>
                        <option value="Design and Construction Department">Design and Construction Department</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>

                </div>
                <div class="admin-auth" id="adminAuth">
                    <h4>Admin Verification Required</h4>
                    <div class="form-group">
                        <label for="admin_auth_username">Admin Username</label>
                        <input type="text" id="admin_auth_username" name="admin_auth_username">
                    </div>
                    <div class="form-group">
                        <label for="admin_auth_password">Admin Password</label>
                        <input type="password" id="admin_auth_password" name="admin_auth_password">
                    </div>
                </div>
                <button type="submit">Create Account</button>
            </form>
            <div class="switch-form">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        <?php else: ?>
            <h1>Login</h1>
            <?php if ($error_message): ?>
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <input type="hidden" name="form_type" value="login">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
            <div class="switch-form">
                Don't have an account? <a href="login.php?action=register">Register here</a>
            </div>
        <?php endif; ?>
    </div>
    <script>
        function toggleAdminAuth() {
            var role = document.getElementById('role').value;
            var adminAuth = document.getElementById('adminAuth');
            if (role === 'admin') {
                adminAuth.classList.add('show');
            } else {
                adminAuth.classList.remove('show');
            }
        }
        function toggleDepartment() {
            var role = document.getElementById('role').value;
            var deptField = document.getElementById('departmentField');
            if (role === 'user') {
                deptField.classList.add('show');
            } else {
                deptField.classList.remove('show');
                document.getElementById('department').value = ''; // Reset selection
            }
        }

    </script>
</body>
</html>