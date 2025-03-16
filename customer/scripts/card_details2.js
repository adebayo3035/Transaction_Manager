// Function to mask card numbers (e.g., show only the last 4 digits)
function maskCardNumber(cardNumber) {
    return `**** **** **** ${cardNumber.slice(-4)}`;
}

// Fetch card data from the endpoint and populate the select input
async function populateCardNumbers() {
    try {
        const response = await fetch('../v2/get_cards.php');
        if (!response.ok) {
            throw new Error('Failed to fetch card data');
        }

        // Parse the response as JSON
        const data = await response.json();

        // Log the response for debugging
        console.log('Response from server:', data);

        // Check if the response contains the `cards` array
        if (!data.success || !Array.isArray(data.cards)) {
            throw new Error('Invalid response format: cards array not found');
        }

        const selectCardInput = document.getElementById('select_card');

        // Clear existing options (except the default one)
        selectCardInput.innerHTML = '<option value="" disabled selected>Select an option</option>';

        // Add new options with masked card numbers
        data.cards.forEach(card => {
            const option = document.createElement('option');
            option.value = card.card_number; // Store the original card number as the value
            option.textContent = maskCardNumber(card.card_number); // Display the masked card number
            selectCardInput.appendChild(option);
        });
    } catch (error) {
        console.error('Error fetching card data:', error);
        alert('Failed to load card numbers. Please try again later.');
    }
}

// Call the function to populate card numbers when the page loads
document.addEventListener('DOMContentLoaded', populateCardNumbers);

// asynchronous function to return Card Details

document.getElementById('getCardsForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const selectedCardId = document.getElementById('select_card').value;
    const secretAnswer = document.getElementById('secret_answer_deleteCard').value;
    const token = document.getElementById('token_deleteCard').value;

    try {
        const response = await fetch('../v2/get_card_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                card_id: selectedCardId,
                secret_answer: secretAnswer,
                token: token,
            }),
        });

        // Check if the response is OK (status code 200-299)
        if (!response.ok) {
            throw new Error('Failed to fetch card details for single card');
        }

        // Parse the response as JSON
        const data = await response.json();

        // Check if the response indicates success
        if (data.success == false) {
            alert(data.message || 'Failed to fetch card details');
            return; // Exit the function if the response is not successful
        }

        // Display the card details in the modal
        const dataDiv = document.getElementById('getCardDetails');
        dataDiv.innerHTML = `
            <h3>Card Details</h3>
            <table>
                
                <tr>
                    <th>Bank Name</th>
                    <td>${data.cardDetails.bank_name}</td>
                </tr>
                <tr>
                    <th>Card Status</th>
                    <td>${data.cardDetails.status}</td>
                </tr>
                <tr>
                    <th>Card Number</th>
                    <td>${data.cardDetails.card_number}</td>
                </tr>
                <tr>
                    <th>Expiry Date</th>
                    <td>${data.cardDetails.expiry_date}</td>
                </tr>
                <tr>
                    <th>CVV</th>
                    <td>
                        <div class="toggle-switch">
                            <label for="toggleCvv">Show CVV</label>
                            <input type="checkbox" id="toggleCvv">
                            <span id="cardCvv">•••</span>
                        </div>
                    </td>
                </tr>
            </table>
        `;

        // Show the card details modal
        const dataModal = document.getElementById('cardDetailsModal');
        dataModal.style.display = 'flex';

        // Add event listeners for toggle switches
        document.getElementById('toggleCvv').addEventListener('change', function () {
            const cardCvvSpan = document.getElementById('cardCvv');
            cardCvvSpan.textContent = this.checked ? data.cardDetails.cvv : '•••';
        });

        // Close modal after 30 seconds and refresh the page
        setTimeout(() => {
            dataModal.style.display = 'none';
            window.location.reload(); // Refresh the page
        }, 30000); // 30 seconds

    } catch (error) {
        console.error('Error fetching card details:', error);
        alert('Failed to fetch card details. Please try again later.');
    }
});

// Close modal when the close icon is clicked
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