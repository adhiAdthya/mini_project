<?php
require_once __DIR__ . '/../auth.php';
require_role('customer');
header('Content-Type: application/json');

$user = current_user();
$pdo = Database::connection();

$stmt = $pdo->prepare('SELECT id FROM customers WHERE user_id=? LIMIT 1');
$stmt->execute([$user['id']]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
$customerId = $c ? (int)$c['id'] : 0;
if (!$customerId) { echo json_encode([]); exit; }

$sql = "SELECT wo.id, wo.status, a.preferred_date, s.name AS service_name,
               v.make, v.model, v.year, v.license_plate
        FROM work_orders wo
        JOIN appointments a ON a.id=wo.appointment_id
        JOIN service_types s ON s.id=a.service_type_id
        JOIN vehicles v ON v.id=a.vehicle_id
        WHERE a.customer_id=?
        ORDER BY FIELD(wo.status,'new','assigned','in_progress','on_hold','completed','billed'), wo.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$customerId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include parts requested per WO
foreach ($rows as &$r) {
    $st = $pdo->prepare("SELECT p.name, p.sku, r.qty, r.status FROM spare_part_requests r JOIN parts p ON p.id=r.part_id WHERE r.work_order_id=? ORDER BY r.id");
    $st->execute([(int)$r['id']]);
    $r['parts'] = $st->fetchAll(PDO::FETCH_ASSOC);
}
unset($r);

echo json_encode($rows);
