<!-- font awesome -->
<script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Nunito&family=Roboto&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="../css/navbar.css">
<?php
session_start();
include ("../v2/config.php");
include ("../v1/header.php");

if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php");
    exit();
}
$customerId = $_SESSION["customer_id"];
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();
        
?>
<header>
    <h1>Customer Dashboard</h1>
    <div class="user-info">
        <span id="customerName">Welcome, <?php echo $_SESSION['customer_name']; ?></span>
        <span id="walletBalance">Your Current Balance is : N<?php echo $balance; ?></span>
        <button id="logoutButton"><a
                href="../v2/logout.php?logout_id=<?php echo $_SESSION['customer_id']; ?>">Logout</a></button>
    </div>
</header>
<nav>
    <ul>
        <li><a href="dashboard.php" id="orderFood">Order Food</a></li>
        <li><a href="view_orders.php" id="viewOrders">View Orders</a></li>
        <li><a href="add_funds.php" id="fundWallet">Fund Wallet</a></li>
        
    </ul>
    
</nav>