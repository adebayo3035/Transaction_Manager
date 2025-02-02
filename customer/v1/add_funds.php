
<?php
// Retrieve the card_number and cvv from the POST parameters
$cardNumber = isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : '';
$cvv = isset($_POST['cvv']) ? htmlspecialchars($_POST['cvv']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Funds</title>
    <link rel="stylesheet" href="../css/add_funds.css">
    
</head>

<body>
    <?php include ('customer_navbar.php'); ?>

    <div class="add-funds-container">
        <h2>Add Funds to Wallet</h2>
        <form id="addFundsForm1" class="addFundsForm">
            <div class="form-input">
                <!-- Display the card number in a readonly input box -->
                <input type="text" id="card_number_addFunds" name="card_number_addFunds" value="<?php echo $cardNumber; ?>" readonly hidden>
            </div>
            <div class="form-input">
                <!-- Display the card number in a readonly input box -->

                <input type="password" id="cvv" name="cvv" value="<?php echo $cvv; ?>" readonly hidden>
            </div>

            <div class="form-input">
                <label for="amount">Enter Amount:</label>
                <input type="number" id="amount" name="amount" min="1" required>
            </div>
            <div class="form-input">
                <label for="pin">PIN:</label>
                <input type="password" id="pin" name="pin" required>
            </div>

            <div class="form-input">
                <label for="token">Token:</label>
                <input type="text" id="token" name="token" readonly onpaste="handlePaste(event)" required>
                <!-- <button type="button" id="generateTokenBtn">Generate Token</button> -->
            </div>
            <button type="submit">Add Funds</button>
    </div>
    
    </form>
    <div id="message"></div>
    </div>

    <script src="../scripts/add_funds.js">

    </script>
</body>

</html>