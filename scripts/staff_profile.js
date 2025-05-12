document.addEventListener('DOMContentLoaded', function () {
    function maskDetails(details) {
        return details.slice(0, 2) + ' ** ** ' + details.slice(-3);
    }
    // Start of Modal script
    // Get the modal and trigger button
    const modal = document.getElementById("profileModal");
    const modifyDriverDetails = document.querySelector('#modifyDriverDetails')
    const editButton = document.querySelector('.edit-icon');
    const closeModal = document.querySelector(".close");
    const closeModal2 = document.querySelector(".close2");

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

    fetch('backend/staff_profile.php')
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Failed to fetch profile:', data.message);
            return;
        }

        const admin = data.admin;

        document.getElementById('profile-picture').src = 'backend/admin_photos/' + admin.photo;
        document.getElementById('uploadedPhoto').src = 'backend/admin_photos/' + admin.photo;
        document.getElementById('customer-name').textContent = admin.firstname + ' ' + admin.lastname;
        document.getElementById('first-name').textContent = admin.firstname;
        document.getElementById('last-name').textContent = admin.lastname;
        document.getElementById('customer-status').textContent = admin.role;

        let email = admin.email;
        let phone = admin.phone;
        let maskedEmail = email.replace(/(.{2})(.*)(@.*)/, "$1****$3");
        let maskedPhone = phone.replace(/(\d{4})(.*)(\d{4})/, "$1****$3");

        let toggleEmail = document.getElementById('toggle-checkbox');
        let togglePhone = document.getElementById('toggle-checkbox1');
        let displayEmail = document.getElementById('display-email');
        let displayPhone = document.getElementById('display-phone');

        displayEmail.textContent = maskedEmail;
        displayPhone.textContent = maskedPhone;

        function toggleDisplay(toggleCheckbox, resultElement, unmaskedData, maskedData) {
            toggleCheckbox.addEventListener('change', function () {
                resultElement.textContent = toggleCheckbox.checked ? unmaskedData : maskedData;
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

        fetch('backend/update_picture.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                var messageElement = document.getElementById('message');
                if (data.success) {
                    document.getElementById('uploadedPhoto').src = '../backend/admin_photos/' + data.file;
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


    fetch('backend/staff_profile.php')
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.admin) {
            throw new Error('Invalid or missing admin data');
        }

        const admin = data.admin;
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        const photoCell = document.querySelector('#driverPhoto');

        orderDetailsTable.innerHTML = `
            <tr>
                <td>Date Onboarded</td>
                <td><input type="text" id="dateCreated" value="${admin.created_at || ''}" disabled></td>
            </tr>
            <tr>
                <td>First Name</td>
                <td><input type="text" id="firstname" value="${admin.firstname || ''}" disabled></td>
            </tr>
            <tr>
                <td>Last Name</td>
                <td><input type="text" id="lastname" value="${admin.lastname || ''}" disabled></td>
            </tr>
            <tr>
                <td>Email</td>
                <td><input type="email" id="email" value="${admin.email || ''}"></td>
            </tr>
            <tr>
                <td>Phone Number</td>
                <td><input type="text" id="phoneNumber" value="${admin.phone || ''}"></td>
            </tr>
            <tr>
                <td>Enter Secret Answer</td>
                <td><input type="password" id="secretAnswer" autocomplete="off"></td>
            </tr>
        `;

        // Display photo
        if (admin.photo) {
            photoCell.innerHTML = `<img src="backend/admin_photos/${admin.photo}" alt="Admin Photo" class="driver-photo">`;
        } else {
            photoCell.innerHTML = `<p>No photo available</p>`;
        }

        // Add action buttons
        orderDetailsTable.innerHTML += `
            <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updateDriverBtn">Update Record</button>
                </td>
            </tr>
        `;

        document.getElementById('updateDriverBtn').addEventListener('click', () => {
            updateStaffProfile(admin.unique_id);
        });
    })
    .catch(error => console.error('Error fetching customer data:', error));


});

function updateStaffProfile(adminId) {

    const staffData = {
        admin_id: adminId,
        email: document.getElementById('email').value,
        phone_number: document.getElementById('phoneNumber').value,
        secret_answer: document.getElementById('secretAnswer').value
    };

    fetch('backend/update_staff.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(staffData)
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

