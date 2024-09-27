 <?php 
  session_start();
  include_once "config.php";
 
// Set session timeout to 2 minutes
$session_timeout = 20 * 60; // 2 minutes in seconds

// Check if user is logged in
if(isset($_SESSION['unique_id'])) {
    // Check if last activity timestamp is set
    if(isset($_SESSION['last_activity'])) {
        // Calculate time difference between current time and last activity time
        $idle_time = time() - $_SESSION['last_activity'];
        
        // If idle time exceeds session timeout, destroy session and log out user
        if($idle_time > $session_timeout) {
            session_unset();    // Unset all session variables
            session_destroy();  // Destroy the session
            header("Location: index.php"); // Redirect to login page
            exit();
        }
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
} else {
    // If user is not logged in, redirect to login page
    header("Location: index.php");
    exit();
}
?>