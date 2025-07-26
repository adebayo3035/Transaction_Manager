<?php
header('Content-Type: application/json');
include_once "config.php";

session_start();

// Initialize logging
logActivity("Script execution started");

if (!isset($_SESSION['unique_id'])) {
    logActivity("Access denied - User not logged in");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];
logActivity("User authenticated: ID {$logged_in_user}, Role: {$loggedInUserRole}");

if ($loggedInUserRole !== "Super Admin") {
    logActivity("Access denied - Insufficient permissions for user {$logged_in_user}");
    echo json_encode(["success" => false, "message" => "Access Denied. Permission not granted!"]);
    exit();
}

// Retrieve and sanitize inputs
$inputs = [
    'firstname' => $_POST['add_firstname'] ?? '',
    'lastname' => $_POST['add_lastname'] ?? '',
    'email' => $_POST['add_email'] ?? '',
    'phone' => $_POST['add_phone_number'] ?? '',
    'gender' => $_POST['add_gender'] ?? '',
    'address' => $_POST['add_address'] ?? '',
    'password' => $_POST['add_password'] ?? '',
    'secret_question' => $_POST['add_secret_question'] ?? '',
    'secret_answer' => $_POST['add_secret_answer'] ?? '',
    'role' => $_POST['add_role'] ?? ''
];

logActivity("Inputs received: " . json_encode($inputs));

// REGEX TO VALIDATE PASSWORD AND SECRET ANSWER
$minLength = 8;
$hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $inputs['password']);
$hasUpperCase = preg_match('/[A-Z]/', $inputs['password']);
$hasDigit = preg_match('/\d/', $inputs['password']);

// Validate phone number
if (!preg_match('/^\d{11}$/', $inputs['phone'])) {
    logActivity("Validation failed - Invalid phone number format");
    echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
    exit();
}

// Check if all fields are filled
foreach ($inputs as $key => $value) {
    if (empty($value)) {
        logActivity("Validation failed - Empty field detected: {$key}");
        echo json_encode(['success' => false, 'message' => 'Please fill all input fields.']);
        exit();
    }
}

// Validate password strength
if (strlen($inputs['password']) < $minLength || !$hasSpecialChar || !$hasUpperCase || !$hasDigit) {
    logActivity("Validation failed - Password does not meet complexity requirements");
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters with at least one uppercase letter, one number, and one special character.']);
    exit();
}

// Validate email
if (!filter_var($inputs['email'], FILTER_VALIDATE_EMAIL)) {
    logActivity("Validation failed - Invalid email format");
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}

// Check for duplicate email and phone
$sql_check = $conn->prepare("SELECT 
    SUM(email = ?) AS email_exists,
    SUM(phone = ?) AS phone_exists
FROM admin_tbl");
$sql_check->bind_param('ss', $inputs['email'], $inputs['phone']);
$sql_check->execute();
$result = $sql_check->get_result();
$row = $result->fetch_assoc();

$errors = [];
if ($row['email_exists'] > 0) {
    $errors['email'] = "Email already exists";
    logActivity("Duplicate detected - Email already exists");
}
if ($row['phone_exists'] > 0) {
    $errors['phone'] = "Phone already exists";
    logActivity("Duplicate detected - Phone number already exists");
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => $errors]);
    exit();
}

// Handle file upload
if (!isset($_FILES['photo'])) {
    logActivity("Validation failed - No photo uploaded");
    echo json_encode(['success' => false, 'message' => 'Please upload a profile photo.']);
    exit();
}

$img_name = $_FILES['photo']['name'];
$img_type = $_FILES['photo']['type'];
$tmp_name = $_FILES['photo']['tmp_name'];
$img_size = $_FILES['photo']['size'];
$img_explode = explode('.', $img_name);
$img_ext = strtolower(end($img_explode));
$extensions = ["jpeg", "png", "jpg"];

if (!in_array($img_ext, $extensions)) {
    logActivity("Validation failed - Invalid image extension: {$img_ext}");
    echo json_encode(['success' => false, 'message' => 'Invalid image extension. Only jpeg, png, jpg allowed.']);
    exit();
}

$types = ["image/jpeg", "image/jpg", "image/png"];
if (!in_array($img_type, $types)) {
    logActivity("Validation failed - Invalid image type: {$img_type}");
    echo json_encode(['success' => false, 'message' => 'Please upload a valid image file (jpg, png, jpeg).']);
    exit();
}

// Check file size (limit to 500KB)
if ($img_size > 500000) {
    logActivity("Validation failed - Image file too large: {$img_size} bytes");
    echo json_encode(['success' => false, 'message' => 'Sorry, your file is too large. Max 500KB allowed.']);
    exit();
}

// Generate a unique hash of the file's contents to check for duplicates
$file_hash = md5_file($tmp_name);
$file_name = $file_hash . '.' . $img_ext;
$upload_dir = 'admin_photos/';
$upload_file = $upload_dir . $file_name;

// Check if the file already exists in the directory
if (file_exists($upload_file)) {
    logActivity("Duplicate detected - Image file already exists in storage");
    echo json_encode(['success' => false, 'message' => 'This image has already been uploaded by another user.']);
    exit();
}

if (!move_uploaded_file($tmp_name, $upload_file)) {
    logActivity("Operation failed - Could not move uploaded file to destination");
    echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
    exit();
}

logActivity("File successfully uploaded: {$upload_file}");

// Insert into the database
$ran_id = rand(time(), 100000000);
$encrypt_pass = password_hash($inputs['password'], PASSWORD_DEFAULT);
$encrypt_secret_answer = password_hash($inputs['secret_answer'], PASSWORD_DEFAULT);
$status = "Active now";

$insert_query = $conn->prepare("INSERT INTO admin_tbl (unique_id, firstname, lastname, email, phone, address, gender, password, secret_question, secret_answer, photo, role, onboarded_by, last_updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?)");
$insert_query->bind_param('isssssssssssii', $ran_id, $inputs['firstname'], $inputs['lastname'], $inputs['email'], $inputs['phone'], $inputs['address'], $inputs['gender'], $encrypt_pass, $inputs['secret_question'], $encrypt_secret_answer, $file_name, $inputs['role'], $logged_in_user, $logged_in_user);

if ($insert_query->execute()) {
    logActivity("Success - New staff onboarded with ID: {$ran_id}");
    echo json_encode(['success' => true, 'message' => 'New Staff has been successfully Onboarded!']);
} else {
    logActivity("Database error - Failed to insert record: " . $insert_query->error);
    echo json_encode(['success' => false, 'message' => 'Failed to add new Staff. Database error.']);
}

$insert_query->close();
$conn->close();
logActivity("Script execution completed");