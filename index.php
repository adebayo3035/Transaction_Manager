<?php include_once "backend/check_session.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Login</title>
</head>
<body>
    <div class="container">
        <header><h1>WELCOME TO TRANSACTION MANAGER</h1></header>

        <div class="login">
            <h3 id="signInText">SIGN IN TO YOUR ACCOUNT</h3>
            <form enctype="multipart/form-data" autocomplete="off" id="loginForm">
                <div class="errorContainer" id="errorContainer"></div>
                <div class="input-box">
                    <label for="username"> Username</label>
                    <input type="text" name="username" id="username" placeholder="barnadota@gmail.com" />
                </div>
                <div class="input-box">
                    <label for="password"> Password</label>
                    <input type="password" name="password" id="password" placeholder="........." />
                </div>
                <div class="checkbox remember_me">
                    <label>
                    <input type="checkbox" id="viewPassword" name="viewPassword" onchange="togglePasswordVisibility()"> Show Password
                    </label>
                </div>
                <input type="submit" id="btnLogin" class="button" value="SIGN IN">
            </form>
             <!-- Trigger the modal -->
             <a href="#" id="resetPasswordBtn"> Forget your password?</a>
            <a href="#" id="getSecretQuestionBtn"> Forgot your Secret Question?</a>
        </div>
    </div>

     <!-- Modal Structure to Reset Password -->
     <div id="passwordResetModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Reset Your Password</h2>
            <form id="resetPasswordForm" autocomplete="off">
                <div class="input-box">
                    <label for="resetEmail">Enter your email</label>
                    <input type="email" name="resetEmail" id="resetEmail" placeholder="your-email@example.com" required />
                </div>
                <div class="input-box">
                    <label for="resetSecretAnswer">Enter your Secret Answer</label>
                    <input type="password" name="resetSecretAnswer" id="resetSecretAnswer" placeholder="Enter Secret Answer" required />
                </div>
                <div class="input-box">
                    <label for="newPassword">New Password</label>
                    <input type="password" name="newPassword" id="newPassword" placeholder="Enter new password" required />
                </div>
                <div class="input-box">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" name="confirmPassword" id="confirmPassword" placeholder="Confirm new password" required />
                </div>
                <input type="submit" class="button" value="Reset Password">
            </form>
        </div>
    </div>

    <!-- Modal Structure to Get Secret Question -->
    <div id="getSecretQuestionModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Get Secret Question</h2>
            <form id="getSecretQuestionForm" autocomplete="off">
                <div class="input-box">
                    <label for="emailSecretQuestion">Enter your email or phone number</label>
                    <input type="email" name="emailSecretQuestion" id="emailSecretQuestion" placeholder="your-email@example.com or 09000000000" required />
                </div>
                <div class="input-box">
                    <label for="passwordSecretQuestion">Enter your password</label>
                    <input type="password" name="passwordSecretQuestion" id="passwordSecretQuestion" placeholder="Enter new password" required />
                </div>
                <input type="submit" class="button" value="Get Secret Question">
            </form>
            <div class="displayQuestion" id="displayQuestion"> </div>
        </div>
    </div>
</body>
<script src="scripts/index.js" charset="UTF-8"></script>
</html>
