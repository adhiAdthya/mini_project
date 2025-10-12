<?php
require_once __DIR__ . '/../auth.php';
require_role('customer');
$user = current_user();
$pdo = Database::connection();

// Ensure a customer profile exists for this user
$stmt = $pdo->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    $stmt = $pdo->prepare('INSERT INTO customers (user_id, phone, address) VALUES (?, NULL, NULL)');
    $stmt->execute([$user['id']]);
    $customer = ['id' => (int)$pdo->lastInsertId()];
}
$customerId = (int)$customer['id'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $vin = trim($_POST['vin'] ?? '');
        $plate = trim($_POST['license_plate'] ?? '');
        $make = trim($_POST['make'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $year = (int)($_POST['year'] ?? 0);
        if ($make === '' || $model === '' || $year <= 0) {
            $errors[] = 'Make, model, and valid year are required.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO vehicles (customer_id, vin, license_plate, make, model, year) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$customerId, $vin ?: null, $plate ?: null, $make, $model, $year]);
            header('Location: vehicles.php');
            exit;
        }
    }
}

// Fetch vehicles for this customer
$stmt = $pdo->prepare('SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC');
$stmt->execute([$customerId]);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Vehicles - AutoCare Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/theme.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:1000px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left}
.form-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px}
.input{padding:10px;border:1px solid #cbd5e1;border-radius:8px;flex:1}
.btn{background:#3498db;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
.link{color:#3498db;text-decoration:none}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - My Vehicles</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>Add Vehicle</h2>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="form-row">
                <input class="input" type="text" name="vin" placeholder="VIN (optional)">
                <input class="input" type="text" name="license_plate" placeholder="License Plate (optional)">
            </div>
            <div class="form-row">
                <input class="input" type="text" name="make" placeholder="Make" required>
                <input class="input" type="text" name="model" placeholder="Model" required>
                <input class="input" type="number" name="year" placeholder="Year" min="1900" max="2100" required>
            </div>
            <button class="btn" type="submit"><i class="fa fa-plus"></i> Add Vehicle</button>
        </form>
    </div>

    <div class="card">
        <h2>Your Vehicles</h2>
        <?php if ($vehicles): ?>
            <table class="table">
                <thead>
                    <tr><th>Make</th><th>Model</th><th>Year</th><th>VIN</th><th>Plate</th></tr>
                </thead>
                <tbody>
                <?php foreach ($vehicles as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['make']) ?></td>
                        <td><?= htmlspecialchars($v['model']) ?></td>
                        <td><?= (int)$v['year'] ?></td>
                        <td><?= htmlspecialchars($v['vin'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($v['license_plate'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No vehicles yet. Add your first vehicle above.</p>
        <?php endif; ?>
    </div>

    <p><a class="link" href="book_appointment.php"><i class="fa fa-calendar-plus"></i> Book an appointment</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>
