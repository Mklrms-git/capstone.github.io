<?php
$conn = new mysqli('localhost', 'root', '', 'lab5_security');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Validate password strength
    if ($password !== $confirm_password) {
        echo "<script>showAlert('Password inputs do not match.', 'Error', 'error');</script>";
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,20}$/', $password)) {
        echo "<script>showAlert('Password must contain letters, numbers, and be 8-20 characters long.', 'Error', 'error');</script>";
    } else {
        $hashed_password = md5($password);

        // Insert user into the database using username only (no account number)
        $sql = "INSERT INTO users (first_name, last_name, username, password, role) 
                VALUES ('$first_name', '$last_name', '$username', '$hashed_password', '$role')";
        if ($conn->query($sql) === TRUE) {
            echo "<script>showAlert('Registration successful!', 'Success', 'success').then(function() { window.location='login.php'; });</script>";
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Registration Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            width: 400px;
            background-color: #fff;
            padding: 60px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        input[type="submit"] {
            background-color: #0D92F4;
            color: white;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #77CDFF;
        }
        .error {
            color: red;
            margin-top: -10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Registration</h2>
        <form method="POST" action="">
            First Name: <input type="text" name="first_name" required><br>
            Last Name: <input type="text" name="last_name" required><br>
            Username: <input type="text" name="username" required><br>
            Password: <input type="password" name="password" required><br>
            Confirm Password: <input type="password" name="confirm_password" required><br>
            Role: 
            <select name="role">
                <option value="Admin">Admin</option>
                <option value="Employee">Employee</option>
            </select><br><br>
            <input type="submit" value="Register">
        </form>
        <p class="mt-3">Already have an account? <a href="login.php">Login here</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function showAlert(message, title = 'Information', icon = 'info') {
            return Swal.fire({
                icon: icon,
                title: title,
                text: message,
                confirmButtonText: 'OK',
                confirmButtonColor: '#0d6efd'
            });
        }
    </script>
</body>
</html>
