<?php
session_start();
include 'db_connect.php';

// Only allow mechanics
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mechanic') {
    header("Location: login.php");
    exit();
}

$message = "";

// Handle status update to "seen"
if (isset($_POST['mark_seen'])) {
    $appointment_id = $_POST['appointment_id'];
    $update_sql = "UPDATE Appointments SET status='seen' WHERE id='$appointment_id'";
    if ($conn->query($update_sql) === TRUE) {
        $message = "Appointment marked as seen.";
    } else {
        $message = "Error: " . $conn->error;
    }
}

// Fetch all appointments with updated status
$appointments_sql = "
    SELECT Appointments.*, Users.name AS customer_name
    FROM Appointments
    JOIN Users ON Appointments.customer_id = Users.id
    ORDER BY service_date DESC, service_time DESC
";
$appointments_result = $conn->query($appointments_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Appointments</title>
<style>
body { font-family: Arial, sans-serif; background:#f5f7fa; margin:0; padding:0; }
.container { max-width:1000px; margin:30px auto; padding:0 15px; }
h1 { text-align:center; margin-bottom:20px; }
table { width:100%; border-collapse:collapse; background:white; box-shadow:0 4px 10px rgba(0,0,0,0.1); border-radius:8px; overflow:hidden; }
th, td { padding:12px; text-align:left; border-bottom:1px solid #ddd; }
th { background:#3498db; color:white; }
tr:hover { background:#f1f1f1; }
.status { font-weight:bold; padding:5px 10px; border-radius:5px; color:white; text-transform:capitalize; }
.status.pending { background:#f39c12; }
.status.seen { background:#2ecc71; }
.btn { padding:5px 10px; border:none; border-radius:5px; cursor:pointer; font-weight:bold; }
.btn-seen { background:#2ecc71; color:white; }
.btn-seen:hover { opacity:0.9; }
.message { text-align:center; margin-bottom:15px; color:green; font-weight:bold; }
.back-btn {
    display:inline-block;
    margin-bottom:20px;
    padding:10px 15px;
    background:#95a5a6;
    color:white;
    text-decoration:none;
    border-radius:5px;
}
.back-btn:hover { background:#7f8c8d; }
</style>
</head>
<body>

<div class="container">
    <!-- Back to Dashboard Button -->
    <a href="mechanic_dashboard.php" class="back-btn">⬅ Back to Dashboard</a>

    <h1>All Appointments</h1>

    <?php if($message != ""): ?>
        <p class="message"><?= $message ?></p>
    <?php endif; ?>

    <?php if($appointments_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Vehicle Model</th>
                    <th>Vehicle Number</th>
                    <th>Service Date</th>
                    <th>Service Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $appointments_result->fetch_assoc()): ?>
                    <?php $status = $row['status'] ?? 'pending'; ?>
                    <tr>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= htmlspecialchars($row['vehicle_model']) ?></td>
                        <td><?= htmlspecialchars($row['vehicle_number']) ?></td>
                        <td><?= $row['service_date'] ?></td>
                        <td><?= $row['service_time'] ?></td>
                        <td><span class="status <?= strtolower($status) ?>"><?= $status ?></span></td>
                        <td>
                            <?php if($status != 'seen'): ?>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="mark_seen" class="btn btn-seen">Mark as Seen</button>
                                </form>
                            <?php else: ?>
                                <span>—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align:center;">No appointments found.</p>
    <?php endif; ?>
</div>

</body>
</html>
