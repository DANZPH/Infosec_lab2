<?php
session_start();
include("db.php");

if(isset($_POST['add'])){

    $student_id = $_POST['student_id'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $course = $_POST['course'];
    $course_description = $_POST['course_description'];

    $query = "INSERT INTO students 
              VALUES ('', '$student_id', '$fullname', 
              '$email', '$course', '$course_description')";

    mysqli_query($conn, $query);

    header("Location: dashboard.php");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Student</title>
</head>
<body>

<h2>Add Student</h2>

<form method="POST">
    Student ID: <input type="text" name="student_id"><br>
    Full Name: <input type="text" name="fullname"><br>
    Email: <input type="text" name="email"><br>
    Course: <input type="text" name="course"><br>
    Course Description: <input type="text" name="course_description"><br>
    <button name="add">Add</button>
</form>

</body>
</html>
