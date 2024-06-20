<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/profile.css">
    <title>Staff Profile</title>
</head>


<body>

    <?php
    // function to mask E-mail Address
    function maskEmail($email)
    {
        // Split email address into local part and domain
        list($local_part, $domain) = explode("@", $email);

        // Get the length of the local part and keep the first letter visible
        $visible_chars = substr($local_part, 0, 1);

        // Mask the remaining characters with asterisks
        $masked_local_part = str_repeat("*", strlen($local_part) - 1);

        // Concatenate the visible character with the masked local part and domain
        $masked_email = $visible_chars . $masked_local_part . "@" . $domain;

        return $masked_email;
    }

    // function to mask Phone Number
    function maskPhoneNumber($phone) {
        // Extract last four digits of the phone number
        $last_four_digits = substr($phone, -4);
        
        // Mask the remaining digits with asterisks
        $masked_number = str_repeat("*", strlen($phone) - 4) . $last_four_digits;
        
        return $masked_number;
    }
    

    ?>
    <?php include "navbar.php";
    $unique_id = $_SESSION['unique_id']; ?>
    <div class="profile-container">
        <div class="profile-header">
            <img src="backend/admin_photos/<?php echo $row['photo']; ?>" alt="Staff Photo">
            <?php echo "<span><a href='change_picture.php?id=" . $unique_id . "'><span class='edit-icon'>&#9998;</span></a></span>"; ?>
            <h1>
                <?php echo "{$firstname} {$lastname}"; $masked_email = maskEmail($email); $masked_phone = maskPhoneNumber($phone)?>
            </h1>

            <p>Position:
                <?php echo "{$role}"; ?>
            </p>
        </div>
        <div class="profile-details">
            <div class="detail">
                <label>First Name:</label>
                <p>
                    <?php echo "{$firstname}"; ?>
                </p>
            </div>
            <div class="detail">
                <label>Last Name:</label>
                <p>
                    <?php echo "{$lastname}"; ?>
                </p>
            </div>
            <div class="detail">
                <label>Email:</label>
                <p>
                <span class = "result" id="display-email"> <?php echo "{$masked_email}"; ?> </span>
                    <br>
                    <span class = "toggle-indicator">ON</span>
                    <label class="switch">
                        <input type="checkbox" id="toggle-checkbox">
                        <span class="slider"></span>
                    </label>
                    <span class = "toggle-indicator">OFF</span>
                </p>
            </div>
            <div class="detail">
                <label>Phone Number:</label>
                <p>
                    
                    <span class = "result" id="display-phone"> <?php echo "{$masked_phone}"; ?> </span>
                    <br>
                    <span class = "toggle-indicator">ON</span>
                    <label class="switch">
                        
                        <input type="checkbox" id="toggle-checkbox1">
                        <span class="slider"></span>
                        
                    </label>
                    <span class = "toggle-indicator"> OFF</span>
                </p>
            </div>
        </div>
    </div>

<script>
    var togglePhone = document.getElementById('toggle-checkbox1');
    var toggleEmail = document.getElementById('toggle-checkbox');
    var displayPhone = document.getElementById('display-phone')
    var displayEmail = document.getElementById('display-email')
    var phone = "<?php echo $phone; ?>";
    var maskedPhone = "<?php echo $masked_phone; ?>";
    var email = "<?php echo $email; ?>";
    var maskedEmail = "<?php echo $masked_email; ?>";

            function toggleDisplay(toggleCheckbox, resultElement, unmasked_data, maskedData) {
                toggleCheckbox.addEventListener('change', function() {
                var isChecked = toggleCheckbox.checked;
                if (isChecked) {
                    resultElement.textContent = unmasked_data;
                } else {
                    resultElement.textContent = maskedData;
                }
                
            });
}
toggleDisplay(togglePhone, displayPhone, phone, maskedPhone)
toggleDisplay(toggleEmail, displayEmail, email, maskedEmail)
</script>
</body>

</html>