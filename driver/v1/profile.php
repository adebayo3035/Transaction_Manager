<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/profile.css">
    <link rel="stylesheet" href="../../customer/css/cards.css">
    <link rel="stylesheet" href="../../customer/css/view_orders.css">
    <link rel="stylesheet" href="../../css/view_driver.css">

</head>
<title>Driver's Profile</title>
</head>

<body>
    <?php include "driver_navbar.php"; ?>
    <div class="profile-container">
        <div class="profile-header">
            <img id="profile-picture" src="../../backend/customer_photos/default.jpg" alt="Customer Profile Picture">
            <!-- <span><a href='update_picture.php'><span class='edit-icon'>&#9998;</span></a></span> -->
            <span><a href="#" id="edit-profile-picture"><span class="edit-icon">&#9998;</span></a></span>

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
            <button id="modifyDriverDetails"> Edit Profile </button>
            <button id="reset-link" style="background-color: #000;"> Reset Secret Question and Answer </button>
        </div>
    </div>

    <!-- Modal  to Update Driver Profile Picture Structure -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <form id="adminForm" method="POST" enctype="multipart/form-data" autocomplete="off">
                <h2>Update Your Picture</h2>
                <div id="photoContainer">
                    <img id="uploadedPhoto" src="../../backend/customer_photos/default.jpg" alt="Uploaded Photo">
                </div>
                <label for="photo">Photo:</label>
                <input type="file" id="photo" name="photo" accept="image/*" required>

                <label for="secret_answer">Secret Answer:</label>
                <input type="password" id="secret_answer" name="secret_answer" required>

                <button type="submit" name="btnChangeCustomerPicture">Change Picture</button>
                <div class="message" id="message"></div>
            </form>
        </div>
    </div>

    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close2 close">&times;</span>
            <h2>Driver Information</h2>
            <div id="driverPhoto" class="photo-container">

            </div>
            <table id="orderDetailsTable" class="ordersTable">
                <tbody>
                    <!-- Driver details will be automatically populated here -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Structure to Reset Secret Question and Answer -->
    <div id="resetQuestionAnswerModal" class="modal">
        <div class="modal-content">
            <span class="close close3">&times;</span>
            <h2>Reset Your Secret Question and Answer</h2>
            <form id="resetQuestionAnswerForm" autocomplete="off">
                <div class="input-box">
                    <label for="resetEmail">Enter your email</label>
                    <input type="email" name="resetEmail" id="resetEmail" placeholder="your-email@example.com"
                        required />
                </div>
                <div class="input-box">
                    <label for="resetPassword">Enter your Password</label>
                    <input type="password" name="resetPassword" id="resetPassword" placeholder="Enter Your Password"
                        required />
                </div>
                <div class="input-box">
                    <label for="secretQuestion">Enter your new Secret Question</label>
                    <input type="text" name="secretQuestion" id="secretQuestion" placeholder="enter secret question"
                        required />
                </div>
                <div class="input-box">
                    <label for="resetSecretAnswer">Enter your Secret Answer</label>
                    <input type="password" name="resetSecretAnswer" id="resetSecretAnswer"
                        placeholder="Enter Secret Answer" required />
                </div>
                <div class="input-box">
                    <label for="confirmAnswer">Confirm Secret Answer</label>
                    <input type="password" name="confirmAnswer" id="confirmAnswer"
                        placeholder="Confirm your secret answer" required />
                </div>
              
                <button type="submit" class="button" value="Reset">Reset</button>
            </form>
        </div>
    </div>

    <script src="../scripts/profile.js"></script>
</body>

</html>