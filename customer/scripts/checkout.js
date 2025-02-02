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
                    totalAmountAfter = totalAmount - discount_value;

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

    // Get Order Items stored in the session
    const orderItems = JSON.parse(sessionStorage.getItem('order_items') || '[]');
    const totalOrder = parseFloat(sessionStorage.getItem('total_amount') || 0);
    const serviceFee = parseFloat(sessionStorage.getItem('service_fee') || 0);
    const deliveryFee = parseFloat(sessionStorage.getItem('delivery_fee') || 0);
    let totalAmount = ((totalOrder - discount_value) + serviceFee + deliveryFee);

    if (!orderItems || orderItems.length === 0) {
        location.replace('../v1/dashboard.php');
        return;
    }

    // Populate the checkout page order table
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

    // Populate the total amounts on the checkout page
    document.getElementById('total-order').textContent = `N ${totalOrder.toFixed(2)}`;
    document.getElementById('service-fee').textContent = `N ${serviceFee.toFixed(2)}`;
    document.getElementById('delivery-fee').textContent = `N ${deliveryFee.toFixed(2)}`;
    document.getElementById('total-fee').textContent = `N ${totalAmount.toFixed(2)}`;

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

    checkoutForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

        let paymentDetails = {
            payment_method: selectedPaymentMethod,
            order_items: orderItems,
            total_amount: totalAmount,
            service_fee: serviceFee,
            delivery_fee: deliveryFee,
            total_order: totalOrder,
            using_promo: usePromo,
            discount: discount_value,
            discount_percent: discount_percent,
            promo_code: promoCode
        };

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

        fetch('../v2/process_payment.php', {
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