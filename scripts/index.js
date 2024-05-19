const form = document.querySelector(".login form"),
continueBtn = form.querySelector(".button"),
errorText = form.querySelector(".errorContainer");

form.onsubmit = (e)=>{
    e.preventDefault();
}

continueBtn.onclick = ()=>{
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "backend/admin_login3.php", true);
    xhr.onload = ()=>{
      if(xhr.readyState === XMLHttpRequest.DONE){
          if(xhr.status === 200){
              let data = xhr.response;
              if(data === "success"){
                location.href = "splashscreen.php";
              }else{
                errorText.style.display = "block";
                errorText.textContent = data;
              }
          }
      }
    }
    let formData = new FormData(form);
    xhr.send(formData);
}


        const passwordInput = document.getElementById('password');
        const showPasswordCheckbox = document.getElementById('viewPassword');

        // Function to toggle password visibility
        function togglePasswordVisibility() {
            if (showPasswordCheckbox.checked) {
                // Display password in plain text
                passwordInput.type = 'text';
            } else {
                // Encrypt password
                passwordInput.type = 'password';
            }
        }

        // Add event listener to checkbox
        showPasswordCheckbox.addEventListener('change', togglePasswordVisibility);