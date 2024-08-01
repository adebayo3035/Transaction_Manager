const form = document.getElementById('adminForm');
const submitBtn = document.getElementById('submitBtn');
const inputs = form.querySelectorAll('input, select');

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

// form to handle Data Submission
form.addEventListener('submit', function (event) {
    event.preventDefault();
    const formData = new FormData(form);

    fetch('backend/admin_onboarding.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Congratulations! Staff has been successfully onboarded.');
                window.location.href = '../Transaction_manager/staffs.php';
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error);
        });
});