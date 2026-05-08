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

$message = "";

if(isset($_POST['add'])){

    $student_id = $_POST['student_id'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $course_id = $_POST['course_id'];
    $enrollment_date = $_POST['enrollment_date'];

    // Basic validation
    if(empty($student_id) || empty($fullname) || empty($email) || empty($course_id)){
        $message = "Please fill in all required fields.";
    }
    // Validate student ID (alphanumeric and hyphens only)
    elseif(!preg_match('/^[a-zA-Z0-9\-]+$/', $student_id)){
        $message = "Student ID can only contain letters, numbers, and hyphens.";
    }
    // Validate full name (letters, spaces, hyphens, periods only)
    elseif(!preg_match('/^[a-zA-Z\s\-\.]+$/', $fullname)){
        $message = "Full name can only contain letters, spaces, hyphens, and periods.";
    }
    // Validate email
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $message = "Please enter a valid email address.";
    }
    else {
        // Set default enrollment date if not provided
        if(empty($enrollment_date)){
            $enrollment_date = date('Y-m-d');
        }

        // Insert with the correct column structure
        $query = "INSERT INTO students (student_id, fullname, email, course_id, enrollment_date) 
                  VALUES ('$student_id', '$fullname', '$email', '$course_id', '$enrollment_date')";

        if(mysqli_query($conn, $query)){
            $new_student_id = mysqli_insert_id($conn);
            
            // Log the successful addition
            $student_data = json_encode([
                'student_id' => $student_id,
                'fullname' => $fullname,
                'email' => $email,
                'course_id' => $course_id,
                'enrollment_date' => $enrollment_date
            ]);
            
            logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'CREATE_STUDENT', 'students', $new_student_id, null, $student_data);
            logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'ADD_STUDENT', "Added new student: $fullname (ID: $student_id)");
            
            $message = "Student added successfully!";
            // Clear form
            $_POST = array();
        } else {
            // Log the failed addition
            logAuditAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'CREATE_STUDENT_FAILED', 'students', null, null, json_encode(['error' => mysqli_error($conn)]));
            logSystemAction($conn, $_SESSION['user_id'], $_SESSION['user'], 'ADD_STUDENT_FAILED', "Failed to add student: " . mysqli_error($conn));
            
            $message = "Error: " . mysqli_error($conn);
        }
    }
}

// Get available courses (if courses table exists)
$courses = [];
$courses_result = mysqli_query($conn, "SELECT id, course_code, course_name FROM courses ORDER BY course_code");
if($courses_result){
    while($row = mysqli_fetch_assoc($courses_result)){
        $courses[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Student</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="form-container">
    <h2>Add Student</h2>

    <?php if(!empty($message)): ?>
        <div style="padding: 10px; margin: 10px 0; border-radius: 3px; 
                    background: <?php echo (strpos($message, 'successfully') !== false) ? '#d4edda; color: #155724; border: 1px solid #c3e6cb' : '#f8d7da; color: #721c24; border: 1px solid #f5c6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label>Student ID:</label>
        <input type="text" name="student_id" value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>" 
               placeholder="e.g., 540463" required>

        <label>Full Name:</label>
        <input type="text" name="fullname" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" 
               placeholder="e.g., John Doe" required>

        <label>Email:</label>
        <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
               placeholder="e.g., john.doe@example.com" required>

        <label>Course:</label>
        <?php if(!empty($courses)): ?>
            <select name="course_id" required>
                <option value="">Select a course</option>
                <?php foreach($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" 
                            <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input type="number" name="course_id" value="<?php echo isset($_POST['course_id']) ? htmlspecialchars($_POST['course_id']) : ''; ?>" 
                   placeholder="Course ID (e.g., 1, 2, 3)" required>
            <small style="color: #666;">Enter a course ID number</small>
        <?php endif; ?>

        <label>Enrollment Date:</label>
        <input type="date" name="enrollment_date" value="<?php echo isset($_POST['enrollment_date']) ? $_POST['enrollment_date'] : date('Y-m-d'); ?>">

        <br><br>
        <button type="submit" name="add">Add Student</button>
        <a href="dashboard.php" style="margin-left: 10px; color: #666;">Cancel</a>
    </form>

    <div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 3px; font-size: 12px;">
        <strong>Input Rules:</strong><br>
        • Student ID: Letters, numbers, and hyphens only<br>
        • Full Name: Letters, spaces, hyphens, and periods only<br>
        • Email: Must be valid email format<br>
        • Course ID: Select from dropdown or enter course ID number<br>
        • Enrollment Date: Defaults to today if not specified
    </div>
</div>

</body>
</html>