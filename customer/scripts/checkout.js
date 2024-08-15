document.addEventListener('DOMContentLoaded', () => {
    const checkoutForm = document.getElementById('checkoutForm');
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit-card-details');
    const paypalDetails = document.getElementById('paypal-details');
    const bankTransferDetails = document.getElementById('bank_transfer_details');
    const bankAccountInput = document.getElementById('bank_account');
    const bankNameSelect = document.getElementById('bank_name');

    


    // Use switch case to select Payment method
    paymentMethods.forEach(method => {
        method.addEventListener('change', function () {
            if (method.value == 'bank_transfer') {

                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'block';
            }
            else if (method.value === 'paypal') {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'block';
                bankTransferDetails.style.display = 'none';
            }
            else {
                creditCardDetails.style.display = 'block';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'none';
            }
        })
    })
    // Form submission
    checkoutForm.addEventListener('submit', function (event) {
        event.preventDefault(); // Prevent default form submission

        // Get Order Items from the session storage
        const orderItems = JSON.parse(sessionStorage.getItem('order_items'));
        const totalOrder = parseFloat(sessionStorage.getItem('total_amount'));
        const serviceFee = parseFloat(sessionStorage.getItem('service_fee'));
        const deliveryFee = parseFloat(sessionStorage.getItem('delivery_fee'));
        const totalAmount = totalOrder + serviceFee + deliveryFee

        // format expiry Date
        // Extract the expiry date value
        const expiryDateInput = document.getElementById('card_expiry').value;
        const [year, month] = expiryDateInput.split('-');

        // Format it as MM/YY for display
        const formattedExpiryDate = `${month}/${year.slice(-2)}`;
        document.getElementById('formatted_expiry_date').value = formattedExpiryDate;

        // Format it as YYYY-MM for PHP processing
        const formattedExpiryDateYYYYMM = `${month.padStart(2, '0')}-${year}`;

        const paymentDetails = {
            payment_method: document.querySelector('input[name="payment_method"]:checked').value,
            card_number: document.getElementById('card_number').value,
            // card_expiry: document.getElementById('card_expiry').value,
            card_expiry: formattedExpiryDateYYYYMM,
            card_cvv: document.getElementById('card_cvv').value,
            card_pin: document.getElementById('card_pin').value,
            paypal_email: document.getElementById('paypal_email').value,
            bank_name: document.getElementById('bank_name').value,
            bank_account: document.getElementById('bank_account').value,
            order_items: orderItems,
            total_amount: totalAmount,
            service_fee: serviceFee,
            delivery_fee: deliveryFee,
            total_order: totalOrder
        };

        fetch('../v2/process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(paymentDetails)
        })
            // Send data to server
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment processed successfully');
                    // Redirect to a different page or show success message
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.'.error);
            });
    });

    bankNameSelect.addEventListener('change', function () {
        const bankName = this.value;
        const virtualBankAccountNumber = generateVirtualBankAccountNumber();
        bankAccountInput.value = virtualBankAccountNumber;
    });
    function generateVirtualBankAccountNumber() {
        return Math.floor(1000000000 + Math.random() * 9000000000).toString();

    }
});

const monthControl = document.querySelector('input[type="month"]');

// Function to set the minimum value of the month input
function setMinMonth() {
    // Get the current date
    const now = new Date();
    // Extract the current year and month
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0'); // Months are zero-based, so add 1
    // Format the date in YYYY-MM
    const currentMonth = `${year}-${month}`;
    // Set the min attribute of the input element
    monthControl.min = currentMonth;
}
// Call the function to set the minimum value
setMinMonth();