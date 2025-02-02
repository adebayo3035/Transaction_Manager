<?php
// include 'config.php';
// session_start();
// header('Content-Type: application/json');

// Define a secret key for encryption (store this securely, not in code)
// define('SECRET_KEY', 'your-secure-key-here'); // Use a secure, long key
// define('SECRET_IV', 'your-secure-iv-here');   // Initialization Vector (should be the same size as required by the cipher)

// Function to encrypt data
// function encryptData($data) {
//     $cipher = 'aes-256-cbc'; // AES-256 encryption
//     $key = hash('sha256', SECRET_KEY); // Derive the key
//     $iv = substr(hash('sha256', SECRET_IV), 0, 16); // Derive the IV
//     $encrypted = openssl_encrypt(json_encode($data), $cipher, $key, 0, $iv); // Encrypt data
//     return base64_encode($encrypted); // Encode to make it safe for JSON transmission
// }

// Check session and prepare response
// if (isset($_SESSION['customer_id'])) {
//     $data = [
//         'customer_id' => $_SESSION['customer_id'],
//         'email' => $_SESSION['email']
//     ];
//     $encryptedData = encrypt($data, $encryption_key, $encryption_iv); // Encrypt the data
//     echo json_encode(['data' => $encryptedData]); // Send encrypted data
// } else {
//     echo json_encode(['data' => null]); // Send null if no session
// }


session_start();
header('Content-Type: application/json');

if (isset($_SESSION['customer_id'])) {
    echo json_encode(['customer_id' => $_SESSION['customer_id'], 'email' => $_SESSION['email']]);
} else {
    // Return null if session has expired or is not set
    echo json_encode(['customer_id' => null]);
}
