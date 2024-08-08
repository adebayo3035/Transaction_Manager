<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Debit Card</title>
    <link rel="stylesheet" href="../css/cards.css">
</head>

<body>
    <?php include ('../customerNavBar.php'); ?>
    <section class="container">
        <div class="card_menu">
            <ul class="card-menu">
                <li> <a onclick = "toggleModal()">Add New Card</a></li>
                <li> <a href="">Delete Card Details</a></li>
                <li> <a href ="add_funds.php">Fund Wallet</a></li>
                <li> <a href ="add_funds.php">Fund Wallet</a></li>
                <li> <a href ="add_funds.php">Change Password</a></li>
                <li> <a href ="add_funds.php">Change Phone Number</a></li>
                <!-- <li> <a href="">Modify Card Information</a></li> -->
                
                <!-- <li> <a href="">View My Cards</a></li> -->
            </ul>
        </div>

        <!--  CARDS SECTION -->
    <div class="card-container" id="card-container">

    </div>
 
    </div>

    <!-- ADD NEW CARD FORM --> 
    </section>

    <!-- Modal to add new Card -->
    <div id="orderModal" class="modal">
        <div class="modal-content" id="card-form">
            <span class="close">&times;</span>
            <h2>Add New Card</h2>
            <form id="addCardsForm">
                <div class="form-input">
                    <label for="bank_name">Bank Name:</label>
                    <input type="text" id="bank_name" name="bank_name" required>
                </div>
                <div class="form-input">
                    <label for="card_number">Card Number:</label>
                    <input type="number" id="card_number" name="card_number" maxlength="15" required>
                </div>
                <div class="form-input">
                    <label for="card_holder">Card Holder Name:</label>
                    <input type="text" id="card_holder" name="card_holder" required>
                </div>
                <div class="form-input date">
                    <div class="month">
                    <label for="month">Expiry Date:</label>
                    <!-- <input type="date" id="expiry_date" name="expiry_date" maxlength="2" required> -->
                    <input type="month"id="expiry_date" name="expiry_date"required >
                    </div>
                    
                    <div class="cvv">
                    <label for="cvv">CVV:</label>
                    <input type="number" id="cvv" name="cvv" maxlength="3" required>
                    </div>
                    <input type="hidden" id="formatted_expiry_date" name="formatted_expiry_date">
                    
                </div>
                <button type="submit">Add Card</button>
            </form>
            <div id="message"></div>
        </div>
    </div>
    
<script src="../scripts/cards.js"></script>
<script src="../scripts/get_cards.js"></script>
</body>

</html>