<?php 
  session_start();
  if(isset($_SESSION['unique_id'])){
    header("location: home.php");
  }
?>

<?php 
include_once "header.php"; ?>
<head> <link rel="stylesheet" href="css/style.css"> </head>

<body>
    <div class="container">
        <header> WELCOME TO TRANSACTION MANAGER</header>
       
        <div class="login">
            <h3 id="signInText">SIGN IN TO YOUR ACCOUNT</h3>
            
            <form action="#" method="POST" enctype="multipart/form-data" autocomplete="off">
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
                    <input type="checkbox" id="rememberMe" name="rememberMe" /> Remember
                    Me
                    </label>
                </div>
                <input type="submit" id="btnSubmit" class="button" value ="SIGN IN"></button>
            </form>
            <a href="customer_onboarding.html"> Forget your password?</a>
            <a href="admin_onboarding.php"> Don't have an Account?</a>
        </div>
    </div>
</body>

<script src = "scripts/index.js" charset="UTF-8"></script>

</html>