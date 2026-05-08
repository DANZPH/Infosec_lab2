<?php
session_start();
include("db.php");
include("log_helper.php");

// Log logout if user is logged in
if(isset($_SESSION['user_id']) && isset($_SESSION['user'])){
    logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'LOGOUT', 'users', $_SESSION['user_id']);
    logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'LOGOUT', 'User logged out');
}

// Destroy all session data
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>