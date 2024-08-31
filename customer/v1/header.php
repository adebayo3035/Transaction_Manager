<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Transaction Manager
  </title>

  <!-- Script to Log User Out whenever they are Inactive for some secs.. here we use 30 secs -->
  <script>
    // Get the ID of the logged in User
    var userId = <?php echo isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : 'null'; ?>;

  // set the time for system to timeout
    const inactivityTimeout = 8 * 60 * 1000; // 15 minutes in milliseconds
    let inactivityTimer;

    // Function to clear and set timeout
    function resetInactivityTimer(userId) {
      clearTimeout(inactivityTimer);
      inactivityTimer = setTimeout(function() {
        // Redirect to logout page or trigger a logout function
        window.location.href = '../v2/logout.php?logout_id=' + userId;
      }, inactivityTimeout);
    }
    resetInactivityTimer(userId);

    document.addEventListener('mousemove', function() {
      resetInactivityTimer(userId);
    });

    document.addEventListener('keydown', function() {
      resetInactivityTimer(userId);
    });
  </script>
</head>
<?php
// include_once "navbar.php"; 
?>