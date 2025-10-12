<?php
session_start();
require_once __DIR__ . '/db_connect.php';

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        if ($name === '' || $email === '' || $password === '' || $password2 === '') {
            $errors[] = 'All fields are required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }
        if ($password !== $password2) {
            $errors[] = 'Passwords do not match.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if (!$errors) {
            try {
                // Check existing
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    $pdo->beginTransaction();
                    $roleId = role_id('customer');
                    if (!$roleId) {
                        throw new Exception('Customer role not found. Import schema.sql first.');
                    }
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role_id, is_active) VALUES (?,?,?,?,1)');
                    $stmt->execute([$name, $email, $hash, $roleId]);
                    $userId = (int)$pdo->lastInsertId();
                    // Create linked customer row (optional phone/address later)
                    $stmt = $pdo->prepare('INSERT INTO customers (user_id, phone, address) VALUES (?, NULL, NULL)');
                    $stmt->execute([$userId]);
                    $pdo->commit();
                    
                    // Do not auto-login after signup. Redirect to login page with success flag.
                    header('Location: login.php?registered=1');
                    exit;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                // Log the detailed error for diagnostics
                error_log('Signup error: ' . $e->getMessage());
                // Show detailed error only in debug mode
                $msg = 'Registration failed. Please try again.';
                if (function_exists('config') && (bool)config('app.debug', false) === true) {
                    $msg .= ' Details: ' . $e->getMessage();
                }
                $errors[] = $msg;
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
<title>Sign Up - AutoCare Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.08);padding:24px;max-width:480px;width:100%}
.h{margin:0 0 10px;color:#2c3e50}
.p{color:#7f8c8d;margin:0 0 18px}
.form-row{display:flex;flex-direction:column;margin-bottom:12px}
.input{padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px}
.btn{background:#e5b840;color:#2c3e50;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
.btn:hover{opacity:.95}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
.link{display:inline-block;margin-top:10px;color:#3498db;text-decoration:none}
.link:hover{text-decoration:underline}
</style>
</head>
<body>
    <div class="card">
        <h2 class="h">Create Account</h2>
        <p class="p">Sign up to book services and track your vehicle status.</p>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <form method="post" action="">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="form-row">
                <label>Full Name</label>
                <input class="input" type="text" name="name" required value="<?= isset($_POST['name'])?htmlspecialchars($_POST['name']):'' ?>">
            </div>
            <div class="form-row">
                <label>Email</label>
                <input class="input" type="email" name="email" required value="<?= isset($_POST['email'])?htmlspecialchars($_POST['email']):'' ?>">
            </div>
            <div class="form-row">
                <label>Password</label>
                <input class="input" type="password" name="password" required>
            </div>
            <div class="form-row">
                <label>Confirm Password</label>
                <input class="input" type="password" name="password2" required>
            </div>
            <div class="form-row">
                <button class="btn" type="submit"><i class="fa fa-user-plus"></i> Sign Up</button>
            </div>
        </form>
        <a class="link" href="login.php">Already have an account? Login</a>
        <br>
        <a class="link" href="index.php">Back to Home</a>
    </div>
</body>
</html>
