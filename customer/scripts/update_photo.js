document.addEventListener('DOMContentLoaded', () => {

    // FUNCTION TO HANDLE IMAGE UPDATE
    document.getElementById('adminForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = document.getElementById('adminForm');
        var formData = new FormData(form);

        fetch('../v2/update_picture.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                var messageElement = document.getElementById('message');
                if (data.success) {
                    document.getElementById('uploadedPhoto').src= '../../backend/customer_photos/' + data.file;
                    messageElement.textContent = 'Profile picture updated successfully!';
                    messageElement.style.color = 'green';
                    console.log(data.message);
                    alert('Customer Profile Picture Updated Successfully')
                } else {
                    messageElement.textContent = data.message;
                    messageElement.style.color = 'red';
                    console.log(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('message').textContent = 'An error occurred while uploading the file.';
            });
    });
})

function displayPhoto(input) {
    var file = input.files[0];
    var time = Math.floor(Date.now() / 1000);
    if (file) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var uploadedPhoto = document.getElementById('uploadedPhoto');
            uploadedPhoto.setAttribute('src', e.target.result);
            document.getElementById('photoContainer').style.display = 'block'; // Show the photo container

            // Set the new file name to a hidden input field
            // document.getElementById('photo_name').value = time + file.name;
        };
        reader.readAsDataURL(file);
    }
}

//CALL UPLOAD PHOTO FUNCTION
let uploadBtn = document.getElementById('photo');
uploadBtn.addEventListener('change', (event) => {
    displayPhoto(event.target);
})