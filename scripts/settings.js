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
  
          const resetEmail = document.getElementById("resetEmail").value,
          resetPassword= document.getElementById("resetPassword").value,
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
                body: JSON.stringify({ userID: userID, accountType: accountType})
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
});
