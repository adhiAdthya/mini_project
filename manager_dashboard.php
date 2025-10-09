<?php
session_start();
include 'db_connect.php';

// Ensure manager is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager'){
    header("Location: ../login.php");
    exit();
}

// Handle Mark as Paid functionality
if(isset($_GET['mark_paid']) && is_numeric($_GET['mark_paid'])) {
    $bill_id = $_GET['mark_paid'];
    
    $update_stmt = $conn->prepare("UPDATE Bills SET payment_status = 'paid' WHERE id = ?");
    $update_stmt->bind_param("i", $bill_id);
    
    if($update_stmt->execute()) {
        // Set session variable for success message
        $_SESSION['success_msg'] = "Bill marked as paid successfully!";
        // Redirect to remove the parameter from URL
        header("Location: manager_dashboard.php");
        exit();
    } else {
        $error_msg = "Error updating bill: " . $conn->error;
    }
}

// Fetch assigned jobs
$jobQuery = "
SELECT Jobs.id as job_id, Users.name as mechanic_name, Appointments.vehicle_model, Appointments.vehicle_number, Jobs.service_details, Jobs.job_status
FROM Jobs
JOIN Users ON Jobs.mechanic_id = Users.id
JOIN Appointments ON Jobs.appointment_id = Appointments.id
ORDER BY Jobs.id DESC
";
$jobResult = $conn->query($jobQuery);

// Fetch bills
$billQuery = "
SELECT Bills.id as bill_id, Users.name as customer_name, Appointments.vehicle_model, Bills.total_amount, Bills.payment_status, Bills.bill_date
FROM Bills
JOIN Users ON Bills.customer_id = Users.id
JOIN Appointments ON Bills.appointment_id = Appointments.id
ORDER BY Bills.bill_date DESC
";
$billResult = $conn->query($billQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manager Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* General */
body { font-family: 'Roboto', sans-serif; background: #f0f2f5; margin:0; padding:0; color:#333; }
h1,h2{color:#1a1a1a;}
.container{width:95%; max-width:1200px; margin:20px auto;}

/* Header */
header{
    background:#4da6ff; /* Light blue */
    color:white; padding:20px 25px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 6px rgba(0,0,0,0.1);
}
header h2{margin:0; font-size:26px;}
header nav a{color:white; text-decoration:none; margin-left:15px; font-weight:500; transition:0.2s;}
header nav a:hover{color:#ffe066;}

/* Module Cards */
.module-links{display:flex; gap:15px; flex-wrap:wrap; margin-bottom:25px;}
.module-card{
    background:#80bfff; /* Light blue card */
    color:white; padding:20px 25px; border-radius:10px; flex:1 1 250px; text-align:center; text-decoration:none; font-weight:500; transition:all 0.3s ease; box-shadow:0 4px 8px rgba(0,0,0,0.15);
}
.module-card:hover{background:#3399ff; transform:translateY(-5px);}
.module-card i{display:block; font-size:26px; margin-bottom:10px;}

/* Table Wrapper */
.table-wrapper{overflow-x:auto; margin-top:15px; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,0.1);}
table{width:100%; border-collapse:collapse; min-width:600px; background:white;}
table th, table td{padding:12px 15px; text-align:left;}
table th{background:#4da6ff; color:white; font-weight:500;}
table tr:nth-child(even){background:#f8f9fa;}
table tr:hover{background:#d9ebff; transition:0.2s;}

/* Collapsible Details */
.details-row {display: none; background-color: #e6f2ff; transition: all 0.3s ease;}
.clickable-row:hover {cursor:pointer; background-color: #cce6ff;}

/* Status Badges */
.status-pending{color:#856404; background:#fff3cd; padding:4px 8px; border-radius:5px; font-weight:500; display:inline-block;}
.status-in-progress{color:#0c5460; background:#d1ecf1; padding:4px 8px; border-radius:5px; font-weight:500; display:inline-block;}
.status-completed, .status-paid{color:#155724; background:#d4edda; padding:4px 8px; border-radius:5px; font-weight:500; display:inline-block;}

/* Action Buttons */
.action-btn{padding:6px 12px; background:#28a745; color:white; border-radius:5px; text-decoration:none; font-weight:500; display:inline-block; transition:all 0.2s;}
.action-btn:hover{background:#218838; transform:translateY(-2px);}

/* Responsive headings */
h1,h2{font-size:1.8rem;}
@media(max-width:768px){
    h1{font-size:1.5rem;}
    h2{font-size:1.3rem;}
}

/* Footer */
footer{text-align:center; padding:15px; background:#f0f2f5; margin-top:25px; color:#555; font-size:14px;}

/* Success/Error Messages */
.alert {
    padding: 12px 15px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-weight: 500;
    animation: fadeOut 3s ease-in-out forwards;
    animation-delay: 2s;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}
.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; display: none; }
}
</style>
</head>
<body>

<header>
<h2>AutoCare Garage Management System</h2>
<nav>
    <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</nav>
</header>

<div class="container">
<h1>Manager Dashboard</h1>

<!-- Module Cards -->
<div class="module-links">
    <a href="view_inventory.php" class="module-card"><i class="fas fa-cogs"></i> Spare Parts Management</a>
    <a href="reports.php" class="module-card"><i class="fas fa-chart-line"></i> Reports & Analytics</a>
    <a href="create_bill.php" class="module-card"><i class="fas fa-file-invoice-dollar"></i> Billing</a>
</div>

<!-- Assigned Jobs -->
<h2>Assigned Jobs</h2>
<div class="table-wrapper">
<table>
<tr>
    <th>Job ID</th>
    <th>Mechanic</th>
    <th>Vehicle Model</th>
    <th>Vehicle Number</th>
    <th>Status</th>
</tr>
<?php if($jobResult->num_rows > 0): ?>
    <?php while($job = $jobResult->fetch_assoc()): ?>
    <tr class="clickable-row" data-target="details-<?= $job['job_id']; ?>">
        <td><?= $job['job_id']; ?></td>
        <td><?= $job['mechanic_name']; ?></td>
        <td><?= $job['vehicle_model']; ?></td>
        <td><?= $job['vehicle_number']; ?></td>
        <td><span class="status-<?= strtolower(str_replace(' ','-',$job['job_status'])); ?>"><?= ucfirst($job['job_status']); ?></span></td>
    </tr>
    <tr id="details-<?= $job['job_id']; ?>" class="details-row">
        <td colspan="5"><strong>Service Details:</strong> <?= $job['service_details']; ?></td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr><td colspan="5" style="text-align:center;">No jobs assigned yet.</td></tr>
<?php endif; ?>
</table>
</div>

<!-- Billing -->
<h2>Billing</h2>

<!-- Success Message (only shown for billing actions) -->
<?php if(isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success" id="successMessage">
        <i class="fas fa-check-circle"></i> <?= $_SESSION['success_msg'] ?>
    </div>
    <?php unset($_SESSION['success_msg']); // Clear the message after displaying ?>
<?php endif; ?>

<?php if(isset($error_msg)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?= $error_msg ?>
    </div>
<?php endif; ?>

<div class="table-wrapper">
<table>
<tr>
    <th>Bill ID</th>
    <th>Customer Name</th>
    <th>Vehicle Model</th>
    <th>Total Amount</th>
    <th>Payment Status</th>
    <th>Bill Date</th>
    <th>Action</th>
</tr>
<?php if($billResult->num_rows > 0): ?>
    <?php while($bill = $billResult->fetch_assoc()): ?>
    <tr>
        <td><?= $bill['bill_id']; ?></td>
        <td><?= $bill['customer_name']; ?></td>
        <td><?= $bill['vehicle_model']; ?></td>
        <td>₹<?= number_format($bill['total_amount'],2); ?></td>
        <td><span class="status-<?= strtolower($bill['payment_status']); ?>"><?= ucfirst($bill['payment_status']); ?></span></td>
        <td><?= $bill['bill_date']; ?></td>
        <td>
            <?php if($bill['payment_status']=='pending'): ?>
                <a class="action-btn" href="manager_dashboard.php?mark_paid=<?= $bill['bill_id']; ?>">
                    <i class="fas fa-check"></i> Mark as Paid
                </a>
            <?php else: ?>
                <span class="status-paid"><i class="fas fa-check-circle"></i> Paid</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr><td colspan="7" style="text-align:center;">No bills generated yet.</td></tr>
<?php endif; ?>
</table>
</div>
</div>

<footer>
&copy; <?= date("Y"); ?> AutoCare Garage. All rights reserved.
</footer>

<!-- Collapsible Row Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.clickable-row');
    rows.forEach(row => {
        row.addEventListener('click', function() {
            const targetId = row.getAttribute('data-target');
            const detailsRow = document.getElementById(targetId);
            if(detailsRow.style.display === 'table-row'){
                detailsRow.style.display = 'none';
            } else {
                detailsRow.style.display = 'table-row';
            }
        });
    });

    // Auto-hide success message after 3 seconds
    const successMessage = document.getElementById('successMessage');
    if(successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 3000);
    }
});
</script>

</body>
</html>