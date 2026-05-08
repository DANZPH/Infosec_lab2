<?php
session_start();
include("db.php");

// Check if user is logged in and has admin role
if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit;
}

if($_SESSION['role'] !== 'admin'){
    $_SESSION['error_message'] = "Access denied. Admin privileges required.";
    header("Location: dashboard.php");
    exit;
}

// Create audit_logs table if it doesn't exist
$create_audit_table = "CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(50) NOT NULL,
    `table_name` varchar(50) DEFAULT NULL,
    `record_id` int(11) DEFAULT NULL,
    `old_values` text DEFAULT NULL,
    `new_values` text DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `created_at` (`created_at`)
)";

mysqli_query($conn, $create_audit_table);

// Log this page access
$user_id = $_SESSION['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

$log_access = "INSERT INTO audit_logs (user_id, action, table_name, ip_address, user_agent) 
               VALUES ('$user_id', 'VIEW_AUDIT_LOGS', 'audit_logs', '$ip_address', '$user_agent')";
mysqli_query($conn, $log_access);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 25;
$offset = ($page - 1) * $records_per_page;

// Filter options
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_user = isset($_GET['user']) ? $_GET['user'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_table = isset($_GET['table']) ? $_GET['table'] : '';

// Build query with filters - join with users table to get username
$where_conditions = [];
if(!empty($filter_action)){
    $where_conditions[] = "al.action LIKE '%$filter_action%'";
}
if(!empty($filter_user)){
    $where_conditions[] = "u.username LIKE '%$filter_user%'";
}
if(!empty($filter_date)){
    $where_conditions[] = "DATE(al.created_at) = '$filter_date'";
}
if(!empty($filter_table)){
    $where_conditions[] = "al.table_name = '$filter_table'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = $count_result ? mysqli_fetch_assoc($count_result)['total'] : 0;
$total_pages = ceil($total_records / $records_per_page);

// Get audit logs with username from users table
$query = "SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id $where_clause ORDER BY al.created_at DESC LIMIT $records_per_page OFFSET $offset";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Audit Logs</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filters { 
            margin: 20px 0; 
            padding: 15px; 
            background: #f8f9fa; 
            border-radius: 5px; 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
            align-items: center;
        }
        .filters input, .filters select { 
            padding: 5px; 
            border: 1px solid #ddd; 
            border-radius: 3px;
        }
        .pagination { 
            margin: 20px 0; 
            text-align: center; 
        }
        .pagination a { 
            margin: 0 5px; 
            padding: 5px 10px; 
            text-decoration: none; 
            border: 1px solid #ddd; 
            border-radius: 3px;
        }
        .pagination a.active { 
            background: #3498db; 
            color: white; 
            border-color: #3498db;
        }
        .log-details { 
            font-size: 12px; 
            color: #666; 
            max-width: 200px; 
            overflow: hidden; 
            text-overflow: ellipsis;
        }
        .action-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            color: white;
            font-weight: bold;
        }
        .action-login { background: #27ae60; }
        .action-logout { background: #e74c3c; }
        .action-create { background: #3498db; }
        .action-update { background: #f39c12; }
        .action-delete { background: #e67e22; }
        .action-view { background: #9b59b6; }
        .action-backup { background: #1abc9c; }
        .action-failed { background: #c0392b; }
        .action-default { background: #95a5a6; }
    </style>
</head>
<body>

<div class="container" style="width: 95%; max-width: 1600px;">
    <h2>Audit Logs</h2>

    <div class="nav-links">
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <!-- Filters -->
    <div class="filters">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <label>Action:</label>
            <select name="action">
                <option value="">All Actions</option>
                <option value="LOGIN" <?php echo $filter_action === 'LOGIN' ? 'selected' : ''; ?>>Login</option>
                <option value="LOGOUT" <?php echo $filter_action === 'LOGOUT' ? 'selected' : ''; ?>>Logout</option>
                <option value="CREATE" <?php echo $filter_action === 'CREATE' ? 'selected' : ''; ?>>Create</option>
                <option value="UPDATE" <?php echo $filter_action === 'UPDATE' ? 'selected' : ''; ?>>Update</option>
                <option value="DELETE" <?php echo $filter_action === 'DELETE' ? 'selected' : ''; ?>>Delete</option>
                <option value="VIEW" <?php echo $filter_action === 'VIEW' ? 'selected' : ''; ?>>View</option>
                <option value="BACKUP" <?php echo $filter_action === 'BACKUP' ? 'selected' : ''; ?>>Backup</option>
                <option value="FAILED" <?php echo $filter_action === 'FAILED' ? 'selected' : ''; ?>>Failed Actions</option>
            </select>
            
            <label>Table:</label>
            <select name="table">
                <option value="">All Tables</option>
                <option value="students" <?php echo $filter_table === 'students' ? 'selected' : ''; ?>>Students</option>
                <option value="users" <?php echo $filter_table === 'users' ? 'selected' : ''; ?>>Users</option>
                <option value="audit_logs" <?php echo $filter_table === 'audit_logs' ? 'selected' : ''; ?>>Audit Logs</option>
            </select>
            
            <label>User:</label>
            <input type="text" name="user" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="Username">
            
            <label>Date:</label>
            <input type="date" name="date" value="<?php echo $filter_date; ?>">
            
            <button type="submit">Filter</button>
            <a href="audit_logs.php" style="padding: 5px 10px; background: #666; color: white; text-decoration: none; border-radius: 3px;">Clear</a>
        </form>
    </div>

    <h3>Security Audit Trail (Total: <?php echo $total_records; ?>)</h3>

    <table>
    <tr>
        <th>ID</th>
        <th>Date/Time</th>
        <th>User</th>
        <th>Action</th>
        <th>Table</th>
        <th>Record ID</th>
        <th>IP Address</th>
        <th>Details</th>
    </tr>

    <?php if($result && mysqli_num_rows($result) > 0): ?>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo date('M j, Y H:i:s', strtotime($row['created_at'])); ?></td>
            <td><?php echo htmlspecialchars($row['username'] ?? 'System'); ?></td>
            <td>
                <?php 
                $action_class = 'action-default';
                $action = strtolower($row['action']);
                if(strpos($action, 'login') !== false && strpos($action, 'failed') === false) $action_class = 'action-login';
                elseif(strpos($action, 'logout') !== false) $action_class = 'action-logout';
                elseif(strpos($action, 'create') !== false || strpos($action, 'add') !== false) $action_class = 'action-create';
                elseif(strpos($action, 'update') !== false || strpos($action, 'edit') !== false) $action_class = 'action-update';
                elseif(strpos($action, 'delete') !== false) $action_class = 'action-delete';
                elseif(strpos($action, 'view') !== false) $action_class = 'action-view';
                elseif(strpos($action, 'backup') !== false) $action_class = 'action-backup';
                elseif(strpos($action, 'failed') !== false) $action_class = 'action-failed';
                ?>
                <span class="action-badge <?php echo $action_class; ?>">
                    <?php echo htmlspecialchars($row['action']); ?>
                </span>
            </td>
            <td><?php echo htmlspecialchars($row['table_name'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['record_id'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
            <td class="log-details">
                <?php if($row['old_values']): ?>
                    <strong>Old:</strong> <?php echo htmlspecialchars(substr($row['old_values'], 0, 50)); ?>...<br>
                <?php endif; ?>
                <?php if($row['new_values']): ?>
                    <strong>New:</strong> <?php echo htmlspecialchars(substr($row['new_values'], 0, 50)); ?>...
                <?php endif; ?>
                <?php if(!$row['old_values'] && !$row['new_values']): ?>
                    -
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="8" style="text-align: center; color: #666;">
                No audit logs found
            </td>
        </tr>
    <?php endif; ?>

    </table>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="pagination">
        <?php if($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&action=<?php echo $filter_action; ?>&user=<?php echo $filter_user; ?>&date=<?php echo $filter_date; ?>&table=<?php echo $filter_table; ?>">&laquo; Previous</a>
        <?php endif; ?>
        
        <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&action=<?php echo $filter_action; ?>&user=<?php echo $filter_user; ?>&date=<?php echo $filter_date; ?>&table=<?php echo $filter_table; ?>" 
               class="<?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&action=<?php echo $filter_action; ?>&user=<?php echo $filter_user; ?>&date=<?php echo $filter_date; ?>&table=<?php echo $filter_table; ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>