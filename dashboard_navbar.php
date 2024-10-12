<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="css/dashboard.css"> <!-- Include your styles file -->
</head>
<body>
<?php
// session_start();

// Check if the user is logged in and retrieve their role
if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
} else {
    // Redirect to login or handle unauthenticated users
    header('Location: login.php');
    exit();
}
?>

<nav>
    <ul class="nav-links">
        <!-- Common links for both Admin and Super Admin -->
        <li><a href="dashboard.php" id="orderFood">Manage Orders</a></li>
        <li><a href="pending_orders.php" id="viewOrders">View Pending Orders</a></li>
        
        <!-- Links only visible to Super Admin -->
        <?php if ($role === 'Super Admin'): ?>
            <li><a href="revenue_dashboard.php" id="fundWallet">Manage Revenue</a></li>
            <li><a href="orders.php" id="allOrder">All Orders</a></li>
            <li><a href="transactions.php" id="allOrder">Transactions</a></li>
        <?php endif; ?>
    </ul>
    
    <a href="javascript:void(0);" class="icon2" onclick="toggleNav()">
        <i class="fa fa-bars"></i>
    </a>
</nav>

</body>
</html>