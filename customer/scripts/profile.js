document.addEventListener('DOMContentLoaded', function() {
    // Start of Modal script
    // Get the modal and trigger button
    const modal = document.getElementById("profileModal");

    const editButton = document.querySelector('.edit-icon');
    const closeModal = document.querySelector(".close");
    // When the user clicks the edit button, open the modal
    editButton.addEventListener('click', () => {
        modal.style.display = "block";
    });
     // When the user clicks on (x), close the modal
     closeModal.addEventListener('click', () => {
        modal.style.display = "none";
    });
     // When the user clicks anywhere outside of the modal, close it
     window.onclick = function (event) {
        if (event.target == modal || event.target == document.getElementById('orderModal')) {
            modal.style.display = "none";
            document.getElementById('orderModal').style.display = "none";
        }
    }

    fetch('../v2/profile.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('profile-picture').src = '../../backend/customer_photos/' + data.photo;
            document.getElementById('uploadedPhoto').src = '../../backend/customer_photos/' + data.photo;
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

        // FUNCTION TO HANDLE IMAGE UPDATE
    document.getElementById('adminForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = document.getElementById('adminForm');
        var formData = new FormData(form);

        fetch('../v2/update_picture.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                var messageElement = document.getElementById('message');
                if (data.success) {
                    document.getElementById('uploadedPhoto').src = '../../backend/customer_photos/' + data.file;
                    messageElement.textContent = 'Profile picture updated successfully!';
                    messageElement.style.color = 'green';
                    console.log(data.message);
                    alert('Customer Profile Picture Updated Successfully')
                    location.reload();
                } else {
                    messageElement.textContent = data.message;
                    messageElement.style.color = 'red';
                    console.log(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('message').textContent = 'An error occurred while uploading the file.';
            });
    });
});

function displayPhoto(input) {
    var file = input.files[0];
    var time = Math.floor(Date.now() / 1000);
    if (file) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var uploadedPhoto = document.getElementById('uploadedPhoto');
            uploadedPhoto.setAttribute('src', e.target.result);
            document.getElementById('photoContainer').style.display = 'block'; // Show the photo container

            // Set the new file name to a hidden input field
            // document.getElementById('photo_name').value = time + file.name;
        };
        reader.readAsDataURL(file);
    }
}

//CALL UPLOAD PHOTO FUNCTION
let uploadBtn = document.getElementById('photo');
uploadBtn.addEventListener('change', (event) => {
    displayPhoto(event.target);
})