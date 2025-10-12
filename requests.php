<?php
require_once __DIR__ . '/../auth.php';
require_role('manager');
$user = current_user();
$pdo = Database::connection();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $req_id = (int)($_POST['id'] ?? 0);
        if ($req_id > 0) {
            if ($action === 'approve') {
                try {
                    $pdo->beginTransaction();
                    // Load request
                    $stmt = $pdo->prepare('SELECT * FROM spare_part_requests WHERE id=? FOR UPDATE');
                    $stmt->execute([$req_id]);
                    $req = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$req) throw new Exception('Request not found.');
                    if ($req['status'] !== 'pending') throw new Exception('Request already processed.');

                    // Check stock
                    $stmt = $pdo->prepare('SELECT * FROM parts WHERE id=? FOR UPDATE');
                    $stmt->execute([(int)$req['part_id']]);
                    $part = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$part) throw new Exception('Part not found.');
                    $qty = (int)$req['qty'];
                    if ((int)$part['stock_qty'] < $qty) throw new Exception('Insufficient stock.');

                    // Deduct stock
                    $stmt = $pdo->prepare('UPDATE parts SET stock_qty = stock_qty - ? WHERE id=?');
                    $stmt->execute([$qty, (int)$req['part_id']]);
                    // Log movement
                    $stmt = $pdo->prepare('INSERT INTO stock_movements (part_id, delta_qty, reason, reference_id, created_by) VALUES (?,?,?,?,?)');
                    $stmt->execute([(int)$req['part_id'], -$qty, 'request_approval', $req_id, $user['id']]);
                    // Update request
                    $stmt = $pdo->prepare("UPDATE spare_part_requests SET status='fulfilled', manager_id=?, approved_qty=? WHERE id=?");
                    $stmt->execute([$user['id'], $qty, $req_id]);

                    $pdo->commit();
                    $success = 'Request approved and stock updated.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Approval failed: ' . $e->getMessage();
                }
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE spare_part_requests SET status='rejected', manager_id=? WHERE id=? AND status='pending'");
                $stmt->execute([$user['id'], $req_id]);
                if ($stmt->rowCount() > 0) {
                    $success = 'Request rejected.';
                } else {
                    $errors[] = 'Reject failed or already processed.';
                }
            }
        }
    }
}

$status = $_GET['status'] ?? 'pending';
$allowed = ['pending','fulfilled','rejected'];
if (!in_array($status, $allowed, true)) $status = 'pending';

$sql = "SELECT r.*, p.name AS part_name, p.sku, u.name AS mech_name, wo.id AS work_order_id
        FROM spare_part_requests r
        JOIN parts p ON p.id=r.part_id
        JOIN users u ON u.id=r.mechanic_id
        JOIN work_orders wo ON wo.id=r.work_order_id
        WHERE r.status=?
        ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$status]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Part Requests - Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
 :root{--primary:#2c3e50;--accent:#3498db;--brand:#e5b840;--muted:#6b7280;--bg:#f5f7fa}
 body{font-family:Segoe UI,Arial,sans-serif;background:var(--bg);margin:0}
 .header{background:var(--primary);color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
 .container{max-width:1100px;margin:0 auto;padding:16px}
 .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px;transition:transform .2s ease, box-shadow .2s ease}
 .card.hoverable:hover{transform:translateY(-6px);box-shadow:0 14px 30px rgba(0,0,0,0.10)}
 .section-title{margin:0 0 8px;color:#111;display:flex;align-items:center;gap:8px}
 .section-title i{color:var(--brand)}
 .subtle{color:var(--muted);margin:0 0 12px}
 .table{width:100%;border-collapse:collapse}
 .table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left}
 .table th{color:#111}
 .form-inline{display:flex;gap:8px;align-items:center}
 .select,.btn{padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px}
 .btn{background:#fff;cursor:pointer;transition:transform .15s ease, box-shadow .15s ease}
 .btn:hover{box-shadow:0 6px 16px rgba(0,0,0,0.06)}
 .btn:active{transform:translateY(1px)}
 .btn.primary{background:var(--accent);color:#fff;border:none}
 .btn.small{padding:8px 10px;font-size:14px}
 .btn.icon i{margin-right:6px}
 .alert{padding:10px;border-radius:8px;margin-bottom:10px}
 .alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
 .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
 .link{color:var(--accent);text-decoration:none}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Manager</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="parts.php">Parts</a> |
        <a class="link" href="invoices.php">Invoices</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card hoverable">
        <h2 class="section-title"><i class="fa fa-list-check"></i> Spare Part Requests</h2>
        <p class="subtle">Approve or reject pending requests from mechanics. Use the status filter to view history.</p>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $e){echo htmlspecialchars($e).'<br>'; } ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="get" class="form-inline" style="margin-bottom:10px;">
            <label>Status:</label>
            <select class="select" name="status" onchange="this.form.submit()">
                <?php foreach(['pending'=>'Pending','fulfilled'=>'Fulfilled','rejected'=>'Rejected'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <table class="table">
            <thead>
                <tr><th>#</th><th>WO</th><th>Mechanic</th><th>Part</th><th>Qty</th><th>Requested At</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($rows): $i=1; foreach($rows as $r): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>#<?= (int)$r['work_order_id'] ?></td>
                    <td><?= htmlspecialchars($r['mech_name']) ?></td>
                    <td><?= htmlspecialchars($r['part_name'] . ($r['sku']?(' ('.$r['sku'].')'):'') ) ?></td>
                    <td><?= (int)$r['qty'] ?></td>
                    <td><?= htmlspecialchars($r['created_at']) ?></td>
                    <td>
                        <?php if ($status==='pending'): ?>
                        <form method="post" class="form-inline" style="display:inline-block;">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn primary small icon" type="submit"><i class="fa fa-check"></i> Approve</button>
                        </form>
                        <form method="post" class="form-inline" style="display:inline-block;margin-left:8px;">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="btn small icon" type="submit"><i class="fa fa-xmark"></i> Reject</button>
                        </form>
                        <?php else: ?>
                            <em>No actions</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7">No requests.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>
