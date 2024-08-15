
function maskCardNumber(cardNumber) {
    const lengthToMask = 10; // Number of digits to mask
    const visibleDigits = cardNumber.length - lengthToMask;
    
    if (visibleDigits <= 0) return cardNumber; // Return the original card number if it's too short to mask
    
    const maskedPart = '*'.repeat(lengthToMask);
    const visiblePart = cardNumber.slice(0, visibleDigits);

    return `${visiblePart}${maskedPart}`;
}
document.addEventListener('DOMContentLoaded', () => {

    // Insert the form into the DOM
    const form = document.createElement('form');
    form.id = 'cardForm';
    form.method = 'POST';
    form.action = '../v1/add_funds.php';
    form.style.display = 'none';
    form.innerHTML = `
        <input type="hidden" name="card_number" id="cardNumberInput">
        <input type="hidden" name="cvv" id="cvvInput">
    `;
    document.body.appendChild(form);

    // Fetch and display orders summary
    fetch('../v2/get_cards.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ordersTableBody = document.querySelector('#card-container');

                ordersTableBody.innerHTML = '';
                data.cards.forEach(cardss => {
                    const cardElement = document.createElement('div');
                    cardElement.className = 'card';
                    // Mask the card number
            const maskedCardNumber = maskCardNumber(cardss.card_number);

                    // Set the card number as a data attribute
                    cardElement.dataset.cardNumber = cardss.card_number;
                    cardElement.dataset.cvv = cardss.cvv;
                    cardElement.title = "Click to Add funds to your wallet";
                    cardElement.style.cursor = "pointer";

                    cardElement.innerHTML = `
                        <div class="card-content">
                            <div class="card-front">
                                <img src="../../images/chip2.png" alt="Micro Chip" class="chip">
                                <div class="card-number">${maskedCardNumber}</div>
                                <div class="card-holder">${cardss.card_holder}</div>
                                <div class="expiry-date">${cardss.expiry_date}</div>
                                <div class="logo">${cardss.bank_name}</div>
                            </div>
                            <div class="card-back">
                                <div class="magnetic-stripe"></div>
                                <div class="signature">${cardss.card_holder}</div>
                                <div class="cvv">${cardss.cvv}</div>
                            </div>
                        </div>
                    `;

                    // Add click event listener to the card element
                    cardElement.addEventListener('click', () => {
                        const cardNumber = cardElement.dataset.cardNumber;
                        const cvv = cardElement.dataset.cvv;
    
                        // Set form values
                        document.getElementById('cardNumberInput').value = cardNumber;
                        document.getElementById('cvvInput').value = cvv;
    
                        // Submit the form
                        document.getElementById('cardForm').submit();
                    });

                    ordersTableBody.appendChild(cardElement);
                });
            } else {
                console.error('Failed to fetch Cards:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching Cards:', error);
        });
});

