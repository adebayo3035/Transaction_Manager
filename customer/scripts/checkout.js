document.addEventListener('DOMContentLoaded', () => {
    const checkoutForm = document.getElementById('checkoutForm');
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit-card-details');
    const paypalDetails = document.getElementById('paypal-details');
    const bankTransferDetails = document.getElementById('bank_transfer_details');
    const bankAccountInput = document.getElementById('bank_account');
    const bankNameSelect = document.getElementById('bank_name');
    const orderItems = JSON.parse(sessionStorage.getItem('order_items'));
    const printReceiptBtn = document.getElementById('receipt-btn');
    const placeOrderBtn = document.getElementById('place-orderBtn')

    if (!orderItems || orderItems.length === 0) {
        location.replace('../v1/dashboard.php');
        return;
    }

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
                clearInputFields('bank_name', 'bank_account', 'paypal_email');
            },
            'paypal': () => {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'block';
                bankTransferDetails.style.display = 'none';
                clearInputFields('card_number', 'card_expiry', 'card_cvv', 'card_pin', 'bank_name', 'bank_account');
            },
            'bank_transfer': () => {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'block';
                clearInputFields('card_number', 'card_expiry', 'card_cvv', 'card_pin', 'paypal_email');
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

        const totalOrder = parseFloat(sessionStorage.getItem('total_amount'));
        const serviceFee = parseFloat(sessionStorage.getItem('service_fee'));
        const deliveryFee = parseFloat(sessionStorage.getItem('delivery_fee'));
        const totalAmount = totalOrder + serviceFee + deliveryFee;

        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

        let paymentDetails = {
            payment_method: selectedPaymentMethod,
            order_items: orderItems,
            total_amount: totalAmount,
            service_fee: serviceFee,
            delivery_fee: deliveryFee,
            total_order: totalOrder
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
                    alert('Payment processed successfully');
                    printReceiptBtn.style.display = 'block';
                    placeOrderBtn.style.display = 'none';
                    // location.replace('../v1/dashboard.php');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
    });

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

    // Function to handle printing the receipt
    function printReceipt() {
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        const orderSummary = document.querySelector('.order-summary').innerHTML;
        const now = new Date();
        const dateTime = now.toLocaleString();

        printWindow.document.write(`
            <html>
            <head>
                <title>Receipt</title>
                <style>
                @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
                    body {
                        box-sizing: border-box;
                        font-family: 'Poppins', sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #fff;
                    }
                    .receipt-header {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    h2 {
                        margin-top: 0;
                    }
                    .order-summary {
                        margin-bottom: 20px;
                    }
                    #orderSummaryTable {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                    }
                    #orderSummaryTable th, #orderSummaryTable td {
                        border: 1px solid #ddd;
                        padding: 8px;
                        text-align: left;
                    }
                    #orderSummaryTable th {
                        background-color: #f2f2f2;
                    }
                    #orderSummaryTable tbody tr td a{
                        text-decoration: none;
                    }
                    .order-item, .order-total {
                        display: flex;
                        justify-content: space-between;
                        padding: 10px 0;
                    }
                    .receipt-footer {
                        text-align: center;
                        margin-top: 20px;
                    }
                </style>
            </head>
            <body>
                <div class="receipt-header">
                    <h2> KaraKata Receipts </h2>
                    <h2>Your Receipt</h2>
                    <p>Date & Time: ${dateTime}</p>
                </div>
                ${orderSummary}
                <div class="receipt-footer">
                    <p>Thank you for your purchase!</p>
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

});
