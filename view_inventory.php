<?php
session_start();
include 'db_connect.php';

// Ensure manager is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager'){
    header("Location: ../login.php");
    exit();
}

$result = $conn->query("SELECT * FROM SpareParts");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory Management</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #4361ee;
    --primary-light: #eef2ff;
    --secondary: #3f37c9;
    --success: #4cc9f0;
    --text: #2d3748;
    --text-light: #718096;
    --border: #e2e8f0;
    --bg: #f7fafc;
    --white: #ffffff;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.6;
    padding: 20px;
    min-height: 100vh;
}

.container {
    max-width: 1100px;
    margin: 0 auto;
    background: var(--white);
    border-radius: 12px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 25px 30px;
    text-align: center;
}

.header h2 {
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.header p {
    opacity: 0.9;
    font-weight: 300;
}

.table-container {
    padding: 25px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
}

th {
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
    padding: 15px 12px;
    text-align: left;
    border-bottom: 2px solid var(--border);
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
}

tr:hover {
    background: #f8faff;
    transition: background 0.2s;
}

.actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn:hover {
    background: var(--secondary);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 1px solid var(--primary);
}

.btn-outline:hover {
    background: var(--primary-light);
}

.price {
    font-weight: 600;
    color: var(--primary);
}

.quantity {
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 20px;
    background: #f0fff4;
    color: #2d7d32;
    display: inline-block;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-light);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: var(--border);
}

@media (max-width: 768px) {
    .container {
        border-radius: 8px;
    }
    
    .header {
        padding: 20px;
    }
    
    .table-container {
        padding: 15px;
    }
    
    table {
        font-size: 0.9rem;
    }
    
    th, td {
        padding: 10px 8px;
    }
    
    .actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Spare Parts Inventory</h2>
        <p>Manage your inventory efficiently</p>
    </div>
    
    <div class="table-container">
        <?php if($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Model</th>
                    <th>Supplier</th>
                    <th>Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['part_name']; ?></td>
                    <td><?= $row['model'] ? $row['model'] : '-'; ?></td>
                    <td><?= $row['supplier'] ? $row['supplier'] : '-'; ?></td>
                    <td><span class="quantity"><?= $row['quantity']; ?></span></td>
                    <td class="price">₹<?= $row['price']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>No Spare Parts Found</h3>
            <p>Get started by adding your first spare part to inventory</p>
        </div>
        <?php endif; ?>
        
        <div class="actions">
            <a href="spare_parts.php" class="btn">
                <i class="fas fa-plus-circle"></i> Add New Spare Part
            </a>
            <a href="manager_dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<script>
// Add subtle row animation
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(10px)';
        
        setTimeout(() => {
            row.style.transition = 'all 0.4s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>
</body>
</html>