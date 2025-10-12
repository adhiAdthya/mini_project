<?php
require_once __DIR__ . '/../auth.php';
require_role('manager');
$user = current_user();
$pdo = Database::connection();

$errors = [];
$success = null;

function invoice_totals(PDO $pdo, int $invoice_id) {
    $stmt = $pdo->prepare('SELECT SUM(qty*unit_price) AS subtotal FROM invoice_items WHERE invoice_id=?');
    $stmt->execute([$invoice_id]);
    $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['subtotal'] ?? 0);
    $stmt = $pdo->prepare('SELECT tax, discount FROM invoices WHERE id=?');
    $stmt->execute([$invoice_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $tax = (float)($row['tax'] ?? 0);
    $discount = (float)($row['discount'] ?? 0);
    $total = max(0, $subtotal + $tax - $discount);
    return [$subtotal, $tax, $discount, $total];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'create_from_wo') {
                $wo_id = (int)($_POST['work_order_id'] ?? 0);
                if ($wo_id > 0) {
                    // Ensure not already invoiced
                    $stmt = $pdo->prepare('SELECT id FROM invoices WHERE work_order_id=?');
                    $stmt->execute([$wo_id]);
                    if ($stmt->fetch()) throw new Exception('Invoice already exists for this work order.');
                    // Create invoice (draft) and auto-populate items from WO
                    $pdo->beginTransaction();
                    try {
                        // Create base invoice record
                        $number = 'INV-' . date('Ymd-His') . '-' . $wo_id;
                        $stmt = $pdo->prepare("INSERT INTO invoices (work_order_id, number, subtotal, tax, discount, total, status, issued_at) VALUES (?,?,?,?,?,?, 'draft', NULL)");
                        $stmt->execute([$wo_id, $number, 0, 0, 0, 0]);
                        $invoice_id = (int)$pdo->lastInsertId();

                        // Fetch service details for labor line
                        $svc = $pdo->prepare("SELECT s.name AS service_name, s.default_rate
                            FROM work_orders wo
                            JOIN appointments a ON a.id=wo.appointment_id
                            JOIN service_types s ON s.id=a.service_type_id
                            WHERE wo.id=? LIMIT 1");
                        $svc->execute([$wo_id]);
                        $svcRow = $svc->fetch(PDO::FETCH_ASSOC);
                        if ($svcRow && (float)$svcRow['default_rate'] > 0) {
                            $desc = 'Service - ' . $svcRow['service_name'];
                            $ins = $pdo->prepare('INSERT INTO invoice_items (invoice_id, type, reference_id, description, qty, unit_price) VALUES (?,?,?,?,?,?)');
                            $ins->execute([$invoice_id, 'labor', null, $desc, 1, (float)$svcRow['default_rate']]);
                        }

                        // Add fulfilled part requests as part lines
                        $partsStmt = $pdo->prepare("SELECT r.id, r.part_id, r.qty, p.name, p.sku, p.sale_price
                            FROM spare_part_requests r JOIN parts p ON p.id=r.part_id
                            WHERE r.work_order_id=? AND r.status='fulfilled' ORDER BY r.id");
                        $partsStmt->execute([$wo_id]);
                        $addedParts = 0;
                        foreach ($partsStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                            $desc = $pr['name'] . ($pr['sku'] ? (' (' . $pr['sku'] . ')') : '');
                            $qty = (float)$pr['qty'];
                            $price = (float)$pr['sale_price'];
                            if ($qty > 0) {
                                $pdo->prepare('INSERT INTO invoice_items (invoice_id, type, reference_id, description, qty, unit_price) VALUES (?,?,?,?,?,?)')
                                    ->execute([$invoice_id, 'part', (int)$pr['part_id'], $desc, $qty, $price]);
                                $addedParts++;
                            }
                        }

                        // Recalculate and persist totals
                        [$subtotal, $tax, $discount, $total] = invoice_totals($pdo, $invoice_id);
                        $pdo->prepare('UPDATE invoices SET subtotal=?, total=? WHERE id=?')->execute([$subtotal, $total, $invoice_id]);

                        $pdo->commit();
                        $success = 'Invoice created: ' . $number . ' (auto-added ' . ($svcRow && (float)$svcRow['default_rate'] > 0 ? 'labor' : 'no labor') . ' + ' . $addedParts . ' parts)';
                    } catch (Throwable $ie) {
                        if ($pdo->inTransaction()) { $pdo->rollBack(); }
                        throw $ie;
                    }
                }
            } elseif ($action === 'add_item') {
                $invoice_id = (int)($_POST['invoice_id'] ?? 0);
                $type = $_POST['type'] === 'part' ? 'part' : 'labor';
                $description = trim($_POST['description'] ?? '');
                $qty = (float)($_POST['qty'] ?? 1);
                $unit_price = (float)($_POST['unit_price'] ?? 0);
                $reference_id = !empty($_POST['reference_id']) ? (int)$_POST['reference_id'] : null;
                if ($invoice_id && $description !== '' && $qty > 0) {
                    $stmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, type, reference_id, description, qty, unit_price) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$invoice_id, $type, $reference_id, $description, $qty, $unit_price]);
                    // Recalc totals
                    [$subtotal, $tax, $discount, $total] = invoice_totals($pdo, $invoice_id);
                    $stmt = $pdo->prepare('UPDATE invoices SET subtotal=?, total=? WHERE id=?');
                    $stmt->execute([$subtotal, $total, $invoice_id]);
                    $success = 'Item added.';
                }
            } elseif ($action === 'update_tax_discount') {
                $invoice_id = (int)($_POST['invoice_id'] ?? 0);
                $tax = (float)($_POST['tax'] ?? 0);
                $discount = (float)($_POST['discount'] ?? 0);
                if ($invoice_id) {
                    [$subtotal] = invoice_totals($pdo, $invoice_id);
                    $total = max(0, $subtotal + $tax - $discount);
                    $stmt = $pdo->prepare('UPDATE invoices SET tax=?, discount=?, total=? WHERE id=?');
                    $stmt->execute([$tax, $discount, $total, $invoice_id]);
                    $success = 'Totals updated.';
                }
            } elseif ($action === 'issue_invoice') {
                $invoice_id = (int)($_POST['invoice_id'] ?? 0);
                if ($invoice_id) {
                    $stmt = $pdo->prepare("UPDATE invoices SET status='issued', issued_at=NOW() WHERE id=?");
                    $stmt->execute([$invoice_id]);
                    $success = 'Invoice issued.';
                }
            } elseif ($action === 'add_payment') {
                $invoice_id = (int)($_POST['invoice_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $method = trim($_POST['method'] ?? 'cash');
                $txn_ref = trim($_POST['txn_ref'] ?? '');
                if ($invoice_id && $amount > 0) {
                    $stmt = $pdo->prepare('INSERT INTO payments (invoice_id, method, amount, paid_at, txn_ref) VALUES (?,?,?,?,?)');
                    $stmt->execute([$invoice_id, $method, $amount, date('Y-m-d H:i:s'), $txn_ref ?: null]);
                    // If paid >= total, mark as paid
                    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS paid FROM payments WHERE invoice_id=?');
                    $stmt->execute([$invoice_id]);
                    $paid = (float)$stmt->fetch(PDO::FETCH_ASSOC)['paid'];
                    $stmt = $pdo->prepare('SELECT total FROM invoices WHERE id=?');
                    $stmt->execute([$invoice_id]);
                    $total = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    if ($paid + 0.01 >= $total) { // small epsilon
                        $pdo->prepare("UPDATE invoices SET status='paid' WHERE id=?")->execute([$invoice_id]);
                    }
                    $success = 'Payment recorded.';
                }
            } elseif ($action === 'delete_item') {
                $invoice_id = (int)($_POST['invoice_id'] ?? 0);
                $item_id = (int)($_POST['item_id'] ?? 0);
                if ($invoice_id && $item_id) {
                    $stmt = $pdo->prepare('DELETE FROM invoice_items WHERE id=? AND invoice_id=?');
                    $stmt->execute([$item_id, $invoice_id]);
                    [$subtotal, $tax, $discount, $total] = invoice_totals($pdo, $invoice_id);
                    $pdo->prepare('UPDATE invoices SET subtotal=?, total=? WHERE id=?')->execute([$subtotal, $total, $invoice_id]);
                    $success = 'Item removed.';
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'Operation failed. ' . $e->getMessage();
        }
    }
}

// Data sources
$invoices = $pdo->query("SELECT inv.*, wo.appointment_id FROM invoices inv JOIN work_orders wo ON wo.id=inv.work_order_id ORDER BY inv.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Work orders eligible for invoicing: completed and not invoiced yet
$woEligible = $pdo->query("SELECT wo.id, wo.appointment_id, wo.completed_at,
    s.name AS service_name, v.make, v.model, v.year, v.license_plate,
    custu.name AS customer_name
    FROM work_orders wo
    JOIN appointments a ON a.id=wo.appointment_id
    JOIN service_types s ON s.id=a.service_type_id
    JOIN vehicles v ON v.id=a.vehicle_id
    LEFT JOIN customers c ON c.id=a.customer_id
    LEFT JOIN users custu ON custu.id=c.user_id
    WHERE wo.status='completed' AND NOT EXISTS (SELECT 1 FROM invoices inv WHERE inv.work_order_id=wo.id)
    ORDER BY wo.completed_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// For adding part items quickly: fulfilled requests per invoice's WO
function fulfilled_parts_for_wo(PDO $pdo, int $work_order_id) {
    $stmt = $pdo->prepare("SELECT r.id, r.part_id, r.qty, p.name, p.sku, p.sale_price
        FROM spare_part_requests r JOIN parts p ON p.id=r.part_id
        WHERE r.work_order_id=? AND r.status='fulfilled' ORDER BY r.id");
    $stmt->execute([$work_order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoices - Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
 :root{--primary:#2c3e50;--accent:#3498db;--brand:#e5b840;--muted:#6b7280;--bg:#f5f7fa}
 body{font-family:Segoe UI,Arial,sans-serif;background:var(--bg);margin:0}
 .header{background:var(--primary);color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
 .container{max-width:1200px;margin:0 auto;padding:16px}
 .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px;transition:transform .2s ease, box-shadow .2s ease}
 .card.hoverable:hover{transform:translateY(-6px);box-shadow:0 14px 30px rgba(0,0,0,0.10)}
 .section-title{margin:0 0 8px;color:#111;display:flex;align-items:center;gap:8px}
 .section-title i{color:var(--brand)}
 .subtle{color:var(--muted);margin:0 0 12px}
 .table{width:100%;border-collapse:collapse}
 .table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left;vertical-align:top}
 .table th{color:#111}
 .form-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
 .input,.select,.btn{padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px}
 .btn{background:#fff;cursor:pointer;transition:transform .15s ease, box-shadow .15s ease}
 .btn:hover{box-shadow:0 6px 16px rgba(0,0,0,0.06)}
 .btn:active{transform:translateY(1px)}
 .btn.primary{background:var(--accent);color:#fff;border:none}
 .btn.small{padding:8px 10px;font-size:14px}
 .btn.icon i{margin-right:6px}
 .badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
 .badge.draft{background:#e0f2fe;color:#0369a1}
 .badge.issued{background:#fef9c3;color:#a16207}
 .badge.paid{background:#dcfce7;color:#166534}
 .link{color:var(--accent);text-decoration:none}
 .alert{padding:10px;border-radius:8px;margin-bottom:10px}
 .alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
 .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Manager</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="parts.php">Parts</a> |
        <a class="link" href="requests.php">Part Requests</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card hoverable">
        <h2 class="section-title"><i class="fa fa-file-invoice"></i> Create Invoice from Work Order</h2>
        <p class="subtle">Select a completed work order to generate a draft invoice.</p>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $e){echo htmlspecialchars($e).'<br>'; } ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="post" class="form-row">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_from_wo">
            <select class="select" name="work_order_id" required style="min-width:260px;">
                <option value="">Select completed work order...</option>
                <?php foreach ($woEligible as $wo): ?>
                    <option value="<?= (int)$wo['id'] ?>">WO#<?= (int)$wo['id'] ?> - <?= htmlspecialchars($wo['customer_name'] ?: 'Customer') ?> - <?= htmlspecialchars($wo['make'].' '.$wo['model'].' '.$wo['year']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn primary icon" type="submit"><i class="fa fa-plus"></i> Create</button>
        </form>
    </div>

    <div class="card hoverable">
        <h2 class="section-title"><i class="fa fa-file-lines"></i> Invoices</h2>
        <p class="subtle">Manage items, totals, issuance and payments. Use Print to open a printable view.</p>
        <table class="table">
            <thead>
                <tr><th>#</th><th>Number</th><th>WO</th><th>Status</th><th>Subtotal</th><th>Tax</th><th>Discount</th><th>Total</th><th>Issued</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($invoices): foreach ($invoices as $inv): ?>
                <?php [$subtotal, $tax, $discount, $total] = invoice_totals($pdo, (int)$inv['id']); ?>
                <tr>
                    <td>#<?= (int)$inv['id'] ?></td>
                    <td><?= htmlspecialchars($inv['number']) ?></td>
                    <td>#<?= (int)$inv['work_order_id'] ?></td>
                    <td><span class="badge <?= htmlspecialchars($inv['status']) ?>"><?= htmlspecialchars(ucfirst($inv['status'])) ?></span></td>
                    <td><?= number_format($subtotal,2) ?></td>
                    <td><?= number_format($tax,2) ?></td>
                    <td><?= number_format($discount,2) ?></td>
                    <td><?= number_format($total,2) ?></td>
                    <td><?= htmlspecialchars($inv['issued_at'] ?: '-') ?></td>
                    <td>
                        <div style="margin-bottom:6px">
                            <a class="btn small icon" href="invoice_print.php?id=<?= (int)$inv['id'] ?>" target="_blank"><i class="fa fa-print"></i> Print</a>
                        </div>
                        <details>
                            <summary>Manage</summary>
                            <div style="padding:10px 0;">
                                <strong>Items</strong>
                                <form method="post" class="form-row">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="add_item">
                                    <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                    <select class="select" name="type" style="width:120px;">
                                        <option value="labor">Labor</option>
                                        <option value="part">Part</option>
                                    </select>
                                    <input class="input" type="text" name="description" placeholder="Description" required style="flex:1;min-width:200px;">
                                    <input class="input" type="number" step="0.01" name="qty" placeholder="Qty" value="1" style="width:90px;">
                                    <input class="input" type="number" step="0.01" name="unit_price" placeholder="Unit Price" value="0" style="width:120px;">
                                    <input class="input" type="number" name="reference_id" placeholder="Ref ID (optional)" style="width:140px;">
                                    <button class="btn icon" type="submit"><i class="fa fa-plus"></i> Add Item</button>
                                </form>
                                <?php 
                                $items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id');
                                $items->execute([(int)$inv['id']]);
                                $items = $items->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php if ($items): ?>
                                    <table class="table" style="margin-top:8px">
                                        <thead><tr><th>Type</th><th>Description</th><th>Qty</th><th>Price</th><th>Amount</th><th></th></tr></thead>
                                        <tbody>
                                        <?php foreach ($items as $it): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($it['type']) ?></td>
                                                <td><?= htmlspecialchars($it['description']) ?></td>
                                                <td><?= (float)$it['qty'] ?></td>
                                                <td><?= number_format((float)$it['unit_price'],2) ?></td>
                                                <td><?= number_format((float)$it['qty'] * (float)$it['unit_price'],2) ?></td>
                                                <td>
                                                    <form method="post" style="display:inline-block;">
                                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="delete_item">
                                                        <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                                        <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                                                        <button class="btn small icon" type="submit"><i class="fa fa-trash"></i> Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p>No items yet. You can also reference fulfilled spare part requests for WO#<?= (int)$inv['work_order_id'] ?>:</p>
                                    <ul>
                                        <?php foreach (fulfilled_parts_for_wo($pdo, (int)$inv['work_order_id']) as $pr): ?>
                                            <li>
                                                <?= htmlspecialchars($pr['name'] . ($pr['sku']?(' ('.$pr['sku'].')'):'') ) ?>
                                                — Qty: <?= (int)$pr['qty'] ?> — Suggested price: <?= number_format((float)$pr['sale_price'],2) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <strong>Totals</strong>
                                <form method="post" class="form-row">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_tax_discount">
                                    <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                    <input class="input" type="number" step="0.01" name="tax" placeholder="Tax" value="<?= number_format($tax,2,'.','') ?>" style="width:120px;">
                                    <input class="input" type="number" step="0.01" name="discount" placeholder="Discount" value="<?= number_format($discount,2,'.','') ?>" style="width:120px;">
                                    <button class="btn icon" type="submit"><i class="fa fa-rotate"></i> Update</button>
                                </form>

                                <strong>Issue & Payment</strong>
                                <div class="form-row">
                                    <form method="post">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="issue_invoice">
                                        <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                        <button class="btn icon" type="submit"><i class="fa fa-paper-plane"></i> Issue Invoice</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="add_payment">
                                        <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                        <select class="select" name="method">
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="upi">UPI</option>
                                            <option value="bank">Bank</option>
                                        </select>
                                        <input class="input" type="number" step="0.01" name="amount" placeholder="Amount" required style="width:140px;">
                                        <input class="input" type="text" name="txn_ref" placeholder="Txn Ref (optional)" style="width:160px;">
                                        <button class="btn primary icon" type="submit"><i class="fa fa-plus"></i> Add Payment</button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="10">No invoices yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>
