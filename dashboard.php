<?php
session_start();
include("db.php");
include("log_helper.php");

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit;
}

$username = $_SESSION['user'];
$role = $_SESSION['role'] ?? 'admin';
$user_id = $_SESSION['user_id'] ?? 1;

// Log dashboard access
logSystemAction($conn, $user_id, $username, 'VIEW_DASHBOARD', "User accessed dashboard");

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container" style="width: 90%; max-width: 1200px;">
    <h2>Welcome <?php echo htmlspecialchars($username); ?> 
        <span style="color: <?php echo ($role === 'admin') ? '#e74c3c' : '#3498db'; ?>;">
            (<?php echo ucfirst(htmlspecialchars($role)); ?>)
        </span>
    </h2>

    <div class="nav-links">
        <?php if($role === 'admin'): ?>
            <a href="add_student.php">Add Student</a>
            <a href="backup.php">Backup & Restore</a>
            <a href="audit_logs.php">Audit Logs</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </div>

    <?php if(!empty($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if(!empty($error_message)): ?>
        <div class="error-message"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if($role === 'student'): ?>
        <div style="padding: 15px; background: #e8f4fd; border-radius: 5px; margin: 20px 0; border-left: 4px solid #3498db;">
            <h4>Student Portal</h4>
            <p>You have read-only access to view student records.</p>
        </div>
    <?php endif; ?>

    <h3>Student List</h3>

    <table>
    <tr>
        <th>ID</th>
        <th>Student ID</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Course ID</th>
        <th>Enrollment Date</th>
        <th>Status</th>
        <?php if($role === 'admin'): ?>
        <th>Action</th>
        <?php endif; ?>
    </tr>

    <?php
    // Query using the actual table structure
    $result = mysqli_query($conn, "SELECT s.*, c.course_name, c.course_code 
                                   FROM students s 
                                   LEFT JOIN courses c ON s.course_id = c.id 
                                   WHERE s.is_active = 1 
                                   ORDER BY s.id DESC");

    if(!$result){
        // Fallback query if courses table doesn't exist
        $result = mysqli_query($conn, "SELECT * FROM students WHERE is_active = 1 ORDER BY id DESC");
    }

    if($result && mysqli_num_rows($result) > 0):
        while($row = mysqli_fetch_assoc($result)):
    ?>
    <tr>
        <td><?php echo htmlspecialchars($row['id']); ?></td>
        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
        <td><?php echo htmlspecialchars($row['fullname']); ?></td>
        <td><?php echo htmlspecialchars($row['email']); ?></td>
        <td>
            <?php 
            if(isset($row['course_name'])){
                echo htmlspecialchars($row['course_code'] . ' - ' . $row['course_name']);
            } else {
                echo htmlspecialchars($row['course_id']);
            }
            ?>
        </td>
        <td><?php echo htmlspecialchars($row['enrollment_date'] ?? 'N/A'); ?></td>
        <td>
            <span style="color: <?php echo $row['is_active'] ? '#27ae60' : '#e74c3c'; ?>;">
                <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
            </span>
        </td>
        <?php if($role === 'admin'): ?>
        <td>
            <a href="delete_student.php?id=<?php echo $row['id']; ?>" 
               onclick="return confirm('Are you sure you want to delete this student?')" 
               style="color: #e74c3c;">
                Delete
            </a>
        </td>
        <?php endif; ?>
    </tr>
    <?php 
        endwhile;
    else:
    ?>
    <tr>
        <td colspan="<?php echo ($role === 'admin') ? '8' : '7'; ?>" style="text-align: center; color: #666;">
            No students found
        </td>
    </tr>
    <?php endif; ?>

    </table>
</div>

</body>
</html>