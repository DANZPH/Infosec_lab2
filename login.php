<?php
session_start();
include("db.php");

if(isset($_POST['login'])){

    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users 
              WHERE username='$username' 
              AND password='$password'";

    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){
        $_SESSION['user'] = $username;
        header("Location: dashboard.php");
    } else {
        echo "Invalid Login";
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
    <h2>Admin Login</h2>

    <form method="POST">
        <input type="text" name="username" placeholder="Username"><br>
        <input type="password" name="password" placeholder="Password"><br>
        <button name="login">Login</button>
    </form>
</div>

</body>
</html>
