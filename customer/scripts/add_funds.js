document.addEventListener('DOMContentLoaded', function() {
    // const generateTokenBtn = document.getElementById('generateTokenBtn');
    // const tokenInput = document.getElementById('token');
    const form = document.getElementById('addFundsForm');
    const messageDiv = document.getElementById('message');

    // Handle form submission
    form.addEventListener('submit', function(event) {
        event.preventDefault();

        const amount = document.getElementById('amount').value;
        const pin = document.getElementById('pin').value;
        const token = document.getElementById('token').value;

        fetch('../v2/add_funds.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `amount=${amount}&pin=${pin}&token=${token}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageDiv.textContent = 'Your wallet has been successfully Credited!';
                alert('Your wallet has been successfully Credited!')
                form.reset();
                window.location.href = '../v1/cards.php'
            } else {
                messageDiv.textContent = data.message;
                alert(data.message)
            }
        })
        .catch(error => {
            messageDiv.textContent = 'Error: ' + error.message;
            alert('An error occured. Please Try Again Later')
        });
    });
});

// function to handle token Pasting
function handlePaste(event) {
    event.preventDefault();
    const pasteData = event.clipboardData.getData('text');
    const inputField = document.getElementById('token');
    inputField.value = pasteData;
}