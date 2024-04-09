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
                    <?php echo "{$masked_email}"; ?>
                </p>
            </div>
            <div class="detail">
                <label>Phone Number:</label>
                <p>
                    <?php echo "{$masked_phone}"; ?>
                </p>
            </div>
        </div>
    </div>


</body>

</html>