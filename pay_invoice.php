<?php
require_once __DIR__ . '/../auth.php';
require_role('customer');
$user = current_user();
$pdo = Database::connection();

$errors = [];
$success = null;

// Validate invoice ownership and load
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
    http_response_code(400);
    echo 'Invalid invoice.';
    exit;
}

// Confirm invoice belongs to this customer
$sql = "SELECT inv.*, a.customer_id, COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id=inv.id),0) AS paid
        FROM invoices inv
        JOIN work_orders wo ON wo.id=inv.work_order_id
        JOIN appointments a ON a.id=wo.appointment_id
        WHERE inv.id=? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}

// Find this user's customer id
$st = $pdo->prepare('SELECT id FROM customers WHERE user_id=? LIMIT 1');
$st->execute([$user['id']]);
$c = $st->fetch(PDO::FETCH_ASSOC);
$customerId = $c ? (int)$c['id'] : 0;

if ((int)$inv['customer_id'] !== $customerId) {
    http_response_code(403);
    echo 'You do not have access to this invoice.';
    exit;
}

$due = max(0, (float)$inv['total'] - (float)$inv['paid']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim($_POST['method'] ?? 'upi');
        $txn_ref = trim($_POST['txn_ref'] ?? '');
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0.';
        } elseif ($amount > $due + 0.01) {
            $errors[] = 'Amount exceeds due balance.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO payments (invoice_id, method, amount, paid_at, txn_ref) VALUES (?,?,?,?,?)');
                $stmt->execute([$invoice_id, $method, $amount, date('Y-m-d H:i:s'), $txn_ref ?: null]);
                // Recompute paid and close if fully paid
                $stp = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS paid FROM payments WHERE invoice_id=?');
                $stp->execute([$invoice_id]);
                $paidNow = (float)$stp->fetch(PDO::FETCH_ASSOC)['paid'];
                if ($paidNow + 0.01 >= (float)$inv['total']) {
                    $pdo->prepare("UPDATE invoices SET status='paid' WHERE id=?")->execute([$invoice_id]);
                }
                header('Location: invoices.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Payment failed. Please try again.';
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
<title>Pay Invoice - AutoCare Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/theme.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:700px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.form-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px}
.input,.select,.btn{padding:10px;border:1px solid #cbd5e1;border-radius:8px}
.btn.primary{background:#3498db;color:#fff;border:none}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Pay Invoice</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="invoices.php" style="color:#fff;">My Invoices</a> |
        <a class="link" href="../logout.php" style="color:#fff;">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>Invoice <?= htmlspecialchars($inv['number']) ?></h2>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <p>Total: <strong><?= number_format((float)$inv['total'],2) ?></strong></p>
        <p>Paid: <strong><?= number_format((float)$inv['paid'],2) ?></strong></p>
        <p>Due: <strong><?= number_format($due,2) ?></strong></p>
        <?php if ($inv['status'] !== 'issued' && $inv['status'] !== 'paid'): ?>
            <p>Status: <?= htmlspecialchars(ucfirst($inv['status'])) ?>. Payment is available when invoice is Issued.</p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="invoice_id" value="<?= (int)$invoice_id ?>">
            <div class="form-row">
                <label style="flex-basis:100%">Payment Method</label>
                <select class="select" name="method" style="min-width:140px">
                    <option value="upi">UPI</option>
                    <option value="card">Card</option>
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                </select>
            </div>
            <div class="form-row">
                <label style="flex-basis:100%">Amount</label>
                <input class="input" type="number" step="0.01" min="0.01" max="<?= number_format($due,2,'.','') ?>" name="amount" value="<?= number_format($due,2,'.','') ?>" required>
            </div>
            <div class="form-row">
                <label style="flex-basis:100%">Reference (optional)</label>
                <input class="input" type="text" name="txn_ref" placeholder="Transaction reference (if any)">
            </div>
            <button class="btn primary" type="submit" <?= ($inv['status']!=='issued' && $inv['status']!=='paid')?'disabled':'' ?>>Pay Now</button>
        </form>
    </div>
    <p><a class="link" href="invoices.php">Back to Invoices</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>
