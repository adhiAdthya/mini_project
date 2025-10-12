<?php
require_once __DIR__ . '/../auth.php';
require_role('supervisor');
$user = current_user();
$pdo = Database::connection();

// Simple stats
$totAppointments = (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$pendingAppointments = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('new','approved')")->fetchColumn();
$assignedWO = (int)$pdo->query("SELECT COUNT(*) FROM work_orders")->fetchColumn();
$activeMechanics = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='mechanic' AND u.is_active=1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Supervisor Dashboard - AutoCare Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/theme.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:1100px;margin:0 auto;padding:16px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px}
.kpi{font-size:28px;font-weight:700;color:#111}
.label{color:#6b7280}
.btn{display:inline-block;background:#3498db;color:#fff;border:none;padding:10px 14px;border-radius:8px;text-decoration:none;margin-right:8px}
.link{color:#3498db;text-decoration:none}
</style>
</head>
<body>
    <div class="header">
        <div>AutoCare Garage - Supervisor</div>
        <div>
            Hello, <?= htmlspecialchars($user['name']) ?> |
            <a class="link" href="appointments.php">Appointments</a> |
            <a class="link" href="mechanics.php">Manage Mechanics</a> |
            <a class="link" href="../logout.php">Logout</a>
        </div>
    </div>
    <div class="container">
        <div class="grid">
            <div class="card"><div class="kpi"><?= $totAppointments ?></div><div class="label">Total Appointments</div></div>
            <div class="card"><div class="kpi"><?= $pendingAppointments ?></div><div class="label">Pending/Approved</div></div>
            <div class="card"><div class="kpi"><?= $assignedWO ?></div><div class="label">Work Orders</div></div>
            <div class="card"><div class="kpi"><?= $activeMechanics ?></div><div class="label">Active Mechanics</div></div>
        </div>
        <div class="card" style="margin-top:16px;">
            <h3>Quick Actions</h3>
            <p>
                <a class="btn" href="appointments.php"><i class="fa fa-calendar-check"></i> View Appointments</a>
                <a class="btn" href="mechanics.php"><i class="fa fa-users"></i> Manage Mechanics</a>
            </p>
        </div>
        <p><a class="link" href="../index.php">Home</a></p>
    </div>
</body>
</html>
