<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/settings.css">
    <!-- <link rel="stylesheet" href="css/restrict_account.css"> -->
    <title>KaraKata - Transaction Manager Settings</title>
</head>

<body>
    <?php include 'navbar.php'; ?> <!-- Include the navbar -->
    

    <div class="container">
        <h1>Explore Quick Links</h1>
        <ul class="links-list">
            <li><button id = "reset-link" style="background-color: #000;">Reset Secret Question and Answer</button></li>
            <li><button id="block-account" style="background-color: #f44336;">Restrict/Block Account</button></li>
            <li><button id="unblock-account" style="background-color: orange;">Unblock/Remove Lien</button></li>
            <li><button id="unlock-account" style="background-color: #111827;">Unlock Account</button></li>
            
        </ul>
    </div>

    <!-- Modal Structure to Reset Password -->
    <div id="resetQuestionAnswerModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
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
                <input type="submit" class="button" value="Reset">
            </form>
        </div>
    </div>

    <!-- Modal Structure to Restrict and Block Account -->
    <div id="restrictionModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Restrict or Block Account</h2>
            <form id="restrictionForm" autocomplete="off">
                <div class="input-box">
                    <label for="staffName">Select an Account</label>
                    <select id="staffName" name="staffName" required>
                        <option value="">--Select a Staff Name--</option>
                    </select>
                </div>
                <div class="input-box">
                    <label for="restrictionType">Select Restriction Type:</label>
                    <select id="restrictionType" name="restrictionType" required>
                        <option value="">-- Select Lien Type -- </option>
                        <option value="restrict">Restrict</option>
                        <option value="block">Block</option>
                    </select>
                </div>

                <input type="submit" class="button" id="restrictBtn" value="Apply">
            </form>

        </div>

    </div>
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <p id="confirmationMessage">Are you sure you want to proceed?</p>
            <button id="confirmButton" class="confirmButton">Yes</button>
            <button id="cancelButton" class="cancelButton">No</button>
        </div>
    </div>


     <!-- Modal Structure to remove Restriction and Unblock Account -->
     <div id="removeRestrictionModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Remove Restrict or Unblock Account</h2>
            <form id="removeRestrictionForm" autocomplete="off">
                <div class="input-box">
                    <label for="staff">Select an Account</label>
                    <select id="staff" name="staff" required>
                        <option value="">--Select a Staff Name--</option>
                    </select>
                </div>
                <div class="input-box">
                    <label for="unblockType">Select Restriction Type:</label>
                    <select id="unblockType" name="unblockType" required>
                        <option value="">-- Select Lien Type -- </option>
                        <option value="unrestrict">Remove Restriction</option>
                        <option value="unblock">Unblock</option>
                    </select>
                </div>

                <input type="submit" class="button" id="unblockBtn" value="Submit">
            </form>

        </div>

    </div>
    <div id="confirmationModal2" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <p id="confirmationMessage2">Are you sure you want to proceed?</p>
            <button id="confirmButton2" class="confirmButton">Yes</button>
            <button id="cancelButton2" class="cancelButton">No</button>
        </div>
    </div>

     <!-- Modal to Unlock Account -->
     <div id="unlockModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Unlock Account</h2>
            <form id="unlockAccountForm" autocomplete="off">
            <div class="input-box">
                    <label for="accountType">Select Account Type</label>
                    <select name="accountType" id="accountType" required>
                    <option value="">-- Select Account Type -- </option>
                        <option value="Customer">Customer's Account</option>
                        <option value="Driver">Driver's Account</option>
                        <option value="Staff">Staff Account</option>
                    </select>
                </div>
                <div class="input-box">
                    <label for="userID">E-mail or Phone Number</label>
                    <input type="text" id="userID" name="userID" placeholder ="Enter Phone Number or E-mail Address " required>
                </div>

                <input type="submit" class="button" id="unlockBtn" value="Submit">
            </form>

        </div>

    </div>
    <div id="confirmationModal3" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <p id="confirmationMessage3">Are you sure you want to proceed?</p>
            <button id="confirmButton3" class="confirmButton">Yes</button>
            <button id="cancelButton3" class="cancelButton">No</button>
        </div>
    </div>


    <script src="scripts/settings.js"></script>
</body>

</html>