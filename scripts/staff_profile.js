document.addEventListener('DOMContentLoaded', function () {
    // Constants and configuration
    const MODAL_IDS = {
        profile: "profileModal",
        order: "orderModal"
    };
    
    // DOM Elements
    const elements = {
        modals: {
            profile: document.getElementById(MODAL_IDS.profile),
            order: document.getElementById(MODAL_IDS.order)
        },
        buttons: {
            edit: document.querySelector('.edit-icon'),
            modifyDriver: document.querySelector('#modifyDriverDetails'),
            close: document.querySelector(".close"),
            close2: document.querySelector(".close2"),
            updateDriver: null // Will be set later
        },
        form: document.getElementById('adminForm'),
        photoInput: document.getElementById('photo'),
        displayElements: {
            profilePic: document.getElementById('profile-picture'),
            uploadedPhoto: document.getElementById('uploadedPhoto'),
            customerName: document.getElementById('customer-name'),
            firstName: document.getElementById('first-name'),
            lastName: document.getElementById('last-name'),
            customerStatus: document.getElementById('customer-status'),
            restrictionStatus: document.getElementById('restriction-status'),
            email: document.getElementById('display-email'),
            phone: document.getElementById('display-phone'),
            message: document.getElementById('message')
        },
        toggleElements: {
            email: document.getElementById('toggle-checkbox'),
            phone: document.getElementById('toggle-checkbox1')
        },
        orderTable: {
            table: document.querySelector('#orderDetailsTable tbody'),
            photoCell: document.querySelector('#driverPhoto')
        }
    };

    // Utility functions
    const utils = {
        maskEmail: (email) => email.replace(/(.{2})(.*)(@.*)/, "$1****$3"),
        maskPhone: (phone) => phone.replace(/(\d{4})(.*)(\d{4})/, "$1****$3"),
        toggleDisplay: (toggleCheckbox, resultElement, unmaskedData, maskedData) => {
            toggleCheckbox.addEventListener('change', () => {
                resultElement.textContent = toggleCheckbox.checked ? unmaskedData : maskedData;
            });
        },
        showModal: (modal) => modal.style.display = "block",
        hideModal: (modal) => modal.style.display = "none",
        handleOutsideClick: (event) => {
            if (event.target === elements.modals.profile || event.target === elements.modals.order) {
                utils.hideModal(elements.modals.profile);
                utils.hideModal(elements.modals.order);
            }
        }
    };

    // Modal event handlers
    const setupModalHandlers = () => {
        elements.buttons.edit.addEventListener('click', () => utils.showModal(elements.modals.profile));
        elements.buttons.modifyDriver.addEventListener('click', () => utils.showModal(elements.modals.order));
        elements.buttons.close.addEventListener('click', () => utils.hideModal(elements.modals.profile));
        elements.buttons.close2.addEventListener('click', () => utils.hideModal(elements.modals.order));
        window.addEventListener('click', utils.handleOutsideClick);
    };

    // Profile photo handlers
    const setupPhotoHandlers = () => {
        elements.photoInput.addEventListener('change', (event) => displayPhoto(event.target));
        
        elements.form.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(elements.form);

            fetch('backend/update_picture.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    elements.displayElements.uploadedPhoto.src = '../backend/admin_photos/' + data.file;
                    elements.displayElements.message.textContent = 'Profile picture updated successfully!';
                    elements.displayElements.message.style.color = 'green';
                    alert('Customer Profile Picture Updated Successfully');
                    location.reload();
                } else {
                    elements.displayElements.message.textContent = data.message;
                    elements.displayElements.message.style.color = 'red';
                }
            })
            .catch(() => {
                elements.displayElements.message.textContent = 'An error occurred while uploading the file.';
            });
        });
    };

    // Fetch and display admin profile
    const fetchAndDisplayProfile = () => {
        fetch('backend/staff_profile.php')
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to fetch profile:', data.message);
                    return;
                }

                const admin = data.admin;
                const photoPath = 'backend/admin_photos/' + admin.photo;

                // Update profile display
                elements.displayElements.profilePic.src = photoPath;
                elements.displayElements.uploadedPhoto.src = photoPath;
                elements.displayElements.customerName.textContent = `${admin.firstname} ${admin.lastname}`;
                elements.displayElements.firstName.textContent = admin.firstname;
                elements.displayElements.lastName.textContent = admin.lastname;
                elements.displayElements.customerStatus.textContent = admin.role;
               if (admin.restriction_id == 1) {
    elements.displayElements.restrictionStatus.innerHTML = `
        <div class="restriction-alert">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#d32f2f">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <div>
                <strong>Account Restricted</strong>
                <p style="margin: 5px 0 0; font-size: 0.9em;">
                    Your account access has been limited. Please contact Super Admin for assistance.
                </p>
            </div>
        </div>
    `;
} else {
    // Clear the content if not restricted
    elements.displayElements.restrictionStatus.textContent = '';
}

                // Mask and toggle email/phone
                const maskedEmail = utils.maskEmail(admin.email);
                const maskedPhone = utils.maskPhone(admin.phone);

                elements.displayElements.email.textContent = maskedEmail;
                elements.displayElements.phone.textContent = maskedPhone;

                utils.toggleDisplay(
                    elements.toggleElements.email, 
                    elements.displayElements.email, 
                    admin.email, 
                    maskedEmail
                );
                
                utils.toggleDisplay(
                    elements.toggleElements.phone, 
                    elements.displayElements.phone, 
                    admin.phone, 
                    maskedPhone
                );

                // Update order details table
                updateOrderDetailsTable(admin);
            })
            .catch(error => console.error('Error fetching customer data:', error));
    };

    // Update order details table
    const updateOrderDetailsTable = (admin) => {
        elements.orderTable.table.innerHTML = `
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
            <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updateStaffBtn">Update Record</button>
                </td>
            </tr>
        `;

        // Display photo
        elements.orderTable.photoCell.innerHTML = admin.photo 
            ? `<img src="backend/admin_photos/${admin.photo}" alt="Admin Photo" class="driver-photo">`
            : `<p>No photo available</p>`;

        // Set up update button
        elements.buttons.updateDriver = document.getElementById('updateStaffBtn');
        elements.buttons.updateDriver.addEventListener('click', () => updateStaffProfile(admin.unique_id));
    };

    // Initialize everything
    setupModalHandlers();
    setupPhotoHandlers();
    fetchAndDisplayProfile();
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
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(staffData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Your record has been successfully Updated.');
            document.getElementById('orderModal').style.display = 'none';
            location.reload();
        } else {
            alert('Failed to Update Your Record: ' + data.message);
        }
    })
    .catch(error => console.error('Error updating your record:', error));
}

function displayPhoto(input) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        const uploadedPhoto = document.getElementById('uploadedPhoto');
        uploadedPhoto.src = e.target.result;
        document.getElementById('photoContainer').style.display = 'block';
    };
    reader.readAsDataURL(file);
}