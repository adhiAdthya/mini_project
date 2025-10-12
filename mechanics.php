<?php
require_once __DIR__ . '/../auth.php';
require_role('supervisor');
$user = current_user();
$pdo = Database::connection();

$errors = [];
$success = null;

// Helpers
function get_mechanic_role_id(PDO $pdo) {
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name='mechanic' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '' || $email === '' || $password === '') {
                $errors[] = 'All fields are required.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            }
            if (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
            if (!$errors) {
                try {
                    $pdo->beginTransaction();
                    $roleId = get_mechanic_role_id($pdo);
                    if (!$roleId) throw new Exception('Mechanic role not found.');
                    // Check existing email
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) throw new Exception('Email already exists.');
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare('INSERT INTO users (name,email,password,role_id,is_active) VALUES (?,?,?,?,?)');
                    $stmt->execute([$name, $email, $hash, $roleId, $active]);
                    $pdo->commit();
                    $success = 'Mechanic created successfully.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Failed to create mechanic.';
                }
            }
        } elseif (in_array($action, ['activate','deactivate'], true)) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $is_active = $action === 'activate' ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE users SET is_active=? WHERE id=? AND role_id=(SELECT id FROM roles WHERE name='mechanic')");
                $stmt->execute([$is_active, $id]);
                if ($stmt->rowCount() > 0) {
                    $success = $is_active ? 'Mechanic activated.' : 'Mechanic deactivated.';
                } else {
                    $errors[] = 'Update failed.';
                }
            }
        }
    }
}

// Fetch mechanics
$mechanics = $pdo->query("SELECT u.id, u.name, u.email, u.is_active, u.created_at FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='mechanic' ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Mechanics - Supervisor</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:1100px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.form-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px}
.input{padding:10px;border:1px solid #cbd5e1;border-radius:8px;flex:1}
.checkbox{margin-top:10px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
.badge.active{background:#dcfce7;color:#166534}
.badge.inactive{background:#fee2e2;color:#991b1b}
.btn{padding:8px 10px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;cursor:pointer}
.btn.primary{background:#3498db;color:#fff;border:none}
.link{color:#3498db;text-decoration:none}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
.alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Supervisor</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="appointments.php">Appointments</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>Create Mechanic</h2>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $e){echo htmlspecialchars($e).'<br>'; } ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <input class="input" type="text" name="name" placeholder="Full Name" required>
                <input class="input" type="email" name="email" placeholder="Email" required>
                <input class="input" type="password" name="password" placeholder="Temp Password" required>
            </div>
            <label class="checkbox"><input type="checkbox" name="is_active" checked> Active</label>
            <div style="margin-top:10px;"><button class="btn primary" type="submit"><i class="fa fa-user-plus"></i> Create</button></div>
        </form>
    </div>

    <div class="card">
        <h2>Mechanics</h2>
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($mechanics): foreach($mechanics as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['name']) ?></td>
                    <td><?= htmlspecialchars($m['email']) ?></td>
                    <td>
                        <?php if ((int)$m['is_active'] === 1): ?>
                            <span class="badge active">Active</span>
                        <?php else: ?>
                            <span class="badge inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline-block">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <?php if ((int)$m['is_active'] === 1): ?>
                                <input type="hidden" name="action" value="deactivate">
                                <button class="btn" type="submit">Deactivate</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="activate">
                                <button class="btn" type="submit">Activate</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4">No mechanics yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>
