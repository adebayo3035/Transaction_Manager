
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
        modal1 = document.getElementById("passwordResetModal"),
        modal2 = document.getElementById("getSecretQuestionModal"),
        closeModalBtns = document.querySelectorAll(".close");

    // Initialize modals as hidden
    [modal1, modal2].forEach(modal => modal.style.display = "none");

    // Toggle modals for reset password and secret question
    resetPasswordBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("passwordResetModal", "flex");
    };

    getSecretQuestionBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("getSecretQuestionModal", "flex");
    };

    // Close modals when 'x' is clicked
    closeModalBtns.forEach(closeBtn => {
        closeBtn.onclick = () => closeBtn.closest(".modal").style.display = "none";
    });

    // Close modal when clicking outside of it
    window.onclick = (e) => {
        if (e.target == modal1) toggleModal("passwordResetModal");
        if (e.target == modal2) toggleModal("getSecretQuestionModal");
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

                // Notify user of successful login
                displayMessage("Login successful! Redirecting...");

                // Wait for 3 seconds before redirecting
                await new Promise(resolve => setTimeout(resolve, 3000));

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

});
