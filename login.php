<?php
session_start();
require_once __DIR__ . '/db_connect.php';

$errors = [];
$success = null;

// Show success notice if redirected after registration
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = 'Your account has been created. Please log in to continue.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $errors[] = 'Email and password are required.';
        } else {
            $stmt = $pdo->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid credentials or inactive account.';
            } else {
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role_name'],
                ];
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - AutoCare Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.08);padding:24px;max-width:420px;width:100%}
.h{margin:0 0 10px;color:#2c3e50}
.p{color:#7f8c8d;margin:0 0 18px}
.form-row{display:flex;flex-direction:column;margin-bottom:12px}
.input{padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px}
.btn{background:#3498db;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
.btn:hover{opacity:.95}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
.alert-success{background:#dcfce7;color:#14532d;border:1px solid #bbf7d0}
.link{display:inline-block;margin-top:10px;color:#3498db;text-decoration:none}
.link:hover{text-decoration:underline}
</style>
</head>
<body>
    <div class="card">
        <h2 class="h">Login</h2>
        <p class="p">Welcome back. Enter your credentials to continue.</p>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <form method="post" action="">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="form-row">
                <label>Email</label>
                <input class="input" type="email" name="email" required>
            </div>
            <div class="form-row">
                <label>Password</label>
                <input class="input" type="password" name="password" required>
            </div>
            <div class="form-row">
                <button class="btn" type="submit"><i class="fa fa-sign-in-alt"></i> Login</button>
            </div>
        </form>
        <a class="link" href="signup.php">Don't have an account? Sign up</a>
        <br>
        <a class="link" href="index.php">Back to Home</a>
    </div>
</body>
</html>
