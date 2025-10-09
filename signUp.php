<?php
include 'db_connect.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $phone = $_POST['phone'];

    $sql = "INSERT INTO Users (name, email, password, role, phone) VALUES ('$name','$email','$password','$role','$phone')";

    if ($conn->query($sql) === TRUE) {
        $message = "User registered successfully! <a href='login.php'>Login here</a>";
    } else {
        $message = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sign Up - Garage Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('https://images.unsplash.com/photo-1596125365740-0a5db438d42b?auto=format&fit=crop&w=1200&q=80') no-repeat center center/cover;
            overflow: hidden;
        }
        .signup-box {
            background: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 400px;
            position: relative;
            z-index: 2;
        }
        .signup-box h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        input, select {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 16px;
        }
        input[type="submit"] {
            background: #e5b840;
            color: #2c3e50;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }
        input[type="submit"]:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        .message {
            padding: 10px;
            background: #e5f3e5;
            color: #2d6b2d;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .login-link {
            text-align: center;
            margin-top: 15px;
        }
        .login-link a {
            color: #3498db;
            font-weight: bold;
            text-decoration: none;
        }

        /* Animated Floating Boxes */
        .box {
            position: absolute;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.2);
            border-radius: 15px;
            animation: moveBox 20s linear infinite;
        }
        .box:nth-child(1) { top: 10%; left: 5%; animation-duration: 18s; }
        .box:nth-child(2) { top: 70%; left: 80%; animation-duration: 25s; }
        .box:nth-child(3) { top: 50%; left: 30%; animation-duration: 22s; }
        @keyframes moveBox {
            0% { transform: translate(0,0) rotate(0deg); }
            50% { transform: translate(200px, 150px) rotate(180deg); }
            100% { transform: translate(0,0) rotate(360deg); }
        }

        @media(max-width:768px){
            .signup-box { width: 90%; padding: 20px; }
        }
    </style>
</head>
<body>
    <!-- Floating Animated Boxes -->
    <div class="box"></div>
    <div class="box"></div>
    <div class="box"></div>

    <div class="signup-box">
        <h2>Sign Up</h2>
        <form method="post">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="">Select Role</option>
                <option value="customer">Customer</option>
                <option value="mechanic">Mechanic</option>
                <option value="manager">Manager</option>
                <option value="supervisor">Supervisor</option>
            </select>
            <input type="text" name="phone" placeholder="Phone Number">
            <input type="submit" value="Register">
        </form>
        <?php if($message) echo "<div class='message'>$message</div>"; ?>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
