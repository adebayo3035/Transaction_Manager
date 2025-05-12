<?php
header('Content-Type: application/json');
include('config.php');
session_start();
require 'sendOTPGmail.php';
$staffId = $_SESSION['unique_id'];
checkAdminSession($staffId);

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    logActivity("Driver onboarding process started");

    // Retrieve and sanitize form data
    $fields = [
        'first_name' => $_POST['add_firstname'] ?? '',
        'last_name' => $_POST['add_lastname'] ?? '',
        'email' => strtolower(trim($_POST['add_email'] ?? '')),
        'phone_number' => $_POST['add_phone_number'] ?? '',
        'gender' => $_POST['add_gender'] ?? '',
        'license_number' => $_POST['add_license_number'] ?? '',
        'vehicle_type' => $_POST['add_vehicle_type'] ?? '',
        'address' => $_POST['add_address'] ?? '',
        'password' => $_POST['add_password'] ?? '',
        'secret_question' => $_POST['add_secret_question'] ?? '',
        'secret_answer' => $_POST['add_secret_answer'] ?? ''
    ];

    logActivity("Received form data: " . json_encode(array_keys($fields)));

    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'email', 'phone_number', 'license_number', 'password', 'secret_question', 'secret_answer'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (empty($fields[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        $response['message'] = 'Missing required fields: ' . implode(', ', $missingFields);
        logActivity("Validation failed - " . $response['message']);
        echo json_encode($response);
        exit;
    }

    // Password validation
    $passwordErrors = [];
    if (strlen($fields['password']) < 8) $passwordErrors[] = "at least 8 characters";
    if (!preg_match('/[A-Z]/', $fields['password'])) $passwordErrors[] = "one uppercase letter";
    if (!preg_match('/\d/', $fields['password'])) $passwordErrors[] = "one number";
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $fields['password'])) $passwordErrors[] = "one special character";

    if (!empty($passwordErrors)) {
        $response['message'] = 'Password must contain: ' . implode(', ', $passwordErrors);
        logActivity("Password validation failed: " . $response['message']);
        echo json_encode($response);
        exit();
    }

    // Email validation
    if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid E-mail address.';
        logActivity("Invalid email format: " . $fields['email']);
        echo json_encode($response);
        exit();
    }

    // Phone number validation
    if (!preg_match('/^\d{11}$/', $fields['phone_number'])) {
        $response['message'] = 'Please input a valid 11-digit Phone Number.';
        logActivity("Invalid phone number format: " . $fields['phone_number']);
        echo json_encode($response);
        exit();
    }

    // Check for duplicates
    $duplicateCheckSql = "SELECT 
        SUM(email = ?) AS email_count,
        SUM(phone_number = ?) AS phone_count,
        SUM(license_number = ?) AS license_count
    FROM driver";
    
    $stmt = $conn->prepare($duplicateCheckSql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('sss', $fields['email'], $fields['phone_number'], $fields['license_number']);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $duplicates = [];
    if ($row['email_count'] > 0) $duplicates[] = 'email';
    if ($row['phone_count'] > 0) $duplicates[] = 'phone number';
    if ($row['license_count'] > 0) $duplicates[] = 'license number';

    if (!empty($duplicates)) {
        $response['message'] = 'The following already exist: ' . implode(', ', $duplicates);
        logActivity("Duplicate data found: " . $response['message']);
        echo json_encode($response);
        exit;
    }

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
    $upload_dir = 'driver_photos/';
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

        // Hash sensitive data
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $hashed_secret_answer = md5($secret_answer);
        $status = "Available";
        $restriction = 0;

        // Insert driver data
        $sql = "INSERT INTO driver (firstname, lastname, gender, email, phone_number, address, license_number, vehicle_type, password, secret_question, secret_answer, photo, date_created, date_updated, status, restriction) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssssssssssssss", 
            $fields['first_name'], 
            $fields['last_name'], 
            $fields['gender'], 
            $fields['email'], 
            $fields['phone_number'], 
            $fields['address'], 
            $fields['license_number'], 
            $fields['vehicle_type'], 
            $hashed_password, 
            $fields['secret_question'], 
            $hashed_secret_answer, 
            $file_name, 
            $status, 
            $restriction
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $conn->commit();
        logActivity("Driver successfully onboarded: " . $fields['email']);

        $response['success'] = true;
        $response['message'] = 'Driver has been successfully onboarded.';

    } catch (Exception $e) {
        $conn->rollback();
        logActivity("Transaction failed: " . $e->getMessage());

        // Clean up uploaded file if transaction failed
        if (file_exists($upload_path)) {
            unlink($upload_path);
            logActivity("Removed uploaded file due to failed transaction");
        }

        $response['message'] = 'An error occurred during onboarding. Please try again.';
    }

} catch (Exception $e) {
    logActivity("Unexpected error: " . $e->getMessage());
    $response['message'] = 'An unexpected error occurred.';
}

echo json_encode($response);
$conn->close();
logActivity("Script execution completed with result: " . ($response['success'] ? 'SUCCESS' : 'FAILURE'));