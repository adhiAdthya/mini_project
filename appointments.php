<?php
require_once __DIR__ . '/../auth.php';
require_role('supervisor');
$user = current_user();
$pdo = Database::connection();

$errors = [];
$success = null;

// Handle actions: approve appointment, assign mechanic (create work order)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'approve') {
            $appointment_id = (int)($_POST['appointment_id'] ?? 0);
            if ($appointment_id > 0) {
                $stmt = $pdo->prepare("UPDATE appointments SET status='approved' WHERE id=? AND status='new'");
                $stmt->execute([$appointment_id]);
                if ($stmt->rowCount() > 0) {
                    $success = 'Appointment approved.';
                } else {
                    $errors[] = 'Unable to approve (maybe already approved or not found).';
                }
            }
        } elseif ($action === 'assign') {
            $appointment_id = (int)($_POST['appointment_id'] ?? 0);
            $mechanic_id = (int)($_POST['mechanic_id'] ?? 0);
            if ($appointment_id > 0 && $mechanic_id > 0) {
                try {
                    $pdo->beginTransaction();
                    // Ensure mechanic is valid and active
                    $stmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='mechanic' AND u.id=? AND u.is_active=1");
                    $stmt->execute([$mechanic_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Invalid mechanic.');
                    }
                    // Ensure appointment exists
                    $stmt = $pdo->prepare('SELECT id FROM appointments WHERE id=?');
                    $stmt->execute([$appointment_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Appointment not found.');
                    }
                    // Create work order if not exists for this appointment
                    $stmt = $pdo->prepare('SELECT id FROM work_orders WHERE appointment_id=?');
                    $stmt->execute([$appointment_id]);
                    $wo = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($wo) {
                        // Update assignment
                        $stmt = $pdo->prepare("UPDATE work_orders SET mechanic_id=?, status='new' WHERE id=?");
                        $stmt->execute([$mechanic_id, (int)$wo['id']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO work_orders (appointment_id, supervisor_id, mechanic_id, status, started_at, completed_at, notes) VALUES (?,?,?,?,NULL,NULL,NULL)");
                        $stmt->execute([$appointment_id, $user['id'], $mechanic_id, 'new']);
                    }
                    // Update appointment status to assigned
                    $stmt = $pdo->prepare("UPDATE appointments SET status='assigned' WHERE id=?");
                    $stmt->execute([$appointment_id]);
                    $pdo->commit();
                    $success = 'Mechanic assigned and work order updated.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Assignment failed. Please try again.';
                }
            }
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$allowed = ['', 'new', 'approved', 'assigned', 'in_progress', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowed, true)) $statusFilter = '';

$sql = "SELECT a.*, c.id AS customer_id, u.name AS customer_name, v.make, v.model, v.year, v.license_plate, s.name AS service_name
        FROM appointments a
        JOIN customers c ON c.id=a.customer_id
        LEFT JOIN users u ON u.id=c.user_id
        JOIN vehicles v ON v.id=a.vehicle_id
        JOIN service_types s ON s.id=a.service_type_id";
$params = [];
if ($statusFilter !== '') {
    $sql .= " WHERE a.status=?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mechanics list for assignment
$mechanics = $pdo->query("SELECT u.id, u.name, u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='mechanic' AND u.is_active=1 ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments - Supervisor</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/theme.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:1200px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left;vertical-align:top}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
.badge.new{background:#e0f2fe;color:#0369a1}
.badge.approved{background:#ecfccb;color:#3f6212}
.badge.assigned{background:#fef9c3;color:#a16207}
.badge.in_progress{background:#ddd6fe;color:#5b21b6}
.badge.completed{background:#dcfce7;color:#166534}
.badge.cancelled{background:#fee2e2;color:#991b1b}
.form-inline{display:flex;gap:8px;align-items:center}
.select,.btn{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px}
.btn.primary{background:#3498db;color:#fff;border:none}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
.alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.link{color:#3498db;text-decoration:none}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Supervisor</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="mechanics.php">Manage Mechanics</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>Appointments</h2>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $e){echo htmlspecialchars($e).'<br>'; } ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="get" class="form-inline" style="margin-bottom:10px;">
            <label>Status:</label>
            <select class="select" name="status" onchange="this.form.submit()">
                <?php foreach([''=>'All','new'=>'New','approved'=>'Approved','assigned'=>'Assigned','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $statusFilter===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Vehicle</th>
                    <th>Service</th>
                    <th>Preferred Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($appointments): $i=1; foreach ($appointments as $a): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($a['customer_name'] ?: ('Customer#'.$a['customer_id'])) ?></td>
                    <td><?= htmlspecialchars($a['make'].' '.$a['model'].' '.$a['year'].' '.($a['license_plate']?('('.$a['license_plate'].')'):'') ) ?></td>
                    <td><?= htmlspecialchars($a['service_name']) ?></td>
                    <td><?= htmlspecialchars($a['preferred_date']) ?></td>
                    <td><span class="badge <?= str_replace('-','_', $a['status']) ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ', $a['status']))) ?></span></td>
                    <td>
                        <div style="display:flex;gap:8px;flex-direction:column;">
                            <?php if ($a['status'] === 'new'): ?>
                                <form method="post" class="form-inline">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>">
                                    <button class="btn primary" type="submit">Approve</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="form-inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>">
                                <select class="select" name="mechanic_id" required>
                                    <option value="">Assign Mechanic...</option>
                                    <?php foreach ($mechanics as $m): ?>
                                        <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['name'].' ('.$m['email'].')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn primary" type="submit">Assign</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7">No appointments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>
