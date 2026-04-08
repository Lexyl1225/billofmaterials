<?php
session_start();
require_once 'activity_logger_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$filter_user = isset($_GET['filter_user']) ? $_GET['filter_user'] : '';
$filter_action = isset($_GET['filter_action']) ? $_GET['filter_action'] : '';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare filters array
$filters = [];
if (!empty($filter_user)) $filters['username'] = $filter_user;
if (!empty($filter_action)) $filters['action'] = $filter_action;
if (!empty($filter_date)) $filters['date'] = $filter_date;
if (!empty($search_query)) $filters['search'] = $search_query;

// Get unique users and actions for filters
$unique_users = getUniqueUsers();
$unique_actions = getUniqueActions();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$total_logs = getActivityLogCount($filters);
$total_pages = ceil($total_logs / $per_page);
$offset = ($page - 1) * $per_page;
$paginated_logs = getActivityLogs($per_page, $offset, $filters);

// Get activity statistics
$total_activities = getActivityLogCount();
$activities_today = getActivityLogCount(['date' => date('Y-m-d')]);
$unique_users_count = count($unique_users);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Logs - BOM/BOQ System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header-info {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }
        
        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px 40px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        
        .filters-section {
            padding: 30px 40px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .search-box {
            grid-column: 1 / -1;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .logs-section {
            padding: 30px 40px;
        }
        
        .logs-count {
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
            font-size: 14px;
            white-space: nowrap;
        }
        
        tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        td {
            padding: 12px 15px;
            font-size: 13px;
            color: #333;
        }
        
        .action-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .action-login {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .action-logout {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .action-create {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .action-update {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .action-delete {
            background: #ffebee;
            color: #c62828;
        }
        
        .action-view {
            background: #e0f2f1;
            color: #00695c;
        }
        
        .action-export {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .action-post {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-unpost {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .action-save {
            background: #e1f5fe;
            color: #0277bd;
        }
        
        .action-default {
            background: #f5f5f5;
            color: #616161;
        }
        
        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .role-admin {
            background: #ff5252;
            color: white;
        }
        
        .role-user {
            background: #4caf50;
            color: white;
        }
        
        .role-guest {
            background: #9e9e9e;
            color: white;
        }
        
        .role-purchasing {
            background: #2196f3;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .no-logs {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-logs i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px 5px;
            }
        }
    </style>
    <script>try{var t=localStorage.getItem('bom_theme');if(t&&t!=='default')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="stylesheet" href="themes.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📊 User Activity Logs</h1>
                <div class="header-info">
                    <span class="user-badge">👤 <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <span class="user-badge">🎭 <?php echo htmlspecialchars(ucfirst($_SESSION['user_role'])); ?></span>
                </div>
            </div>
            <a href="save_bomboq.php" class="back-btn">← Back to Main</a>
        </div>
        
        <div class="stats-section">
            <div class="stat-card">
                <h3><?php echo number_format($total_activities); ?></h3>
                <p>Total Activities</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($activities_today); ?></h3>
                <p>Activities Today</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($unique_users_count); ?></h3>
                <p>Unique Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format(count($unique_actions)); ?></h3>
                <p>Action Types</p>
            </div>
        </div>
        
        <div class="filters-section">
            <form method="GET" action="user_logs.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="filter_user">Filter by User</label>
                        <select name="filter_user" id="filter_user">
                            <option value="">All Users</option>
                            <?php foreach ($unique_users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user); ?>" <?php echo $filter_user === $user ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_action">Filter by Action</label>
                        <select name="filter_action" id="filter_action">
                            <option value="">All Actions</option>
                            <?php foreach ($unique_actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $action))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_date">Filter by Date</label>
                        <input type="date" name="filter_date" id="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                    
                    <div class="filter-group search-box">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" placeholder="Search username, action, details, or page..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                    <a href="user_logs.php" class="btn btn-secondary">🔄 Reset</a>
                </div>
            </form>
        </div>
        
        <div class="logs-section">
            <div class="logs-count">
                Showing <?php echo number_format(count($paginated_logs)); ?> of <?php echo number_format($total_logs); ?> activities
                <?php if ($filter_user || $filter_action || $filter_date || $search_query): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            
            <?php if (count($paginated_logs) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Page</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($log['username']); ?></strong></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower($log['role']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($log['role'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['department'] ?: '-'); ?></td>
                                    <td>
                                        <?php
                                        $action_class = 'action-default';
                                        if (strpos($log['action'], 'login') !== false) $action_class = 'action-login';
                                        elseif (strpos($log['action'], 'logout') !== false) $action_class = 'action-logout';
                                        elseif (strpos($log['action'], 'post') !== false && strpos($log['action'], 'unpost') === false) $action_class = 'action-post';
                                        elseif (strpos($log['action'], 'unpost') !== false) $action_class = 'action-unpost';
                                        elseif (strpos($log['action'], 'save') !== false) $action_class = 'action-save';
                                        elseif (strpos($log['action'], 'create') !== false || strpos($log['action'], 'add') !== false) $action_class = 'action-create';
                                        elseif (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false) $action_class = 'action-update';
                                        elseif (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'remove') !== false) $action_class = 'action-delete';
                                        elseif (strpos($log['action'], 'view') !== false) $action_class = 'action-view';
                                        elseif (strpos($log['action'], 'export') !== false) $action_class = 'action-export';
                                        ?>
                                        <span class="action-badge <?php echo $action_class; ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td><?php echo htmlspecialchars($log['page']); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $filter_user ? '&filter_user=' . urlencode($filter_user) : ''; ?><?php echo $filter_action ? '&filter_action=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&filter_date=' . urlencode($filter_date) : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>">← Previous</a>
                        <?php else: ?>
                            <span class="disabled">← Previous</span>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?page=1<?php echo $filter_user ? '&filter_user=' . urlencode($filter_user) : ''; ?><?php echo $filter_action ? '&filter_action=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&filter_date=' . urlencode($filter_date) : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $filter_user ? '&filter_user=' . urlencode($filter_user) : ''; ?><?php echo $filter_action ? '&filter_action=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&filter_date=' . urlencode($filter_date) : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages; ?><?php echo $filter_user ? '&filter_user=' . urlencode($filter_user) : ''; ?><?php echo $filter_action ? '&filter_action=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&filter_date=' . urlencode($filter_date) : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $filter_user ? '&filter_user=' . urlencode($filter_user) : ''; ?><?php echo $filter_action ? '&filter_action=' . urlencode($filter_action) : ''; ?><?php echo $filter_date ? '&filter_date=' . urlencode($filter_date) : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>">Next →</a>
                        <?php else: ?>
                            <span class="disabled">Next →</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-logs">
                    <div style="font-size: 64px;">📋</div>
                    <h2>No Activities Found</h2>
                    <p>No activity logs match your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="themes.js"></script>
</body>
</html>
