const form = document.querySelector(".modal .modal-content form");
const addUnitBtn = form.querySelector("button");
const errorNotifier = form.querySelector(".error_notifier");
const modal = document.getElementById("addGroupModal");
const unit_name = form.querySelector("input");
const group_name = form.querySelector("select");

form.onsubmit = (e) => {
    e.preventDefault();
};

addUnitBtn.onclick = () => {
    if (group_name.value === "") {
        alert("Please select an option");
        return false; // Prevent form submission
    } else if (unit_name.value === "") {
        alert("Please Enter a valid Unit Name");
        return false; // Prevent form submission
    } else {

        let xhr = new XMLHttpRequest();
        xhr.open("POST", "backend/add_team.php", true);
        xhr.onload = () => {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    let data = xhr.response;
                    if (data === "success") {
                        errorNotifier.style.display = "block";
                        errorNotifier.textContent = "Team has been successfully Created";
                        errorNotifier.style.color = "green";
                        modal.style.display = none;
                        unit_name.value = "";
                        group_name.selectedIndex.value = " ";
                        location.href = "groups.php";
                    } else {
                        errorNotifier.style.display = "block";
                        errorNotifier.style.color = "red";
                        errorNotifier.textContent = data;
                        unit_name.value = "";
                    }
                }
            }
        };

        let formData = new FormData(form);
        xhr.send(formData);
        return true;
    }
};
