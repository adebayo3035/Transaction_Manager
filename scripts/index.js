
const displayError = (message) => {
    errorText.style.display = "block";
    errorText.textContent = message;
};

// Password visibility toggle
const passwordInput = document.getElementById('password'),
    showPasswordCheckbox = document.getElementById('viewPassword');

showPasswordCheckbox.addEventListener('change', () => {
    passwordInput.type = showPasswordCheckbox.checked ? 'text' : 'password';
});

// Reusable modal toggle function
const toggleModal = (modalId, display = "none") => {
    const modal = document.getElementById(modalId);
    modal.style.display = display;
};

document.addEventListener("DOMContentLoaded", () => {
    const resetPasswordBtn = document.getElementById("resetPasswordBtn"),
        getSecretQuestionBtn = document.getElementById('getSecretQuestionBtn'),
        reactivateAccountBtn = document.getElementById('reactivateAccountBtn'),
        modal1 = document.getElementById("passwordResetModal"),
        modal2 = document.getElementById("getSecretQuestionModal"),
        modal3 = document.getElementById("accountActivationModal"),
        closeModalBtns = document.querySelectorAll(".close");

    // Initialize modals as hidden
    [modal1, modal2, modal3].forEach(modal => modal.style.display = "none");

    // Parse URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');

    // If the action is reset-password, open the modal
    if (action === "reset-password") {
        toggleModal("passwordResetModal", "flex");
    }

    // Toggle modals for reset password and secret question
    resetPasswordBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("passwordResetModal", "flex");
    };

    getSecretQuestionBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("getSecretQuestionModal", "flex");
    };
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
        if (e.target == modal1) toggleModal("passwordResetModal");
        if (e.target == modal2) toggleModal("getSecretQuestionModal");
        if (e.target == modal3) toggleModal("accountActivationModal");

    };

    // Reset Password Form Submission
    const resetPasswordForm = document.getElementById("resetPasswordForm");

    resetPasswordForm.onsubmit = async (e) => {
        e.preventDefault();

        const resetEmail = document.getElementById("resetEmail").value,
            resetSecretAnswer = document.getElementById("resetSecretAnswer").value,
            newPassword = document.getElementById("newPassword").value,
            confirmPassword = document.getElementById("confirmPassword").value;

        try {
            const response = await fetch('backend/reset_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: resetEmail, password: newPassword, secret_answer: resetSecretAnswer, confirmPassword: confirmPassword })
            });

            const data = await response.json();
            if (data.success) {
                alert("Password reset successful!");
                toggleModal("passwordResetModal");
            } else {
                alert(`Error resetting password: ${data.message}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert("An error occurred. Please try again.");
        }
    };

    // Get Secret Question form
    const getSecretQuestionForm = document.getElementById("getSecretQuestionForm");
    getSecretQuestionForm.onsubmit = async (e) => {
        e.preventDefault();

        const email = document.getElementById("emailSecretQuestion").value,
            password = document.getElementById("passwordSecretQuestion").value

        try {
            const response = await fetch('backend/secret_question.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, password: password })
            });

            const data = await response.json();
            if (data.success) {
                alert(`Your Secret Question is: " ${data.secret_question}`);
                document.getElementById('displayQuestion').textContent = `Your Secret Question is: " ${data.secret_question}`;
            } else {
                alert(`Error fetching secret question: ${data.message}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert("An error occurred. Please try again.");
        }
    };

    // Login form submission
    // Form submission logic
    const loginForm = document.getElementById("loginForm");
    const loginButton = document.getElementById('btnLogin');
    const errorContainer = document.querySelector(".errorContainer");

    // Event listener for form submission
    loginForm.addEventListener("submit", async (e) => {
        e.preventDefault(); // Prevent default form submission

        // Get values from input fields
        const username = loginForm.querySelector('input[name="username"]').value;
        const password = loginForm.querySelector('input[name="password"]').value;

        // Basic validation
        if (!username || !password) {
            displayError("Please enter both username and password.");
            return;
        }

        // Prepare data to send
        const postData = {
            username: username,
            password: password
        };

        try {
            // Send the data using fetch
            const response = await fetch("backend/admin_login3.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json" // Send data as JSON
                },
                body: JSON.stringify(postData)
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Disable all form elements and indicate processing
                disableForm(loginForm);
                document.getElementById('resetPasswordBtn').style.cursor = "not-allowed";
                document.getElementById('resetPasswordBtn').style.opacity = "0.6";
                document.getElementById('getSecretQuestionBtn').style.cursor = "not-allowed";
                document.getElementById('getSecretQuestionBtn').style.opacity = "0.6";

                // Notify user of successful login
                displayMessage("Login successful! Redirecting...");

                // Wait for 3 seconds before redirecting
                await new Promise(resolve => setTimeout(resolve, 0));
                console.log("Login is now successful")
                // Redirect to splash screen
                window.location.href = "splashscreen.php";
            } else {
                // Display error message from the server
                displayMessage(data.message || "Login failed. Please try again.");
            }
        } catch (error) {
            // Handle network or unexpected errors
            displayMessage("An error occurred while processing your request. Please try again later.");
        }
    });
    // Helper function to display messages with auto-close
    function displayMessage(message) {
        // Create modal elements
        const modal = document.createElement('div');
        modal.className = 'custom-alert-modal';

        const modalContent = document.createElement('div');
        modalContent.className = 'custom-alert-content';

        const messageElement = document.createElement('p');
        messageElement.className = 'custom-alert-message';
        messageElement.textContent = message;

        const okButton = document.createElement('button');
        okButton.className = 'custom-alert-button';
        okButton.textContent = 'OK';

        // Assemble modal
        modalContent.appendChild(messageElement);
        modalContent.appendChild(okButton);
        modal.appendChild(modalContent);
        document.body.appendChild(modal);

        // Auto-close after 3 seconds
        const timeoutId = setTimeout(() => {
            if (document.body.contains(modal)) {
                modal.remove();
            }
        }, 3000);

        // Close on button click
        okButton.addEventListener('click', () => {
            clearTimeout(timeoutId);
            modal.remove();
        });

        // Update error container (your original functionality)
        errorContainer.textContent = message;
        errorContainer.style.display = "block";
    }

    function disableForm(form) {
        Array.from(form.elements).forEach(element => {
            element.disabled = true;
        });
        form.style.opacity = "0.6"; // Optional: Reduce opacity to indicate it's disabled
        form.style.pointerEvents = "none"; // Prevent interactions
    }

    // Account Reactivation and OTP generation Request Method and processing

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
        const response = await fetch("backend/send_otp.php", {
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
            
            // Keep the button disabled for 2 minutes (OTP validity period)
            sendOTPButton.textContent = "Wait 2 minutes...";
            setTimeout(() => {
                sendOTPButton.disabled = false;
                sendOTPButton.textContent = "Send OTP4";
                sendOTPButton.style.opacity = "1"; 
                sendOTPButton.style.cursor = "pointer"; 
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

});

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

// You might want to automatically switch to validate tab after OTP generation
// function switchToValidateTab() {
//     document.getElementById('validateOTPTab').style.display = "block";
//     document.getElementById('generateOTPTab').style.display = "none";
    
//     // Update active button
//     const tabButtons = document.getElementsByClassName("tab-button");
//     tabButtons[0].className = tabButtons[0].className.replace(" active", "");
//     tabButtons[1].className += " active";
// }