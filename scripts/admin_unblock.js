document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('restrictionForm');
    const modal = document.getElementById('confirmationModal');
    const confirmButton = document.getElementById('confirmButton');
    const cancelButton = document.getElementById('cancelButton');
    const errorContainer = document.querySelector('.errorContainer');
    let formData;

    form.onsubmit = (e) => {
        e.preventDefault();
        formData = new FormData(form);
        document.getElementById('confirmationMessage').textContent = `Are you sure you want to ${formData.get('restrictionType')} this account?`;
        modal.style.display = 'block';
    };

    confirmButton.onclick = () => {
        let xhr = new XMLHttpRequest();
        xhr.open("POST", "backend/unblock_account.php", true);
        xhr.onload = () => {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    let response = JSON.parse(xhr.response);
                    if (response.success) {
                        // alert("Operation successful!");
                        alert(response.message);
                        form.reset();
                        location.reload();
                    } else {
                        errorContainer.style.display = "block";
                        alert(response.message);
                        errorContainer.textContent = response.message;
                    }
                } else {
                    errorContainer.style.display = "block";
                    alert("An error occurred while processing your request. Please try again later.");
                    errorContainer.textContent = "An error occurred while processing your request. Please try again later.";
                }
            }
        };
        xhr.send(formData);
        modal.style.display = 'none';
    };

    cancelButton.onclick = () => {
        modal.style.display = 'none';
    };

    window.onclick = (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };

    document.querySelector('.close').onclick = () => {
        modal.style.display = 'none';
    };
});
