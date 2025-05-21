<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaraKata Drivers</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="customer/css/cards.css">
    <link rel="stylesheet" href="css/view_driver.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <h1>KaraKata Drivers</h1>
        <!-- Separate row for "Add New Customer" button -->
        <div id="customer-form">
            <button onclick="toggleModal('addNewDriverModal')"><i class="fa fa-plus" aria-hidden="true"></i> Add New
                Driver</button>
        </div>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>


    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <th>FirstName</th>
                <th>LastName</th>
                <!-- <th>License Number</th>
                <th>Phone Number</th>
                <th>E-mail Address</th> -->
                <th>Driver Status</th>
                <th>Account Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Drivers will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>


    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Driver Information</h2>
            <div id="driverPhoto" class="photo-container">

            </div>
            <table id="orderDetailsTable" class="ordersTable">
                <tbody>
                    <!-- Driver details will be automatically populated here -->
                </tbody>
            </table>
        </div>
    </div>


    <!-- Modal to add new Driver -->
    <div id="addNewDriverModal" class="modal">
        <div class="modal-content" id="card-form">
            <span class="close" id="closeaddNewDriverModal">&times;</span>
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
                        <option value="Bicycle">Bicycle</option>
                        <option value="Mototcycle">Motorcycle</option>
                        <option value="Tricycle">Tricycle</option>
                        <option value="Car">Car</option>
                        <option value="Bus">Bus</option>
                        <option value="Lorry">Lorry</option>
                        <option value="Others">-- Specify Others --</option>
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
                    <input type="text" id="add_secret_answer" name="add_secret_answer" required>
                </div>

                <div class="form-input">
                    <label for="add_photo">Photo:</label>
                    <input type="file" id="add_photo" name="add_photo" accept="image/*" required
                        onchange="previewPhoto(event)">
                </div>
                <div id="photo_container">
                    <img id="photo_preview" src="#" alt="Photo Preview"
                        style="display: none; max-width: 100%; height: auto;" />
                </div>

                <button type="submit">Add Driver</button>
            </form>

            <div id="addDriverMessage"></div>

        </div>
    </div>

    <script src="scripts/driver.js"></script>
    <script>
        // function to handle Photo Upload
        function previewPhoto(event) {
            const file = event.target.files[0];
            const reader = new FileReader();

            reader.onload = function (e) {
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