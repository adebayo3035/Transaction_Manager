<?php
include('activity_logger.php');
$hostname = "localhost";
$username = "root";
$password = "";
$dbname = "transaction_manager";
$encryption_key = "ee31a5cb725c5105a18ac247c4e55e6407461a7246da217658ecb1b720c750b4";
$ciphering_value = "AES-256-CTR";
$encryption_iv = '1abx78612hfyui7y';

$conn = mysqli_connect($hostname, $username, $password, $dbname);
if (!$conn) {
  echo "Database connection error" . mysqli_connect_error();
}
function encrypt($string, $key, $iv)
{
  $ciphering = "AES-256-CTR";
  $options = 0;
  return openssl_encrypt($string, $ciphering, $key, $options, $iv);
}
function decrypt($encryptedString, $key, $iv)
{
  $ciphering = "AES-256-CTR";
  $options = 0;
  return openssl_decrypt($encryptedString, $ciphering, $key, $options, $iv);
}

function checkSession($CustomerID)
{
  if (!isset($CustomerID)) {
    $error_message = "Customer not logged in. Customer's Session ID Cannot be found.";
    http_response_code(401);  // Unauthorized
    error_log($error_message);
    logActivity($error_message);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
  }
}

function sendAdminNotification($conn, $title, $eventType, $eventDetails, $userId, $logMessage = null) {
  // Insert into admin_notifications
  $stmt = $conn->prepare("INSERT INTO admin_notifications (event_title, event_type, event_details, created_at, user_id) VALUES (?, ?, ?, NOW(), ?)");
  $stmt->bind_param("ssss", $title, $eventType, $eventDetails, $userId);
  $stmt->execute();
  $stmt->close();
}