<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/navbar.css">
    <link rel="icon" href="../images/trans_manager.png">
    <script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
    <title>Dashboard</title>
</head>
<body>
<header>
    <h1><a href="homepage.php">KaraKata</a></h1>
    <div class="user-info">
        <span id="customerName">Loading...</span>
        <span id="walletBalance">Loading balance...</span>
        <button id="logoutButton"><a href="../v2/logout.php">Logout</a></button>
    </div>
</header>
<nav>
    <ul>
        <li><a href="dashboard.php" id="orderFood">Order Food</a></li>
        <li><a href="view_orders.php" id="viewOrders">Orders</a></li>
        <li><a href="view_credit.php" id="viewOrders">Credit History</a></li>
        
        <!-- <li><a href="profile.php" id="fundWallet">My Profile</a></li> -->
        <li><a href="transactions.php" id="viewOrders">Transactions</a></li>
        <li><a href="profile.php" id="fundWallet">Profile</a></li>
        <li><a href="cards.php" id="fundWallet">Self Service</a></li>
        <!-- <li><a href="generate_token.php" id="fundWallet">Generate Token</a></li> -->
        
    </ul>
</nav>

<script src="../scripts/navbar.js"></script>

</body>
</html>
