document.addEventListener('DOMContentLoaded', () => {
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
    bankNameSelect.addEventListener('change', function () {
        const bankName = this.value;
        const virtualBankAccountNumber = generateVirtualBankAccountNumber();
        bankAccountInput.value = virtualBankAccountNumber;
    });
    function generateVirtualBankAccountNumber() {
        // Generate a 10-digit random number
        // const myBanks = ['Wema Bank', 'Providus Bank']
        // const virtualBankAccountNumber = [];
        // const selectedBank = myBanks[Math.floor(Math.random()* myBanks.length)]
        // const generateAccount =  Math.floor(1000000000 + Math.random() * 9000000000).toString();
        // virtualBankAccountNumber.push(generateAccount);
        // virtualBankAccountNumber.push(selectedBank)
        // return virtualBankAccountNumber;

        // Generate a 10-digit random number based on selected bank
        return Math.floor(1000000000 + Math.random() * 9000000000).toString();

    }
});