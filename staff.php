<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaraKata Staffs</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="customer/css/cards.css">
    <link rel="stylesheet" href="css/view_driver.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <h1>KaraKata Staffs</h1>
        <!-- Separate row for "Add New Customer" button -->
        <div id="customer-form">
            <button onclick="toggleModal('addNewStaffModal')"><i class="fa fa-plus" aria-hidden="true"></i> Add New
                Staff</button>
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
                <th>Mobile Number</th>
                <th>E-mail Address</th>
                <th>Restriction Indicator</th>
                <th>Block Indicator</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Staffs Information will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>


    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Staff Information</h2>
            <div id="driverPhoto" class="photo-container">

            </div>
            <table id="orderDetailsTable" class="ordersTable">
                <tbody>
                    <!-- Driver details will be automatically populated here -->
                </tbody>
            </table>
        </div>
    </div>


    <!-- Modal to add new Staff -->
    <div id="addNewStaffModal" class="modal">
        <div class="modal-content" id="card-form">
            <span class="close">&times;</span>
            <h2>Add New Staff</h2>
            <form id="addStaffForm">
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
                    <option value="">-- Select Gender --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Others">Others</option>
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
                    <input type="file" id="photo" name="photo" accept="image/*" required
                        onchange="previewPhoto(event)">
                </div>
                <div id="photo_container">
                    <img id="photo_preview" src="#" alt="Photo Preview"
                        style="display: none; max-width: 100%; height: auto;" />
                </div>
                <label for="selcetOption">Select Role</label>
                <select name="add_role" id="selectOption" required>
                    <option value="">--Select a Group--</option>
                    <option value="Admin">Admin</option>
                    <option value="Super Admin">Super Admin</option>
                </select>
                
                <button type="submit" id="submitBtn">Add Staff</button>
            </form>

            <div id="addStaffMessage"></div>

        </div>
    </div>

    <script src="scripts/staff.js"></script>
    <!-- <script src="scripts/staff.js"></script> -->
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