<?php
// Include the database connection file
include 'config.php';
include 'auth_utils.php';
session_start();
require 'sendOTPGmail.php';
$staffId = $_SESSION['unique_id'];
checkAdminSession($staffId);

// Initialize response array
$response = ['success' => false, 'message' => ''];

// Log the start of the script
logActivity("Customer onboarding process started");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method.';
        logActivity("Invalid request method - expected POST");
        echo json_encode($response);
        exit();
    }

    // Log received POST data (excluding sensitive fields)
    $loggablePost = $_POST;
    unset($loggablePost['add_password'], $loggablePost['add_secret_answer']);
    logActivity("Received POST data: " . json_encode($loggablePost));

    // Retrieve and sanitize form inputs
    $requiredFields = [
        'add_firstname' => 'First Name',
        'add_lastname' => 'Last Name',
        'add_email' => 'Email',
        'add_phone_number' => 'Phone Number',
        'add_gender' => 'Gender',
        'add_address' => 'Address',
        'add_password' => 'Password',
        'add_secret_question' => 'Secret Question',
        'add_secret_answer' => 'Secret Answer',
        'group' => 'Group',
        'unit' => 'Unit'
    ];

    // Validate required fields
    $missingFields = [];
    foreach ($requiredFields as $field => $name) {
        if (empty($_POST[$field])) {
            $missingFields[] = $name;
        }
    }

    if (!empty($missingFields)) {
        $response['message'] = 'Missing required fields: ' . implode(', ', $missingFields);
        logActivity("Validation failed - " . $response['message']);
        echo json_encode($response);
        exit();
    }

    // Assign variables
    $first_name = trim($_POST['add_firstname']);
    $last_name = trim($_POST['add_lastname']);
    $email = strtolower(trim($_POST['add_email']));
    $phone_number = trim($_POST['add_phone_number']);
    $gender = $_POST['add_gender'];
    $address = trim($_POST['add_address']);
    $password = $_POST['add_password'];
    $secret_question = trim($_POST['add_secret_question']);
    $secret_answer = trim($_POST['add_secret_answer']);
    $group_id = (int) $_POST['group'];
    $unit_id = (int) $_POST['unit'];

    // Password validation
    $passwordErrors = [];
    if (strlen($password) < 8)
        $passwordErrors[] = "at least 8 characters";
    if (!preg_match('/[A-Z]/', $password))
        $passwordErrors[] = "one uppercase letter";
    if (!preg_match('/\d/', $password))
        $passwordErrors[] = "one number";
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password))
        $passwordErrors[] = "one special character";

    if (!empty($passwordErrors)) {
        $response['message'] = 'Password must contain: ' . implode(', ', $passwordErrors);
        logActivity("Password validation failed: " . $response['message']);
        echo json_encode($response);
        exit();
    }

    // Validate phone number format
    if (!preg_match('/^\d{11}$/', $phone_number)) {
        $response['message'] = 'Please input a valid 11-digit Phone Number.';
        logActivity("Invalid phone number format: $phone_number");
        echo json_encode($response);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = "Invalid email address format.";
        logActivity("Invalid email format: $email");
        echo json_encode($response);
        exit();
    }

    // Check for existing email
    $sql_email = $conn->prepare("SELECT email FROM customers WHERE email = ?");
    $sql_email->bind_param('s', $email);
    $sql_email->execute();

    if ($sql_email->get_result()->num_rows > 0) {
        $response['message'] = "Email already exists.";
        logActivity("Duplicate email detected: $email");
        $sql_email->close();
        echo json_encode($response);
        exit();
    }
    $sql_email->close();

    // Check for existing phone number
    $sql_phone = $conn->prepare("SELECT mobile_number FROM customers WHERE mobile_number = ?");
    $sql_phone->bind_param('s', $phone_number);
    $sql_phone->execute();

    if ($sql_phone->get_result()->num_rows > 0) {
        $response['message'] = "Phone number already exists.";
        logActivity("Duplicate phone number detected: $phone_number");
        $sql_phone->close();
        echo json_encode($response);
        exit();
    }
    $sql_phone->close();

    // Handle photo upload
    if (!isset($_FILES['add_photo'])) {
        $response['message'] = 'Profile photo is required.';
        logActivity("No photo uploaded");
        echo json_encode($response);
        exit();
    }

    $photo = $_FILES['add_photo'];
    $allowed_extensions = ["jpeg", "png", "jpg"];
    $allowed_types = ["image/jpeg", "image/jpg", "image/png"];
    $max_size = 500000; // 500KB

    $img_explode = explode('.', $photo['name']);
    $img_ext = strtolower(end($img_explode));

    // Validate image file
    if (!in_array($img_ext, $allowed_extensions) || !in_array($photo['type'], $allowed_types)) {
        $response['message'] = 'Only JPEG, PNG, and JPG images are allowed.';
        logActivity("Invalid image type: " . $photo['type']);
        echo json_encode($response);
        exit();
    }

    if ($photo['size'] > $max_size) {
        $response['message'] = 'Image size exceeds 500KB limit.';
        logActivity("Image too large: " . $photo['size'] . " bytes");
        echo json_encode($response);
        exit();
    }

    // Generate unique filename
    $file_hash = md5_file($photo['tmp_name']);
    $file_name = $file_hash . '.' . $img_ext;
    $upload_dir = 'customer_photos/';
    $upload_path = $upload_dir . $file_name;

    // Check for duplicate image
    if (file_exists($upload_path)) {
        $response['message'] = 'This image has already been uploaded.';
        logActivity("Duplicate image detected: $file_hash");
        echo json_encode($response);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();
    logActivity("Database transaction started");

    try {
        // Move uploaded file
        if (!move_uploaded_file($photo['tmp_name'], $upload_path)) {
            throw new Exception("Failed to move uploaded file");
        }
        logActivity("Image successfully uploaded to: $upload_path");

        // Encrypt sensitive data
        $encrypted_password = password_hash($password, PASSWORD_DEFAULT);
        $encrypted_answer = password_hash($secret_answer, PASSWORD_DEFAULT);

        // Generate and validate unique customer ID
        $max_attempts = 5; // Prevent infinite loops
        $attempts = 0;
        $customer_id = null;
        $is_unique = false;

        while ($attempts < $max_attempts && !$is_unique) {
            $customer_id = mt_rand(100000000, 999999999);
            $attempts++;

            // Check if customer_id already exists
            $check_id = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
            $check_id->bind_param('i', $customer_id);
            $check_id->execute();
            $result = $check_id->get_result();

            $is_unique = ($result->num_rows === 0);
            $check_id->close();

            if ($is_unique) {
                logActivity("Generated unique customer ID: $customer_id (attempt $attempts)");
            }
        }

        if (!$is_unique) {
            throw new Exception("Failed to generate unique customer ID after $max_attempts attempts");
        }

        // Insert customer data
        $sql = "INSERT INTO customers (customer_id, firstname, lastname, gender, email, password, mobile_number, address, secret_question, secret_answer, photo, group_id, unit_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param('issssssssssii', $customer_id, $first_name, $last_name, $gender, $email, $encrypted_password, $phone_number, $address, $secret_question, $encrypted_answer, $file_name, $group_id, $unit_id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $email2 = "adebayoabdulrahmon@gmail.com";
        $subject = "Login Credentials for New Customer ". $first_name ." ". $last_name;
        $link = "http://localhost/transaction_manager/index.php?action=reset-password";
        $body = "Dear $first_name kindly follow the link below to reset your password: $link. It expires in 2 minutes.";  // OTP message
        $status = sendEmailWithGmailSMTP($email2, $body, $subject);
        // ====== 2. SEND OTP VIA EMAIL ======
        if (!($status)) {
            logActivity("Failed to send E-mail to New Staff E-mail Address");
            throw new Exception("Email send failed");
        }

        $conn->commit();
        logActivity("Customer successfully onboarded with ID: $customer_id");

        $response['success'] = true;
        $response['message'] = 'Customer successfully onboarded. Customer should check E-mail to Reset Password';
        $response['customer_id'] = $customer_id;

    } catch (Exception $e) {
        $conn->rollback();
        logActivity("Transaction failed: " . $e->getMessage());

        // Clean up uploaded file if transaction failed
        if (file_exists($upload_path)) {
            unlink($upload_path);
            logActivity("Removed uploaded file due to failed transaction");
        }

        $response['message'] = 'An error occurred during onboarding. Please try again.';
        error_log("Onboarding Error: " . $e->getMessage());
    }

} catch (Exception $e) {
    logActivity("Unexpected error: " . $e->getMessage());
    $response['message'] = 'An unexpected error occurred.';
    error_log("System Error: " . $e->getMessage());
}

// Close connection and return response
$conn->close();
logActivity("Script execution completed with result: " . ($response['success'] ? 'SUCCESS' : 'FAILURE'));
echo json_encode($response);