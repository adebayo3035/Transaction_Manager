<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drivers</title>
    <link rel="stylesheet" href="customer/css/cards.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <section class="container">
        <div class="card_menu">
            <ul class="card-menu">
                <li> <a onclick="toggleModal('addNewDriverModal')">Add New Driver</a></li>
                <li> <a href= "view_drivers.php">View Drivers Information</a></li>
                
            </ul>

        </div>

        <!--  DRIVERS SECTION -->
        <div class="driver-container" id="driver-container">
            <!-- driver info will be populated here -->

        </div>

        <!-- ADD NEW CARD FORM -->
    </section>

    <!-- Modal to add new Driver -->
    <div id="addNewDriverModal" class="modal">
        <div class="modal-content" id="card-form">
            <span class="close">&times;</span>
            <h2>Add New Driver</h2>
            <form id="addDriverForm">
                <div class="form-input">
                    <label for="add_first_name">First Name:</label>
                    <input type="text" id="add_firstname" name="add_firstname" required>
                </div>

                <div class="form-input">
                    <label for="add_lastname">Last Name:</label>
                    <input type="text" id="add_lastname" name="add_lastname" required>
                </div>

                <div class="form-input">
                    <label for="add_email">Email:</label>
                    <input type="email" id="add_email" name="add_email" required>
                </div>

                <div class="form-input">
                    <label for="add_phone_number">Phone Number:</label>
                    <input type="text" id="add_phone_number" name="add_phone_number" required>
                </div>

                <div class="form-input">
                    <label for="add_gender">Gender:</label>
                    <select id="add_gender" name="add_gender" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="form-input">
                    <label for="add_license_number">License Number:</label>
                    <input type="text" id="add_license_number" name="add_license_number" required readonly>
                </div>

                <div class="form-input">
                    <label for="add_vehicle_type">Type of Vehicle:</label>
                    <select id="add_vehicle_type" name="add_vehicle_type" required>
                        <option value="Bike">Bike</option>
                        <option value="Bicycle">Bicycle</option>
                        <option value="Car">Car</option>
                    </select>
                </div>

                <div class="form-input">
                    <label for="add_address">Address:</label>
                    <input type="text" id="add_address" name="add_address" required>
                </div>

                <div class="form-input">
                    <label for="add_password">Password:</label>
                    <input type="password" id="add_password" name="add_password" required>
                </div>

                <div class="form-input">
                    <label for="add_secret_question">Secret Question:</label>
                    <input type="text" id="add_secret_question" name="add_secret_question" required>
                </div>

                <div class="form-input">
                    <label for="add_secret_answer">Secret Answer:</label>
                    <input type="password" id="add_secret_answer" name="add_secret_answer" required>
                </div>

                <div class="form-input">
                    <label for="add_photo">Photo:</label>
                    <input type="file" id="add_photo" name="add_photo" accept="image/*" required
                        onchange="previewPhoto(event)">
                </div>
                
                <button type="submit">Add Driver</button>
            </form>
            <div id="photo_container" style="margin-top: 10px;">
                    <img id="photo_preview" src="#" alt="Photo Preview"
                         />
                </div>
            <div id="addDriverMessage"></div>

        </div>

    </div>
    <script src="scripts/driver.js"></script>
    <script>
        // function to handle Photo Upload
    function previewPhoto(event) {
        const file = event.target.files[0];
        const reader = new FileReader();
    
        reader.onload = function(e) {
            const photoPreview = document.getElementById('photo_preview');
            // photoPreview.src = e.target.result;
            photoPreview.style.display = 'block';
            photoPreview.style.maxWidth = '150px';
            photoPreview.style.maxHeight = '100px';
            photoPreview.style.alignSelf = 'center';
            photoPreview.setAttribute('src', e.target.result);
            // document.getElementById('photoContainer').style.display = 'block';
        }
    
        if (file) {
            reader.readAsDataURL(file);
        }
    }
   
    </script>
</body>

</html>