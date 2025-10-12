<h1>Garage Management System</h1>
<p>This is a minimal vanilla PHP MVC scaffold running on XAMPP.</p>

<?php if (!empty($user)): ?>
    <div class="card">
        <h3>Your Dashboard (<?= htmlspecialchars($user['role']) ?>)</h3>
        <ul>
            <?php if ($user['role'] === 'customer'): ?>
                <li><a href="#">Book Appointment</a> (coming soon)</li>
                <li><a href="#">View Vehicle Status</a> (coming soon)</li>
                <li><a href="#">Pay Bill</a> (coming soon)</li>
            <?php elseif ($user['role'] === 'supervisor'): ?>
                <li><a href="#">View Appointments</a> (coming soon)</li>
                <li><a href="#">Assign Jobs</a> (coming soon)</li>
                <li><a href="#">Manage Employees</a> (coming soon)</li>
            <?php elseif ($user['role'] === 'mechanic'): ?>
                <li><a href="#">View Assigned Jobs</a> (coming soon)</li>
                <li><a href="#">Update Job Status</a> (coming soon)</li>
                <li><a href="#">Request Spare Parts</a> (coming soon)</li>
            <?php elseif ($user['role'] === 'manager'): ?>
                <li><a href="#">Manage Spare Parts</a> (coming soon)</li>
                <li><a href="#">Approve Spare Part Requests</a> (coming soon)</li>
                <li><a href="#">Generate Bills & Reports</a> (coming soon)</li>
            <?php endif; ?>
        </ul>
    </div>
<?php else: ?>
    <div class="card">
        <p>Please <a href="<?= BASE_URL ?>/login">log in</a> to access your dashboard.</p>
        <p>Demo users (password: <code>password</code>):</p>
        <ul>
            <li>customer@example.com</li>
            <li>supervisor@example.com</li>
            <li>mechanic@example.com</li>
            <li>manager@example.com</li>
        </ul>
    </div>
<?php endif; ?>
