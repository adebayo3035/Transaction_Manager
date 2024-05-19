
    // function displayPhoto(input) {
    //   var file = input.files[0];
    //   if (file) {
    //     var reader = new FileReader();
    //     reader.onload = function(e) {
    //       document.getElementById('uploadedPhoto').setAttribute('src', e.target.result);
    //       document.getElementById('photo_name').setAttribute('src', e.target.result);
    //     };
    //     reader.readAsDataURL(file);
    //   }
    // }

    
    function displayPhoto(input) {
      var file = input.files[0];
      var time = Math.floor(Date.now() / 1000);
      if (file) {
          var reader = new FileReader();
          reader.onload = function(e) {
              document.getElementById('uploadedPhoto').setAttribute('src', e.target.result);
              document.getElementById('photo_name').value = time+file.name; // Set the file name as text content
          };
          reader.readAsDataURL(file);
      }
  }
  
