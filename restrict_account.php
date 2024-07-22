<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Restrict or Block Account</title>
    <link rel="stylesheet" href="css/restrict_account.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <h1>Restrict or Block Account</h1>
        <form id="restrictionForm">
            <label for="identifier">Staff Unique ID, Email, or Phone Number:</label>
            <input type="text" id="identifier" name="identifier" required>
            
            <label for="restrictionType">Select Restriction Type:</label>
            <select id="restrictionType" name="restrictionType" required>
                <option value="restrict">Restrict</option>
                <option value="block">Block</option>
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

    <script src="scripts/admin_restrict_block.js"></script>
</body>

</html>
