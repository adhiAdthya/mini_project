<?php
require_once __DIR__ . '/../auth.php';
require_role('customer');
$user = current_user();
$pdo = Database::connection();

// Get or create customer profile
$stmt = $pdo->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$c) {
    $stmt = $pdo->prepare('INSERT INTO customers (user_id, phone, address) VALUES (?, NULL, NULL)');
    $stmt->execute([$user['id']]);
    $customerId = (int)$pdo->lastInsertId();
} else {
    $customerId = (int)$c['id'];
}

// Fetch vehicles of this customer
$stmt = $pdo->prepare('SELECT id, make, model, year, license_plate FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC');
$stmt->execute([$customerId]);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch service types
$services = $pdo->query('SELECT id, name FROM service_types ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $service_type_id = (int)($_POST['service_type_id'] ?? 0);
        $preferred_date = trim($_POST['preferred_date'] ?? '');

        if ($vehicle_id <= 0 || $service_type_id <= 0 || $preferred_date === '') {
            $errors[] = 'Please select a vehicle, service type and a preferred date/time.';
        } else {
            // Ensure vehicle belongs to this customer
            $stmt = $pdo->prepare('SELECT 1 FROM vehicles WHERE id = ? AND customer_id = ?');
            $stmt->execute([$vehicle_id, $customerId]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid vehicle selection.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO appointments (customer_id, vehicle_id, service_type_id, preferred_date, status) VALUES (?,?,?,?,"new")');
            $stmt->execute([$customerId, $vehicle_id, $service_type_id, $preferred_date]);
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Appointment - AutoCare Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/theme.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:800px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.form-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px}
.select,.input{padding:10px;border:1px solid #cbd5e1;border-radius:8px;flex:1}
.btn{background:#3498db;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
.link{color:#3498db;text-decoration:none}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
.notice{background:#eff6ff;color:#1e3a8a;border:1px solid #bfdbfe;padding:10px;border-radius:8px;margin-bottom:12px}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Book Appointment</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>New Appointment</h2>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if (!$vehicles): ?>
            <div class="notice">You need to add a vehicle before booking an appointment. Go to <a class="link" href="vehicles.php">My Vehicles</a>.</div>
        <?php endif; ?>
        <?php if (!$services): ?>
            <div class="notice">No service types are configured yet. Please add entries into the <code>service_types</code> table (e.g., Oil Change, Brake Repair) via phpMyAdmin.</div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="form-row">
                <label style="flex-basis:100%">Select Vehicle</label>
                <select class="select" name="vehicle_id" required <?= !$vehicles?'disabled':'' ?>>
                    <option value="">-- Choose Vehicle --</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['make'].' '.$v['model'].' '.$v['year'].' '.($v['license_plate']?('('.$v['license_plate'].')'):'') ) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label style="flex-basis:100%">Service Type</label>
                <select class="select" name="service_type_id" required <?= !$services?'disabled':'' ?>>
                    <option value="">-- Choose Service --</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label style="flex-basis:100%">Preferred Date & Time</label>
                <input class="input" type="datetime-local" name="preferred_date" required>
            </div>
            <button class="btn" type="submit" <?= (!$vehicles||!$services)?'disabled':'' ?>><i class="fa fa-calendar-plus"></i> Book Appointment</button>
        </form>
    </div>
    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>
