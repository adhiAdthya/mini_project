<?php
session_start();
include 'db_connect.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM Users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'manager') header("Location: manager_dashboard.php");
            elseif ($user['role'] == 'mechanic') header("Location: mechanic_dashboard.php");
            elseif ($user['role'] == 'customer') header("Location: customer_dashboard.php");
            elseif ($user['role'] == 'supervisor') header("Location: supervisor_dashboard.php");
            exit();
        } else $message = "Invalid password.";
    } else $message = "No account found with this email.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Garage Management</title>
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
            background: url('https://images.unsplash.com/photo-1571607382369-f2c7a4d12791?auto=format&fit=crop&w=1200&q=80') no-repeat center center/cover;
            overflow: hidden;
        }
        .login-box {
            background: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 350px;
            position: relative;
            z-index: 2;
        }
        .login-box h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        input[type="email"], input[type="password"], input[type="submit"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 16px;
        }
        input[type="submit"] {
            background: #3498db;
            color: white;
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
            color: red;
            margin-top: 10px;
            text-align: center;
        }
        .signup-link {
            text-align: center;
            margin-top: 15px;
        }
        .signup-link a {
            color: #e5b840;
            text-decoration: none;
            font-weight: bold;
        }

        /* Moving Boxes Animation */
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
    </style>
</head>
<body>
    <!-- Moving Boxes -->
    <div class="box"></div>
    <div class="box"></div>
    <div class="box"></div>

    <div class="login-box">
        <h2>Login</h2>
        <form method="post">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="submit" value="Login">
        </form>
        <div class="message"><?php echo $message; ?></div>
        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign Up</a>
        </div>
    </div>
</body>
</html>