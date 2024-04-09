
    function displayPhoto(input) {
      var file = input.files[0];
      if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('uploadedPhoto').setAttribute('src', e.target.result);
        };
        reader.readAsDataURL(file);
      }
    }

    
