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
    <div class="header">
        <h1>Admin Notifications</h1>
    </div>

    <main>
        <span id="role"></span>
        <div id="notifications-container">
            <!-- Notifications will be dynamically inserted here -->
        </div>

        <!-- Pagination buttons -->
        <div id="pagination-controls" class="pagination-controls">
            <button id="prev-page" class="pagination-btn" disabled>Previous</button>
            <span id="current-page">Page 1</span>
            <button id="next-page" class="pagination-btn">Next</button>
        </div>
        <button id="refresh-notifications">Refresh Notifications</button>
    </main>

    <script src="scripts/notification.js"></script> <!-- Link to your JavaScript file -->
</body>

</html>