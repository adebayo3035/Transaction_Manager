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
            // Store the PHP session data into sessionStorage
            sessionStorage.setItem('order_items', JSON.stringify(<?php echo json_encode($_SESSION['order_items'] ?? []); ?>));
            sessionStorage.setItem('total_amount', "<?php echo $_SESSION['total_amount'] ?? 0; ?>");
            sessionStorage.setItem('service_fee', "<?php echo $_SESSION['service_fee'] ?? 0; ?>");
            sessionStorage.setItem('delivery_fee', "<?php echo $_SESSION['delivery_fee'] ?? 0; ?>");

            // Clear session data after it has been stored in sessionStorage
            <?php
            $_SESSION['order_items'] = [];
            $_SESSION['total_amount'] = 0;
            $_SESSION['service_fee'] = 0;
            $_SESSION['delivery_fee'] = 0;
            ?>

            // Retrieve order details from sessionStorage
            const orderItems = JSON.parse(sessionStorage.getItem('order_items') || '[]');
            const totalAmount = parseFloat(sessionStorage.getItem('total_amount') || 0);
            const serviceFee = parseFloat(sessionStorage.getItem('service_fee') || 0);
            const deliveryFee = parseFloat(sessionStorage.getItem('delivery_fee') || 0);

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

            // Populate the checkout page
            document.getElementById('total-order').textContent = `N ${totalAmount.toFixed(2)}`;
            document.getElementById('service-fee').textContent = `N ${serviceFee.toFixed(2)}`;
            document.getElementById('delivery-fee').textContent = `N ${deliveryFee.toFixed(2)}`;
            document.getElementById('total-fee').textContent = `N ${totalFee.toFixed(2)}`;
        });
    </script>



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
        <form id="checkoutForm">
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