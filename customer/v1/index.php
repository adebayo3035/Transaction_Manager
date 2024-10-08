<?php
// include_once "header.php"; ?>

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
        color: #A020F0;
        font-weight: 700;
        text-align: center;
    }
    .container .login form #btnSubmit{
        background-color: #A020F0;
        align-self: center;
        width: 100%
    }
    .container .login form #btnSubmit:hover{
        transition: background 0.3s, transform 0.3s;
        color:#A020F0;
        background-color: #fff;
        font-weight: 800;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        border: 1px solid #A020F0;
    }
    .container .login a{
        color:#A020F0;
    }
    .container .login a:hover{
        color:#3457D5;
    }
</style>

<body>
    <div class="container">
        <header> <h1>WELCOME TO KARAKATA </h1></header>

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
        </div>
    </div>
</body>

<script src ="../scripts/index.js"> </script>

</html>