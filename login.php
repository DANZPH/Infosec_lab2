<?php
session_start();
include("db.php");
include("log_helper.php");

$error_message = "";

if(isset($_POST['login'])){

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Input validation
    if(empty($username) || empty($password)){
        $error_message = "Username and password are required.";
    }
    // Check for special characters and symbols in username (allow only alphanumeric and underscore)
    elseif(!preg_match('/^[a-zA-Z0-9_]+$/', $username)){
        $error_message = "Username can only contain letters, numbers, and underscores.";
    }
    // Check username length
    elseif(strlen($username) > 50){
        $error_message = "Username is too long.";
    }
    else {
        // Sanitize input to prevent XSS
        $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        
        // Use prepared statement to get user with role information
        $stmt = $conn->prepare("SELECT u.id, u.username, u.password, u.role_id, r.role_name 
                               FROM users u 
                               LEFT JOIN roles r ON u.role_id = r.id 
                               WHERE u.username = ?");
        
        if($stmt){
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if($result->num_rows > 0){
                $user = $result->fetch_assoc();
                
                // Check if password is hashed or plain text
                if(password_verify($password, $user['password']) || $password === $user['password']){
                    $_SESSION['user'] = $username;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role_name'] ?? 'admin'; // Default to admin if no role found
                    $_SESSION['role_id'] = $user['role_id'] ?? 1;
                    
                    // Log successful login
                    logAuditAction($conn, $user['id'], $username, 'LOGIN_SUCCESS', 'users', $user['id']);
                    logSystemAction($conn, $user['id'], $username, 'LOGIN', 'User logged in successfully');
                    
                    header("Location: dashboard.php");
                    exit;
                }
            }
            $stmt->close();
        } else {
            // Fallback for simple users table without roles
            $simple_query = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
            if($simple_query && mysqli_num_rows($simple_query) > 0){
                $user = mysqli_fetch_assoc($simple_query);
                
                if(password_verify($password, $user['password']) || $password === $user['password']){
                    $_SESSION['user'] = $username;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = ($username === 'admin') ? 'admin' : 'student';
                    $_SESSION['role_id'] = ($username === 'admin') ? 1 : 2;
                    
                    // Log successful login
                    logAuditAction($conn, $user['id'], $username, 'LOGIN_SUCCESS', 'users', $user['id']);
                    logSystemAction($conn, $user['id'], $username, 'LOGIN', 'User logged in successfully');
                    
                    header("Location: dashboard.php");
                    exit;
                }
            }
        }
        
        // Log failed login attempt
        logAuditAction($conn, null, $username, 'LOGIN_FAILED', 'users', null, null, json_encode(['attempted_username' => $username]));
        logSystemAction($conn, null, $username, 'LOGIN_FAILED', 'Failed login attempt for username: ' . $username);
        
        $error_message = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <h2>Login</h2>

    <?php if(!empty($error_message)): ?>
        <div class="error-message"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" maxlength="50" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button name="login">Login</button>
    </form>
    
    <div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 3px; font-size: 12px;">
        <strong>Test Credentials:</strong><br>
        Admin: admin / admin123<br>
        Student: student1 / student123
    </div>
</div>

</body>
</html>