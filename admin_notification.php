<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications</title>
    <link rel="stylesheet" href="css/notification.css"> <!-- Link to your CSS file -->
</head>

<body>
<?php include "navbar.php"; ?>
    <div class = "header">
        <h1>Admin Notifications</h1>
    </div>

    <main>
        <div id="notifications-container">
            <!-- Notifications will be dynamically inserted here -->
        </div>
        <button id="refresh-notifications">Refresh Notifications</button>
    </main>

    <script src="scripts/notification.js"></script> <!-- Link to your JavaScript file -->
</body>

</html>
