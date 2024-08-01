function displayPhoto(input) {
    var file = input.files[0];
    var time = Math.floor(Date.now() / 1000);
    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var uploadedPhoto = document.getElementById('uploadedPhoto');
            uploadedPhoto.setAttribute('src', e.target.result);
            document.getElementById('photoContainer').style.display = 'block'; // Show the photo container

            // Set the new file name to a hidden input field
            document.getElementById('photo_name').value = time + file.name;
        };
        reader.readAsDataURL(file);
    }
}

document.getElementById('photo').addEventListener('change', function() {
    displayPhoto(this);
});
