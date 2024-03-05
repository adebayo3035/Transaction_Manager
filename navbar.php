 <!-- font awesome -->
 <script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Nunito&family=Roboto&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="css/navbar.css">
<?php include "session_checker.php" ?>
<header class="nav-header">
        <?php 
         $sql = mysqli_query($conn, "SELECT * FROM admin_tbl WHERE unique_id = {$_SESSION['unique_id']}");
         
         if(mysqli_num_rows($sql) > 0){
           $row = mysqli_fetch_assoc($sql);
           $firstname = $row['firstname'];
            $lastname = $row['lastname'];
            $phone = $row['firstname'];
            $email = $row['lastname'];
         }
        
        $userId = isset($_SESSION['unique_id']) ? $_SESSION['unique_id'] : 'null';
      ?>

  <div class="navbar">
        <a href="homepage.php">Home</a>
        <a href="staffs.php">Staffs</a>
        <a href="customer.php">Customers</a>
        <a href="dashboard.php">Transactions</a>
        <a href="groups.php">Groups</a>
        <a href="units.php">Units</a>
        <a href="teams.php">Teams</a>


      <div class="dropdown">
          <button class="dropbtn">
            <img src="backend/admin_photos/<?php echo $row['photo']; ?>" alt="User Image" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 8px;"> <i class="fa fa-sort-desc" aria-hidden="true"></i>
            <?php echo "Welcome, {$firstname} {$lastname}"; ?>
          </button>
          <div class="dropdown-content">
            <?php echo "<a href='staff_profile.php?id=" . $row['unique_id'] . "'>Profile</a></td>"; ?>
              <a href="settings.php">Settings</a>
              <a href="backend/logout.php?logout_id=<?php echo $row['unique_id']; ?>">Logout</a>
          </div>
      </div>
  </div>
</header>