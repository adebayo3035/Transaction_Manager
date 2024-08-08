<?php
  $hostname = "localhost";
  $username = "root";
  $password = "";
  $dbname = "transaction_manager";
  $encryption_key = "ee31a5cb725c5105a18ac247c4e55e6407461a7246da217658ecb1b720c750b4";
  $ciphering_value = "AES-256-CTR";
  $encryption_iv = '1abx78612hfyui7y';  

  $conn = mysqli_connect($hostname, $username, $password, $dbname);
  if(!$conn){
    echo "Database connection error".mysqli_connect_error();
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