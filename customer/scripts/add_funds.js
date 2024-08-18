document.addEventListener('DOMContentLoaded', function() {
    const form1 = document.getElementById('addFundsForm1');
    const messageDiv = document.getElementById('message');

    function handleFormSubmission(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const amount = form.querySelector('#amount').value;
            const pin = form.querySelector('#pin').value;
            const token = form.querySelector('#token').value;
            const card_number = form.querySelector('#card_number_addFunds').value;
            const card_cvv = form.querySelector('#cvv').value;

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
                    window.location.href = '../v1/cards.php'
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
    }

    handleFormSubmission(form1);
});

// Function to handle token pasting
function handlePaste(event) {
    event.preventDefault();
    const pasteData = event.clipboardData.getData('text');
    const inputField = document.getElementById('token');
    inputField.value = pasteData;
}
