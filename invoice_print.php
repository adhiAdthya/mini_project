<?php
require_once __DIR__ . '/../auth.php';
require_role('manager');
$user = current_user();
$pdo = Database::connection();

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id <= 0) {
    http_response_code(400);
    echo 'Invalid invoice ID';
    exit;
}

// Load invoice, customer and vehicle details
$sql = "SELECT inv.*, wo.id AS wo_id, a.preferred_date, s.name AS service_name,
               v.make, v.model, v.year, v.license_plate,
               cu.id AS customer_id, u.name AS customer_name, u.email AS customer_email
        FROM invoices inv
        JOIN work_orders wo ON wo.id=inv.work_order_id
        JOIN appointments a ON a.id=wo.appointment_id
        JOIN service_types s ON s.id=a.service_type_id
        JOIN vehicles v ON v.id=a.vehicle_id
        LEFT JOIN customers cu ON cu.id=a.customer_id
        LEFT JOIN users u ON u.id=cu.user_id
        WHERE inv.id=? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) { http_response_code(404); echo 'Invoice not found'; exit; }

// Items
$itemsStmt = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id');
$itemsStmt->execute([$invoice_id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Payments
$payStmt = $pdo->prepare('SELECT * FROM payments WHERE invoice_id=? ORDER BY paid_at');
$payStmt->execute([$invoice_id]);
$pays = $payStmt->fetchAll(PDO::FETCH_ASSOC);

function fmt($n){ return number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?= htmlspecialchars($inv['number']) ?> - Print</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;margin:0;padding:20px;background:#fff;color:#111}
.header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111;padding-bottom:10px;margin-bottom:16px}
.h-title{font-size:22px;font-weight:700}
.meta{color:#374151}
.section{margin-bottom:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left}
.right{text-align:right}
.small{color:#6b7280;font-size:12px}
.print-btn{position:fixed;right:20px;top:20px;padding:8px 12px;background:#111;color:#fff;border-radius:6px;text-decoration:none}
@media print {.print-btn{display:none}}
</style>
</head>
<body>
<a class="print-btn" href="#" onclick="window.print();return false;">Print</a>
<div class="header">
    <div>
        <div class="h-title">AutoCare Garage</div>
        <div class="small">Invoice: <?= htmlspecialchars($inv['number']) ?></div>
        <div class="small">Issued: <?= htmlspecialchars($inv['issued_at'] ?: '-') ?> | Status: <?= htmlspecialchars(ucfirst($inv['status'])) ?></div>
    </div>
    <div class="meta">
        <div><strong>Bill To:</strong> <?= htmlspecialchars($inv['customer_name'] ?: 'Customer') ?></div>
        <div><?= htmlspecialchars($inv['customer_email'] ?: '') ?></div>
        <div>WO#<?= (int)$inv['wo_id'] ?> • <?= htmlspecialchars($inv['make'].' '.$inv['model'].' '.$inv['year']) ?> <?= $inv['license_plate']?('('.htmlspecialchars($inv['license_plate']).')'):'' ?></div>
        <div>Service: <?= htmlspecialchars($inv['service_name']) ?></div>
    </div>
</div>

<div class="section">
    <table class="table">
        <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit Price</th><th class="right">Amount</th></tr></thead>
        <tbody>
        <?php $subtotal=0; foreach ($items as $it): $amt=((float)$it['qty']*(float)$it['unit_price']); $subtotal+=$amt; ?>
            <tr>
                <td><?= htmlspecialchars($it['description']) ?></td>
                <td class="right"><?= fmt($it['qty']) ?></td>
                <td class="right"><?= fmt($it['unit_price']) ?></td>
                <td class="right"><?= fmt($amt) ?></td>
            </tr>
        <?php endforeach; if (!$items): ?>
            <tr><td colspan="4">No items.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php $tax=(float)$inv['tax']; $discount=(float)$inv['discount']; $total=max(0,$subtotal+$tax-$discount); ?>
<div class="section" style="display:flex;justify-content:flex-end;">
    <table>
        <tr><td style="padding:4px 8px;">Subtotal</td><td class="right" style="padding:4px 8px;">₹<?= fmt($subtotal) ?></td></tr>
        <tr><td style="padding:4px 8px;">Tax</td><td class="right" style="padding:4px 8px;">₹<?= fmt($tax) ?></td></tr>
        <tr><td style="padding:4px 8px;">Discount</td><td class="right" style="padding:4px 8px;">-₹<?= fmt($discount) ?></td></tr>
        <tr><td style="padding:4px 8px;font-weight:700;">Total</td><td class="right" style="padding:4px 8px;font-weight:700;">₹<?= fmt($total) ?></td></tr>
    </table>
</div>

<div class="section">
    <div><strong>Payments</strong></div>
    <table class="table">
        <thead><tr><th>Date</th><th>Method</th><th>Txn Ref</th><th class="right">Amount</th></tr></thead>
        <tbody>
        <?php $paid=0; foreach ($pays as $p): $paid+=(float)$p['amount']; ?>
            <tr>
                <td><?= htmlspecialchars($p['paid_at']) ?></td>
                <td><?= htmlspecialchars(strtoupper($p['method'])) ?></td>
                <td><?= htmlspecialchars($p['txn_ref'] ?: '-') ?></td>
                <td class="right">₹<?= fmt($p['amount']) ?></td>
            </tr>
        <?php endforeach; if (!$pays): ?>
            <tr><td colspan="4">No payments yet.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="right" style="font-weight:700;">Paid</td><td class="right" style="font-weight:700;">₹<?= fmt($paid) ?></td></tr>
            <tr><td colspan="3" class="right" style="font-weight:700;">Due</td><td class="right" style="font-weight:700;">₹<?= fmt(max(0,$total-$paid)) ?></td></tr>
        </tfoot>
    </table>
</div>

<div class="small">Thank you for your business.</div>
</body>
</html>
