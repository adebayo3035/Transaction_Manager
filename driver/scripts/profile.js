document.addEventListener('DOMContentLoaded', function () {
    function maskDetails(details) {
        return details.slice(0, 2) + ' ** ** ' + details.slice(-3);
    }
    // Modal toggle function
    function toggleModal(modalId, display) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = display;
    }
    // Get elements and bind events
    const modal = document.getElementById("profileModal");
    const editButton = document.querySelector('.edit-icon');
    const modifyDriverDetails = document.querySelector('#modifyDriverDetails');
    const resetLink = document.querySelector('#reset-link');
    const closeModalButtons = document.querySelectorAll(".close");

    // Event listeners for modals
    editButton?.addEventListener('click', () => toggleModal("profileModal", "block"));
    modifyDriverDetails?.addEventListener('click', () => toggleModal('orderModal', 'block'));
    resetLink?.addEventListener('click', () => toggleModal('resetQuestionAnswerModal', 'block'));
    closeModalButtons.forEach(btn => btn.addEventListener('click', () => btn.closest('.modal').style.display = 'none'));

    // Close modals when clicking outside
    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }

    // Fetch profile data
    fetch('../v2/profile.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('profile-picture').src = '../../backend/driver_photos/' + data.photo;
            document.getElementById('uploadedPhoto').src = '../../backend/driver_photos/' + data.photo;
            document.getElementById('customer-name').textContent = `${data.firstname} ${data.lastname}`;
            document.getElementById('first-name').textContent = data.firstname;
            document.getElementById('last-name').textContent = data.lastname;

            const email = data.email;
            const phone = data.phone_number;
            const maskedEmail = email.replace(/(.{2})(.*)(@.*)/, "$1****$3");
            const maskedPhone = phone.replace(/(\d{4})(.*)(\d{4})/, "$1****$3");

            const toggleEmail = document.getElementById('toggle-checkbox');
            const togglePhone = document.getElementById('toggle-checkbox1');
            const displayEmail = document.getElementById('display-email');
            const displayPhone = document.getElementById('display-phone');
            displayEmail.textContent = maskedEmail;
            displayPhone.textContent = maskedPhone;

            // Toggle email/phone display
            const toggleDisplay = (toggleCheckbox, resultElement, unmasked, masked) => {
                toggleCheckbox.addEventListener('change', () => {
                    resultElement.textContent = toggleCheckbox.checked ? unmasked : masked;
                });
            }
            toggleDisplay(toggleEmail, displayEmail, email, maskedEmail);
            toggleDisplay(togglePhone, displayPhone, phone, maskedPhone);
        })
        .catch(error => console.error('Error fetching customer data:', error));

    // FUNCTION TO HANDLE IMAGE UPDATE
    // Profile picture update
    document.getElementById('adminForm')?.addEventListener('submit', async function (event) {
        event.preventDefault();
        const form = event.currentTarget;
        const formData = new FormData(form);

        try {
            const response = await fetch('../v2/update_picture.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            const messageElement = document.getElementById('message');
            if (data.success) {
                document.getElementById('uploadedPhoto').src = '../../backend/driver_photos/' + data.file;
                messageElement.textContent = 'Profile picture updated successfully!';
                messageElement.style.color = 'green';
                alert('Customer Profile Picture Updated Successfully');
                location.reload();
            } else {
                messageElement.textContent = data.message;
                messageElement.style.color = 'red';
            }
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('message').textContent = 'An error occurred while uploading the file.';
        }
    });

    // Fetch and display driver info for update
    fetch('../v2/profile.php')
        .then(response => response.json())
        .then(data => {
            const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
            const photoCell = document.querySelector('#driverPhoto');

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
                    <td><input type="email" id="email" value="${data.email}"></td>
                </tr>
                <tr>
                    <td>Phone Number</td>
                    <td><input type="text" id="phoneNumber" value="${data.phone_number}"></td>
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
                    <td>
                        <select id="vehicleType" name="vehicleType">
                            <option value="Bicycle" ${data.vehicle_type === 'Bicycle' ? 'selected' : ''}>Bicycle</option>
                            <option value="Bike" ${data.vehicle_type === 'Bike' ? 'selected' : ''}>Bike</option>
                            <option value="Motorcycle" ${data.vehicle_type === 'Motorcycle' ? 'selected' : ''}>Motorcycle</option>
                            <option value="Tricycle" ${data.vehicle_type === 'Tricycle' ? 'selected' : ''}>Tricycle</option>
                            <option value="Car" ${data.vehicle_type === 'Car' ? 'selected' : ''}>Car</option>
                            <option value="Bus" ${data.vehicle_type === 'Bus' ? 'selected' : ''}>Bus</option>
                            <option value="Lorry" ${data.vehicle_type === 'Lorry' ? 'selected' : ''}>Lorry</option>
                            <option value="Others" ${data.vehicle_type === 'Others' ? 'selected' : ''}>Others</option>
                        </select>
                    </td>
                </tr>
                <tr id="otherVehicleContainer" style="display: ${data.vehicle_type === 'Others' ? 'table-row' : 'none'};">
                    <td>Other Vehicle Type</td>
                    <td><input type="text" id="vehicleTypeOther" placeholder="Specify other vehicle type"></td>
                </tr>
                <tr id="secretAnswerContainer"">
                    <td>Enter Secret Answer:</td>
                    <td><input type="password" id="secretAnswer"></td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <button id="updateDriverBtn">Update</button>
                    </td>
                </tr>
            `;

            // Element references
            const vehicleTypeSelect = document.getElementById('vehicleType');
            const otherVehicleContainer = document.getElementById('otherVehicleContainer');
            const vehicleTypeOther = document.getElementById('vehicleTypeOther');

            // Toggle otherVehicleContainer based on vehicle type selection
            vehicleTypeSelect.addEventListener('change', function () {
                if (vehicleTypeSelect.value === 'Others') {
                    otherVehicleContainer.style.display = 'table-row';
                } else {
                    otherVehicleContainer.style.display = 'none';
                    vehicleTypeOther.value = ''; // Clear other vehicle input when not needed
                }

            });
            // Display photo if available
            if (data.photo) {
                const photo = data.photo;
                photoCell.innerHTML = `<img src="../../backend/driver_photos/${photo}" alt="Driver Photo" class="driver-photo">`;
            } else {
                photoCell.innerHTML = `<p>No photo available</p>`;
            }

            // Attach event listener after the button is created
            document.getElementById('updateDriverBtn').addEventListener('click', () => {
                updateDriver(data.id);
            });
        })
        .catch(error => console.error('Error fetching customer data:', error));

    // Reset Secret Question and Answer
    const resetQuestionAnswerForm = document.getElementById("resetQuestionAnswerForm");
    resetQuestionAnswerForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const resetEmail = document.getElementById("resetEmail")?.value;
        const resetPassword = document.getElementById("resetPassword")?.value;
        const secretQuestion = document.getElementById("secretQuestion")?.value;
        const resetSecretAnswer = document.getElementById("resetSecretAnswer")?.value;
        const confirmAnswer = document.getElementById("confirmAnswer")?.value;

        if (!resetEmail || !resetPassword || !secretQuestion || !resetSecretAnswer || !confirmAnswer) {
            alert("All fields are required!");
            return;
        }

        try {
            const response = await fetch('../v2/reset_answer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: resetEmail,
                    password: resetPassword,
                    secret_question: secretQuestion,
                    secret_answer: resetSecretAnswer,
                    confirm_answer: confirmAnswer
                })
            });
            const data = await response.json();
            if (data.success) {
                alert("Secret Question and Answer successfully updated!");
                toggleModal("resetQuestionAnswerModal", "none");
            } else {
                alert(`Error resetting Secret Question and Answer: ${data.message}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert("An error occurred. Please try again.");
        }
    });



    function updateDriver(driverId) {
        const driverData = {
            id: driverId,
            email: document.getElementById('email')?.value,
            phone_number: document.getElementById('phoneNumber')?.value,
            gender: document.getElementById('gender')?.value,
            address: document.getElementById('address')?.value,
            vehicle_type: document.getElementById('vehicleType')?.value,
            vehicle_type_others: document.getElementById('vehicleTypeOther')?.value,
            secret_answer: document.getElementById('secretAnswer')?.value
        };

        fetch('../v2/update_driver.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(driverData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Your record has been successfully updated.');
                    location.reload();
                } else {
                    alert('Failed to update record.');
                }
            })
            .catch(error => console.error('Error updating driver data:', error));
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

});

