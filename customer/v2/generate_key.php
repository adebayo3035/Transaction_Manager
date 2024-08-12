<?php
/**
 * Encrypts a given string using AES-128-CTR.
 *
 * @param string $string The string to be encrypted.
 * @param string $key The encryption key.
 * @param string $iv The initialization vector (IV) for encryption.
 * @return string The encrypted string.
 */
function encryptString($string, $key, $iv)
{
    $ciphering = "AES-256-CTR";
    $options = 0;
    return openssl_encrypt($string, $ciphering, $key, $options, $iv);
}

/**
 * Decrypts a given string using AES-128-CTR.
 *
 * @param string $encryptedString The encrypted string to be decrypted.
 * @param string $key The decryption key.
 * @param string $iv The initialization vector (IV) used for encryption.
 * @return string The decrypted string.
 */
function decryptString($encryptedString, $key, $iv)
{
    $ciphering = "AES-256-CTR";
    $options = 0;
    return openssl_decrypt($encryptedString, $ciphering, $key, $options, $iv);
}

// Example usage:
$original_string = "Welcome to JavaTpoint advance learner \n";
$encryption_key = "JavaTpoint";
$encryption_iv = '1abx78612hfyui7y';

// Encrypt the original string
$encrypted_string = encryptString($original_string, $encryption_key, $encryption_iv);
echo "Original String: " . $original_string . "<br><br>";
echo "Encrypted Input String: " . $encrypted_string . "<br><br>";

// Decrypt the string
$decrypted_string = decryptString($encrypted_string, $encryption_key, $encryption_iv);
echo "Decrypted Input String: " . $decrypted_string . "\n";

include('config.php');
function getStoredPinHash($cardNumber, $conn) {
    $stmt = $conn->prepare("SELECT card_pin FROM cards WHERE card_number = ?");
    $stmt->bind_param("s", $cardNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['card_pin'] : null;
}

// Validate PIN
$storedPinHash = getStoredPinHash($cardNumber, $conn);
if (md5('1234') <> $storedPinHash) {
    echo "Invalid PIN";
    exit;
}