// Function to toggle modals
function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}


document.addEventListener('DOMContentLoaded', () => {
    // Close modals
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            closeBtn.closest('.modal').style.display = 'none';
            location.reload();
        });
    });

    window.addEventListener('click', (event) => {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Add New Driver form submission
    const addStaffForm = document.getElementById('addStaffForm');
    const inputs = addStaffForm.querySelectorAll('input, select');
    const submitBtn = document.getElementById('submitBtn');
    
    function checkFormCompletion() {
        let allFilled = true;
        inputs.forEach(input => {
            if (input.type !== 'hidden' && (input.type !== 'file' ? !input.value : !input.files.length)) {
                allFilled = false;
            }
        });
        submitBtn.style.visibility = allFilled ? 'visible' : 'hidden';
    }
    inputs.forEach(input => {
        input.addEventListener('input', checkFormCompletion);
        input.addEventListener('change', checkFormCompletion);
    });
    
    window.onload = checkFormCompletion;
    function handleFormSubmission(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            const messageDiv = document.getElementById('addStaffMessage');

            fetch('backend/admin_onboarding.php', {
                method: 'POST',
                // headers: {
                //     'Content-Type': 'application/x-www-form-urlencoded'
                // },
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Success:', data.message);
                        messageDiv.textContent = 'Staff has been successfully Onboarded!';
                        alert('Staff has been successfully Onboarded!')
                        location.reload();
                        // window.location.href = '../../Transaction_manager/dashboard.php';
                    } else {
                        console.log('Error:', data.message);
                        messageDiv.textContent = data.message;
                        // alert(data.message)
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.textContent = 'Error: ' + error.message;
                    alert('An error occurred. Please Try Again Later')
                });
        });
    }
    handleFormSubmission(addStaffForm);
});