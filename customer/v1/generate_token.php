<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Token</title>
    <link rel="stylesheet" href="../css/generate_token.css">
    <style>
        .token-container {
            margin: 20px;
        }

        .copy-btn {
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php include ('../customerNavBar.php'); ?>
    <div class="token-container">
        <h2> KaraKata e-Token</h2>
        <span class="header"> Click on the "Generate Token" button to get a One Time password to complete transactions securely.</span>
        <input type="text" id="token" readonly>


        <div class="timer-container">
            <svg class="progress-ring" width="120" height="120">
                <circle class="progress-ring__circle" stroke="tomato" stroke-width="10" fill="transparent" r="54"
                    cx="60" cy="60" />
            </svg>
            <div class="timer-text" id="timer">180</div>
        </div>

        <button class="copy-btn" id="copy-token" onclick="copyToken()">Copy Token</button>
        <button id="generate-token">Generate Token</button>
        <span class="footer"> This Token wil expire after 1 minute(s)</span>
    </div>
    <script src="../scripts/generate_token.js"></script>


</body>

</html>