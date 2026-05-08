<?php
session_start();
include("db.php");
include("log_helper.php");

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

// Log backup page access
logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'VIEW_BACKUP', "User accessed backup page");

$message = "";
$message_type = "";

// Handle backup creation
if(isset($_POST['create_backup'])){
    $backup_name = 'infosec_lab_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Database connection details
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'infosec_lab';
    
    // Create backup content
    $backup_content = "-- InfoSec Lab Database Backup\n";
    $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $backup_content .= "-- Database: $db_name\n\n";
    
    $backup_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $backup_content .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $backup_content .= "START TRANSACTION;\n";
    $backup_content .= "SET time_zone = \"+00:00\";\n\n";
    
    // Get all tables
    $tables_result = mysqli_query($conn, "SHOW TABLES");
    
    while($table = mysqli_fetch_array($tables_result)){
        $table_name = $table[0];
        
        // Get table structure
        $create_table_result = mysqli_query($conn, "SHOW CREATE TABLE `$table_name`");
        $create_table = mysqli_fetch_array($create_table_result);
        
        $backup_content .= "DROP TABLE IF EXISTS `$table_name`;\n";
        $backup_content .= $create_table[1] . ";\n\n";
        
        // Get table data
        $data_result = mysqli_query($conn, "SELECT * FROM `$table_name`");
        
        if(mysqli_num_rows($data_result) > 0){
            while($row = mysqli_fetch_assoc($data_result)){
                $backup_content .= "INSERT INTO `$table_name` VALUES (";
                
                $values = array();
                foreach($row as $value){
                    if($value === null){
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . mysqli_real_escape_string($conn, $value) . "'";
                    }
                }
                
                $backup_content .= implode(', ', $values) . ");\n";
            }
            $backup_content .= "\n";
        }
    }
    
    $backup_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $backup_content .= "COMMIT;\n";
    
    // Log successful backup creation
    logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'CREATE_BACKUP', 'database', null, null, json_encode(['backup_name' => $backup_name, 'size' => strlen($backup_content)]));
    logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'CREATE_BACKUP', "Created database backup: $backup_name");
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backup_name . '"');
    header('Content-Length: ' . strlen($backup_content));
    
    echo $backup_content;
    exit;
}

// Handle restore (file upload)
if(isset($_POST['restore_backup']) && isset($_FILES['backup_file'])){
    if($_FILES['backup_file']['error'] === UPLOAD_ERR_OK){
        $sql_content = file_get_contents($_FILES['backup_file']['tmp_name']);
        
        // Clean and prepare SQL content
        $sql_content = trim($sql_content);
        
        // Remove comments and empty lines
        $lines = explode("\n", $sql_content);
        $clean_lines = [];
        
        foreach($lines as $line) {
            $line = trim($line);
            // Skip empty lines and comments, but keep important SET commands
            if(!empty($line) && substr($line, 0, 2) !== '--' && substr($line, 0, 2) !== '/*') {
                $clean_lines[] = $line;
            }
        }
        
        $clean_sql = implode("\n", $clean_lines);
        
        // Split by semicolon but be more careful about it
        $queries = [];
        $current_query = '';
        $in_quotes = false;
        $quote_char = '';
        
        for($i = 0; $i < strlen($clean_sql); $i++) {
            $char = $clean_sql[$i];
            
            if(!$in_quotes && ($char === '"' || $char === "'")) {
                $in_quotes = true;
                $quote_char = $char;
            } elseif($in_quotes && $char === $quote_char) {
                $in_quotes = false;
                $quote_char = '';
            } elseif(!$in_quotes && $char === ';') {
                $current_query = trim($current_query);
                if(!empty($current_query)) {
                    $queries[] = $current_query;
                }
                $current_query = '';
                continue;
            }
            
            $current_query .= $char;
        }
        
        // Add the last query if it doesn't end with semicolon
        $current_query = trim($current_query);
        if(!empty($current_query)) {
            $queries[] = $current_query;
        }
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        // Start transaction and disable foreign key checks for restore
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
        mysqli_query($conn, "START TRANSACTION");
        
        foreach($queries as $query){
            $query = trim($query);
            if(!empty($query) && strlen($query) > 3) { // Skip very short queries
                // Skip certain commands that might cause issues
                $query_upper = strtoupper($query);
                if(strpos($query_upper, 'START TRANSACTION') !== false || 
                   strpos($query_upper, 'COMMIT') !== false) {
                    continue; // Skip transaction commands as we handle them ourselves
                }
                
                if(mysqli_query($conn, $query)){
                    $success_count++;
                } else {
                    $error_count++;
                    $error_msg = mysqli_error($conn);
                    $errors[] = $error_msg;
                    
                    // Log individual query errors for debugging
                    error_log("SQL Error in restore: $error_msg for query: " . substr($query, 0, 100));
                }
            }
        }
        
        // Commit transaction and re-enable foreign key checks
        if($error_count === 0) {
            mysqli_query($conn, "COMMIT");
        } else {
            mysqli_query($conn, "ROLLBACK");
        }
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        
        if($error_count === 0){
            // Log successful restore
            logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'RESTORE_DATABASE', 'database', null, null, json_encode(['filename' => $_FILES['backup_file']['name'], 'queries_executed' => $success_count]));
            logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'RESTORE_DATABASE', "Successfully restored database from: " . $_FILES['backup_file']['name'] . " ($success_count queries)");
            
            $message = "Database restored successfully! ($success_count queries executed)";
            $message_type = "success";
        } else {
            // Log partial restore
            logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'RESTORE_DATABASE_PARTIAL', 'database', null, null, json_encode(['filename' => $_FILES['backup_file']['name'], 'success' => $success_count, 'errors' => $error_count, 'error_details' => $errors]));
            logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'RESTORE_DATABASE_PARTIAL', "Partial database restore from: " . $_FILES['backup_file']['name'] . " (Success: $success_count, Errors: $error_count)");
            
            $message = "Restore completed with some issues. Success: $success_count, Errors: $error_count";
            $message_type = "warning";
        }
    } else {
        // Log failed restore
        logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'RESTORE_DATABASE_FAILED', 'database', null, null, json_encode(['error' => 'File upload error']));
        logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'RESTORE_DATABASE_FAILED', "Failed to restore database - file upload error");
        
        $message = "Error uploading file.";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Backup & Restore</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container" style="width: 90%; max-width: 800px;">
    <h2>Backup & Restore</h2>

    <div class="nav-links">
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if(!empty($message)): ?>
        <div class="<?php echo $message_type; ?>-message"><?php echo $message; ?></div>
    <?php endif; ?>

    <div style="display: flex; gap: 20px; margin: 20px 0;">
        <!-- Create Backup -->
        <div style="flex: 1; padding: 20px; background: #f8f9fa; border-radius: 5px;">
            <h3>Create Backup</h3>
            <p>Download a complete backup of the database as an SQL file.</p>
            
            <form method="POST">
                <button type="submit" name="create_backup" style="background: #27ae60; padding: 10px 20px;">
                    📥 Download Backup
                </button>
            </form>
        </div>

        <!-- Restore Backup -->
        <div style="flex: 1; padding: 20px; background: #fff3cd; border-radius: 5px; border: 1px solid #ffeaa7;">
            <h3>Restore Database</h3>
            <div style="color: #856404; margin: 10px 0;">
                <strong>⚠️ Warning:</strong> This will overwrite the current database!
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="backup_file" accept=".sql" required style="margin: 10px 0; width: 100%;">
                <br>
                <button type="submit" name="restore_backup" 
                        onclick="return confirm('This will overwrite the current database. Are you sure?')"
                        style="background: #e74c3c; padding: 10px 20px;">
                    📤 Restore Database
                </button>
            </form>
        </div>
    </div>

</div>

</body>
</html>