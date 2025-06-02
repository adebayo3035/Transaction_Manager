<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaraKata - Transaction Manager</title>
    <link rel="stylesheet" href="css/navbar.css">
    <!-- font awesome -->
    <script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito&family=Roboto&display=swap" rel="stylesheet" />
</head>

<body>
    <!-- log user out after 2 minutes of Inactivity -->
  

    <div class="topnav" id="myTopnav">
        <a href="homepage.php" class="active">Home</a>
        <a href="staff.php">Staffs</a>
        <a href="driver.php">Drivers</a>
        <a href="customer.php">Customers</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="group.php">Groups</a>
        <a href="unit.php">Units</a>
        <a href="food.php">Foods</a>
         <a href="staff_account_history.php">Reactivation History</a>

        <div class="dropdown">
            <button class="dropbtn" id="welcomeMessage">Welcome!  <i class="fa fa-caret-down"></i></button>
            <div class="dropdown-content">
                <a href='staff_profile.php'>Profile</a>
                <a href="settings.php">Settings</a>
                <a href="admin_notification.php">Notifications (<span id="notification-badge">0</span>)</a>
                <a href="javascript:void(0);" id="logoutButton">Logout</a>
            </div>
        </div>
        <a href="javascript:void(0);" class="icon" onclick="myFunction()">&#9776;</a>
    </div>

    <script src="scripts/navbar.js"></script>
</body>

</html>
