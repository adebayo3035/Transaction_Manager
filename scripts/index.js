
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
                // Successful login, redirect the user
                window.location.href = "splashscreen.php";
            } else {
                // Display error message from the server
                displayError(data.message || "Login failed. Please try again.");
            }
        } catch (error) {
            // Handle network or unexpected errors
            displayError("An error occurred while processing your request. Please try again later.");
        }
    });

    // Helper function to display error messages
    function displayError(message) {
        errorContainer.textContent = message;
        errorContainer.style.display = "block"; // Show the error message container
    }

});
