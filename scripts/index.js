const form = document.querySelector(".login form"),
continueBtn = form.querySelector(".button"),
errorText = form.querySelector(".errorContainer");

form.onsubmit = (e) => {
    e.preventDefault();
}

continueBtn.onclick = () => {
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "backend/admin_login3.php", true);
    xhr.onload = () => {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                try {
                    let data = JSON.parse(xhr.response);
                    if (data.success) {
                        location.href = "splashscreen.php";
                    } else {
                        errorText.style.display = "block";
                        // errorText.textContent = data.message;
                        alert("Error "+ data.message)
                    }
                } catch (e) {
                    console.error("Error parsing JSON response:", e);
                    errorText.style.display = "block";
                    errorText.textContent = "An unexpected error occurred. Please try again later.";
                }
            } else {
                console.error("Error: Server returned status", xhr.status);
                errorText.style.display = "block";
                errorText.textContent = "An error occurred while processing your request. Please try again later.";
            }
        }
    }
    let formData = new FormData(form);
    xhr.send(formData);
}

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
