document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('adminForm');
    const modal = document.getElementById('resetModal');
    const closeModal = document.getElementById('closeModal');
    const resetPasswordForm = document.getElementById('resetPasswordForm');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const email = document.getElementById('email').value;
        const secretAnswer = document.getElementById('secret_answer').value;

        let validationInfo = {
            email: email,
            secret_answer: secretAnswer
        };

        try {
            const response = await fetch('../v2/reset_password2.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(validationInfo)
            });

            const data = await response.json();
            if (data.success) {
                // Show the modal if validation is successful
                modal.style.display = 'block';
            } else {
                alert('Invalid email or secret answer. Please try again.');
            }
        } catch (error) {
            console.error('An error occurred:', error);
            alert('An error occurred while validating the information.');
        }
    });

    // Close the modal
    closeModal.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    // Close the modal when clicking outside of it
    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    resetPasswordForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (newPassword !== confirmPassword) {
            alert('Passwords do not match.');
            return;
        }

        let newPasswordInfo = {
            email: document.getElementById('email').value,
            new_password: newPassword
        };

        try {
            const response = await fetch('../v2/update_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(newPasswordInfo)
            });

            const data = await response.json();
            if (data.success) {
                alert('Password has been reset successfully.');
                modal.style.display = 'none';
                form.reset(); // Clear the form fields
                window.location.href ="../v1/index.php";
            } else {
                alert('An error occurred while resetting the password.');
            }
        } catch (error) {
            console.error('An error occurred:', error);
            alert('An error occurred while resetting the password.');
        }
    });
});
