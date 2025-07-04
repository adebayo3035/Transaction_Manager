<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/navbar.css">
    <link rel="icon" href="../images/trans_manager.png">

<body>
    <header>
        <h1><a href="dashboard.php">KaraKata </a></h1>
        <div class="user-info">
            <span id="customerName">Loading...</span>
            <span id="walletBalance">Loading Status...</span>
            
            <button id="logoutButton"><a href="../v2/logout.php">Logout</a>
            </button>
        </div>
    </header>
    <nav>
        <ul>
            <li><a href="dashboard.php" id="orderFood">Update Order Status</a></li>
            <li><a href="view_orders.php" id="viewOrders">My Orders</a></li>
            <li><a href="profile.php" id="fundWallet">My Profile</a></li>
            <li><a href="account_history.php" id="fundWallet">Account History</a></li>
        </ul>
        <span id="wallet">Loading Balance...</span>
        <span id="dateTimeLabel">Loading Time...</span>
    </nav>
    <script src="../scripts/navbar.js"></script>
</body>