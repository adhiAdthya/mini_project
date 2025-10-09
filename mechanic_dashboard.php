<?php
session_start();
include 'db_connect.php';

// Only allow mechanics
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mechanic'){
    header("Location: ../login.php");
    exit();
}

$mechanic_id = $_SESSION['user_id'];

// Fetch assigned jobs
$jobs_query = "
    SELECT Jobs.*, Appointments.vehicle_model, Appointments.vehicle_number, Users.name AS customer_name, Appointments.customer_id
    FROM Jobs
    JOIN Appointments ON Jobs.appointment_id = Appointments.id
    JOIN Users ON Appointments.customer_id = Users.id
    WHERE Jobs.mechanic_id = $mechanic_id
    ORDER BY Jobs.id DESC
";
$jobs = $conn->query($jobs_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mechanic Dashboard</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
body { font-family: Arial, sans-serif; background:#f5f7fa; margin:0; padding:0; }
header { background:#2c3e50; color:white; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; }
header h1 { margin:0; }
header .logout-btn { background:#e5b840; padding:8px 15px; border:none; border-radius:20px; cursor:pointer; font-weight:bold; }
.container { max-width:1200px; margin:20px auto; padding:0 15px; }
.button-container { text-align:right; margin-bottom:20px; }
/* Big View Appointments Button */
.btn-view-all {
    background:#2ecc71;
    color:white;
    font-size:18px;
    padding:12px 25px;
    border:none;
    border-radius:10px;
    font-weight:bold;
    cursor:pointer;
}
.btn-view-all:hover { opacity:0.9; }
.card { background:white; padding:20px; border-radius:10px; margin-bottom:15px; box-shadow:0 5px 15px rgba(0,0,0,0.1); }
.card h3 { margin-top:0; }
.btn { padding:8px 15px; border:none; border-radius:5px; cursor:pointer; font-weight:bold; margin-right:5px; }
.btn-update { background:#3498db; color:white; }
.btn-update:hover { opacity:0.9; }
.status { font-weight:bold; padding:3px 8px; border-radius:5px; color:white; }
.status.assigned { background:#f39c12; }
.status.in-progress { background:#3498db; }
.status.completed { background:#2ecc71; }
</style>
</head>
<body>

<header>
    <h1>Mechanic Dashboard</h1>
    <form method="post" action="../logout.php">
        <button type="submit" class="logout-btn">Logout</button>
    </form>
</header>

<div class="container">
    <h2>Assigned Jobs</h2>

    <!-- Single Big View Appointments Button -->
    <div class="button-container">
        <form method="get" action="view_appointments.php">
            <a href= "view_appoinments.php" class="btn-view-all">View All Appointments</a>
        </form>
    </div>

    <?php if($jobs->num_rows > 0): ?>
        <?php while($job = $jobs->fetch_assoc()): ?>
            <div class="card">
                <h3>Appointment ID: <?= $job['appointment_id'] ?></h3>
                <p>Customer: <?= $job['customer_name'] ?> (ID: <?= $job['customer_id'] ?>)</p>
                <p>Vehicle: <?= $job['vehicle_model'] ?> (<?= $job['vehicle_number'] ?>)</p>
                <p>Service Details: <?= $job['service_details'] ?></p>
                <p>Estimated Time: <?= $job['estimated_time'] ?></p>
                <p>Status: <span class="status <?= str_replace(' ', '-', strtolower($job['job_status'])) ?>"><?= ucfirst($job['job_status']) ?></span></p>

                <?php if($job['job_status'] != 'completed'): ?>
                <form method="post" action="update_status.php" style="margin-top:10px;">
                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                    <select name="job_status" required>
                        <option value="assigned" <?= $job['job_status']=='assigned'?'selected':'' ?>>Assigned</option>
                        <option value="in-progress" <?= $job['job_status']=='in-progress'?'selected':'' ?>>In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                    <button type="submit" class="btn btn-update">Update Status</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No jobs assigned yet.</p>
    <?php endif; ?>
</div>

</body>
</html>