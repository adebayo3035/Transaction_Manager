<!DOCTYPE html>
<html lang="en"></html>
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <link rel="stylesheet" href="../../css/style.css">
</head>
<style>
    body {
        background-color: #fff;
    }

    .container {
        /* background-color: #28a745; */
        border-radius: 10px;

    }

    h3#signInText {
        color: #28a745;
        font-weight: 700;
        text-align: center;
    }

    .container .login form #btnSubmit {
        background-color: #28a745;
        align-self: center;
        width: 100%
    }

    .container .login form #btnSubmit:hover {
        transition: background 0.3s, transform 0.3s;
        color: #28a745;
        background-color: #fff;
        font-weight: 800;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);

        border: 1px solid #28a745;
    }

    .container .login a {
        color: #3B71CA;
    }

    .container .login a:hover {
        color: #3457D5;
    }
</style>

<body>
    <div class="container">
        <header>
            <h1>WELCOME TO KARAKATA </h1>
        </header>

        <div class="login">
            <h3 id="signInText">SIGN IN TO YOUR ACCOUNT</h3>

            <form method="POST" autocomplete="off" id="login-form">
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
                        <input type="checkbox" id="viewPassword" name="viewPassword"
                            onchange="togglePasswordVisibility()"> Show Password
                    </label>
                </div>
                <input type="submit" id="btnSubmit" class="button" value="SIGN IN"></button>
            </form>
            <div id="message"></div>
            <a href="reset_password.php"> Forget your password?</a>
            <a href="secret_question.php"> Forgot your Secret Question?</a>
            <a href="#" id = "reactivateAccountBtn"> Unlock My Account</a>
        </div>
    </div>
    <!-- Modal Structure to Raise Account Reactivation Request -->
    <div id="accountActivationModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Raise Account Re-activation Request</h2>

            <!-- Tab Navigation Pills -->
            <div class="tab-container">
                <button class="tab-button active" onclick="openTab(event, 'generateOTPTab')">Generate OTP</button>
                <button class="tab-button" onclick="openTab(event, 'validateOTPTab')">Validate & Submit</button>
            </div>

            <!-- Generate OTP Tab -->
            <div id="generateOTPTab" class="tab-content" style="display: block;">
                <form id="otpGenerationForm" autocomplete="off">
                    <div class="input-box">
                        <label for="emailActivateAccount">Enter your email address</label>
                        <input type="email" name="emailActivateAccount" id="emailActivateAccount"
                            placeholder="your-email@example.com" required />
                        <button type="submit" id="sendOTP" class="button">Generate & Send OTP</button>
                        <span id="OTPResponse"></span>
                    </div>
                </form>
            </div>

            <!-- Validate OTP and Submit Request Tab -->
            <div id="validateOTPTab" class="tab-content">
                <form id="accountActivationForm" autocomplete="off">
                    <div class="input-box">
                        <label for="otp">Enter OTP</label>
                        <input type="text" name="otp" id="otp" disabled required />
                    </div>
                    <div class="input-box">
                        <label for="validateEmail">Email-Address</label>
                        <input type="email" name="validateEmail" id="validateEmail"
                            placeholder="Enter your E-mail address" disabled required />
                    </div>
                    <div class="input-box">
                        <label for="reason" class="form-label">Reason for Reactivation</label>
                        <textarea id="reason" name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                    <input type="submit" class="button" value="Submit Request">
                </form>
            </div>

            <div class="displayQuestion" id="displayResponse"></div>
        </div>
    </div>
</body>

<script src="../scripts/index.js"> </script>

</html>