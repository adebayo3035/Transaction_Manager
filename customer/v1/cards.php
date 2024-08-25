<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Debit Card</title>
    <link rel="stylesheet" href="../css/cards.css">
</head>

<body>
    <?php include('../customerNavBar.php'); ?>
    <section class="container">
        <div class="card_menu">
            <ul class="card-menu">
                <li> <a onclick="toggleModal('orderModal')">Add New Card</a></li>
                <li> <a onclick ="toggleModal('deleteCardModal')">Delete Card</a></li>
                <li> <a onclick="toggleModal('fundsModal')">Add Funds</a></li>
                <li> <a onclick="toggleModal('customerInfoModal')">Update Customer Info</a></li>
                <li> <a onclick = "toggleModal('resetQuestionAnswer')">Reset Secret Question and Answer</a></li>
            </ul>

        </div>

        <!--  CARDS SECTION -->
        <div class="card-container" id="card-container">
            <!-- Customer Cards will be automatically populated here -->

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
                        <input type="month" id="expiry_date" name="expiry_date" required>
                    </div>
                    <div class="cvv">
                        <label for="pin">PIN:</label>
                        <input type="password" id="pin" name="pin" maxlength="4" required>
                    </div>


                    <div class="cvv">
                        <label for="cvv">CVV:</label>
                        <input type="password" id="cvv" name="cvv" maxlength="3" required>
                    </div>
                    <input type="hidden" id="formatted_expiry_date" name="formatted_expiry_date">

                </div>
                <button type="submit">Add Card</button>
            </form>
            <div id="addCardmessage"></div>
        </div>
    </div>

    <!-- Modal to Add Funds -->
    <div id="fundsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add Funds</h2>
            <form id="addFundsForm" class="addFundsForm">
                <div class="form-input">
                    <label for="card">Select Card to Fund:</label>
                    <select id="card_numbers" name="card_numbers" required>
                        <option value="" disabled selected>Select an option</option>
                    </select>
                </div>
                <div class="form-input">
                    <label for="amount">Amount:</label>
                    <input type="number" id="amount" name="amount" required>
                </div>
                <div class="form-input">
                    <label for="pin">Pin:</label>
                    <input type="password" id="pin_addFund" name="pin_addFund" required>
                </div>
                <div class="form-input">
                    <label for="card_cvv">CVV:</label>
                    <input type="password" id="cvv_addFund" name="cvv_addFund" required>
                </div>
                <div class="form-input">
                    <label for="token">Token:</label>
                    <input type="text" id="token_addFund" name="token_addFund" required>
                </div>
                <button type="submit">Add Funds</button>
            </form>
            <div id="fundsMessage"></div>
        </div>
    </div>

    <!-- Modal to Delete Card -->
    <div id="deleteCardModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Delete Card</h2>
            <form id="deleteCardsForm" class="deleteCardsForm">
                <div class="form-input">
                    <label for="card">Select Card to Delete:</label>
                    <select id="select_card_delete" name="card_numbers" required>
                        <option value="" disabled selected>Select an option</option>
                    </select>
                </div>
                <div class="form-input">
                    <label for="secret_answer_deleteCard">Secret Answer:</label>
                    <input type="password" id="secret_answer_deleteCard" name="secret_answer_deleteCard" placeholder="Secret Answer" autocomplete="none" required>
                </div>
                <div class="form-input">
                    <label for="token_deleteCard">Token:</label>
                    <input type="text" id="token_deleteCard" name="token_deleteCard" placeholder="Token" required>
                </div>
                <button type="submit">Delete Card</button>
            </form>
            <div id="deleteCardMessage"></div>
        </div>
    </div>

    <!-- Modal to Update Customer Information -->
    <!-- Customer Information Update Modal -->
    <div id="customerInfoModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeCustomerInfoModal">&times;</span>
            <h2>Update Customer Information</h2>
            <form id="customerInfoForm">
                <div class="form-input">
                    <label for="update_option">Select Update Type:</label>
                    <select id="update_option" name="update_option" required>
                        <option value="" disabled selected>Select an option</option>
                        <option value="password">Password</option>
                        <option value="phone_number">Phone Number</option>
                        <option value="email">Email</option>
                    </select>
                </div>

                <div id="updateFields" style="display: none;">
                    <div class="form-input">
                        <label for="current_data">Current Data:</label>
                        <input type="text" id="current_data" name="current_data" autocapitalize="off" required>
                    </div>
                    <div class="form-input">
                        <label for="new_data">New Data:</label>
                        <input type="text" id="new_data" name="new_data" autocapitalize="off" required>
                    </div>
                    <div class="form-input">
                        <label for="confirm_new_data">Confirm New Data:</label>
                        <input type="text" id="confirm_new_data" name="confirm_new_data" autocapitalize="off" required>
                    </div>

                    <div class="validation">
                        <div class="form-input">
                            <label for="token">Token:</label>
                            <input type="text" id="token" name="token" required>
                        </div>
                        <div class="form-input">
                            <label for="secret_answer">Secret Answer:</label>
                            <input type="password" id="secret_answer" name="secret_answer" required>
                        </div>

                    </div>

                    <button type="submit">Update Information</button>
                </div>
            </form>
            <div id="customerInfoMessage"></div>
        </div>
    </div>


    <script src="../scripts/cards.js"></script>
    <script src="../scripts/get_cards.js"></script>
    <script src="../scripts/update_customer.js"></script>
</body>

</html>