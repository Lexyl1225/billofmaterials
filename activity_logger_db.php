<?php
/**
 * Activity Logger Helper - MySQL Version
 * This replaces the JSON-based activity logger with database storage
 */

require_once __DIR__ . '/db-config.php';

function logActivity($action, $details = '', $page = '') {
    global $db;
    
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
    
    $log_id = uniqid('log_');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Get user ID from database
    $user_id = null;
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_id = $row['id'];
        }
        $stmt->close();
    }
    
    // Insert activity log
    $stmt = $db->prepare("INSERT INTO activity_logs (log_id, username, user_id, role, department, action, details, page, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('ssisssssss', $log_id, $username, $user_id, $role, $department, $action, $details, $page, $ipAddress, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}

function getActivityLogs($limit = 1000, $offset = 0, $filters = []) {
    global $db;
    
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Apply filters
    if (!empty($filters['username'])) {
        $where_clauses[] = "username = ?";
        $params[] = $filters['username'];
        $types .= 's';
    }
    
    if (!empty($filters['action'])) {
        $where_clauses[] = "action = ?";
        $params[] = $filters['action'];
        $types .= 's';
    }
    
    if (!empty($filters['date'])) {
        $where_clauses[] = "DATE(timestamp) = ?";
        $params[] = $filters['date'];
        $types .= 's';
    }
    
    if (!empty($filters['search'])) {
        $where_clauses[] = "(username LIKE ? OR action LIKE ? OR details LIKE ? OR page LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'ssss';
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    $query = "SELECT * FROM activity_logs $where_sql ORDER BY timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
        
        return $logs;
    }
    
    return [];
}

function getActivityLogCount($filters = []) {
    global $db;
    
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Apply same filters as getActivityLogs
    if (!empty($filters['username'])) {
        $where_clauses[] = "username = ?";
        $params[] = $filters['username'];
        $types .= 's';
    }
    
    if (!empty($filters['action'])) {
        $where_clauses[] = "action = ?";
        $params[] = $filters['action'];
        $types .= 's';
    }
    
    if (!empty($filters['date'])) {
        $where_clauses[] = "DATE(timestamp) = ?";
        $params[] = $filters['date'];
        $types .= 's';
    }
    
    if (!empty($filters['search'])) {
        $where_clauses[] = "(username LIKE ? OR action LIKE ? OR details LIKE ? OR page LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'ssss';
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    $query = "SELECT COUNT(*) as count FROM activity_logs $where_sql";
    $stmt = $db->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['count'];
    }
    
    return 0;
}

function getUniqueUsers() {
    global $db;
    $result = $db->query("SELECT DISTINCT username FROM activity_logs ORDER BY username");
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row['username'];
    }
    return $users;
}

function getUniqueActions() {
    global $db;
    $result = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
    $actions = [];
    while ($row = $result->fetch_assoc()) {
        $actions[] = $row['action'];
    }
    return $actions;
}
?>
