<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Checkout</title>
    <link rel="stylesheet" href="../css/checkout.css">
    <!-- <link rel="stylesheet" href="../../css/customer.css"> -->
</head>

<body>
    <?php include('customer_navbar.php'); ?>

    <div class="checkout-container">
        <h1>Order Checkout</h1>

        <!-- Order Summary -->
        <div class="order-summary">
            <h2>Order Summary</h2>

            <div class="order-item">
                <span class="item-name">Order Price</span>
                <span class="total-order" id="total-order">0.00</span>
            </div>
            <div class="order-item">
                <span class="item-name">Service Fee</span>
                <span class="total-order" id="service-fee">0.00</span>
            </div>
            <div class="order-item">
                <span class="item-name">Delivery Fee</span>
                <span class="total-order" id="delivery-fee">0.00</span>
            </div>
            <div class="order-item" id="discount-item">
                <span class="item-name">Discount (#)</span>
                <span class="total-order" id="discount-value">0.00</span>
            </div>
            <div class="order-item">
                <span class="item-name" id="totalAmount-label">Total Amount</span>
                <span class="total-order" id="total-fee">0.00</span>
            </div>
            <div class="order-item" id="total-after-discount">
                <span class="item-name" id="after-discount">Total Amount After Discount</span>
                <span class="total-order" id="total-fee-after">0.00</span>
            </div>
            
            
            <table id="orderSummaryTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Food Name</th>
                        <th>Quantity</th>
                        <th>Price per Unit</th>
                        <th>Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Order items will be populated here -->
                </tbody>
            </table>
        </div>

        <div class="promoIndicator">
            <input type="checkbox" id="promoCheckBox" name="promoCheckbox" class="promoCheckBox"> 
            <label for="promoCheckBox">Apply Promo Code</label>
        </div>


        <div class="promoContainer" id="promoContainer">
            <div class="form-group">
                <label for="promo_code">Enter Promo Code:</label>
                <input type="text" id="promo_code" name="promo_code" placeholder="XXXXXXXXXXX" maxlength="8">
            </div>
            <div class="form-group">
                <button type="submit" id="validate_promo">Validate Promo Code</button>
            </div>
        </div>

        <!-- Payment Method -->
        <form id="checkoutForm" class="checkoutForm">
            <div class="payment-method">
                <h2>Select Payment Method</h2>
                <div class="payment-option">
                    <input type="radio" id="credit-card" name="payment_method" value="Card" checked>
                    <label for="credit-card">Credit Card</label>
                </div>
                <div class="payment-option">
                    <input type="radio" id="paypal" name="payment_method" value="paypal">
                    <label for="paypal">PayPal</label>
                </div>
                <div class="payment-option">
                    <input type="radio" id="bank-transfer" name="payment_method" value="bank_transfer">
                    <label for="bank-transfer">Bank Transfer</label>
                </div>
                <div class="payment-option">
                    <input type="radio" id="credit" name="payment_method" value="credit">
                    <label for="credit">Order on Credit</label>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="payment-details" id="credit-card-details">
                <h2>Credit Card Details</h2>
                <div class="form-group">
                    <label for="card_number">Card Number:</label>
                    <input type="number" id="card_number" name="card_number" placeholder="1234 5678 9012 3456">
                </div>
                <div class="form-group">
                    <label for="card_expiry">Expiry Date:</label>
                    <input type="month" id="card_expiry" name="card_expiry" placeholder="December 1990">
                </div>
                <div class="form-group">
                    <label for="card_cvv">CVV:</label>
                    <input type="number" id="card_cvv" name="card_cvv" placeholder="123">
                </div>
                <div class="form-group">
                    <label for="card_pin">PIN:</label>
                    <input type="password" id="card_pin" name="card_pin" placeholder="****">
                </div>
                <input type="hidden" id="formatted_expiry_date" name="formatted_expiry_date">
            </div>

            <div class="payment-details" id="paypal-details">
                <h2>PayPal Details</h2>
                <div class="form-group">
                    <label for="paypal_email">PayPal Email:</label>
                    <input type="email" id="paypal_email" name="paypal_email" placeholder="email@example.com">
                </div>
            </div>

            <div class="payment-details" id="credit-details">
                <h2>Customer Details</h2>
                <div class="form-group">
                    <label for="customer_secret_answer">Enter Your Secret Answer:</label>
                    <input type="password" id="customer_secret_answer" name="customer_secret_answer" autocomplete = "off">
                </div>
            </div>

            <div class="form-input payment-details" id="bank_transfer_details">
                <label for="bank_name">Bank Name:</label>
                <select id="bank_name" name="bank_name">
                    <option value="">Select Bank</option>
                    <option value="providus">Providus Bank</option>
                    <option value="wema">Wema Bank</option>
                </select>
                <label for="bank_account">Bank Account Number:</label>
                <input type="text" id="bank_account" name="bank_account" readonly>
            </div>

            <div class="form-group" id="buttons">
                <button type="submit" id="place-orderBtn">Place Order</button>
                <!-- Print Receipt Button (Initially Hidden) -->
                <button type="button" id="receipt-btn" style="display: none;">Print Receipt</button>

            </div>


        </form>
    </div>

    <script src="../scripts/checkout.js"></script>
    <script src="../scripts/order_form.js"></script>
</body>

</html>