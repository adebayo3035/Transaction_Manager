<!-- font awesome -->
<!-- <script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Nunito&family=Roboto&display=swap" rel="stylesheet" /> -->
<link rel="stylesheet" href="../css/navbar.css">
<link rel="icon" href="../images/trans_manager.png">
<?php
session_start();
include ("../v2/config.php");
include ("../v1/header.php");

if (!isset($_SESSION['driver_id'])) {
    header("Location: index.php");
    exit();
}
$driverId = $_SESSION["driver_id"];
$stmt = $conn->prepare("SELECT status FROM driver WHERE id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        $stmt->bind_result($status);
        $stmt->fetch();
        $stmt->close();
        
?>
<header>
    <h1><a href="homepage.php">KaraKata </a></h1>
    <div class="user-info">
        <span id="customerName">Welcome, <?php echo $_SESSION['driver_name'] . " ($driverId)"; ?></span>
        <span id="walletBalance">Your Current Status is : <?php echo $status; ?></span>
        <button id="logoutButton"><a
                href="../v2/logout.php?logout_id=<?php echo $_SESSION['driver_id']; ?>">Logout</a>
            </button>
    </div>
</header>
<nav>
    <ul>
        <li><a href="dashboard.php" id="orderFood">Update Order Status</a></li>
        <li><a href="view_orders.php" id="viewOrders">View Order History</a></li>
        <li><a href="profile.php" id="fundWallet">My Profile</a></li>
        
    </ul>
    
</nav>