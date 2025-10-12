<?php
require_once __DIR__ . '/../auth.php';
require_role('mechanic');
$user = current_user();
$pdo = Database::connection();
// Default tax rate to apply on auto-generated invoices (e.g., 18%)
$DEFAULT_TAX_RATE = 0.18;

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_status') {
            $wo_id = (int)($_POST['work_order_id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            if ($wo_id > 0 && in_array($new_status, ['in_progress','on_hold','completed'], true)) {
                try {
                    $pdo->beginTransaction();
                    // Verify ownership
                    $stmt = $pdo->prepare('SELECT status FROM work_orders WHERE id=? AND mechanic_id=? FOR UPDATE');
                    $stmt->execute([$wo_id, $user['id']]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) throw new Exception('Work order not found.');

                    // Update status and timestamps
                    $startedAt = null; $completedAt = null;
                    if ($new_status === 'in_progress') { $startedAt = date('Y-m-d H:i:s'); }
                    if ($new_status === 'completed') { $completedAt = date('Y-m-d H:i:s'); }

                    $stmt = $pdo->prepare('UPDATE work_orders SET status=?, started_at=COALESCE(started_at, ?), completed_at=IF(? IS NOT NULL, ?, completed_at) WHERE id=?');
                    $stmt->execute([$new_status, $startedAt, $completedAt, $completedAt, $wo_id]);

                    // History
                    $stmt = $pdo->prepare('INSERT INTO work_order_status_history (work_order_id, status, changed_by) VALUES (?,?,?)');
                    $stmt->execute([$wo_id, $new_status, $user['id']]);

                    $pdo->commit();
                    $success = 'Status updated.';

                    // Auto-generate invoice when WO is completed
                    if ($new_status === 'completed') {
                        // If not already invoiced, create invoice and auto-populate items
                        $chk = $pdo->prepare('SELECT id FROM invoices WHERE work_order_id=?');
                        $chk->execute([$wo_id]);
                        if (!$chk->fetch()) {
                            try {
                                $pdo->beginTransaction();
                                // Create invoice shell
                                $number = 'INV-' . date('Ymd-His') . '-' . $wo_id;
                                $insInv = $pdo->prepare("INSERT INTO invoices (work_order_id, number, subtotal, tax, discount, total, status, issued_at) VALUES (?,?,?,?,?,?, 'draft', NULL)");
                                $insInv->execute([$wo_id, $number, 0, 0, 0, 0]);
                                $invoice_id = (int)$pdo->lastInsertId();

                                // Labor from service type default rate
                                $svc = $pdo->prepare("SELECT s.name AS service_name, s.default_rate
                                    FROM work_orders wo
                                    JOIN appointments a ON a.id=wo.appointment_id
                                    JOIN service_types s ON s.id=a.service_type_id
                                    WHERE wo.id=? LIMIT 1");
                                $svc->execute([$wo_id]);
                                $svcRow = $svc->fetch(PDO::FETCH_ASSOC);
                                if ($svcRow && (float)$svcRow['default_rate'] > 0) {
                                    $desc = 'Service - ' . $svcRow['service_name'];
                                    $pdo->prepare('INSERT INTO invoice_items (invoice_id, type, reference_id, description, qty, unit_price) VALUES (?,?,?,?,?,?)')
                                        ->execute([$invoice_id, 'labor', null, $desc, 1, (float)$svcRow['default_rate']]);
                                }

                                // Fulfilled part requests
                                $partsStmt = $pdo->prepare("SELECT r.id, r.part_id, r.qty, p.name, p.sku, p.sale_price
                                    FROM spare_part_requests r JOIN parts p ON p.id=r.part_id
                                    WHERE r.work_order_id=? AND r.status='fulfilled' ORDER BY r.id");
                                $partsStmt->execute([$wo_id]);
                                foreach ($partsStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                                    $desc = $pr['name'] . ($pr['sku'] ? (' (' . $pr['sku'] . ')') : '');
                                    $qty = (float)$pr['qty'];
                                    $price = (float)$pr['sale_price'];
                                    if ($qty > 0) {
                                        $pdo->prepare('INSERT INTO invoice_items (invoice_id, type, reference_id, description, qty, unit_price) VALUES (?,?,?,?,?,?)')
                                            ->execute([$invoice_id, 'part', (int)$pr['part_id'], $desc, $qty, $price]);
                                    }
                                }

                                // Recalculate totals
                                $sum = $pdo->prepare('SELECT COALESCE(SUM(qty*unit_price),0) AS subtotal FROM invoice_items WHERE invoice_id=?');
                                $sum->execute([$invoice_id]);
                                $subtotal = (float)$sum->fetch(PDO::FETCH_ASSOC)['subtotal'];
                                $tax = round($subtotal * $DEFAULT_TAX_RATE, 2);
                                $discount = 0.0;
                                $total = max(0, $subtotal + $tax - $discount);
                                // Apply totals and auto-issue the invoice
                                $pdo->prepare("UPDATE invoices SET subtotal=?, tax=?, discount=?, total=?, status='issued', issued_at=NOW() WHERE id=?")
                                    ->execute([$subtotal, $tax, $discount, $total, $invoice_id]);

                                $pdo->commit();
                                // Append info to success message for visibility
                                $success .= ' Invoice drafted automatically.';
                            } catch (Throwable $ie) {
                                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                                // Do not hard fail the mechanic action; log or swallow silently in UI
                            }
                        }
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Failed to update status.';
                }
            }
        } elseif ($action === 'request_part') {
            $wo_id = (int)($_POST['work_order_id'] ?? 0);
            $part_id = (int)($_POST['part_id'] ?? 0);
            $qty = max(1, (int)($_POST['qty'] ?? 1));
            if ($wo_id > 0 && $part_id > 0) {
                try {
                    // Verify this WO belongs to mechanic
                    $stmt = $pdo->prepare('SELECT 1 FROM work_orders WHERE id=? AND mechanic_id=?');
                    $stmt->execute([$wo_id, $user['id']]);
                    if (!$stmt->fetch()) throw new Exception('Invalid work order.');

                    $stmt = $pdo->prepare('INSERT INTO spare_part_requests (work_order_id, mechanic_id, part_id, qty, status) VALUES (?,?,?,?,"pending")');
                    $stmt->execute([$wo_id, $user['id'], $part_id, $qty]);
                    $success = 'Spare part request submitted.';
                } catch (Throwable $e) {
                    $errors[] = 'Failed to submit part request.';
                }
            }
        }
    }
}

// Fetch assigned work orders
$sql = "SELECT wo.*, a.preferred_date, s.name AS service_name, v.make, v.model, v.year, v.license_plate,
               custu.name AS customer_name
        FROM work_orders wo
        JOIN appointments a ON a.id=wo.appointment_id
        JOIN service_types s ON s.id=a.service_type_id
        JOIN vehicles v ON v.id=a.vehicle_id
        LEFT JOIN customers c ON c.id=a.customer_id
        LEFT JOIN users custu ON custu.id=c.user_id
        WHERE wo.mechanic_id=?
        ORDER BY FIELD(wo.status,'new','in_progress','on_hold','completed','billed'), wo.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parts for selection
$parts = $pdo->query('SELECT id, name, sku, stock_qty FROM parts ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Jobs - Mechanic</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:1200px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #eee;padding:10px;vertical-align:top;text-align:left}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
.badge.new{background:#e0f2fe;color:#0369a1}
.badge.in_progress{background:#ddd6fe;color:#5b21b6}
.badge.on_hold{background:#fee2e2;color:#991b1b}
.badge.completed{background:#dcfce7;color:#166534}
.form-inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.select,.input,.btn{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px}
.btn.primary{background:#3498db;color:#fff;border:none}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
.alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.link{color:#3498db;text-decoration:none}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Mechanic</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>My Assigned Jobs</h2>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $e){echo htmlspecialchars($e).'<br>'; } ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
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
            <?php if ($jobs): $i=1; foreach($jobs as $job): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($job['customer_name'] ?: 'Walk-in') ?></td>
                    <td><?= htmlspecialchars($job['make'].' '.$job['model'].' '.$job['year'].' '.($job['license_plate']?('('.$job['license_plate'].')'):'') ) ?></td>
                    <td><?= htmlspecialchars($job['service_name']) ?></td>
                    <td><?= htmlspecialchars($job['preferred_date']) ?></td>
                    <td><span class="badge <?= str_replace('-','_', $job['status']) ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ', $job['status']))) ?></span></td>
                    <td>
                        <div style="display:flex;gap:10px;flex-direction:column;">
                            <form method="post" class="form-inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="work_order_id" value="<?= (int)$job['id'] ?>">
                                <select class="select" name="status" required>
                                    <option value="">Set Status...</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="completed">Completed</option>
                                </select>
                                <button class="btn primary" type="submit">Update</button>
                            </form>
                            <form method="post" class="form-inline">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="request_part">
                                <input type="hidden" name="work_order_id" value="<?= (int)$job['id'] ?>">
                                <select class="select" name="part_id" required>
                                    <option value="">Request Part...</option>
                                    <?php foreach ($parts as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name'].($p['sku']?' ('.$p['sku'].')':'').' - Stock: '.$p['stock_qty']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="input" type="number" name="qty" min="1" value="1" style="width:90px">
                                <button class="btn" type="submit">Request</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7">No jobs assigned yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>
