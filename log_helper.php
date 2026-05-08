<?php
// Simple logging helper functions

function logAuditAction($conn, $user_id, $username, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Create audit_logs table if it doesn't exist (without username column)
    $create_table = "CREATE TABLE IF NOT EXISTS `audit_logs` (
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
        PRIMARY KEY (`id`)
    )";
    mysqli_query($conn, $create_table);
    
    // Insert log entry (without username)
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if($stmt) {
        $stmt->bind_param("ississss", $user_id, $action, $table_name, $record_id, $old_values, $new_values, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

function logSystemAction($conn, $user_id, $username, $action, $details = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Create system_logs table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS `system_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `username` varchar(100) DEFAULT NULL,
        `action` varchar(100) NOT NULL,
        `details` text DEFAULT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `timestamp` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    )";
    mysqli_query($conn, $create_table);
    
    // Insert log entry
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    if($stmt) {
        $stmt->bind_param("issss", $user_id, $username, $action, $details, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
}
?>