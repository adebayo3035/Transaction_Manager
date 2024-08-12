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
<?php include ('../customerNavBar.php'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Retrieve order details from session
            const orderItems = <?php echo json_encode($_SESSION['order_items'] ?? []); ?>;
            const totalAmount = parseFloat(<?php echo $_SESSION['total_amount'] ?? 0; ?>);
            const serviceFee = parseFloat(<?php echo $_SESSION['service_fee'] ?? 0; ?>);
            const deliveryFee = parseFloat(<?php echo $_SESSION['delivery_fee'] ?? 0; ?>);

            const totalFee = totalAmount + serviceFee + deliveryFee;


             // Populate the checkout page
             const orderTableBody = document.getElementById('orderSummaryTable').querySelector('tbody');
            orderItems.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${item.food_name}</td>
                    <td>${item.quantity}</td>
                    <td>N ${item.price_per_unit.toFixed(2)}</td>
                    <td class="total-price">N ${item.total_price.toFixed(2)}</td>
                `;
                orderTableBody.appendChild(row);
            });
            
            <?php
            // Clear session data after it has been used
            $_SESSION['order_items'] = [];
            $_SESSION['total_amount'] = 0;
            $_SESSION['service_fee'] = 0;
            $_SESSION['delivery_fee'] = 0;
            ?>

            // Populate the checkout page
            document.getElementById('total-order').textContent = `N ${totalAmount.toFixed(2)}`;
            document.getElementById('service-fee').textContent = `N ${serviceFee.toFixed(2)}`;
            document.getElementById('delivery-fee').textContent = `N ${deliveryFee.toFixed(2)}`;
            document.getElementById('total-fee').textContent = `N ${totalFee.toFixed(2)}`;
            // Other calculations and updates based on totalAmount
        });
    </script>
   
    <div class="checkout-container">
        <h1>Order Checkout</h1>

        <form id="checkoutForm">
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
                <div class="order-item">
                    <span class="item-name">Total Amount</span>
                    <span class="total-order" id="total-fee">0.00</span>
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

            <!-- Payment Method -->
            <div class="payment-method">
                <h2>Select Payment Method</h2>
                <div class="payment-option">
                    <input type="radio" id="credit-card" name="payment_method" value="credit_card" checked>
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
            </div>

            <!-- Payment Details -->
            <div class="payment-details" id="credit-card-details">
                <h2>Credit Card Details</h2>
                <div class="form-group">
                    <label for="card_number">Card Number:</label>
                    <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
                </div>
                <div class="form-group">
                    <label for="card_expiry">Expiry Date:</label>
                    <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" required>
                </div>
                <div class="form-group">
                    <label for="card_cvv">CVV:</label>
                    <input type="text" id="card_cvv" name="card_cvv" placeholder="123" required>
                </div>
            </div>

            <div class="payment-details" id="paypal-details">
                <h2>PayPal Details</h2>
                <div class="form-group">
                    <label for="paypal_email">PayPal Email:</label>
                    <input type="email" id="paypal_email" name="paypal_email" placeholder="email@example.com" required>
                </div>
            </div>

            <div class="form-input" id="bank_transfer_details">
                <label for="bank_name">Bank Name:</label>
                <select id="bank_name" name="bank_name">
                    <option value="">Select Bank</option>
                    <option value="providus">Providus Bank</option>
                    <option value="wema">Wema Bank</option>
                </select>
                <label for="bank_account">Bank Account Number:</label>
                <input type="text" id="bank_account" name="bank_account" readonly>
            </div>

            <div class="form-group">
                <button type="submit">Place Order</button>
            </div>
        </form>
    </div>

    <script src="../scripts/checkout.js"></script>
    <script src="../scripts/order_form.js"></script>
</body>

</html>