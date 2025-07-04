// Reusable modal toggle function
const toggleModal = (modalId, display = "none") => {
    const modal = document.getElementById(modalId);
    modal.style.display = display;
};
// Close modals when 'x' is clicked
const closeModalBtns = document.querySelectorAll(".close");

closeModalBtns.forEach(closeBtn => {
    closeBtn.onclick = function () {
        const modal = closeBtn.closest(".modal");
        if (modal) {
            modal.style.display = "none"; // Close the modal
        } else {
            console.error("Modal not found for this close button.");
        }
    };
});

// Close the modal if the user clicks outside of it
window.onclick = function (event) {
    if (event.target.classList.contains("modal")) {
        event.target.style.display = "none"; // Close modal when clicking outside
    }
};

document.addEventListener("DOMContentLoaded", () => {
    const resetQuestionAnswerBtn = document.getElementById("reset-link");
    const restrictAccountBtn = document.getElementById('block-account');
    const unblockBtn = document.getElementById('unblock-account');
    const unlockBtn = document.getElementById('unlock-account')
    const reactivateBtn = document.getElementById('reactivate-account')
    const removeRestrctictionBtn = document.getElementById('remove-restriction')

    //validate user and button to display
    // Fetch logged-in user role
    fetch('backend/staff_profile.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const userRole = data.admin.role;

                if (userRole === 'Admin') {
                    // Show only reset secret answer
                    resetQuestionAnswerBtn.style.display = "inline-block";

                } else if (userRole === 'Super Admin') {
                    // Show all buttons
                    resetQuestionAnswerBtn.style.display =
                        restrictAccountBtn.style.display =
                        unblockBtn.style.display =
                        unlockBtn.style.display =
                        removeRestrctictionBtn.style.display =
                        reactivateBtn.style.display = "inline-block";
                }
            } else {
                console.error("Could not determine user role");
            }
        })
        .catch(error => console.error('Error fetching user role:', error));

    resetQuestionAnswerBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("resetQuestionAnswerModal", "flex");
    };
    // Toggle modals for restriction and blocking of account
    restrictAccountBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("restrictionModal", "flex");
    };

    unblockBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("removeRestrictionModal", "flex");
    };

    unlockBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("unlockModal", "flex");
    };
    reactivateBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("reactivateModal", "flex");
    };
    removeRestrctictionBtn.onclick = (e) => {
        e.preventDefault();
        toggleModal("removeRestrictionModal2", "flex");
    }

    // fetch List of customers to Block or restrict
    function fetchStaffs(selectOption, filterType = 'all') {
        fetch(`backend/get_staffs.php`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.staffs.forEach(staff => {
                        if (staff.role === 'Admin') {
                            // Filter based on the current module's need
                            if (filterType === 'unblock') {
                                // Show only staff that are restricted or blocked
                                if (staff.restriction_id === 1 || staff.block_id === 1) {
                                    addStaffOption(selectOption, staff);
                                }
                            } else if (filterType === 'block') {
                                // Show only staff that are either restricted nor blocked
                                if (staff.restriction_id === 0 || staff.block_id === 0) {
                                    addStaffOption(selectOption, staff);
                                }
                            } else {
                                // Default: Show all staff
                                addStaffOption(selectOption, staff);
                            }
                        }
                    });
                } else {
                    console.error('Failed to fetch Staff Records:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    // Helper function to add the staff option to the select dropdown
    function addStaffOption(selectOption, staff) {
        const option = document.createElement('option');
        option.value = staff.unique_id;

        let statusText = '';
        if (staff.restriction_id === 1 && staff.block_id === 1) {
            statusText = 'Restricted and Blocked';
        }
        else if (staff.block_id === 1 && staff.restriction_id === 0) {
            statusText = 'Blocked no restriction';
        }
        else if (staff.block_id === 0 && staff.restriction_id === 1) {
            statusText = 'Restricted no block';
        } else {
            statusText = 'No Restrictions or Block';
        }

        option.textContent = `${staff.firstname} ${staff.lastname} - ${statusText}`;
        selectOption.appendChild(option);
    }

    selectStaff = document.getElementById('staffName');

    fetchStaffs(selectStaff, 'block');

    const restrictionForm = document.getElementById("restrictionForm");
    const confirmationModal = document.getElementById("confirmationModal");
    const confirmButton = document.getElementById("confirmButton");
    const cancelButton = document.getElementById("cancelButton");
    let staffID, restrictionType;

    restrictionForm.onsubmit = (e) => {
        e.preventDefault();

        // Get values from the form
        staffID = document.getElementById("staffName").value;
        restrictionType = document.getElementById("restrictionType").value;
        // Show confirmation modal
        confirmationModal.style.display = "flex";
    };

    // Confirmation modal buttons
    confirmButton.onclick = async () => {
        // Proceed with the request after confirmation
        confirmationModal.style.display = "none";  // Close the modal

        try {
            const response = await fetch('backend/restrict_block_account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ staffID: staffID, restrictionType: restrictionType })
            });

            const data = await response.json();
            if (data.success) {
                alert(`Account has been successfully ${restrictionType === 'restrict' ? 'restricted' : 'blocked'}!`);
                toggleModal("restrictionModal");  // Assuming this closes the main form modal
                location.reload();
                fetchStaffs();
            } else {
                alert(`Error placing ${restrictionType} on account: ${data.message}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert("An error occurred. Please try again.");
        }
    };

    cancelButton.onclick = () => {
        confirmationModal.style.display = "none";  // Close modal without submitting
    };

    // Reset Secret Question and Answer
    // Reset Password Form Submission
    const resetQuestionAnswerForm = document.getElementById("resetQuestionAnswerForm");

    resetQuestionAnswerForm.onsubmit = async (e) => {
        e.preventDefault();
        if (confirm('Are you sure you want to Update Secret Question and Answer?')) {
            const resetEmail = document.getElementById("resetEmail").value,
                resetPassword = document.getElementById("resetPassword").value,
                secretQuestion = document.getElementById("secretQuestion").value,
                resetSecretAnswer = document.getElementById("resetSecretAnswer").value,
                confirmAnswer = document.getElementById("confirmAnswer").value;

            try {
                const response = await fetch('backend/secret_answer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: resetEmail, password: resetPassword, secret_question: secretQuestion, secret_answer: resetSecretAnswer, confirm_answer: confirmAnswer })
                });

                const data = await response.json();
                if (data.success) {
                    alert("Secret Question and Answer has been successful updated!");
                    toggleModal("resetQuestionAnswerModal", "none");
                } else {
                    alert(`Error resetting Secret Question and Answer: ${data.message}`);
                }
            } catch (error) {
                console.error('Error:', error);
                alert("An error occurred. Please try again.");
            }
        }

    };


    //   Unblock or Remove Restriction
    adminID = document.getElementById('staff');

    fetchStaffs(adminID, 'unblock');

    const unblockForm = document.getElementById("removeRestrictionForm");
    const confirmationModal2 = document.getElementById("confirmationModal2");
    const confirmButton2 = document.getElementById("confirmButton2");
    const cancelButton2 = document.getElementById("cancelButton2");
    let staff, unblockType;

    unblockForm.onsubmit = (e) => {
        e.preventDefault();

        // Get values from the form
        staff = document.getElementById("staff").value;
        unblockType = document.getElementById("unblockType").value;

        // Show confirmation modal
        confirmationModal2.style.display = "flex";
    };

    // Confirmation modal buttons
    confirmButton2.onclick = async () => {
        // Proceed with the request after confirmation
        confirmationModal2.style.display = "none";  // Close the modal

        try {
            const response = await fetch('backend/unblock_account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ staffID: staff, unblockType: unblockType })
            });

            const data = await response.json();
            if (data.success) {
                alert(`Account has been successfully ${unblockType === 'unrestrict' ? 'Unrestricted' : 'Unblocked'}!`);
                toggleModal("removeRestrictionModal");  // Assuming this closes the main form modal
                location.reload();
                fetchStaffs();
            } else {
                alert(`Error ${unblockType} account: ${data.message}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert("An error occurred. Please try again.");
        }
    };

    cancelButton2.onclick = () => {
        confirmationModal2.style.display = "none";  // Close modal without submitting
    };

    // Function to unlock an account
    const unlockAccountForm = document.getElementById('unlockAccountForm');
    const confirmationModal3 = document.getElementById("confirmationModal3");
    const confirmButton3 = document.getElementById("confirmButton3");
    const cancelButton3 = document.getElementById("cancelButton3");
    let userID;
    unlockAccountForm.onsubmit = (e) => {
        e.preventDefault();

        // Get values from the form
        userID = document.getElementById("userID").value;
        accountType = document.getElementById('accountType').value;

        // Show confirmation modal
        confirmationModal3.style.display = "flex";
    };
    // Confirmation modal buttons
    confirmButton3.onclick = async () => {
        // Proceed with the request after confirmation
        confirmationModal3.style.display = "none";  // Close the modal
        try {
            const response = await fetch('backend/unlock_account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ userID: userID, accountType: accountType })
            });

            const data = await response.json();
            if (data.success) {
                alert(`Account has been successfully Unlocked'}!`);
                toggleModal("unlockModal");  // Assuming this closes the main form modal
                location.reload();
            } else {
                alert(`Error Unlocking account: ${data.message}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert("An error occurred. Please try again.");
        }
    };

    cancelButton3.onclick = () => {
        confirmationModal3.style.display = "none";  // Close modal without submitting
    };


    // Function to retrieve deactivated customer and drivers accounts and  Re-activate an account
    const typeSelect = document.getElementById('accountTypes');
    const accountSelect = document.getElementById('deactivatedAccounts');

    // When account type changes
    typeSelect.addEventListener('change', async () => {
        console.log(`Account type changed to: ${typeSelect.value}`);

        if (!typeSelect.value) {
            accountSelect.innerHTML = '<option value="">Select type first</option>';
            console.log("Reset account dropdown - no type selected");
            return;
        }

        accountSelect.innerHTML = '<option value="">Loading...</option>';
        console.log("Initiated account loading");

        const formData = new FormData();
        formData.append('accountType', typeSelect.value);

        try {
            console.log("Sending request to backend");
            const response = await fetch('backend/fetch_deactivated_account.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log(`Received response: ${JSON.stringify(data)}`);

            if (data.success) {
                accountSelect.innerHTML = data.accounts.map(a =>
                    `<option value="${a.id}">${a.id} - (${a.name})</option>`
                ).join('');
                // accountSelect.innerHTML = '';
                // data.accounts.forEach(a => {
                //     accountSelect.innerHTML += `<option value="${a.email}">${a.id} - ${a.name}</option>`;
                // });
                console.log(`Loaded ${data.accounts.length} accounts`);
            } else {
                accountSelect.innerHTML = '<option value="">Error loading accounts</option>';
                console.log("Failed to load accounts: " + (data.message || 'Unknown error'));
            }

        } catch (error) {
            console.log("Network error: " + error.message);
            accountSelect.innerHTML = '<option value="">Network error</option>';
        }
    });


    const reactivateAccountForm = document.getElementById('reactivateAccountForm');
    const confirmationModal4 = document.getElementById("confirmationModal4");
    const confirmButton4 = document.getElementById("confirmButton4");
    const cancelButton4 = document.getElementById("cancelButton4");
    let userIDs;
    reactivateAccountForm.onsubmit = (e) => {
        e.preventDefault();

        // Get values from the form
        userIDs = document.getElementById("deactivatedAccounts").value;
        accountTypes = document.getElementById('accountTypes').value;
        secretAnswer = document.getElementById('secret_answer').value;

        // Show confirmation modal
        confirmationModal4.style.display = "flex";
    };
    // Confirmation modal buttons
    confirmButton4.onclick = async () => {
        // Proceed with the request after confirmation
        confirmationModal4.style.display = "none";  // Close the modal
        try {
            const response = await fetch('backend/reactivate_account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ userID: userIDs, accountType: accountTypes, secretAnswer: secretAnswer })
            });

            const data = await response.json();
            if (data.success) {
                alert(`Account has been successfully Re-activated'}!`);
                toggleModal("reactivateModal");  // Assuming this closes the main form modal
                location.reload();
            } else {
                alert(`Error Unlocking account: ${data.message}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert("An error occurred. Please try again.");
        }
    };

    cancelButton4.onclick = () => {
        confirmationModal4.style.display = "none";  // Close modal without submitting
    };

    // Function to retrieve Restricted driver and customer's accounts and remove Restriction on Acccoount
    const RestrictedAccountType = document.getElementById('accounts');
    const restrictedAccounts = document.getElementById('restrictedAccounts');

    // When account type changes
    RestrictedAccountType.addEventListener('change', async () => {
        console.log(`Account type changed to: ${RestrictedAccountType.value}`);

        if (!RestrictedAccountType.value) {
            restrictedAccounts.innerHTML = '<option value="">Select type first</option>';
            console.log("Reset account dropdown - no type selected");
            return;
        }

        restrictedAccounts.innerHTML = '<option value="">Loading...</option>';
        console.log("Initiated account loading");

        const formData = new FormData();
        formData.append('typeOfAccount', RestrictedAccountType.value);

        try {
            console.log("Sending request to backend");
            const response = await fetch('backend/fetch_restricted_account.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log(`Received response: ${JSON.stringify(data)}`);

            if (data.success) {
                // Put the empty option first, then append mapped accounts
                restrictedAccounts.innerHTML = `
        <option value="">-- Please Select an Option --</option>
        ${data.accounts.map(a =>
                    `<option value="${a.id}" data-reference-id="${a.reference_id}">
                ${a.id} - (${a.name})
            </option>`
                ).join('')}
    `;
                console.log(`Loaded ${data.accounts.length} accounts`);
            } else {
                restrictedAccounts.innerHTML = '<option value="">Error loading accounts</option>';
                console.log("Failed to load accounts: " + (data.message || 'Unknown error'));
            }

        } catch (error) {
            console.log("Network error: " + error.message);
            restrictedAccounts.innerHTML = '<option value="">Network error</option>';
        }
    });

    restrictedAccounts.addEventListener('change', () => {
        const selectedOption = restrictedAccounts.options[restrictedAccounts.selectedIndex];
        const referenceId = selectedOption.dataset.referenceId || '';
        console.log('Selected reference ID:', referenceId);
        const refInput = document.getElementById('selected_reference_id');
        if (refInput) {
            refInput.value = referenceId;
        } else {
            console.warn('No element with id "selected_reference_id" found');
        }
    });


    const removeRestrictionForm2 = document.getElementById('removeRestrictionForm2');
    const confirmationModal5 = document.getElementById("confirmationModal5");
    const confirmButton5 = document.getElementById("confirmButton5");
    const cancelButton5 = document.getElementById("cancelButton5");
    let restrictedAccountID;
    removeRestrictionForm2.onsubmit = (e) => {
        e.preventDefault();

        // Get values from the form
        restrictedAccountID = document.getElementById("restrictedAccounts").value;
        accounts = document.getElementById('accounts').value;
        secretAnswerRestriction = document.getElementById('secret_answer_restriction').value;

        // Show confirmation modal
        confirmationModal5.style.display = "flex";
    };

    // Confirmation modal buttons
    confirmButton5.onclick = async () => {

        // Proceed with the request after confirmation
        confirmationModal5.style.display = "none";  // Close the modal
        try {
            const referenceId = document.getElementById("selected_reference_id").value;
            if (!restrictedAccountID || !referenceId) {
                alert("Please select a valid account.");
                return;
            }

            const response = await fetch('backend/remove_restriction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    userID: restrictedAccountID,
                    accountType: accounts,
                    secretAnswer: secretAnswerRestriction,
                    reference_id: referenceId  // ✅ pass it to the backend
                })
            });

            const data = await response.json();
            if (data.success) {
                alert(`Restriction has been successfully lifted!`);
                toggleModal("removeRestrictionModal2");  // Assuming this closes the main form modal
                location.reload();
            } else {
                alert(`Error Unlocking account: ${data.message}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert("An error occurred. Please try again.");
        }
    };

    cancelButton5.onclick = () => {
        confirmationModal5.style.display = "none";  // Close modal without submitting
    };
});


