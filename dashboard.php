<?php
session_start();
include("db.php");

if(!isset($_SESSION['user'])){
    header("Location: login.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h2>Welcome <?php echo $_SESSION['user']; ?></h2>

<a href="add_student.php">Add Student</a> |
<a href="logout.php">Logout</a>

<h3>Student List</h3>

<table border="1">
<tr>
    <th>ID</th>
    <th>Student ID</th>
    <th>Full Name</th>
    <th>Email</th>
    <th>Course</th>
    <th>Action</th>
</tr>

<?php
$result = mysqli_query($conn, "SELECT * FROM students");

while($row = mysqli_fetch_assoc($result)){
?>
<tr>
    <td><?php echo $row['id']; ?></td>
    <td><?php echo $row['student_id']; ?></td>
    <td><?php echo $row['fullname']; ?></td>
    <td><?php echo $row['email']; ?></td>
    <td><?php echo $row['course']; ?></td>
    <td>
        <a href="delete_student.php?id=<?php echo $row['id']; ?>">
            Delete
        </a>
    </td>
</tr>
<?php } ?>

</table>

</body>
</html>
