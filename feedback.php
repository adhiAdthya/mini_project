<?php
require_once __DIR__ . '/../auth.php';
require_role('manager');
$user = current_user();
$pdo = Database::connection();

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$min_rating = isset($_GET['min_rating']) ? max(1, min(5, (int)$_GET['min_rating'])) : 1;

// Fetch feedback with joins for context
$sql = "SELECT f.*, 
               wo.id AS wo_id,
               a.preferred_date,
               s.name AS service_name,
               cu.id AS customer_id,
               u.name AS customer_name,
               u.email AS customer_email
        FROM feedback f
        JOIN work_orders wo ON wo.id=f.work_order_id
        JOIN appointments a ON a.id=wo.appointment_id
        JOIN service_types s ON s.id=a.service_type_id
        JOIN customers cu ON cu.id=f.customer_id
        JOIN users u ON u.id=cu.user_id
        WHERE f.created_at BETWEEN CONCAT(?, ' 00:00:00') AND CONCAT(?, ' 23:59:59')
          AND f.rating >= ?
        ORDER BY f.created_at DESC";
$st = $pdo->prepare($sql);
$st->execute([$start, $end, $min_rating]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$avgStmt = $pdo->prepare("SELECT AVG(rating) avg_rating, COUNT(*) cnt FROM feedback WHERE created_at BETWEEN CONCAT(?, ' 00:00:00') AND CONCAT(?, ' 23:59:59')");
$avgStmt->execute([$start, $end]);
$avg = $avgStmt->fetch(PDO::FETCH_ASSOC) ?: ['avg_rating' => null, 'cnt' => 0];

$baseQuery = http_build_query(['start' => $start, 'end' => $end, 'min_rating' => $min_rating]);

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="feedback_' . $start . '_to_' . $end . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Created At','Work Order','Customer','Email','Service','Rating','Comments']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['created_at'],
            $r['wo_id'],
            $r['customer_name'],
            $r['customer_email'],
            $r['service_name'],
            $r['rating'],
            preg_replace('/\s+/', ' ', (string)($r['comments'] ?? '')),
        ]);
    }
    fclose($out);
    exit;
}

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Feedback - Manager</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:1200px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left}
.input,.btn{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px}
.btn.primary{background:#3498db;color:#fff;border:none}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
.badge.good{background:#dcfce7;color:#166534}
.badge.poor{background:#fee2e2;color:#991b1b}
.small{color:#6b7280;font-size:12px}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Customer Feedback</div>
    <div>
        Hello, <?= esc($user['name']) ?> |
        <a class="link" href="dashboard.php" style="color:#fff;">Dashboard</a> |
        <a class="link" href="reports.php" style="color:#fff;">Reports</a> |
        <a class="link" href="../logout.php" style="color:#fff;">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>Filters</h2>
        <form method="get" class="form-row" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <label>Start</label>
            <input class="input" type="date" name="start" value="<?= esc($start) ?>">
            <label>End</label>
            <input class="input" type="date" name="end" value="<?= esc($end) ?>">
            <label>Min Rating</label>
            <select class="input" name="min_rating">
                <?php for ($i=1;$i<=5;$i++): ?>
                    <option value="<?= $i ?>" <?= $i===$min_rating?'selected':'' ?>><?= $i ?>+</option>
                <?php endfor; ?>
            </select>
            <button class="btn primary" type="submit">Apply</button>
            <a class="btn" href="?<?= $baseQuery ?>&export=csv">Export CSV</a>
        </form>
        <div class="small" style="margin-top:6px">Average rating: <strong><?= $avg['avg_rating'] ? number_format((float)$avg['avg_rating'],2) : 'N/A' ?></strong> from <?= (int)$avg['cnt'] ?> responses.</div>
    </div>

    <div class="card">
        <h3>Feedback</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Created</th>
                    <th>WO #</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Service</th>
                    <th>Rating</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $r): ?>
                <tr>
                    <td><?= esc($r['created_at']) ?></td>
                    <td>#<?= (int)$r['wo_id'] ?></td>
                    <td><?= esc($r['customer_name']) ?></td>
                    <td><?= esc($r['customer_email']) ?></td>
                    <td><?= esc($r['service_name']) ?></td>
                    <td>
                        <span class="badge <?= (int)$r['rating'] >= 4 ? 'good' : ((int)$r['rating'] <= 2 ? 'poor' : '') ?>">
                            <?= (int)$r['rating'] ?> / 5
                        </span>
                    </td>
                    <td><?= nl2br(esc($r['comments'] ?? '')) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7">No feedback in range.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p><a class="link" href="dashboard.php">Back to Dashboard</a></p>
</div>
</body>
</html>
