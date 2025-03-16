<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../css/card_details.css">
    <!-- <link rel="stylesheet" href="../css/cards.css"> -->
</head>

<body>
<?php include ('customer_navbar.php'); ?>
    <!-- Modal to Delete Card -->
    <div id="CardModal" class="modal">
            <h2>Get Card Details</h2>
            <form id="getCardsForm" class="getCardsForm">
                <div class="form-input">
                    <label for="card">Select Card:</label>
                    <select id="select_card" name="card_numbers" required>
                        <option value="" disabled selected>Select an option</option>
                        <option value="cheki">Checki</option>
                    </select>
                </div>
                <div class="form-input">
                    <label for="secret_answer_deleteCard">Secret Answer:</label>
                    <input type="password" id="secret_answer_deleteCard" name="secret_answer_deleteCard"
                        placeholder="Secret Answer" autocomplete="none" required>
                </div>
                <div class="form-input">
                    <label for="token_deleteCard">Token:</label>
                    <input type="password" id="token_deleteCard" name="token_deleteCard" placeholder="Token" required>
                </div>
                <button type="submit">Get Card Details</button>
            </form>
          
    </div>

 <!-- Placeholder for Card Details Modal -->
<div id="cardDetailsModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div id="getCardDetails" class="card-details"></div>
    </div>
</div>
    <script src="../scripts/card_details.js"></script>
</body>

</html>