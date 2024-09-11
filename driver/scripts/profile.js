document.addEventListener('DOMContentLoaded', function () {
    function maskDetails(details) {
        return details.slice(0, 2) + ' ** ** ' + details.slice(-3);
    }
    // Start of Modal script
    // Get the modal and trigger button
    const modal = document.getElementById("profileModal");

    const editButton = document.querySelector('.edit-icon');
    const closeModal = document.querySelector(".close");
    const closeModal2 = document.querySelector(".close2");
    const modifyDriverDetails = document.querySelector('#modifyDriverDetails')

    // When the user clicks the edit button, open the modal
    editButton.addEventListener('click', () => {
        modal.style.display = "block";
    });
    // when user clicks the Update button display modal for Update
    modifyDriverDetails.addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'block';
    })

    // When the user clicks on (x), close the modal
    closeModal.addEventListener('click', () => {
        modal.style.display = "none";
    });

    // When the user clicks on (x), close the modal
    closeModal2.addEventListener('click', () => {
        document.getElementById('orderModal').style.display = "none";
    });

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function (event) {
        if (event.target == modal || event.target == document.getElementById('orderModal')) {
            modal.style.display = "none";
            document.getElementById('orderModal').style.display = "none";
        }
    }

    // end of modal script
    fetch('../v2/profile.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('profile-picture').src = '../../backend/driver/driver_photos/' + data.photo;
            document.getElementById('uploadedPhoto').src = '../../backend/driver/driver_photos/' + data.photo;
            document.getElementById('customer-name').textContent = data.firstname + ' ' + data.lastname;
            document.getElementById('first-name').textContent = data.firstname;
            document.getElementById('last-name').textContent = data.lastname;

            let email = data.email;
            let phone = data.phone_number;
            let maskedEmail = email.replace(/(.{2})(.*)(@.*)/, "$1****$3");
            let maskedPhone = phone.replace(/(\d{4})(.*)(\d{4})/, "$1****$3");

            let toggleEmail = document.getElementById('toggle-checkbox');
            let togglePhone = document.getElementById('toggle-checkbox1');
            let displayEmail = document.getElementById('display-email');
            let displayPhone = document.getElementById('display-phone');
            displayEmail.textContent = maskedEmail;
            displayPhone.textContent = maskedPhone;

            function toggleDisplay(toggleCheckbox, resultElement, unmasked_data, maskedData) {
                toggleCheckbox.addEventListener('change', function () {
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
                    document.getElementById('uploadedPhoto').src = '../../backend/driver/driver_photos/' + data.file;
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

    // This section displays driver information which are available for modification

    fetch('../v2/profile.php')
        .then(response => response.json())
        .then(data => {
            const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
            const photoCell = document.querySelector('#driverPhoto');

            // Store original email and phone number in hidden fields
            // const hiddenEmailInput = `<input type="hidden" id="originalEmail" value="${data.email}">`;
            // const hiddenPhoneNumberInput = `<input type="hidden" id="originalPhoneNumber" value="${data.phone_number}">`;

            orderDetailsTable.innerHTML = `
                <tr>
                    <td>Date Onboarded</td>
                    <td><input type="text" id="dateCreated" value="${data.date_created}" disabled></td>
                </tr>
                <tr>
                    <td>First Name</td>
                    <td><input type="text" id="firstname" value="${data.firstname}" disabled></td>
                </tr>
                <tr>
                    <td>Last Name</td>
                    <td><input type="text" id="lastname" value="${data.lastname}" disabled></td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>
                        <input type="email" id="email" value="${(data.email)}">
                       
                    </td>
                </tr>
                <tr>
                    <td>Phone Number</td>
                    <td>
                        <input type="text" id="phoneNumber" value="${(data.phone_number)}">
                        
                    </td>
                </tr>
                <tr>
                    <td>Gender</td>
                    <td>
                        <select id="gender">
                            <option value="Male" ${data.gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${data.gender === 'Female' ? 'selected' : ''}>Female</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>House Address</td>
                    <td><input type="text" id="address" value="${data.address}"></td>
                </tr>
                <tr>
                    <td>License Number</td>
                    <td><input type="text" id="licenseNumber" value="${data.license_number}" disabled></td>
                </tr>
                <tr>
                    <td>Vehicle Type</td>
                    <td><input type="text" id="vehicleType" value="${data.vehicle_type}"></td>
                </tr>
                <tr>
                    <td>Secret Question</td>
                    
                        <td><input type="text" id="secretQuestion" value="${data.secret_question}"></td>
                    
                </tr>
                <tr>
                    <td>Secret Answer</td>
                    
                        <td><input type="password" id="secretAnswer" value="${data.secret_answer}"></td>
                    
                </tr>
            `;

            // Display photo if available
            if (data.photo) {

                const photo = data.photo;
                photoCell.innerHTML = `<img src="../../backend/driver/driver_photos/${photo}" alt="Driver Photo" class="driver-photo">`;
            } else {
                photoCell.innerHTML = `<p>No photo available</p>`;
            }

            // Add "Update" and "Delete" buttons below the table for performing actions
            const actionButtons = `
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <button id="updateDriverBtn">Update</button>
                    </td>
                </tr>
            `;
            orderDetailsTable.innerHTML += actionButtons;

             // Attach event listener after the button is created
             document.getElementById('updateDriverBtn').addEventListener('click', () => {
                updateDriver(data.id);
            });

        })
        .catch(error => console.error('Error fetching customer data:', error));
});


function updateDriver(driverId) {

    const driverData = {
        id: driverId,
        email: document.getElementById('email').value,
        phone_number: document.getElementById('phoneNumber').value,
        gender: document.getElementById('gender').value,
        address: document.getElementById('address').value,
        vehicle_type: document.getElementById('vehicleType').value,
        secret_question: document.getElementById('secretQuestion').value,
        secret_answer: document.getElementById('secretAnswer').value
    };

    fetch('../v2/update_driver.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(driverData)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Your record has been successfully Updated.');
                document.getElementById('orderModal').style.display = 'none';
                location.reload(); // Refresh the table after update
            } else {
                alert('Failed to Update Your Record: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating your record:', error);
        });
}

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

