<?php
include('activity_logger.php');
  $hostname = "localhost";
  $username = "root";
  $password = "";
  $dbname = "transaction_manager";

  $conn = mysqli_connect($hostname, $username, $password, $dbname);
  if(!$conn){
    echo "Database connection error".mysqli_connect_error();
  }

  function checkAdminSession($staffID)
  {
    if (!isset($staffID)) {
      $error_message = "Admin not logged in. Admin's Session ID Cannot be found.";
      http_response_code(401);  // Unauthorized
      error_log($error_message);
      logActivity($error_message);
      echo json_encode(['error' => 'Unauthorized access']);
      exit();
    }
  }