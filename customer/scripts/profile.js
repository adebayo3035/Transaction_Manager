document.addEventListener('DOMContentLoaded', function() {
    fetch('../v2/profile.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('profile-picture').src = '../../backend/customer_photos/' + data.photo;
            document.getElementById('customer-name').textContent = data.firstname + ' ' + data.lastname;
            document.getElementById('first-name').textContent = data.firstname;
            document.getElementById('last-name').textContent = data.lastname;
            
            let email = data.email;
            let phone = data.mobile_number;
            let maskedEmail = email.replace(/(.{2})(.*)(@.*)/, "$1****$3");
            let maskedPhone = phone.replace(/(\d{4})(.*)(\d{4})/, "$1****$3");

            let toggleEmail = document.getElementById('toggle-checkbox');
            let togglePhone = document.getElementById('toggle-checkbox1');
            let displayEmail = document.getElementById('display-email');
            let displayPhone = document.getElementById('display-phone');
            displayEmail.textContent = maskedEmail;
            displayPhone.textContent = maskedPhone;

            function toggleDisplay(toggleCheckbox, resultElement, unmasked_data, maskedData) {
                toggleCheckbox.addEventListener('change', function() {
                    if (toggleCheckbox.checked) {
                        resultElement.textContent = unmasked_data;
                    } else {
                        resultElement.textContent = maskedData;
                    }
                });
            }

            toggleDisplay(toggleEmail, displayEmail, email, maskedEmail);
            toggleDisplay(togglePhone, displayPhone, phone, maskedPhone);
        })
        .catch(error => console.error('Error fetching customer data:', error));
});