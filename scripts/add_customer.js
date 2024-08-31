document.getElementById('addCustomerForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('backend/add_customer.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Congratulations! Customer has been successfully onboarded.');
                window.location.href = '../transaction_manager/customer.php';
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error', error);
        });
});

var selectedGroupId; // Variable to store the selected group ID

function handleGroupSelection() {
    var selectElement = document.getElementById("selectOption");
    selectedGroupId = selectElement.value;

    // Fetch and update the units based on the selected group
    fetchUnits(selectedGroupId);
}

function fetchUnits(groupId) {
    var unitSelectElement = document.getElementById("selectedUnit");

    // Clear existing options
    unitSelectElement.innerHTML = '<option value="">--Select a Unit--</option>';

    fetch(`fetchUnits.php?groupId=${groupId}`)
        .then(response => response.json())
        .then(units => {
            units.forEach(unit => {
                var option = document.createElement("option");
                option.value = unit.unit_id;
                option.text = unit.unit_name;
                unitSelectElement.add(option);
            });

            // Show additional input div
            document.getElementById("additionalInput").style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching units:', error);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    var selectElement = document.getElementById("selectOption");
    var unitSelectElement = document.getElementById("selectedUnit");
    var additionalInputDiv = document.getElementById("additionalInput");
    var btnAddCustomer = document.getElementById("btnAddCustomer");

    function updateDisplay() {
        if (selectElement.value) {
            additionalInputDiv.style.display = 'block';
        } else {
            additionalInputDiv.style.display = 'none';
        }

        if (selectElement.value && unitSelectElement.value) {
            btnAddCustomer.style.display = 'block';
        } else {
            btnAddCustomer.style.display = 'none';
        }
    }

    selectElement.addEventListener('change', updateDisplay);
    unitSelectElement.addEventListener('change', updateDisplay);
});

