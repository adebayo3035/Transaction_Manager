const form = document.querySelector(".modal .modal-content form")
const addGroupBtn = form.querySelector("button")
const errorNotifier = form.querySelector(".error_notifier");
const modal = document.getElementById('addGroupModal');
const group_name = form.querySelector("input");

form.onsubmit = (e)=>{
    e.preventDefault();
}

addGroupBtn.onclick = ()=>{
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "backend/add_group.php", true);
    xhr.onload = ()=>{
      if(xhr.readyState === XMLHttpRequest.DONE){
          if(xhr.status === 200){
              let data = xhr.response;
              if(data === "success"){
                errorNotifier.style.display = "block";
                errorNotifier.textContent = "Group has been successfully Created";
                setTimeout(function() {
                  location.reload();
              }, 1000);
                errorNotifier.style.color = "green";
                modal.style.display = none;
                group_name.value = "";
                location.href="groups.php";
              }else{
                errorNotifier.style.display = "block";
                errorNotifier.textContent = data;
                group_name.value = "";
              }
          }
      }
    }
   
    let formData = new FormData(form);
    xhr.send(formData);
}