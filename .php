<?php
session_start();
include("db.php");
include("functions.php");

// Check if user is logged in and has permission
if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
if(!hasPermission($conn, $user_id, 'archive')){
    header("Location: dashboard.php");
    exit;
}

// Handle restore action
if(isset($_GET['restore']) && is_numeric($_GET['restore'])){
    $archive_id = (int)$_GET['restore'];
    
    // Get archived student data
    $stmt = $conn->prepare("SELECT * FROM students_archive WHERE id = ?");
    $stmt->bind_param("i", $archive_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0){
        $archived_student = $result->fetch_assoc();
        
        // Restore to students table
        $restore_stmt = $conn->prepare("INSERT INTO students (student_id, fullname, email, course_id, enrollment_date) VALUES (?, ?, ?, ?, ?)");
        $restore_stmt->bind_param("sssis", 
            $archived_student['student_id'],
            $archived_student['fullname'],
            $archived_student['email'],
            $archived_student['course_id'],
            $archived_student['enrollment_date']
        );
        
        if($restore_stmt->execute()){
            $new_id = $conn->insert_id;
            
            // Log the restore action
            logAudit($conn, $user_id, 'RESTORE', 'students', $new_id, null, json_encode($archived_student));
            
            // Remove from archive
            $delete_archive_stmt = $conn->prepare("DELETE FROM students_archive WHERE id = ?");
            $delete_archive_stmt->bind_param("i", $archive_id);
            $delete_archive_stmt->execute();
            $delete_archive_stmt->close();
            
            $_SESSION['success_message'] = "Student restored successfully.";
        }
        $restore_stmt->close();
    }
    $stmt->close();
    
    header("Location: archive.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Archive</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container" style="width: 90%; max-width: 1200px;">
    <h2>Student Archive</h2>

    <div class="nav-links">
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="success-message"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="error-message"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <h3>Archived Students</h3>

    <table>
    <tr>
        <th>Student ID</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Course</th>
        <th>Archived Date</th>
        <th>Archived By</th>
        <th>Reason</th>
        <th>Action</th>
    </tr>

    <?php
    $query = "SELECT sa.*, c.course_name, c.course_code, u.username as archived_by_user 
              FROM students_archive sa 
              LEFT JOIN courses c ON sa.course_id = c.id 
              LEFT JOIN users u ON sa.archived_by = u.id 
              ORDER BY sa.archived_at DESC";
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0):
        while($row = mysqli_fetch_assoc($result)):
    ?>
    <tr>
        <td><?php echo sanitizeOutput($row['student_id']); ?></td>
        <td><?php echo sanitizeOutput($row['fullname']); ?></td>
        <td><?php echo sanitizeOutput($row['email']); ?></td>
        <td><?php echo sanitizeOutput($row['course_code'] . ' - ' . $row['course_name']); ?></td>
        <td><?php echo sanitizeOutput($row['archived_at']); ?></td>
        <td><?php echo sanitizeOutput($row['archived_by_user']); ?></td>
        <td><?php echo sanitizeOutput($row['archive_reason']); ?></td>
        <td>
            <a href="archive.php?restore=<?php echo $row['id']; ?>" 
               onclick="return confirm('Are you sure you want to restore this student?')" 
               style="color: #27ae60;">
                Restore
            </a>
        </td>
    </tr>
    <?php 
        endwhile;
    else:
    ?>
    <tr>
        <td colspan="8" style="text-align: center; color: #666;">
            No archived students found
        </td>
    </tr>
    <?php endif; ?>

    </table>

</div>

</body>
</html>