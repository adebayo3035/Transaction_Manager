<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/settings.css">
    <title>Beautiful Links</title>
</head>
<body>
<?php include "navbar.php"; $unique_id = $_SESSION['unique_id'];?>
<div class="container">
    <h1>Explore Quick Links</h1>
    <ul class="links-list">
        <?php
            echo "<li><a href='edit_staff.php?id=" . $unique_id . "'>Edit Profile</a></li>";
           
            echo "<li><a href='secret_answer.php?id=" . $unique_id . "'>Reset Secret Question and Answer</a></li>";
        ?>
        <li><a href="https://example4.com">Delete Account</a></li>
        <li><a href="https://example5.com">Restrict Account</a></li>
        
    </ul>
</div>
<?php echo $_SESSION["unique_id"]; ?>

</body>
</html>
