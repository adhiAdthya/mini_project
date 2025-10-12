<?php
require_once __DIR__ . '/../auth.php';
require_role('manager');
$user = current_user();
$pdo = Database::connection();

// Date filters
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$group = $_GET['group'] ?? 'day'; // 'day' or 'month'
$group = in_array($group, ['day','month'], true) ? $group : 'day';

// Revenue by date range (payments)
if ($group === 'month') {
    $st = $pdo->prepare("SELECT DATE_FORMAT(paid_at, '%Y-%m') d, SUM(amount) amt FROM payments WHERE paid_at BETWEEN CONCAT(?, ' 00:00:00') AND CONCAT(?, ' 23:59:59') GROUP BY DATE_FORMAT(paid_at, '%Y-%m') ORDER BY d");
} else {
    $st = $pdo->prepare("SELECT DATE(paid_at) d, SUM(amount) amt FROM payments WHERE paid_at BETWEEN CONCAT(?, ' 00:00:00') AND CONCAT(?, ' 23:59:59') GROUP BY DATE(paid_at) ORDER BY d");
}
$st->execute([$start, $end]);
$revenueRows = $st->fetchAll(PDO::FETCH_ASSOC);
$revenueTotal = array_sum(array_map(fn($r)=>(float)$r['amt'], $revenueRows));

// Parts usage (fulfilled requests)
$st = $pdo->prepare("SELECT p.name, p.sku, SUM(r.qty) qty FROM spare_part_requests r JOIN parts p ON p.id=r.part_id WHERE r.status='fulfilled' AND r.created_at BETWEEN CONCAT(?, ' 00:00:00') AND CONCAT(?, ' 23:59:59') GROUP BY p.id ORDER BY qty DESC");
$st->execute([$start, $end]);
$partsUsage = $st->fetchAll(PDO::FETCH_ASSOC);

// Outstanding invoices
$st = $pdo->query("SELECT inv.*, COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id=inv.id),0) AS paid FROM invoices inv WHERE inv.status IN ('draft','issued') ORDER BY inv.id DESC");
$outstanding = $st->fetchAll(PDO::FETCH_ASSOC);

// Build base query string for export links
$baseQuery = http_build_query(['start' => $start, 'end' => $end, 'group' => $group]);

// CSV Export handlers
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="report_' . preg_replace('/[^a-z_]/i','', $type) . '_' . $start . '_to_' . $end . '.csv"');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fprintf($out, "\xEF\xBB\xBF");

    if ($type === 'revenue') {
        fputcsv($out, ['Date', 'Amount']);
        foreach ($revenueRows as $r) {
            fputcsv($out, [$r['d'], number_format((float)$r['amt'], 2, '.', '')]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Total', number_format((float)$revenueTotal, 2, '.', '')]);
    } elseif ($type === 'parts') {
        fputcsv($out, ['Part', 'SKU', 'Qty Used']);
        foreach ($partsUsage as $p) {
            fputcsv($out, [$p['name'], $p['sku'] ?: '-', (int)$p['qty']]);
        }
    } elseif ($type === 'outstanding') {
        fputcsv($out, ['ID', 'Number', 'Status', 'Total', 'Paid', 'Due']);
        foreach ($outstanding as $inv) {
            $due = max(0, (float)$inv['total'] - (float)$inv['paid']);
            fputcsv($out, [
                (int)$inv['id'],
                $inv['number'],
                $inv['status'],
                number_format((float)$inv['total'], 2, '.', ''),
                number_format((float)$inv['paid'], 2, '.', ''),
                number_format($due, 2, '.', ''),
            ]);
        }
    } else {
        fputcsv($out, ['Unsupported export type']);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:1200px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left}
.form-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.input,.btn{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px}
.btn.primary{background:#3498db;color:#fff;border:none}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
.badge.issued{background:#fef9c3;color:#a16207}
.badge.draft{background:#e0f2fe;color:#0369a1}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Reports</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php" style="color:#fff;">Dashboard</a> |
        <a class="link" href="../logout.php" style="color:#fff;">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>Filters</h2>
        <form method="get" class="form-row">
            <label>Start</label>
            <input class="input" type="date" name="start" value="<?= htmlspecialchars($start) ?>">
            <label>End</label>
            <input class="input" type="date" name="end" value="<?= htmlspecialchars($end) ?>">
            <label>Group</label>
            <select class="input" name="group">
                <option value="day" <?= $group==='day'?'selected':'' ?>>Daily</option>
                <option value="month" <?= $group==='month'?'selected':'' ?>>Monthly</option>
            </select>
            <button class="btn primary" type="submit">Apply</button>
        </form>
    </div>

    <div class="card">
        <h3>Revenue (Payments) — <?= $group==='month' ? 'Monthly' : 'Daily' ?> — Total ₹<?= number_format($revenueTotal,2) ?></h3>
        <div class="form-row">
            <a class="btn primary" href="?<?= $baseQuery ?>&export=revenue">Export CSV</a>
            <button class="btn" type="button" onclick="window.print()">Print</button>
        </div>
        <div style="max-width:800px">
            <canvas id="revenueChart" height="120"></canvas>
        </div>
        <table class="table">
            <thead><tr><th><?= $group==='month' ? 'Month' : 'Date' ?></th><th>Amount</th></tr></thead>
            <tbody>
            <?php if ($revenueRows): foreach ($revenueRows as $r): ?>
                <tr><td><?= htmlspecialchars($r['d']) ?></td><td>₹<?= number_format((float)$r['amt'],2) ?></td></tr>
            <?php endforeach; else: ?>
                <tr><td colspan="2">No payments in range.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Parts Usage</h3>
        <div class="form-row">
            <a class="btn primary" href="?<?= $baseQuery ?>&export=parts">Export CSV</a>
        </div>
        <table class="table">
            <thead><tr><th>Part</th><th>SKU</th><th>Qty Used</th></tr></thead>
            <tbody>
            <?php if ($partsUsage): foreach ($partsUsage as $p): ?>
                <tr><td><?= htmlspecialchars($p['name']) ?></td><td><?= htmlspecialchars($p['sku'] ?: '-') ?></td><td><?= (int)$p['qty'] ?></td></tr>
            <?php endforeach; else: ?>
                <tr><td colspan="3">No fulfilled requests in range.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Outstanding Invoices</h3>
        <div class="form-row">
            <a class="btn primary" href="?<?= $baseQuery ?>&export=outstanding">Export CSV</a>
        </div>
        <table class="table">
            <thead><tr><th>#</th><th>Number</th><th>Status</th><th>Total</th><th>Paid</th><th>Due</th></tr></thead>
            <tbody>
            <?php if ($outstanding): foreach ($outstanding as $inv): ?>
                <?php $due = max(0, (float)$inv['total'] - (float)$inv['paid']); ?>
                <tr>
                    <td>#<?= (int)$inv['id'] ?></td>
                    <td><?= htmlspecialchars($inv['number']) ?></td>
                    <td><span class="badge <?= htmlspecialchars($inv['status']) ?>"><?= htmlspecialchars(ucfirst($inv['status'])) ?></span></td>
                    <td><?= number_format((float)$inv['total'],2) ?></td>
                    <td><?= number_format((float)$inv['paid'],2) ?></td>
                    <td><?= number_format($due,2) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6">No outstanding invoices.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>
</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;
    const labels = <?= json_encode(array_map(fn($r)=>$r['d'], $revenueRows)) ?>;
    const data = <?= json_encode(array_map(fn($r)=>(float)$r['amt'], $revenueRows)) ?>;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Daily Revenue',
                data,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.15)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
});
</script>
