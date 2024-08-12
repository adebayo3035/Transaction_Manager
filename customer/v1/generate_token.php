<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Token</title>
    <style>
        .token-container { margin: 20px; }
        .copy-btn { cursor: pointer; }
    </style>
</head>
<body>
<?php include ('../customerNavBar.php'); ?>
    <div class="token-container">
        <button id="generate-token">Generate Token</button>
        <br><br>
        <input type="text" id="token" readonly>
        <button class="copy-btn" onclick="copyToken()">Copy Token</button>
    </div>
<script src="../scripts/generate_token.js"></script>
       
    
</body>
</html>
