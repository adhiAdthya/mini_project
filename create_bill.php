<?php
session_start();
include 'db_connect.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager'){
    header("Location: ../login.php");
    exit();
}

// Check if coming from "Mark as Paid" with appointment ID
$prefill_paid = isset($_GET['pay']) ? $_GET['pay'] : 0;
$prefill_appointment_id = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : 0;

$appQuery = "
    SELECT Appointments.id AS app_id, Appointments.vehicle_model, Appointments.vehicle_number, Users.name AS customer_name, Users.id AS customer_id
    FROM Appointments
    JOIN Users ON Appointments.customer_id = Users.id
    ORDER BY Appointments.id DESC
";
$appResult = $conn->query($appQuery);

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $appointment_id = $_POST['appointment_id'];
    $customer_id    = $_POST['customer_id'];
    $total_amount   = $_POST['total_amount'];
    $payment_status = $_POST['payment_status'];

    $stmt = $conn->prepare("INSERT INTO Bills (appointment_id, customer_id, total_amount, payment_status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iids", $appointment_id, $customer_id, $total_amount, $payment_status);

    if($stmt->execute()){
        header("Location: manager_dashboard.php?msg=BillCreated");
        exit();
    } else {
        $error = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generate Bill</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: white;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.container {
    background: #f8fafc;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    width: 100%;
    max-width: 500px;
    border: 1px solid #e2e8f0;
}

.header {
    background: #1e293b;
    color: white;
    padding: 25px;
    text-align: center;
    border-radius: 15px 15px 0 0;
}

.header h2 {
    font-size: 24px;
    font-weight: 600;
}

.header i {
    font-size: 32px;
    margin-bottom: 10px;
    color: #3b82f6;
}

.form-container {
    padding: 30px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
    font-size: 14px;
}

input, select {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.3s;
    background: white;
}

input:focus, select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

input:read-only {
    background: #f9fafb;
    color: #6b7280;
}

.btn {
    width: 100%;
    padding: 14px;
    background: #1e293b;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 10px;
}

.btn:hover {
    background: #374151;
    transform: translateY(-1px);
}

.back-btn {
    width: 100%;
    padding: 14px;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
    transition: all 0.3s;
    text-align: center;
    text-decoration: none;
    display: inline-block;
}

.back-btn:hover {
    background: #4b5563;
}

.error {
    background: #fef2f2;
    color: #dc2626;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    border-left: 4px solid #dc2626;
    font-size: 14px;
}

.paid-notice {
    background: #d1fae5;
    color: #065f46;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    border-left: 4px solid #10b981;
    font-size: 14px;
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <i class="fas fa-file-invoice-dollar"></i>
        <h2>Generate Bill</h2>
        <?php if($prefill_paid): ?>
            <p style="margin-top:10px; opacity:0.8;">Marking appointment as paid</p>
        <?php endif; ?>
    </div>

    <div class="form-container">
        <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
        <?php if($prefill_paid): ?>
            <div class="paid-notice">
                <i class="fas fa-check-circle"></i> Pre-selecting "Paid" status for this bill
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Select Appointment</label>
                <select name="appointment_id" required onchange="updateCustomer()" id="appSelect">
                    <option value="">-- Choose Appointment --</option>
                    <?php while($row = $appResult->fetch_assoc()): ?>
                        <option value="<?= $row['app_id']; ?>" 
                                data-customer="<?= $row['customer_id']; ?>"
                                <?= ($prefill_appointment_id == $row['app_id']) ? 'selected' : '' ?>>
                            <?= $row['vehicle_model']." (".$row['vehicle_number'].") - ".$row['customer_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Customer ID</label>
                <input type="text" name="customer_id" id="customerId" readonly required>
            </div>

            <div class="form-group">
                <label>Total Amount (₹)</label>
                <input type="number" name="total_amount" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Payment Status</label>
                <select name="payment_status" required>
                    <option value="pending">Pending</option>
                    <option value="paid" <?= $prefill_paid ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-file-invoice"></i> Generate Bill
            </button>
        </form>

        <!-- Back to Dashboard Button -->
        <a href="manager_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    </div>
</div>

<script>
function updateCustomer() {
    const select = document.getElementById('appSelect');
    const customerField = document.getElementById('customerId');
    const selectedOption = select.options[select.selectedIndex];
    
    if(select.selectedIndex > 0) {
        customerField.value = selectedOption.getAttribute('data-customer');
    } else {
        customerField.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateCustomer();
});
</script>

</body>
</html>
