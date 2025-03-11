document.addEventListener('DOMContentLoaded', () => {
    const checkoutForm = document.getElementById('checkoutForm');
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit-card-details');
    const paypalDetails = document.getElementById('paypal-details');
    const creditDetails = document.getElementById('credit-details');
    const bankTransferDetails = document.getElementById('bank_transfer_details');
    const bankAccountInput = document.getElementById('bank_account');
    const bankNameSelect = document.getElementById('bank_name');
    const printReceiptBtn = document.getElementById('receipt-btn');
    const placeOrderBtn = document.getElementById('place-orderBtn');
    const promoCheckBox = document.getElementById('promoCheckBox');
    const promoInput = document.getElementById('promo_code');
    const promoBtn = document.getElementById('validate_promo');

    let usePromo = false;
    let discount_value = 0;
    let discount_percent = 0;
    let promoCode = '';
    let orderData = {}; // Declare orderData at the top to make it accessible globally


    creditDetails.style.display = "none";

    // Toggle display of Promo Container
    promoCheckBox.addEventListener('change', function () {
        const promoContainer = document.getElementById('promoContainer');
        if (this.checked) {
            promoContainer.style.display = 'block';
            checkoutForm.style.display = 'none';
        } else {
            promoContainer.style.display = 'none';
            checkoutForm.style.display = 'block';
        }
    });

    // Validate promo code
    promoBtn.addEventListener('click', () => {
        const discountItem = document.getElementById('discount-item');
        const totalAfterDiscount = document.getElementById('total-after-discount');
        const totalAmountLabel = document.getElementById('totalAmount-label');
        const discountValueElem = document.getElementById('discount-value');
        const totalFeeAfterElem = document.getElementById('total-fee-after');

        // Show confirmation dialog
        if (!confirm("Are you sure you want to validate this promo code?")) {
            promoInput.value = ''; // Clear input if user cancels
            return;
        }

        // Proceed with validation if the user confirms
        const promoDetails = {
            promo_code: promoInput.value,
            total_order: totalOrder
        };

        fetch('../v2/validate_promo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(promoDetails)
        })
            .then(response => response.json())
            .then(data => {
                if (data.eligible) {
                    alert('Promo Code has been successfully Validated.');
                    usePromo = true;
                    discount_value = data.discount;
                    discount_percent = data.discount_percent;
                    promoCode = data.promo_code;
                    let totalAmountAfter = parseFloat(totalAmount) - discount_value;

                    // Update UI with discount information
                    discountItem.style.display = "flex";
                    totalAfterDiscount.style.display = "flex";
                    totalAmountLabel.textContent = 'Total Amount Before Discount';
                    discountValueElem.textContent = `N ${discount_value.toFixed(2)}`;
                    totalFeeAfterElem.textContent = `N ${totalAmountAfter.toFixed(2)}`;

                    // Disable promo elements
                    promoCheckBox.disabled = true;
                    promoBtn.disabled = true;
                    promoBtn.style.cursor = 'not-allowed';
                    promoBtn.style.backgroundColor = '#ccc';
                    promoInput.disabled = true;

                    checkoutForm.style.display = 'block';
                } else {
                    alert('Error: ' + data.message);
                    console.error(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
    });

    // Fetch order data from the server
    fetch('../v2/get_order_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                orderData = data.data;
                console.log("Order data retrieved from session:", orderData);

                // Populate the checkout page order table
                const orderTableBody = document.getElementById('orderSummaryTable').querySelector('tbody');
                orderTableBody.innerHTML = ""; // Clear existing rows

                orderData.order_items.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${item.food_name}</td>
                            <td>${item.quantity}</td>
                            <td>N ${parseFloat(item.price_per_unit).toFixed(2)}</td>
                            <td class="total-price">N ${parseFloat(item.total_price).toFixed(2)}</td>
                        `;
                    orderTableBody.appendChild(row);
                });

                // Calculate total amount including discounts, service fee, and delivery fee
                
                let totalAmount = ((parseFloat(orderData.total_order) - discount_value) + parseFloat(orderData.service_fee) + parseFloat(orderData.delivery_fee));

                // Populate the total amounts on the checkout page
                document.getElementById('total-order').textContent = `N ${parseFloat(orderData.total_order).toFixed(2)}`;
                document.getElementById('service-fee').textContent = `N ${parseFloat(orderData.service_fee).toFixed(2)}`;
                document.getElementById('delivery-fee').textContent = `N ${parseFloat(orderData.delivery_fee).toFixed(2)}`;
                document.getElementById('total-fee').textContent = `N ${totalAmount.toFixed(2)}`;
            } else {
                console.error("Failed to retrieve order data:", data.message);
                alert("Failed to retrieve order data. Please try again.");
            }
        })
        .catch(error => {
            console.error("Error fetching order data:", error);
            alert("An error occurred while fetching order data. Please try again.");
        });

    const clearInputFields = (...fields) => {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) field.value = '';
        });
    };

    const togglePaymentDetails = (method) => {
        const paymentDetails = {
            'Card': () => {
                creditCardDetails.style.display = 'block';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'none';
                creditDetails.style.display = 'none';
                clearInputFields('bank_name', 'bank_account', 'paypal_email', 'customer_secret_answer');
            },
            'paypal': () => {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'block';
                bankTransferDetails.style.display = 'none';
                creditDetails.style.display = 'none';
                clearInputFields('card_number', 'card_expiry', 'card_cvv', 'card_pin', 'bank_name', 'bank_account', 'customer_secret_answer');
            },
            'bank_transfer': () => {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'block';
                creditDetails.style.display = 'none';
                clearInputFields('card_number', 'card_expiry', 'card_cvv', 'card_pin', 'paypal_email', 'customer_secret_answer');
            },
            'credit': () => {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'none';
                creditDetails.style.display = 'block';
                clearInputFields('card_number', 'card_expiry', 'card_cvv', 'card_pin', 'bank_name', 'bank_account', 'paypal_email');
            }
        };

        (paymentDetails[method.value] || (() => { }))();
    };

    paymentMethods.forEach(method => {
        method.addEventListener('change', () => togglePaymentDetails(method));
    });

    const formatExpiryDate = (expiryDate) => {
        const [year, month] = expiryDate.split('-');
        return `${month.padStart(2, '0')}-${year}`;
    };

    // Payment form submission handler
    checkoutForm.addEventListener('submit', (event) => {
        event.preventDefault();

        // Show confirmation dialog
        const isConfirmed = confirm('Are you sure you want to proceed with the payment?');
        if (!isConfirmed) {
            alert("Your Order Payment Process has been Cancelled!");
            return; // Stop execution if the user cancels
        }

        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

        // Use the fetched order data to populate paymentDetails
        let paymentDetails = {
            payment_method: selectedPaymentMethod,
            order_items: orderData.order_items, // Use order items from session
            total_amount: parseFloat(orderData.total_order), // Use total order from session
            service_fee: parseFloat(orderData.service_fee), // Use service fee from session
            delivery_fee: parseFloat(orderData.delivery_fee), // Use delivery fee from session
            total_order: parseFloat(orderData.total_order), // Use total order from session
            using_promo: usePromo, // Replace with actual value if available
            discount: discount_value, // Replace with actual value if available
            discount_percent: discount_percent, // Replace with actual value if available
            promo_code: promoCode // Replace with actual value if available
        };

        // Add payment method-specific details
        if (selectedPaymentMethod === 'Card') {
            paymentDetails = {
                ...paymentDetails,
                card_number: document.getElementById('card_number').value,
                card_expiry: formatExpiryDate(document.getElementById('card_expiry').value),
                card_cvv: document.getElementById('card_cvv').value,
                card_pin: document.getElementById('card_pin').value
            };
        } else if (selectedPaymentMethod === 'paypal') {
            paymentDetails.paypal_email = document.getElementById('paypal_email').value;
        } else if (selectedPaymentMethod === 'bank_transfer') {
            paymentDetails.bank_name = document.getElementById('bank_name').value;
            paymentDetails.bank_account = document.getElementById('bank_account').value;
        } else if (selectedPaymentMethod === 'credit') {
            paymentDetails.customer_secret_answer = document.getElementById('customer_secret_answer').value;
            paymentDetails.is_credit = true;
        }

        // Send payment details to the server
        fetch('../v2/process_paymentOld.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(paymentDetails)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Your Order has been successfully received. Kindly wait for approval.');
                    printReceiptBtn.style.display = 'block';
                    placeOrderBtn.style.display = 'none';

                    // Disable all input fields, radio buttons, and checkboxes
                    disableFormElements();
                } else {
                    alert('Error: ' + data.message);
                    console.log(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
    });
    // Function to disable form elements
    function disableFormElements() {
        // Disable radio buttons
        paymentMethods.forEach(method => {
            method.disabled = true;
        });

        // Disable promo checkbox and input
        promoCheckBox.disabled = true;
        promoInput.disabled = true;
        promoBtn.disabled = true;

        // Disable input fields in payment details sections
        const inputFields = document.querySelectorAll('input, select, textarea');
        inputFields.forEach(field => {
            field.disabled = true;
        });
    }

    bankNameSelect.addEventListener('change', () => {
        bankAccountInput.value = generateVirtualBankAccountNumber();
    });

    const generateVirtualBankAccountNumber = () => Math.floor(1000000000 + Math.random() * 9000000000).toString();

    const setMinMonth = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        document.querySelector('input[type="month"]').min = `${year}-${month}`;
    };

    setMinMonth();

    // Print Receipt function
    function printReceipt() {
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        const orderSummary = document.querySelector('.order-summary').innerHTML;
        const now = new Date();
        const dateTime = now.toLocaleString();

        printWindow.document.write(`
            <html>
            <head>
                <title>Receipt</title>
                <link rel ="stylesheet" href = "../css/receipt.css">
            </head>
            <body>
                <div class="receipt-container">
                    <div class="receipt-header">
                        <h1>KaraKata Receipts</h1>
                        <h2>Your Receipt</h2>
                        <p>Date & Time: ${dateTime}</p>
                    </div>
                    <div class="order-summary">
                        ${orderSummary}
                    </div>
                    <div class="receipt-footer">
                        <p>Thank you for your purchase!</p>
                    </div>
                </div>
                <script>
                    window.print();
                    window.onafterprint = function() { window.close(); }
                </script>
            </body>
            </html>
        `);

        printWindow.document.close();
    }

    // Assuming you have a button with ID 'printReceiptButton'
    const printButton = document.getElementById('receipt-btn');
    if (printButton) {
        printButton.addEventListener('click', printReceipt);
    }
    // Redirect to Order Page when the checkout Page is refreshed
    const navigationEntries = performance.getEntriesByType("navigation");
    if (navigationEntries.length > 0) {
        const navigationEntry = navigationEntries[0];
        if (navigationEntry.type === "reload") {
            window.location.href = '../v1/dashboard.php';
        }
    }
});