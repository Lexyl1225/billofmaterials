<?php
/**
 * Activity Logger Helper
 */

define('LOG_FILE', __DIR__ . '/user_activity_logs.json');

function logActivity($action, $details = '', $page = '') {
    $logs = [];
    
    if (file_exists(LOG_FILE)) {
        $content = file_get_contents(LOG_FILE);
        $logs = json_decode($content, true) ?: [];
    }
    
    $username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Unknown';
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
    $department = isset($_SESSION['user_department']) ? $_SESSION['user_department'] : '';
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    if (empty($page)) {
        $page = basename($_SERVER['PHP_SELF'] ?? 'Unknown');
    }
    
    $logEntry = [
        'id' => uniqid('log_'),
        'timestamp' => date('Y-m-d H:i:s'),
        'username' => $username,
        'role' => $role,
        'department' => $department,
        'action' => $action,
        'details' => $details,
        'page' => $page,
        'ip_address' => $ipAddress,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
    
    $logs[] = $logEntry;
    
    if (count($logs) > 10000) {
        $logs = array_slice($logs, -10000);
    }
    
    return file_put_contents(LOG_FILE, json_encode($logs, JSON_PRETTY_PRINT)) !== false;
}

function getActivityLogs() {
    if (!file_exists(LOG_FILE)) {
        return [];
    }
    
    $content = file_get_contents(LOG_FILE);
    return json_decode($content, true) ?: [];
}
?>