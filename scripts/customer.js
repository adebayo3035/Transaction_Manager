// Function to toggle modals
function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}


document.addEventListener('DOMContentLoaded', () => {
    // Close modals
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            closeBtn.closest('.modal').style.display = 'none';
            location.reload();
        });
    });

    window.addEventListener('click', (event) => {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Add New Driver form submission
    const addCustomerForm = document.getElementById('addCustomerForm');
    function handleFormSubmission(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            const messageDiv = document.getElementById('addCustomerMessage');

            fetch('backend/add_customer.php', {
                method: 'POST',
                // headers: {
                //     'Content-Type': 'application/x-www-form-urlencoded'
                // },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Success:', data.message);
                        messageDiv.textContent = 'Customer has been successfully Onboarded!';
                        alert('Customer has been successfully Onboarded!')
                        location.reload();
                        // window.location.href = '../../Transaction_manager/dashboard.php';
                    } else {
                        console.log('Error:', data.message);
                        messageDiv.textContent = data.message;
                        // alert(data.message)
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.textContent = 'Error: ' + error.message;
                    alert('An error occurred. Please Try Again Later')
                });
        });
    }
    loadGroups();
    handleFormSubmission(addCustomerForm);
});

// fetch group from db
function loadGroups() {
    fetch('backend/fetch_groups.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const groupSelect = document.getElementById('selectOption');
                groupSelect.innerHTML = '<option value="">--Select a Group--</option>';

                data.groups.forEach(group => {
                    const option = document.createElement('option');
                    option.value = group.group_id;
                    option.textContent = group.group_name;
                    groupSelect.appendChild(option);
                });
            } else {
                console.error('Error:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching groups:', error);
        });
}

// Function to load units based on selected group using POST request
function loadUnits(groupId) {
    if (!groupId) {
        document.getElementById('selectedUnit').innerHTML = '<option value="">--Select a Unit--</option>';
        return;
    }
    fetch('backend/fetch_units.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ group_id: groupId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const unitSelect = document.getElementById('selectedUnit');
                unitSelect.innerHTML = '<option value="">--Select a Unit--</option>';

                data.units.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.unit_id;
                    option.textContent = unit.unit_name;

                    unitSelect.appendChild(option);
                });
            } else {
                console.error('Error:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching Units:', error);
        });
}