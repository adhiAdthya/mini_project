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
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $reorder_level = max(0, (int)($_POST['reorder_level'] ?? 0));
            $cost_price = (float)($_POST['cost_price'] ?? 0);
            $sale_price = (float)($_POST['sale_price'] ?? 0);
            if ($name === '') $errors[] = 'Name is required.';
            if (!$errors) {
                $stmt = $pdo->prepare('INSERT INTO parts (sku,name,stock_qty,reorder_level,cost_price,sale_price) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$sku ?: null, $name, 0, $reorder_level, $cost_price, $sale_price]);
                $success = 'Part created.';
            }
        } elseif ($action === 'adjust_stock') {
            $part_id = (int)($_POST['part_id'] ?? 0);
            $delta = (int)($_POST['delta_qty'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'adjustment');
            if ($part_id > 0 && $delta !== 0) {
                try {
                    $pdo->beginTransaction();
                    // Update qty
                    $stmt = $pdo->prepare('UPDATE parts SET stock_qty = stock_qty + ? WHERE id=?');
                    $stmt->execute([$delta, $part_id]);
                    // Movement log
                    $stmt = $pdo->prepare('INSERT INTO stock_movements (part_id, delta_qty, reason, reference_id, created_by) VALUES (?,?,?,?,?)');
                    $stmt->execute([$part_id, $delta, $reason, null, $user['id']]);
                    $pdo->commit();
                    $success = 'Stock adjusted.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Failed to adjust stock.';
                }
            }
        } elseif ($action === 'update_part') {
            $part_id = (int)($_POST['part_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $reorder_level = max(0, (int)($_POST['reorder_level'] ?? 0));
            $cost_price = (float)($_POST['cost_price'] ?? 0);
            $sale_price = (float)($_POST['sale_price'] ?? 0);
            if ($part_id > 0 && $name !== '') {
                $stmt = $pdo->prepare('UPDATE parts SET name=?, sku=?, reorder_level=?, cost_price=?, sale_price=? WHERE id=?');
                $stmt->execute([$name, ($sku ?: null), $reorder_level, $cost_price, $sale_price, $part_id]);
                $success = 'Part updated.';
            }
        }
    }
}

$parts = $pdo->query('SELECT * FROM parts ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parts - Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
 :root{--primary:#2c3e50;--accent:#3498db;--brand:#e5b840;--muted:#6b7280;--bg:#f5f7fa}
 body{font-family:Segoe UI,Arial,sans-serif;background:var(--bg);margin:0}
 .header{background:#2c3e50;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center}
 .container{max-width:1200px;margin:0 auto;padding:16px}
 .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.06);padding:16px;margin-bottom:16px;transition:transform .2s ease, box-shadow .2s ease}
 .card.hoverable:hover{transform:translateY(-6px);box-shadow:0 14px 30px rgba(0,0,0,0.10)}
 .section-title{margin:0 0 8px;color:#111;display:flex;align-items:center;gap:8px}
 .section-title i{color:var(--brand)}
 .subtle{color:var(--muted);margin:0 0 12px}
 .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
 .form-field{display:flex;flex-direction:column}
 .form-field label{font-size:12px;color:#374151;margin-bottom:6px}
 .input,.select{padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px}
 .btn{padding:10px 12px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;cursor:pointer;transition:transform .15s ease, box-shadow .15s ease, background .15s ease}
 .btn:hover{box-shadow:0 6px 16px rgba(0,0,0,0.06)}
 .btn:active{transform:translateY(1px)}
 .btn.primary{background:#3498db;color:#fff;border:none}
 .btn.outline{background:#fff;color:#111}
 .btn.small{padding:8px 10px;font-size:14px}
 .btn.icon i{margin-right:6px}
 .badge.low{background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:999px}
 .link{color:#3498db;text-decoration:none}
 .alert{padding:10px;border-radius:8px;margin-bottom:10px}
 .alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
 .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
 .table{width:100%;border-collapse:collapse}
 .table th,.table td{border-bottom:1px solid #eee;padding:10px;text-align:left}
 .table th{color:#111}
 .actions{display:flex;gap:8px}
 /* Modal */
 .modal{position:fixed;inset:0;background:rgba(0,0,0,0.35);display:none;align-items:center;justify-content:center;padding:16px;z-index:50}
 .modal.open{display:flex}
 .modal .modal-card{background:#fff;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,0.2);width:100%;max-width:420px;border:1px solid #e5e7eb}
 .modal .modal-header{padding:14px 16px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
 .modal .modal-body{padding:16px}
 .modal .modal-actions{padding:12px 16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:10px}
 .close-x{background:none;border:none;font-size:20px;cursor:pointer}
 .muted{color:#6b7280}
 </style>
</head>
<body>
<div class="header">
    <div>AutoCare Garage - Manager</div>
    <div>
        Hello, <?= htmlspecialchars($user['name']) ?> |
        <a class="link" href="dashboard.php">Dashboard</a> |
        <a class="link" href="requests.php">Part Requests</a> |
        <a class="link" href="invoices.php">Invoices</a> |
        <a class="link" href="../logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card hoverable">
        <h2 class="section-title"><i class="fa fa-gear"></i> Create Part</h2>
        <p class="subtle">Add a new spare part to your inventory.</p>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $e){echo htmlspecialchars($e).'<br>'; } ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-field">
                    <label for="name">Name</label>
                    <input id="name" class="input" type="text" name="name" placeholder="e.g., Brake Pad" required>
                </div>
                <div class="form-field">
                    <label for="sku">SKU (optional)</label>
                    <input id="sku" class="input" type="text" name="sku" placeholder="e.g., BRK-001">
                </div>
                <div class="form-field">
                    <label for="reorder_level">Reorder Level</label>
                    <input id="reorder_level" class="input" type="number" name="reorder_level" min="0" value="0" placeholder="e.g., 10">
                </div>
                <div class="form-field">
                    <label for="cost_price">Cost Price</label>
                    <input id="cost_price" class="input" type="number" step="0.01" name="cost_price" value="0" placeholder="e.g., 1200.00">
                </div>
                <div class="form-field">
                    <label for="sale_price">Sale Price</label>
                    <input id="sale_price" class="input" type="number" step="0.01" name="sale_price" value="0" placeholder="e.g., 1800.00">
                </div>
            </div>
            <div style="margin-top:10px">
                <button class="btn primary icon" type="submit"><i class="fa fa-plus"></i> Create</button>
            </div>
        </form>
    </div>

    <div class="card hoverable">
        <h2 class="section-title"><i class="fa fa-boxes-stacked"></i> Parts Inventory</h2>
        <p class="subtle">Review and manage your parts. Use Actions to edit details or adjust stock.</p>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Stock</th>
                    <th>Reorder Level</th>
                    <th>Cost</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($parts): foreach($parts as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['sku'] ?: '-') ?></td>
                    <td><?= (int)$p['stock_qty'] ?><?= ((int)$p['stock_qty'] <= (int)$p['reorder_level']) ? ' <span class="badge low">Low</span>' : '' ?></td>
                    <td><?= (int)$p['reorder_level'] ?></td>
                    <td><?= number_format((float)$p['cost_price'],2) ?></td>
                    <td><?= number_format((float)$p['sale_price'],2) ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn small outline icon" type="button" data-edit-toggle="row-<?= (int)$p['id'] ?>"><i class="fa fa-pen"></i> Edit</button>
                            <button class="btn small primary icon" type="button" data-adjust-open data-part-id="<?= (int)$p['id'] ?>" data-part-name="<?= htmlspecialchars($p['name']) ?>"><i class="fa fa-scale-balanced"></i> Adjust</button>
                        </div>
                    </td>
                </tr>
                <tr id="row-<?= (int)$p['id'] ?>" style="display:none;background:#fbfbfb">
                    <td colspan="7">
                        <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_part">
                            <input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>">
                            <div class="form-field">
                                <label>Name</label>
                                <input class="input" type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required>
                            </div>
                            <div class="form-field">
                                <label>SKU</label>
                                <input class="input" type="text" name="sku" value="<?= htmlspecialchars($p['sku']) ?>">
                            </div>
                            <div class="form-field">
                                <label>Reorder Level</label>
                                <input class="input" type="number" name="reorder_level" value="<?= (int)$p['reorder_level'] ?>">
                            </div>
                            <div class="form-field">
                                <label>Cost Price</label>
                                <input class="input" type="number" step="0.01" name="cost_price" value="<?= number_format((float)$p['cost_price'],2,'.','') ?>">
                            </div>
                            <div class="form-field">
                                <label>Sale Price</label>
                                <input class="input" type="number" step="0.01" name="sale_price" value="<?= number_format((float)$p['sale_price'],2,'.','') ?>">
                            </div>
                            <div>
                                <button class="btn primary icon" type="submit"><i class="fa fa-save"></i> Save</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7">No parts yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p><a class="link" href="dashboard.php">Back to Dashboard</a> | <a class="link" href="../index.php">Home</a></p>
</div>

<!-- Adjust Quantity Modal -->
<div class="modal" id="adjustModal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-card">
    <div class="modal-header">
      <strong><i class="fa fa-scale-balanced" style="color:#3498db"></i> Adjust Quantity</strong>
      <button class="close-x" type="button" data-close-modal>&times;</button>
    </div>
    <form method="post" id="adjustForm">
      <div class="modal-body">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="adjust_stock">
        <input type="hidden" name="part_id" id="adjust_part_id" value="0">
        <div class="form-field">
          <label>Part</label>
          <div class="muted" id="adjust_part_name">-</div>
        </div>
        <div class="form-grid">
          <div class="form-field">
            <label for="delta_qty">± Quantity</label>
            <input id="delta_qty" class="input" type="number" name="delta_qty" placeholder="e.g., +5 or -3" required>
          </div>
          <div class="form-field">
            <label for="reason">Reason</label>
            <input id="reason" class="input" type="text" name="reason" placeholder="e.g., received shipment" value="adjustment">
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn outline" type="button" data-close-modal>Cancel</button>
        <button class="btn primary" type="submit">Apply</button>
      </div>
    </form>
  </div>
</div>

<script>
// Toggle inline edit rows
document.querySelectorAll('[data-edit-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
        const rowId = btn.getAttribute('data-edit-toggle');
        const row = document.getElementById(rowId);
        if (!row) return;
        row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
    });
});

// Adjust modal logic
const adjustModal = document.getElementById('adjustModal');
const adjustPartId = document.getElementById('adjust_part_id');
const adjustPartName = document.getElementById('adjust_part_name');

function openAdjustModal(id, name){
    adjustPartId.value = id;
    adjustPartName.textContent = name;
    adjustModal.classList.add('open');
    document.getElementById('delta_qty').focus();
}
function closeAdjustModal(){
    adjustModal.classList.remove('open');
}

document.querySelectorAll('[data-adjust-open]').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-part-id');
        const name = btn.getAttribute('data-part-name');
        openAdjustModal(id, name);
    });
});
document.querySelectorAll('[data-close-modal]').forEach(btn => btn.addEventListener('click', closeAdjustModal));
adjustModal.addEventListener('click', (e) => { if (e.target === adjustModal) closeAdjustModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAdjustModal(); });
</script>
</div>
</body>
</html>
