function displayInput() {
    var selectElement = document.getElementById("selectOption");
    var unitSelectElement = document.getElementById("selectedUnit");
    var additionalInputDiv = document.getElementById("additionalInput");
    var add_customer = document.getElementById("add_customer");

    // Display the additional input only if "option2" is selected
    if (selectElement.value !== "") {
        additionalInputDiv.style.display = "flex";
        additionalInputDiv.style.flexDirection = "column";
    } 
    else if ( unitSelectElement.value == ""){
        add_customer.style.display = "none";
    }
    else if(selectElement.value == ""){
        add_customer.style.display = "none";
        unitSelectElement.value = "";
    }
    else {
        additionalInputDiv.style.display = "none";
        add_customer.style.display = "none";
        unitSelectElement.value = "";
    }
}

// Display and Hide Button if Unit and Group are not selected
function displayButton() {
    var selectedUnit = document.getElementById("selectedUnit");
    var selectElement = document.getElementById("selectOption");
    var add_customer = document.getElementById("add_customer");

    // Display the additional submit button if both group and unit are not empty
    if ((selectedUnit.value !== "") && (selectElement.value !== "")) {
        add_customer.style.display = "flex";
        // additionalInputDiv.style.flexDirection = "column";
    } else {
        add_customer.style.display = "none";
    }
}

var selectedGroupId; // Variable to store the selected group ID

        function handleGroupSelection() {
            var selectElement = document.getElementById("selectOption");
            selectedGroupId = selectElement.value;

            // You can use selectedGroupId in another query or perform other actions
            // console.log("Selected Group ID:", selectedGroupId);

            // Fetch and update the units based on the selected group
            fetchUnits(selectedGroupId);
        }

        function fetchUnits(groupId) {
            var unitSelectElement = document.getElementById("selectedUnit");

            // Clear existing options
            unitSelectElement.innerHTML = "";
            var option = document.createElement("option");
                        option.value = "";
                        option.text = "--Select a Unit--";
                        unitSelectElement.add(option);

            // Use AJAX to fetch units from the server
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    // Parse the JSON response
                    var units = JSON.parse(this.responseText);

                    // Populate the unit dropdown with the fetched units
                    units.forEach(function(unit) {
                        var option = document.createElement("option");
                        option.value = unit.unit_id;
                        option.text = unit.unit_name;
                        unitSelectElement.add(option);
                    });
                }
            };

            // Make a GET request to the PHP script with the selected group ID
            xhr.open("GET", "fetchUnits.php?groupId=" + groupId, true);
            xhr.send();
        }

        function handleClick(){
            displayInput();
            handleGroupSelection();
            // displayButton();
        }