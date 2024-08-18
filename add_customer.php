<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Customer Information</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>
<?php include "navbar.php"; ?>

<header>
  <h1>Onboard New Customer</h1>
</header>

<main>
  <form method="POST" enctype="multipart/form-data" autocomplete="off" id="addCustomerForm">
    <label for="firstName">First Name:</label>
    <input type="text" id="firstName" name="firstName" required>

    <label for="lastName">Last Name:</label>
    <input type="text" id="lastName" name="lastName" required>

    <label for="gender">Gender:</label>
    <select id="gender" name="gender" required>
      <option value="male">Male</option>
      <option value="female">Female</option>
      <option value="other">Other</option>
    </select>

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required>

    <label for="password">Password:</label>
    <input type="password" id="password" name="password" required>

    <label for="phoneNumber">Phone Number:</label>
    <input type="tel" id="phoneNumber" name="phoneNumber" required>

    <label for="address">Address:</label>
    <input type="text" id="address" name="address" required>

    <label for="secret_question">Secret Question:</label>
    <input type="text" id="secret_question" name="secret_question" required>

    <label for="secret_answer">Secret Answer:</label>
    <input type="password" id="secret_answer" name="secret_answer" required>

    <label for="photo">Photo:</label>
    <input type="file" id="photo" name="photo" accept="image/*" required>

    <!-- Hidden input to store the photo name -->
    <input type="hidden" id="photo_name" name="photo_name">

    <div id="photoContainer" style="display:none;">
      <img id="uploadedPhoto" src="#" alt="Uploaded Photo">
    </div>

    <label for="selectOption">Select Group:</label>
    <select id="selectOption" name="group" required onchange="handleGroupSelection()">
      <option value="">--Select a Group--</option>
      <!-- Add options for groups dynamically -->
      <?php
        include 'backend/config.php';
        $sql = "SELECT group_id, group_name FROM groups";
        $result = mysqli_query($conn, $sql);
        if (mysqli_num_rows($result) > 0) {
          while ($row = mysqli_fetch_assoc($result)) {
            echo '<option value="' . $row['group_id'] . '">' . $row['group_name'] . '</option>';
          }
        } else {
          echo '<option value="">No groups found</option>';
        }
        mysqli_close($conn);
      ?>
    </select>

    <div id="additionalInput" style="display: none;">
      <label for="selectedUnit">Select Unit:</label>
      <select id="selectedUnit" name="unit">
        <option value="">--Select a Unit--</option>
        <!-- Options will be populated dynamically -->
      </select>
    </div>

    <button type="submit" id="btnAddCustomer" style="display: none;">Add Customer</button>
  </form>
</main>

<script src="scripts/photo_upload.js"></script>
<script src="scripts/add_customer.js"></script>
</body>
</html>
