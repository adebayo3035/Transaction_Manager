// Function to toggle modals
function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}

document.addEventListener('DOMContentLoaded', () => {
    // Function to populate a select input with card options
    function populateCardSelect(selectId, cards) {
        const cardSelect = document.querySelector(`#${selectId}`); // Select input for card numbers

        // Clear existing options
        // cardSelect.innerHTML = '';

        // Populate options
        cards.forEach(card => {
            // Mask the card number
            const maskedCardNumber = maskCardNumber(card.card_number);

            // Create option element for the select input
            const optionElement = document.createElement('option');
            optionElement.value = card.card_number; // Use the unmasked card number as the value
            optionElement.textContent = `${maskedCardNumber} - ${card.bank_name}`; // Display the masked card number

            // Append option to the select input
            cardSelect.appendChild(optionElement);
        });
    }

    fetch('../v2/get_cards.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Define the IDs of the select elements to populate
                const selectIds = ['card_numbers', 'select_card_delete'];

                // Populate each select input
                selectIds.forEach(selectId => {
                    populateCardSelect(selectId, data.cards);
                });
            }
            else {
                console.error('Failed to fetch Cards:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching Cards:', error);
        });

    // Function to mask the card number
    function maskCardNumber(cardNumber) {
        return cardNumber.slice(0, 4) + ' **** **** ' + cardNumber.slice(-4);
    }

    // Close modals
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            closeBtn.closest('.modal').style.display = 'none';
        });
    });

    window.addEventListener('click', (event) => {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Set the minimum month for expiry date
    setMinMonth();
    const addFundsForm = document.getElementById('addFundsForm');
    // Add funds form submission
    function handleFormSubmission(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const amount = form.querySelector('#amount').value;
            const pin = form.querySelector('#pin_addFund').value;
            const token = form.querySelector('#token_addFund').value;
            const card_number = form.querySelector('#card_numbers').value;
            const card_cvv = form.querySelector('#cvv_addFund').value;
            const messageDiv = document.getElementById('fundsMessage');

            fetch('../v2/add_funds.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `card_number=${card_number}&card_cvv=${card_cvv}&amount=${amount}&pin=${pin}&token=${token}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Success:', data.message);
                        messageDiv.textContent = 'Your wallet has been successfully Credited!';
                        alert('Your wallet has been successfully Credited!')
                        form.reset();
                        window.location.href = '../v1/dashboard.php'
                    } else {
                        console.log('Error:', data.message);
                        messageDiv.textContent = data.message;
                        alert(data.message)
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.textContent = 'Error: ' + error.message;
                    alert('An error occurred. Please Try Again Later')
                });
        });

        // Function to handle Card Delete
    }
    handleFormSubmission(addFundsForm);



    // Function to set minimum month value
    function setMinMonth() {
        const monthControl = document.querySelector('input[type="month"]');
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const currentMonth = `${year}-${month}`;
        monthControl.min = currentMonth;
    }

    // Show and hide fields based on selected update option
    const updateOption = document.getElementById('update_option');
    const updateFields = document.getElementById('updateFields');

    updateOption.addEventListener('change', function () {
        if (this.value !== "") {
            updateFields.style.display = 'block';
        } else {
            updateFields.style.display = 'none';
        }
    });
});
