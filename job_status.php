<?php
require_once __DIR__ . '/../auth.php';
require_role('customer');
$user = current_user();
$pdo = Database::connection();

// Get this customer's id
$stmt = $pdo->prepare('SELECT id FROM customers WHERE user_id=? LIMIT 1');
$stmt->execute([$user['id']]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
$customerId = $c ? (int)$c['id'] : 0;
if (!$customerId) {
    http_response_code(400);
    echo 'Customer profile not found. Please add a vehicle first.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Status - AutoCare Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0}
.header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
.container{max-width:1100px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left;vertical-align:top}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
.badge.new{background:#e0f2fe;color:#0369a1}
.badge.assigned{background:#fef9c3;color:#a16207}
.badge.in_progress{background:#ddd6fe;color:#5b21b6}
.badge.on_hold{background:#fee2e2;color:#991b1b}
.badge.completed{background:#dcfce7;color:#166534}
.badge.billed{background:#fde68a;color:#92400e}
.link{color:#3498db;text-decoration:none}
.small{color:#6b7280;font-size:12px}
</style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Job Status</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>Your Work Orders</h2>
        <div id="jobs"></div>
        <div class="small">Auto-refreshing every 10s…</div>
    </div>
    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>
<script>
async function loadJobs(){
  try {
    const res = await fetch('job_status_data.php');
    const data = await res.json();
    const el = document.getElementById('jobs');
    if (!Array.isArray(data) || data.length===0){ el.innerHTML = '<p>No jobs yet.</p>'; return; }
    let html = '<table class="table"><thead><tr><th>#</th><th>Vehicle</th><th>Service</th><th>Status</th><th>Preferred Date</th><th>Parts</th></tr></thead><tbody>';
    let i=1;
    for (const j of data){
      const parts = (j.parts||[]).map(p=>`${p.name}${p.sku?(' ('+p.sku+')'):''} x ${p.qty} [${p.status}]`).join('<br>');
      html += `<tr>
        <td>${i++}</td>
        <td>${escapeHtml(j.make)} ${escapeHtml(j.model)} ${j.year} ${j.license_plate?('('+escapeHtml(j.license_plate)+')'):''}</td>
        <td>${escapeHtml(j.service_name)}</td>
        <td><span class="badge ${j.status.replaceAll('-','_')}">${titleCase(j.status)}</span></td>
        <td>${escapeHtml(j.preferred_date||'')}</td>
        <td>${parts || '-'}</td>
      </tr>`;
    }
    html += '</tbody></table>';
    el.innerHTML = html;
  } catch(err){
    console.error(err);
  }
}
function titleCase(s){return s.replace(/_/g,' ').replace(/\w\S*/g, (t)=> t.charAt(0).toUpperCase()+t.substr(1).toLowerCase());}
function escapeHtml(s){return (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m]));}
loadJobs();
setInterval(loadJobs, 10000);
</script>
</body>
</html>
