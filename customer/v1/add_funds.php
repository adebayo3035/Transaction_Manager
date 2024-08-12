<?php
// Retrieve the card_number from the query parameters
$cardNumber = isset($_GET['card_number']) ? htmlspecialchars($_GET['card_number']) : '';
$cvv = isset($_GET['cvv']) ? htmlspecialchars($_GET['cvv']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Funds</title>
    <link rel="stylesheet" href="../css/add_funds.css">
</head>

<body>
    <?php include ('../customerNavBar.php'); ?>
    <div class="add-funds-container">
        <h2>Add Funds to Wallet</h2>
        <form id="addFundsForm">
            <div class="form-input">
                <!-- Display the card number in a readonly input box -->
                <label for="card_number">Card Number:</label>
                <input type="text" id="card_number" name="card_number" value="<?php echo $cardNumber; ?>" readonly >
            </div>
            <div class="form-input">
                <!-- Display the card number in a readonly input box -->
                <label for="card_number">CVV:</label>
                <input type="text" id="cvv" name="cvv" value="<?php echo $cvv; ?>" readonly>
            </div>

            <div class="form-input">
                <label for="amount">Enter Amount:</label>
                <input type="number" id="amount" name="amount" min="1" required>
            </div>
            <div class="form-input">
                <label for="pin">PIN:</label>
                <input type="password" id="pin" name="pin" required>

                <div class="form-input">
                    <label for="token">Token:</label>
                    <input type="text" id="token" name="token"  required>
                    <button type="button" id="generateTokenBtn">Generate Token</button>
                </div>
               
            </div>
            <button type="submit">Add Funds</button>
        </form>
        <div id="message"></div>
    </div>

    <script src="../scripts/add_funds.js">
        
    </script>
</body>

</html>