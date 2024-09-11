document.getElementById('login-form').addEventListener('submit', function (event) {
    event.preventDefault();
    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => data[key] = value);

    fetch('../v2/index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            const message = document.getElementById('message');
            if (data.success) {
                message.style.color = 'green';
                message.textContent = 'Login successful!';
                window.location.href = 'splashscreen.php'; // Redirect to customer customer splashscreen
            } else {
                message.style.color = 'red';
                message.textContent = 'Login failed: ' + data.message;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const message = document.getElementById('message');
            message.style.color = 'red';
            message.textContent = 'An error occurred. Please try again.';
        });
});

const passwordInput = document.getElementById('password');
const showPasswordCheckbox = document.getElementById('viewPassword');

// Function to toggle password visibility
function togglePasswordVisibility() {
    if (showPasswordCheckbox.checked) {
        // Display password in plain text
        passwordInput.type = 'text';
    } else {
        // Encrypt password
        passwordInput.type = 'password';
    }
}

// Add event listener to checkbox
showPasswordCheckbox.addEventListener('change', togglePasswordVisibility);