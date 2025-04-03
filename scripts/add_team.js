const form = document.querySelector(".modal .modal-content form");
const addUnitBtn = form.querySelector("button");
const errorNotifier = form.querySelector(".error_notifier");
const modal = document.getElementById("addGroupModal");
const unit_name = form.querySelector("input");
const group_name = form.querySelector("select");

form.onsubmit = (e) => {
    e.preventDefault();
};

addUnitBtn.onclick = async () => {
    if (group_name.value === "") {
        alert("Please select an option");
        return false; // Prevent form submission
    } 
    
    if (unit_name.value === "") {
        alert("Please Enter a valid Unit Name");
        return false; // Prevent form submission
    }

    if (!confirm("Are you sure you want to add a new Team?")) {
        return false; // Prevent form submission if user cancels
    }

    try {
        let formData = new FormData(form);
        let response = await fetch("backend/add_team.php", {
            method: "POST",
            body: formData,
        });

        let data = await response.text(); // Get response text

        if (response.ok) {
            if (data === "success") {
                errorNotifier.style.display = "block";
                errorNotifier.textContent = "Team has been successfully created";
                errorNotifier.style.color = "green";

                modal.style.display = "none";
                unit_name.value = "";
                group_name.selectedIndex = 0; // Reset dropdown
                location.href = "groups.php"; // Redirect to groups page
            } else {
                errorNotifier.style.display = "block";
                errorNotifier.style.color = "red";
                errorNotifier.textContent = data;
                unit_name.value = "";
            }
        } else {
            throw new Error(`Error: ${response.statusText}`);
        }
    } catch (error) {
        console.error("Error submitting form:", error);
        errorNotifier.style.display = "block";
        errorNotifier.style.color = "red";
        errorNotifier.textContent = "An error occurred while processing your request.";
    }
};
