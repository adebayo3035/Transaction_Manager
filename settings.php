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
        <!-- <li><a href="restrict_account.php">Delete Account</a></li>
        <li><a href="restrict_account.php">Restrict Account</a></li> -->
        <?php if ($_SESSION['role'] === 'Super Admin'): ?>
           <li> <button id="block" onclick="location.href='restrict_account.php'">Restrict/Block Account</button></li>
           <li> <button id="unblock" onclick="location.href='unblock_account.php'">Unblock/Remove Lien</button></li>
        <?php else: ?>
            <li><button id="block" onclick="location.href='unauthorized.php'">Restrict/Block Account</button></li>
            <li><button id="unblock" onclick="location.href='unauthorized.php'">Unblock/Remove Lien</button></li>
        <?php endif; ?>
    </ul>
</div>


</body>
</html>
