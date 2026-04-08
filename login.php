<?php
// Login Form with User Registration
session_start();

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

// Include database config (must be before user_manager so DB connection is available)
require_once __DIR__ . '/db-config.php';

// Include user manager (handles database + JSON backup)
require_once __DIR__ . '/user_manager.php';

// Initialize default users if none exist
initializeDefaultUsers();

// Try to sync users on page load (database is primary, JSON is backup)
if (isDatabaseAvailable()) {
    syncUsers();
}

// Check if user is already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: save_bomboq.php');
    exit();
}

$error_message = '';
$success_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'login';

// Note: load_users(), save_users(), and find_user() functions are now provided by user_manager.php

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

                // Update last login time in database
                updateLastLogin($username);

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
                        // Admin verified, create admin account via database
                        $result = createUser($new_username, $new_password, $role, '');
                        if ($result['success']) {
                            logActivity('create_account', 'Admin account created: ' . $new_username, 'login.php');
                            $success_message = 'Admin account created successfully! You can now login.';
                            $action = 'login';
                        } else {
                            $error_message = $result['message'];
                        }
                    }
                }
            } elseif ($role === 'user') {
                // Verify user credentials for creating user account
                if (empty($admin_auth_username) || empty($admin_auth_password)) {
                    $error_message = 'Existing User credentials are required to create a User account.';
                } else {
                    $auth_user = find_user($admin_auth_username);
                    if (!$auth_user || ($auth_user['role'] !== 'user' && $auth_user['role'] !== 'admin') || !password_verify($admin_auth_password, $auth_user['password'])) {
                        $error_message = 'Invalid credentials. Only existing Users or Admins can create User accounts.';
                    } else {
                        // User verified, create user account via database
                        $result = createUser($new_username, $new_password, $role, $department);
                        if ($result['success']) {
                            logActivity('create_account', 'User account created: ' . $new_username . ' (Role: ' . $role . ', Department: ' . $department . ') by ' . $admin_auth_username, 'login.php');
                            $success_message = 'User account created successfully! You can now login.';
                            $action = 'login';
                        } else {
                            $error_message = $result['message'];
                        }
                    }
                }
            } else {
                // Create new user (guest/purchasing - no auth required) via database
                $result = createUser($new_username, $new_password, $role, $department);
                if ($result['success']) {
                    logActivity('create_account', 'User account created: ' . $new_username . ' (Role: ' . $role . ')', 'login.php');
                    $success_message = 'Account created successfully! You can now login.';
                    $action = 'login';
                } else {
                    $error_message = $result['message'];
                }
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
    <script>try{var t=localStorage.getItem('bom_theme');if(t&&t!=='default')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="stylesheet" href="themes.css">
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
                    </select>
                </div>
                
                <div class="form-group department-field" id="departmentField">
                    <label for="department">Department</label>
                    <select id="department" name="department">
                        <option value="">-- Select Department --</option>
                        <option value="Purchasing">Purchasing</option>
                        <option value="Design and Construction Department">Design and Construction Department</option>
                        <option value="Operations">Operations</option>
                        <option value="Electrical">Electrical</option>
                        <option value="Technical">Technical</option>
                    </select>
                </div>
                
                <div class="admin-auth" id="adminAuth">
                    <h4 id="authTitle">Admin Verification Required</h4>
                    <div class="form-group">
                        <label for="admin_auth_username" id="authUsernameLabel">Admin Username</label>
                        <input type="text" id="admin_auth_username" name="admin_auth_username">
                    </div>
                    <div class="form-group">
                        <label for="admin_auth_password" id="authPasswordLabel">Admin Password</label>
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
            var authTitle = document.getElementById('authTitle');
            var authUsernameLabel = document.getElementById('authUsernameLabel');
            var authPasswordLabel = document.getElementById('authPasswordLabel');
            
            if (role === 'admin') {
                adminAuth.classList.add('show');
                authTitle.textContent = 'Admin Verification Required';
                authUsernameLabel.textContent = 'Admin Username';
                authPasswordLabel.textContent = 'Admin Password';
            } else if (role === 'user') {
                adminAuth.classList.add('show');
                authTitle.textContent = 'User Verification Required';
                authUsernameLabel.textContent = 'Existing User/Admin Username';
                authPasswordLabel.textContent = 'Password';
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
    <script src="themes.js"></script>
</body>
</html>