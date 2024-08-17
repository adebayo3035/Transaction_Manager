document.addEventListener('DOMContentLoaded', () => {
    const checkoutForm = document.getElementById('checkoutForm');
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit-card-details');
    const paypalDetails = document.getElementById('paypal-details');
    const bankTransferDetails = document.getElementById('bank_transfer_details');
    const bankAccountInput = document.getElementById('bank_account');
    const bankNameSelect = document.getElementById('bank_name');
    const orderItems = JSON.parse(sessionStorage.getItem('order_items'));

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

        (paymentDetails[method.value] || (() => {}))();
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
                location.replace('../v1/dashboard.php');
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
});
