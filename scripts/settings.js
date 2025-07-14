// Toggle modal display
const toggleModal = (modalId, display = "none") => {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = display;
};

// Fetch wrapper
const fetchJSON = async (url, method = 'GET', body = null) => {
    const config = {
        method,
        headers: { 'Content-Type': 'application/json' },
    };
    if (body) config.body = JSON.stringify(body);
    const response = await fetch(url, config);
    return response.json();
};

// Fetch form wrapper (for FormData usage)
const fetchFormData = async (url, formData) => {
    const response = await fetch(url, {
        method: 'POST',
        body: formData
    });
    return response.json();
};

// Add option to a dropdown
const addSelectOption = (select, value, text) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = text;
    select.appendChild(option);
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

// === Fetch Logged-in User Role and Display Buttons ===

document.addEventListener("DOMContentLoaded", async () => {
    const buttons = {
        Admin: ["reset-link"],
        "Super Admin": ["reset-link", "block-account", "unblock-account", "unlock-account", "reactivate-account", "restrict-account", "remove-restriction"]
    };

    try {
        const data = await fetchJSON('backend/staff_profile.php');
        if (data.success) {
            const userRole = data.admin.role;
            const buttonIds = buttons[userRole] || [];
            const linkList = document.getElementById('links-list');

            // Common functionality for all roles
            buttonIds.forEach(id => {
                const btn = document.getElementById(id);
                if (btn) btn.style.display = "inline-block";
            });

            // Admin-specific functionality
            if (userRole === "Admin") {
                Object.assign(linkList.style, {
                    display: "flex",
                    flexDirection: "column",
                    justifyContent: "center"
                });
            }
        }
    } catch (error) {
        console.error("Failed to fetch user role:", error);
    }

    setupModalButton("reset-link", "resetQuestionAnswerModal");
    setupModalButton("block-account", "restrictionModal");
    setupModalButton("unblock-account", "removeRestrictionModal");
    setupModalButton("unlock-account", "unlockModal");
    setupModalButton("reactivate-account", "reactivateDriverCustomerModal");
    // setupModalButton("restrict-account", "driverCustomerRestrictionModal");
    setupModalButton("remove-restriction", "removeRestrictionModal2");

    bindFormSubmission("restrictionForm", "confirmationModal", async ({ staffName, restrictionType }) => {
        return await fetchJSON('backend/restrict_block_staff_account.php', 'POST', { staffID: staffName, restrictionType });
    });

    bindFormSubmission("removeRestrictionForm", "confirmationModal2", async ({ staff, unblockType }) => {
        return await fetchJSON('backend/unrestrict_unblock_staff.php', 'POST', { staffID: staff, unblockType });
    });

    bindFormSubmission("unlockAccountForm", "confirmationModal3", async ({ userID, accountType }) => {
        return await fetchJSON('backend/unlock_account.php', 'POST', { userID, accountType });
    });

    bindFormSubmission("reactivateDriverCustomerForm", "confirmationModal4", async ({ deactivatedDriverCustomer, selectAccountType, secretAnswerReactivation, deactivation_reference_id }) => {
        return await fetchJSON('backend/reactivate_driver_customer.php', 'POST', {
            userID: deactivatedDriverCustomer,
            accountType: selectAccountType,
            secretAnswer: secretAnswerReactivation,
            reference_id: deactivation_reference_id
        });
    });

    bindFormSubmission("removeRestrictionForm2", "confirmationModal5", async ({ restrictedAccounts, typeOfAccount, secretAnswerRestriction, selected_reference_id }) => {
        return await fetchJSON('backend/remove_restriction.php', 'POST', {
            userID: restrictedAccounts,
            accountType: typeOfAccount,
            secretAnswer: secretAnswerRestriction,
            reference_id: selected_reference_id
        });
    });

    bindFormSubmission("resetQuestionAnswerForm", null, async ({ resetEmail, resetPassword, secretQuestion, resetSecretAnswer, confirmAnswer }) => {
        return await fetchJSON('backend/secret_answer.php', 'POST', {
            email: resetEmail,
            password: resetPassword,
            secret_question: secretQuestion,
            secret_answer: resetSecretAnswer,
            confirm_answer: confirmAnswer
        });
    }, true);

    setupDropdownDependentFetch("selectAccountType", "deactivatedDriverCustomer", "backend/fetch_deactivated_account.php", "selectAccountType", true);
    setupDropdownDependentFetch("accounts", "restrictedAccounts", "backend/fetch_restricted_account.php", "typeOfAccount", true);

    document.getElementById('restrictedAccounts')?.addEventListener('change', function () {
        const referenceId = this.options[this.selectedIndex]?.dataset.referenceId || '';
        const refInput = document.getElementById('selected_reference_id');
        if (refInput) refInput.value = referenceId;
    });
    document.getElementById('deactivatedDriverCustomer')?.addEventListener('change', function () {
        const referenceId = this.options[this.selectedIndex]?.dataset.referenceId || '';
        const refInput = document.getElementById('deactivation_reference_id');
        if (refInput) refInput.value = referenceId;
    });

    const accountTypeSelect = document.getElementById('driverCustomer');
    const accountNameSelect = document.getElementById('driverCustomerName');
    accountTypeSelect?.addEventListener('change', () => {
        const selected = accountTypeSelect.value;
        if (!selected) return accountNameSelect.innerHTML = '<option value="">--Select Account Name--</option>';
        accountNameSelect.innerHTML = '<option value="">Loading...</option>';
        fetchCustomerDriverAccounts(accountNameSelect, selected);
    });

    fetchStaffs(document.getElementById('staffName'), 'block');
    fetchStaffs(document.getElementById('staff'), 'unblock');
});

// === Helper Functions ===

const setupModalButton = (btnId, modalId) => {
    const btn = document.getElementById(btnId);
    if (btn) {
        btn.onclick = e => {
            e.preventDefault();
            toggleModal(modalId, "flex");
        };
    }
};

const bindFormSubmission = (formId, modalId, submitCallback, confirmImmediately = false) => {
    const form = document.getElementById(formId);
    if (!form) return;

    form.onsubmit = async e => {
        e.preventDefault();
        const inputs = Object.fromEntries(new FormData(form).entries());

        const confirmAction = async () => {
            try {
                const result = await submitCallback(inputs);
                if (result.success) {
                    alert("Action successful!");
                    if (modalId) toggleModal(modalId, "none");
                    location.reload();
                } else {
                    alert("Failed: " + result.message);
                }
            } catch (err) {
                alert("Network error. Please try again.");
                console.error(err);
            }
        };

        if (confirmImmediately) {
            confirmAction();
        } else {
            toggleModal(modalId, "flex");

            // Get confirm and cancel buttons dynamically
            const modal = document.getElementById(modalId);
            if (!modal) return;

            const confirmBtn = modal.querySelector(".confirmButton");
            const cancelBtn = modal.querySelector(".cancelButton");

            if (confirmBtn && cancelBtn) {
                // Remove any previous handlers to avoid duplicate submissions
                confirmBtn.onclick = async () => {
                    toggleModal(modalId, "none");
                    await confirmAction();
                };

                cancelBtn.onclick = () => {
                    toggleModal(modalId, "none");
                };
            } else {
                console.warn(`Confirm/Cancel buttons not found in modal: ${modalId}`);
            }
        }
    };
};


const setupDropdownDependentFetch = (typeId, dropdownId, url, formField, includeRefId = false) => {
    const typeSelect = document.getElementById(typeId);
    const accountSelect = document.getElementById(dropdownId);

    typeSelect?.addEventListener('change', async () => {
        if (!typeSelect.value) {
            accountSelect.innerHTML = '<option value="">Select type first</option>';
            return;
        }

        const formData = new FormData();
        formData.append(formField, typeSelect.value);

        try {
            const data = await fetchFormData(url, formData);
            if (data.success) {
                const options = data.accounts.map(a => {
                    const ref = includeRefId ? ` data-reference-id="${a.reference_id}"` : '';
                    return `<option value="${a.id}"${ref}>${a.id} - (${a.name})</option>`;
                });
                accountSelect.innerHTML = `<option value="">-- Please Select an Option --</option>${options.join('')}`;
            } else {
                accountSelect.innerHTML = '<option value="">Error loading accounts</option>';
            }
        } catch (error) {
            accountSelect.innerHTML = '<option value="">Network error</option>';
            console.error(error);
        }
    });
};

function fetchStaffs(selectOption, filterType = 'all') {
    fetchJSON('backend/get_staffs.php')
        .then(data => {
            if (data.success) {
                data.staffs.forEach(staff => {
                    if (staff.role !== 'Admin') return;
                    const isBlocked = staff.block_id === 1;
                    const isRestricted = staff.restriction_id === 1;

                    if (filterType === 'unblock' && (isBlocked || isRestricted)) {
                        addStaffOption(selectOption, staff);
                    } else if (filterType === 'block' && (!isBlocked || !isRestricted)) {
                        addStaffOption(selectOption, staff);
                    } else if (filterType === 'all') {
                        addStaffOption(selectOption, staff);
                    }
                });
            } else {
                console.error("Fetch staff failed:", data.message);
            }
        })
        .catch(err => console.error("Error loading staff:", err));
}

function addStaffOption(selectOption, staff) {
    const option = document.createElement('option');
    option.value = staff.unique_id;

    let statusText = 'No Restrictions or Block';
    if (staff.restriction_id === 1 && staff.block_id === 1) {
        statusText = 'Restricted and Blocked';
    } else if (staff.block_id === 1) {
        statusText = 'Blocked no restriction';
    } else if (staff.restriction_id === 1) {
        statusText = 'Restricted no block';
    }

    option.textContent = `${staff.firstname} ${staff.lastname} - ${statusText}`;
    selectOption.appendChild(option);
}

function fetchCustomerDriverAccounts(selectOption, accountType) {
    fetchJSON('backend/get_customer_driver.php', 'POST', { account_type: accountType })
        .then(data => {
            selectOption.innerHTML = '<option value="">--Select Account--</option>';
            if (data.success && Array.isArray(data.data)) {
                data.data.forEach(account => {
                    const isRestricted = parseInt(account.restriction) === 1;
                    const isDeactivated = account.delete_status === 'Yes';

                    let statusText = 'Active';
                    if (isRestricted && isDeactivated) {
                        statusText = 'Restricted & Deactivated';
                    } else if (isRestricted) {
                        statusText = 'Restricted';
                    } else if (isDeactivated) {
                        statusText = 'Deactivated';
                    }

                    addSelectOption(selectOption, account.id, `${account.firstname} ${account.lastname} (${statusText})`);
                });
            } else {
                console.error("Failed to load accounts:", data.message);
            }
        })
        .catch(err => console.error("Fetch account error:", err));
}


