<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/profile.css">
    <title>Customer Profile</title>
</head>

<body>
    <?php include "../customerNavBar.php"; ?>
    <div class="profile-container">
        <div class="profile-header">
            <img id="profile-picture" src="../../backend/customer_photos/default.jpg" alt="Customer Profile Picture">
            <span><a href='update_picture.php'><span class='edit-icon'>&#9998;</span></a></span>
            <h1 id="customer-name">My Name</h1>
        </div>
        <div class="profile-details">
            <div class="detail">
                <label>First Name:</label>
                <p id="first-name">First Name</p>
            </div>
            <div class="detail">
                <label>Last Name:</label>
                <p id="last-name">Last Name</p>
            </div>
            <div class="detail">
                <label>Email:</label>
                <p>
                    <span class="result" id="display-email"></span>
                    <br>
                    <span class="toggle-indicator">ON</span>
                    <label class="switch">
                        <input type="checkbox" id="toggle-checkbox">
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-indicator">OFF</span>
                </p>
            </div>
            <div class="detail">
                <label>Phone Number:</label>
                <p>
                    <span class="result" id="display-phone"></span>
                    <br>
                    <span class="toggle-indicator">ON</span>
                    <label class="switch">
                        <input type="checkbox" id="toggle-checkbox1">
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-indicator">OFF</span>
                </p>
            </div>
        </div>
    </div>
    <script src="../scripts/profile.js"></script>
    </body>
    </html>