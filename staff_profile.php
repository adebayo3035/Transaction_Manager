<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/profile.css">
    <title>Staff Profile</title>
</head>


<body>
<?php include "navbar.php"; $unique_id = $_SESSION['unique_id'];?>
<div class="profile-container">
    <div class="profile-header">
        <img src="backend/admin_photos/<?php echo $row['photo']; ?>" alt="Staff Photo">
        <?php echo "<span><a href='change_picture.php?id=" . $unique_id . "'><span class='edit-icon'>&#9998;</span></a></span>"; ?>
        <h1> <?php echo "{$firstname} {$lastname}"; ?> </h1>
        
        

        
        <p>Position: <?php echo "{$role}"; ?></p>
    </div>
    <div class="profile-details">
        <div class="detail">
            <label>First Name:</label>
            <p><?php echo "{$firstname}"; ?></p>
        </div>
        <div class="detail">
            <label>Last Name:</label>
            <p><?php echo "{$lastname}"; ?></p>
        </div>
        <div class="detail">
            <label>Email:</label>
            <p><?php echo "{$email}"; ?></p>
        </div>
        <div class="detail">
            <label>Phone Number:</label>
            <p><?php echo "{$phone}"; ?></p>
        </div>
    </div>
</div>


</body>
</html>
