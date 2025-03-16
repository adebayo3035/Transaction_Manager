// Function to mask card numbers
function maskCardNumber(cardNumber) {
    return '•••• •••• •••• ' + cardNumber.slice(-4);
}

// Function to populate the card dropdown
async function populateCardNumbers() {
    try {
        const response = await fetch('../v2/get_cards.php');
        if (!response.ok) {
            throw new Error('Failed to fetch card data');
        }

        const data = await response.json();
        console.log('Card data:', data);

        const selectCardInput = document.getElementById('select_card');
        selectCardInput.innerHTML = '<option value="" disabled selected>Select an option</option>';

        if (data.success && data.cards.length > 0) {
            data.cards.forEach(card => {
                const option = document.createElement('option');
                option.value = card.card_number;
                option.textContent = maskCardNumber(card.card_number);
                selectCardInput.appendChild(option);
            });
        } else {
            throw new Error(data.message || 'Invalid card data received.');
        }
    } catch (error) {
        console.error('Error fetching card numbers:', error);
        alert('Failed to load card numbers. Please try again later.');
    }
}

// Function to fetch and display card details
function fetchCardDetails(selectedCardId, secretAnswer, token) {
    if (!selectedCardId) {
        alert('Please select a card.');
        return;
    }

    fetch('../v2/get_card_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            card_id: selectedCardId,
            secret_answer: secretAnswer,
            token: token,
        }),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to fetch card details');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.cardDetails) {
            populateCardDetails(data.cardDetails);
            const dataModal = document.getElementById('cardDetailsModal');
            dataModal.style.display = 'flex';

            // Auto-close modal after 30 seconds
            setTimeout(() => {
                dataModal.style.display = 'none';
                location.reload();
            }, 30000);
        } else {
            alert(data.message || 'Failed to fetch card details.');
            console.error('Error fetching card details:', data);
        }
    })
    .catch(error => {
        console.error('Error fetching card details:', error);
        alert('An error occurred while fetching card details.');
    });
}

// Function to populate card details in the modal
function populateCardDetails(details) {
    const dataDiv = document.getElementById('getCardDetails');
    dataDiv.innerHTML = `
     <h3>Card Details</h3>
        <table>
        <tr>
                <th>Card Holder</th>
                <td>${(details.card_holder)}</td>
            </tr>
            <tr>
                <th>Card Number</th>
                <td>${(details.card_number)}</td>
            </tr>
            <tr>
                <th>Bank Name</th>
                <td>${details.bank_name || 'N/A'}</td>
            </tr>
            <tr>
                <th>Card Status</th>
                <td>${details.status}</td>
            </tr>
            <tr>
                <th>Expiry Date</th>
                <td>${details.expiry_date}</td>
            </tr>
            <tr>
                <th>Card CVV</th>
                <td>
                    <div class="toggle-switch">
                        <input type="checkbox" id="toggleCvv">
                        <label for="toggleCvv">Show CVV</label>
                        <span id="cardCvv">•••</span>
                    </div>
                </td>
            </tr>
        </table>
    `;

    // Add event listener for CVV toggle switch
    document.getElementById('toggleCvv').addEventListener('change', function () {
        const cardCvvSpan = document.getElementById('cardCvv');
        cardCvvSpan.textContent = this.checked ? details.cvv : '•••';
    });

    // Auto-close modal after 30 seconds
    setTimeout(() => {
        document.getElementById('cardDetailsModal').style.display = 'none';
        window.location.reload(); // Refresh the page
    }, 30000);
}

// Close modal when clicking the close button
document.querySelector('.close-modal').addEventListener('click', function () {
    const dataModal = document.getElementById('cardDetailsModal');
    dataModal.style.display = 'none';
    window.location.reload(); // Refresh the page
});

// Close modal when clicking outside the modal content
document.getElementById('cardDetailsModal').addEventListener('click', function (event) {
    if (event.target === this) {
        this.style.display = 'none';
        window.location.reload(); // Refresh the page
    }
});

// Initialize the dropdown with card numbers
document.addEventListener('DOMContentLoaded', populateCardNumbers);

// Event listener for form submission
document.getElementById('getCardsForm').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent form from submitting and reloading the page
    
    // Get input values from form
    const selectedCardId = document.getElementById('select_card').value;
    const secretAnswer = document.getElementById('secret_answer_deleteCard').value;
    const token = document.getElementById('token_deleteCard').value; // Assuming there's an input field with id 'token'

    if (!selectedCardId || !secretAnswer || !token) {
        alert('Please fill in all fields.');
        return;
    }

    fetchCardDetails(selectedCardId, secretAnswer, token);
});
