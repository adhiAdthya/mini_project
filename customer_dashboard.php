<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$message = "";

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicle_model = $_POST['vehicle_model'];
    $vehicle_number = $_POST['vehicle_number'];
    $service_date = $_POST['service_date'];
    $service_time = $_POST['service_time'];

    $check_sql = "SELECT * FROM Appointments WHERE service_date='$service_date' AND service_time='$service_time'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        $message = "This slot is already booked. Please choose another time.";
    } else {
        $insert_sql = "INSERT INTO Appointments (customer_id, vehicle_model, vehicle_number, service_date, service_time) 
                       VALUES ('$customer_id', '$vehicle_model', '$vehicle_number', '$service_date', '$service_time')";
        if ($conn->query($insert_sql) === TRUE) {
            $message = "Appointment booked successfully!";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}

// Fetch customer appointments
$appointments_sql = "SELECT * FROM Appointments WHERE customer_id='$customer_id' ORDER BY service_date DESC, service_time DESC";
$appointments_result = $conn->query($appointments_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Dashboard - Garage Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2c3e50; --secondary: #e5b840; --accent: #3498db; --gray: #7f8c8d; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        h1, h2 { color: var(--primary); }
        .logout { float: right; text-decoration: none; background: var(--secondary); color: var(--primary); padding: 8px 15px; border-radius: 20px; font-weight: bold; transition: 0.3s; }
        .logout:hover { opacity: 0.9; }
        form { display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px; }
        input, select { padding: 10px; border-radius: 8px; border: 1px solid #ccc; width: 100%; font-size: 16px; }
        input[type="submit"] { background: var(--accent); color: #fff; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        input[type="submit"]:hover { opacity: 0.9; transform: translateY(-2px); }
        .message { padding: 10px; background: #e5f3e5; color: #2d6b2d; border-radius: 8px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 12px; text-align: center; border-bottom: 1px solid #ddd; }
        table th { background: var(--primary); color: #fff; }
        table tr:nth-child(even) { background: #f9f9f9; }
        table tr:hover { background: #f1f1f1; }
        @media(max-width:768px){ .container{ margin:20px; padding:15px; } table th, table td{ font-size:14px; padding:8px; } }
    </style>
</head>
<body>
<div class="container">
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    <h1>Welcome Customer!</h1>

    <h2>Book a Service Appointment</h2>
    <?php if($message) echo "<div class='message'>$message</div>"; ?>
    <form method="post">
        <input type="text" name="vehicle_model" placeholder="Vehicle Model" required>
        <input type="text" name="vehicle_number" placeholder="Vehicle Number" required>
        <input type="date" name="service_date" required>
        <input type="time" name="service_time" required>
        <input type="submit" value="Book Appointment">
    </form>

    <h2>Your Appointments</h2>
    <table>
        <tr>
            <th>Vehicle Model</th>
            <th>Vehicle Number</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
        </tr>
        <?php
        if ($appointments_result->num_rows > 0) {
            while ($row = $appointments_result->fetch_assoc()) {
                echo "<tr>
                        <td>".$row['vehicle_model']."</td>
                        <td>".$row['vehicle_number']."</td>
                        <td>".$row['service_date']."</td>
                        <td>".$row['service_time']."</td>
                        <td>".$row['status']."</td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No appointments found.</td></tr>";
        }
        ?>
    </table>
</div>
</body>
</html>