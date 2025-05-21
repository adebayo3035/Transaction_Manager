// Reusable modal toggle function
const toggleModal = (modalId, display = "none") => {
    const modal = document.getElementById(modalId);
    modal.style.display = display;
};
document.addEventListener('DOMContentLoaded', () => {
        reactivateAccountBtn = document.getElementById('reactivateAccountBtn'),
        modal3 = document.getElementById("accountActivationModal"),
        closeModalBtns = document.querySelectorAll(".close");

    // Initialize modals as hidden
    [modal3].forEach(modal => modal.style.display = "none");

    // Parse URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');

    // If the action is reset-password, open the modal
    reactivateAccountBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("accountActivationModal", "flex");
    };

    // Close modals when 'x' is clicked
    closeModalBtns.forEach(closeBtn => {
        closeBtn.onclick = () => closeBtn.closest(".modal").style.display = "none";
    });

    // Close modal when clicking outside of it
    window.onclick = (e) => {
        if (e.target == modal3) toggleModal("accountActivationModal");

    };

})
function openTab(evt, tabName) {
    // Hide all tab contents
    const tabContents = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
    }
    
    // Remove active class from all tab buttons
    const tabButtons = document.getElementsByClassName("tab-button");
    for (let i = 0; i < tabButtons.length; i++) {
        tabButtons[i].className = tabButtons[i].className.replace(" active", "");
    }
    
    // Show the current tab and add active class to the button
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}
// Tab switching function (add this if not already in your code)
function switchToValidateTab() {
    // Hide all tab contents
    const tabContents = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
    }
    
    // Remove active class from all tab buttons
    const tabButtons = document.getElementsByClassName("tab-button");
    for (let i = 0; i < tabButtons.length; i++) {
        tabButtons[i].className = tabButtons[i].className.replace(" active", "");
    }
    
    // Show the validate tab and activate its button
    document.getElementById('validateOTPTab').style.display = "block";
    tabButtons[1].className += " active";
}
// Generate and send OTP - Now tied to the otpGenerationForm
document.getElementById('otpGenerationForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const email = document.getElementById("emailActivateAccount").value;
    const sendOTPButton = document.getElementById("sendOTP");
    const responseMessage = document.getElementById("OTPResponse");
    const requestData = { email };

    // Disable button immediately after clicking
    sendOTPButton.disabled = true;
    sendOTPButton.textContent = "Sending...";
    sendOTPButton.style.opacity = "0.6";
    // sendOTPButton.style.pointerEvents = "none"; 

    // Reset message display
    responseMessage.textContent = "";
    responseMessage.style.color = "";

    try {
        const response = await fetch("../../backend/send_otp.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(requestData)
        });

        const result = await response.json();
        
        // Display response message
        responseMessage.textContent = result.message;
        responseMessage.style.color = result.success ? "green" : "red";

        if (result.success) {
            // Switch to the validation tab on success
            alert(result.message);
            switchToValidateTab();
            
            // Pre-fill the email in the validation form if needed
            document.getElementById("validateEmail").value = email;
            document.getElementById('otp').value = result.message;
            
            // Keep the button disabled for 2 minutes (OTP validity period)
            sendOTPButton.textContent = "Wait 2 minutes...";
            setTimeout(() => {
                sendOTPButton.disabled = true;
                sendOTPButton.textContent = "Send OTP4";
                sendOTPButton.style.opacity = "1"; 
                sendOTPButton.style.cursor = "not-allowed"; 
                window.location.reload();
            }, 120000);
        } else {
            // If request fails, re-enable button immediately
            sendOTPButton.disabled = false;
            sendOTPButton.textContent = "Send OTP3";
            sendOTPButton.style.opacity = "1"; 
            sendOTPButton.style.cursor = "pointer"; 
        }
    } catch (error) {
        console.error("Error submitting OTP request:", error);
        responseMessage.textContent = "An error occurred. Please try again.";
        responseMessage.style.color = "red";

        // Re-enable button on failure
        sendOTPButton.disabled = false;
        sendOTPButton.textContent = "Send OTP2";
        sendOTPButton.style.opacity = "1"; 
        sendOTPButton.style.cursor = "pointer"; 
    }
});
// Form submission for account reactivation - Now tied to accountActivationForm
document.getElementById("accountActivationForm").addEventListener("submit", async function (event) {
    event.preventDefault();

    const email = document.getElementById("emailActivateAccount").value;
    const reason = document.getElementById("reason").value;
    const otp = document.getElementById('otp').value;

    const requestData = {
        email,
        reason,
        otp
    };

    const submitButton = event.target.querySelector('input[type="submit"]');
    const originalButtonText = submitButton.value;
    
    try {
        // Show loading state
        submitButton.disabled = true;
        submitButton.value = "Processing...";

        const response = await fetch("backend/account_reactivation_request.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(requestData)
        });

        const result = await response.json();
        
        // Show result in a more elegant way than alert()
        const displayResponse = document.getElementById("displayResponse");
        displayResponse.textContent = result.message;
        displayResponse.style.color = result.success ? "green" : "red";
        displayResponse.style.display = "block";

        if (result.success) {
            document.getElementById("accountActivationForm").reset();
            // Optionally switch back to first tab
            // document.querySelector('.tab-button').click();
        }
    } catch (error) {
        console.error("Error submitting reactivation request:", error);
        document.getElementById("displayResponse").textContent = "An error occurred. Please try again.";
        document.getElementById("displayResponse").style.color = "red";
        document.getElementById("displayResponse").style.display = "block";
    } finally {
        submitButton.disabled = false;
        submitButton.value = originalButtonText;
    }
});

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