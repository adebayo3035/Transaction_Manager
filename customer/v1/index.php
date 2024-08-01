<?php
include_once "header.php"; ?>

<head>
    <link rel="stylesheet" href="../../css/style.css">
</head>
<style>
    body{
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
    .container .login form #btnSubmit{
        background-color: #28a745;
        align-self: center;
        width: 100%
    }
    .container .login form #btnSubmit:hover{
        transition: background 0.3s, transform 0.3s;
        color:#28a745;
        background-color: #fff;
        font-weight: 800;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);

        border: 1px solid #28a745;
    }
    .container .login a{
        color:#28a745;
    }
    .container .login a:hover{
        color:#3457D5;
    }
</style>

<body>
    <div class="container">
        <header> <h1>WELCOME TO TRANSACTION MANAGER </h1></header>

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
            <a href="../../reset_password.php"> Forget your password?</a>
            <a href="secret_question.php"> Forgot your Secret Question?</a>
        </div>
    </div>
</body>

<script>
    document.getElementById('login-form').addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => data[key] = value);

        fetch('../v2/index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(data => {
                const message = document.getElementById('message');
                if (data.success) {
                    message.style.color = 'green';
                    message.textContent = 'Login successful!';
                    window.location.href = 'dashboard.php'; // Redirect to customer dashboard
                } else {
                    message.style.color = 'red';
                    message.textContent = 'Login failed: ' + data.message;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const message = document.getElementById('message');
                message.style.color = 'red';
                message.textContent = 'An error occurred. Please try again.';
            });
    });

    const passwordInput = document.getElementById('password');
    const showPasswordCheckbox = document.getElementById('viewPassword');

    // Function to toggle password visibility
    function togglePasswordVisibility() {
        if (showPasswordCheckbox.checked) {
            // Display password in plain text
            passwordInput.type = 'text';
        } else {
            // Encrypt password
            passwordInput.type = 'password';
        }
    }

    // Add event listener to checkbox
    showPasswordCheckbox.addEventListener('change', togglePasswordVisibility);
</script>

</html>