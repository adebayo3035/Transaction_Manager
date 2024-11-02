<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications</title>
    <link rel="stylesheet" href="css/notification.css">
</head>

<body>
    <?php include "navbar.php"; ?>

    <div class="container">
        <header class="header">
            <h1>Admin Notifications</h1>
            <button id="mark-all" class="mark-read-btn">Mark All as Read</button>
        </header>

        <main class="notification-section">
            <div id="notifications-container" class="notifications-list">
                <!-- Notifications will be dynamically inserted here -->
            </div>

            <!-- Pagination controls -->
            <div id="pagination-controls" class="pagination-controls">
                <button id="prev-page" class="pagination-btn" disabled>Previous</button>
                <span id="current-page">Page 1</span>
                <button id="next-page" class="pagination-btn">Next</button>
            </div>

            <button id="refresh-notifications" class="refresh-btn">Refresh Notifications</button>
        </main>

    </div>

    <script src="scripts/notification.js"></script>
</body>

</html>
