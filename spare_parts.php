<?php
session_start();
include 'db_connect.php';

// Ensure manager is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager'){
    header("Location: ../login.php");
    exit();
}

$message = "";

// Handle form submission
if(isset($_POST['add_part'])){
    $part_name = $_POST['part_name'];
    $model     = $_POST['model'];
    $supplier  = $_POST['supplier'];
    $quantity  = $_POST['quantity'];
    $price     = $_POST['price'];

    if(!empty($part_name) && !empty($model) && !empty($supplier) && !empty($quantity) && !empty($price)){
        // Insert into DB
        $stmt = $conn->prepare("INSERT INTO SpareParts (part_name, model, supplier, quantity, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssid", $part_name, $model, $supplier, $quantity, $price);

        if($stmt->execute()){
            $message = "Spare part added successfully!";
        } else {
            $message = "Error adding spare part: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Spare Part</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Roboto',sans-serif; background:#f0f2f5; margin:0; padding:0;}
.container{max-width:600px; margin:50px auto; background:white; padding:25px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.1);}
h2{text-align:center; margin-bottom:20px;}
form input, form button{width:100%; padding:12px; margin-bottom:15px; border-radius:5px; border:1px solid #ccc; font-size:16px;}
button{background:#4da6ff; color:white; border:none; cursor:pointer; transition:0.3s;}
button:hover{background:#3399ff;}
.message{text-align:center; margin-bottom:15px; color:green;}

/* New minimal button styles */
.action-links {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.action-link {
    flex: 1;
    text-align: center;
    padding: 12px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s;
    border: 2px solid #4da6ff;
}

.inventory-link {
    background: #4da6ff;
    color: white;
}

.inventory-link:hover {
    background: #3a90e0;
    transform: translateY(-1px);
}

.dashboard-link {
    background: white;
    color: #4da6ff;
}

.dashboard-link:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
}
</style>
</head>
<body>
<div class="container">
<h2>Add Spare Part</h2>
<?php if($message!=""): ?>
<p class="message"><?= $message; ?></p>
<?php endif; ?>
<form method="POST">
    <input type="text" name="part_name" placeholder="Spare Part Name" required>
    <input type="text" name="model" placeholder="Model" required>
    <input type="text" name="supplier" placeholder="Supplier" required>
    <input type="number" name="quantity" placeholder="Quantity" min="0" required>
    <input type="number" step="0.01" name="price" placeholder="Price per Unit" min="0" required>
    <button type="submit" name="add_part">Add Spare Part</button>
</form>

<div class="action-links">
    <a href="view_inventory.php" class="action-link inventory-link">View Inventory</a>
    <a href="manager_dashboard.php" class="action-link dashboard-link">Back to Dashboard</a>
</div>

</div>
</body>
</html>