<?php
header('Content-Type: application/json');
include_once "config.php";

session_start();
if (!isset($_SESSION['unique_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}
$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];
if ($loggedInUserRole !== "Super Admin") {
    echo json_encode(["success" => false, "message" => "Access Denied. Permission not granted!"]);
    exit();
}

// Retrieve and sanitize inputs
$firstname = $_POST['add_firstname'];
$lastname = $_POST['add_lastname'];
$email = $_POST['add_email'];
$phone = $_POST['add_phone_number'];
$gender = $_POST['add_gender'];
$address = $_POST['add_address'];
$password = $_POST['add_password'];
$secret_question = $_POST['add_secret_question'];
$secret_answer = $_POST['add_secret_answer'];
$role = $_POST['add_role'];

// REGEX TO VALIDATE PASSWORD AND SECRET ANSWER
$minLength = 8;
$hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
$hasUpperCase = preg_match('/[A-Z]/', $password);
$hasDigit = preg_match('/\d/', $password);

// Validate phone number
if (!preg_match('/^\d{11}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
    exit();
}

// Check if all fields are filled
if (!empty($firstname) && !empty($lastname) && !empty($email) && !empty($phone) && !empty($password) && !empty($secret_question) && !empty($secret_answer) && !empty($role) && !empty($gender) && !empty($address)) {

    // Validate password strength
    if (strlen($password) < $minLength || !$hasSpecialChar || !$hasUpperCase || !$hasDigit) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
        exit();
    }

    // Validate email
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

        // Check for duplicate email
        $sql_email = $conn->prepare("SELECT * FROM admin_tbl WHERE email = ?");
        $sql_email->bind_param('s', $email);
        $sql_email->execute();
        $sql_email->store_result();
        if ($sql_email->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => "Email already exists. Please use a different email."]);
            exit();
        }

        // Check for duplicate phone number
        $sql_phone = $conn->prepare("SELECT * FROM admin_tbl WHERE phone = ?");
        $sql_phone->bind_param('s', $phone);
        $sql_phone->execute();
        $sql_phone->store_result();
        if ($sql_phone->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => "Phone Number already exists. Please use a different phone number."]);
            exit();
        }

        // Handle file upload
        if (isset($_FILES['photo'])) {
            $img_name = $_FILES['photo']['name'];
            $img_type = $_FILES['photo']['type'];
            $tmp_name = $_FILES['photo']['tmp_name'];
            $img_explode = explode('.', $img_name);
            $img_ext = end($img_explode);
            $extensions = ["jpeg", "png", "jpg"];
            if (in_array($img_ext, $extensions) === true) {
                $types = ["image/jpeg", "image/jpg", "image/png"];
                if (in_array($img_type, $types) === true) {

                    // Specify Upload Directory
                    $upload_dir = 'admin_photos/';

                    // Generate a unique hash of the file's contents to check for duplicates
                    $file_hash = md5_file($tmp_name);
                    $file_name = $file_hash . '.' . $img_ext;
                    $upload_file = $upload_dir . $file_name;

                    // Check if the file already exists in the directory
                    if (file_exists($upload_file)) {
                        echo json_encode(['success' => false, 'message' => 'This image has already been uploaded by another user.']);
                        exit;
                    }
                    // Check file size (limit to 500KB)
                    if ($_FILES["add_photo"]["size"] > 500000) {
                        echo json_encode(['success' => false, 'message' => 'Sorry, your file is too large.']);
                        exit;
                    }
                    if (move_uploaded_file($tmp_name, "admin_photos/" . $file_name)) {

                        // Insert into the database
                        $ran_id = rand(time(), 100000000);
                        $encrypt_pass = md5($password);
                        $encrypt_secret_answer = md5($secret_answer);
                        $status = "Active now";

                        // Prepared statement for insert
                        $insert_query = $conn->prepare("INSERT INTO admin_tbl (unique_id, firstname, lastname, email, phone, address, gender, password, secret_question, secret_answer, photo, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert_query->bind_param('isssssssssss', $ran_id, $firstname, $lastname, $email, $phone, $address, $gender, $encrypt_pass, $secret_question, $encrypt_secret_answer, $file_name, $role);

                        if ($insert_query->execute()) {
                            echo json_encode(['success' => true, 'message' => 'New Staff has been successfully Onboarded!']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to add new Staff.']);
                        }

                        $insert_query->close();
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Please upload a valid image file (jpg, png, jpeg).']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid image extension.']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please fill all input fields.']);
}

$conn->close();

