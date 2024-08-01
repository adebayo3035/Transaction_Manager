<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Restrict or Block Account</title>
    <link rel="stylesheet" href="css/restrict_account.css">
</head>

<body>
    <?php include ('navbar.php'); ?>
    <div class="container">
        <h1>Remove Lien or Unblock Account</h1>
        <form id="restrictionForm">
            <label for="identifier">Select Staff :</label>
            <select id="identifier" name="identifier" required>
                <option value="">--Select a Staff Name--</option>
                <!-- Add options for groups dynamically -->
                <?php
                include 'backend/config.php';
                $sql = "SELECT unique_id, firstname, lastname, restriction_id, block_id FROM admin_tbl WHERE role = 'Admin' AND (restriction_id = 1 OR block_id = 1)";
                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        if ($row['block_id'] == 1 && $row['restriction_id'] == 1) {
                            // Check for both restricted and blocked first
                            echo '<option value="' . $row['unique_id'] . '">' . $row['unique_id'] . " - " . $row['firstname'] . " " . $row['lastname'] . ' (Restricted and Blocked Account)' . '</option>';
                        } else if ($row['restriction_id'] == 1) {
                            // Check for restricted
                            echo '<option value="' . $row['unique_id'] . '">' . $row['unique_id'] . " - " . $row['firstname'] . " " . $row['lastname'] . ' (Restricted Account)' . '</option>';
                        } else if ($row['block_id'] == 1) {
                            // Check for blocked
                            echo '<option value="' . $row['unique_id'] . '">' . $row['unique_id'] . " - " . $row['firstname'] . " " . $row['lastname'] . ' (Blocked Account)' . '</option>';
                        } else {
                            // Neither restricted nor blocked (implicitly not possible due to the query filter, but added for completeness)
                            echo '<option value="' . $row['unique_id'] . '">' . $row['unique_id'] . " - " . $row['firstname'] . " " . $row['lastname'] . '</option>';
                        }
                    }
                } else {
                    echo '<option value="">No Staff Record Found</option>';
                }

                mysqli_close($conn);

                ?>
            </select>

            <label for="restrictionType">Select Restriction Type:</label>
            <select id="restrictionType" name="restrictionType" required>
                <option value="unrestrict">Remove Lien</option>
                <option value="unblock">Unblock Account</option>
            </select>

            <button type="submit" class="button">Submit</button>
        </form>

        <div id="confirmationModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <p id="confirmationMessage">Are you sure you want to proceed?</p>
                <button id="confirmButton">Yes</button>
                <button id="cancelButton">No</button>
            </div>
        </div>

        <div class="errorContainer"></div>
    </div>

    <script src="scripts/admin_unblock.js"></script>
</body>

</html>