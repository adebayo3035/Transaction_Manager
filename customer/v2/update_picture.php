<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
include 'auth_utils.php';
header('Content-Type: application/json');

//Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-XSS-Protection: 0");

$response = ['success' => false, 'message' => 'An error occurred. Please try again.'];

try {
    // Logging start
    logActivity("Script execution started.");

    $customer_id = $_SESSION['customer_id'] ?? null;
    checkSession($customer_id);
    logActivity("Session validated successfully for Customer ID: $customer_id.");

    // Fetch current photo and secret answer
    $stmt = $conn->prepare("SELECT photo, secret_answer FROM customers WHERE customer_id = ?");
    if (!$stmt) throw new Exception("Failed to prepare SELECT statement.");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $stmt->bind_result($current_photo, $secret_answer);
    $stmt->fetch();
    $stmt->close();
    logActivity("Fetched profile details for Customer ID: $customer_id.");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logActivity("Invalid request method.");
        throw new Exception('Invalid request method.');
    }

    if (!isset($_FILES['photo'], $_POST['secret_answer'])) {
        logActivity("Missing photo or secret_answer.");
        throw new Exception('Secret answer or file not provided.');
    }

    $user_provided_secret_answer = $_POST['secret_answer'];
    logActivity("Validating secret answer for Customer ID: $customer_id.");
    if (!verifyAndUpgradeSecretAnswer($conn, $customer_id, $user_provided_secret_answer, $secret_answer)) {
        logActivity("Invalid secret answer.");
        throw new Exception('Account validation failed.');
    }

    logActivity("Secret answer validated.");

    $img = $_FILES['photo'];
    $img_name = $img['name'];
    $img_type = $img['type'];
    $tmp_name = $img['tmp_name'];
    $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));

    $allowed_exts = ['jpeg', 'jpg', 'png'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];

    if (!in_array($img_ext, $allowed_exts) || !in_array($img_type, $allowed_types)) {
        logActivity("Invalid file format uploaded by Customer ID: $customer_id.");
        throw new Exception('Invalid file format. Only JPEG and PNG images are allowed.');
    }

    if ($img['size'] > 500000) {
        logActivity("File too large: {$img['size']} bytes.");
        throw new Exception('Image size is too large. Max allowed is 500KB.');
    }

    $upload_dir = '../../backend/customer_photos/';
    $file_hash = md5_file($tmp_name);
    $file_name = $file_hash . '.' . $img_ext;
    $upload_path = $upload_dir . $file_name;

    if (file_exists($upload_path)) {
        logActivity("Duplicate image detected.");
        throw new Exception('This image has already been uploaded by another user.');
    }

    if (!move_uploaded_file($tmp_name, $upload_path)) {
        logActivity("Failed to move uploaded file.");
        throw new Exception('File upload failed.');
    }

    logActivity("File uploaded successfully. Updating database.");

    // Update profile picture
    $stmt = $conn->prepare("UPDATE customers SET photo = ? WHERE customer_id = ?");
    if (!$stmt) throw new Exception("Failed to prepare UPDATE statement.");
    $stmt->bind_param('si', $file_name, $customer_id);

    if (!$stmt->execute()) {
        logActivity("Database update failed.");
        throw new Exception('Failed to update profile picture.');
    }
    $stmt->close();

    // Delete old image
    $old_path = $upload_dir . $current_photo;
    if ($current_photo && file_exists($old_path)) {
        unlink($old_path);
        logActivity("Deleted old profile picture.");
    }

    $response = [
        'success' => true,
        'message' => 'Profile picture updated successfully.',
        'file'    => $file_name
    ];
    logActivity("Profile picture updated successfully for Customer ID: $customer_id.");

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    logActivity("Error: " . $e->getMessage());
} finally {
    $conn->close();
    logActivity("Script execution completed for Customer ID: $customer_id.");
    echo json_encode($response);
    exit;
}
