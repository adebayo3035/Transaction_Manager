<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaraKata - Transaction Manager </title>
    <link rel="stylesheet" href="css/navbar.css">
    <!-- font awesome -->
    <script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito&family=Roboto&display=swap" rel="stylesheet" />
</head>

<body>
    <?php include "session_checker.php"; ?>
    <?php
    $sql = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE unique_id = {$_SESSION['unique_id']}");
    if (mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_assoc($sql);
        $firstname = $row['firstname'];
        $lastname = $row['lastname'];
        $phone = $row['phone'];
        $email = $row['email'];
        $role = $row['role'];
        $unique_id = $row['unique_id'];
        $restriction_id = $row['restriction_id'];
    }
    $userId = isset($_SESSION['unique_id']) ? $_SESSION['unique_id'] : 'null';
    ?>
    <div class="topnav" id="myTopnav">
        <a href="homepage.php" class="active">Home</a>
        <a href="staffs.php">Staffs</a>
        <a href="customer.php">Customers</a>
        <a href="dashboard.php">Transactions</a>
        <a href="groups.php">Groups</a>
        <a href="units.php">Units</a>
        <a href="food.php">Foods</a>
       
        <div class="dropdown">
            
            <button class="dropbtn"><?php echo "Welcome, {$firstname} {$lastname}"; ?>
                
                <i class="fa fa-caret-down"></i>
                
            </button>
            <div class="dropdown-content">
                <?php echo "<a href='staff_profile.php?id=" . $row['unique_id'] . "'>Profile</a>"; ?>
                <a href="settings.php">Settings</a>
                <a href="admin_notification.php">Notifications (<span id="notification-badge"> 0 </span>)</a>
                <a href="backend/logout.php?logout_id=<?php echo $row['unique_id']; ?>">Logout</a>
            </div>
        </div>
        <a href="javascript:void(0);" class="icon" onclick="myFunction()">&#9776;</a>
    </div>
    <script>
        /* Toggle between adding and removing the "responsive" class to topnav when the user clicks on the icon */
        function myFunction() {
            var x = document.getElementById("myTopnav");
            if (x.className === "topnav") {
                x.className += " responsive";
            } else {
                x.className = "topnav";
            }
        }
    </script>
    <script src = "scripts/notification.js"></script>
</body>

</html>