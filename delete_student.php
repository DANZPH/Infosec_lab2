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

if(isset($_GET['id']) && is_numeric($_GET['id'])){
    $student_id = (int)$_GET['id'];
    
    // First, get the student data before deletion for logging
    $get_student = mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id");
    
    if($get_student && mysqli_num_rows($get_student) > 0){
        $student_data = mysqli_fetch_assoc($get_student);
        $student_info = json_encode($student_data);
        
        // Delete the student using prepared statement
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        
        if($stmt->execute()){
            if($stmt->affected_rows > 0){
                // Log successful deletion
                logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT', 'students', $student_id, $student_info, null);
                logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT', "Deleted student: {$student_data['fullname']} (ID: {$student_data['student_id']})");
                
                $_SESSION['success_message'] = "Student deleted successfully.";
            } else {
                // Log failed deletion - student not found
                logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT_FAILED', 'students', $student_id, null, json_encode(['error' => 'Student not found']));
                logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT_FAILED', "Attempted to delete non-existent student ID: $student_id");
                
                $_SESSION['error_message'] = "Student not found.";
            }
        } else {
            // Log database error
            logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT_FAILED', 'students', $student_id, null, json_encode(['error' => $stmt->error]));
            logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT_FAILED', "Database error deleting student ID $student_id: " . $stmt->error);
            
            $_SESSION['error_message'] = "Error deleting student.";
        }
        $stmt->close();
    } else {
        // Log attempt to delete non-existent student
        logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT_FAILED', 'students', $student_id, null, json_encode(['error' => 'Student not found in database']));
        logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT_FAILED', "Attempted to delete non-existent student ID: $student_id");
        
        $_SESSION['error_message'] = "Student not found.";
    }
} else {
    // Log invalid student ID attempt
    $invalid_id = $_GET['id'] ?? 'missing';
    logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT_FAILED', 'students', null, null, json_encode(['error' => 'Invalid student ID', 'provided_id' => $invalid_id]));
    logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'DELETE_STUDENT_FAILED', "Invalid student ID provided: $invalid_id");
    
    $_SESSION['error_message'] = "Invalid student ID.";
}

header("Location: dashboard.php");
exit;
?>