<?php
header('Content-Type: application/json');

// Include database connection file
include('../config.php');

try {
    // Retrieve form data
    $first_name = $_POST['add_firstname'] ?? '';
    $last_name = $_POST['add_lastname'] ?? '';
    $email = $_POST['add_email'] ?? '';
    $phone_number = $_POST['add_phone_number'] ?? '';
    $gender = $_POST['add_gender'] ?? '';
    $license_number = $_POST['add_license_number'] ?? '';
    $vehicle_type = $_POST['add_vehicle_type'] ?? '';
    $address = $_POST['add_address'] ?? '';
    $password = $_POST['add_password'] ?? '';
    $secret_question = $_POST['add_secret_question'] ?? '';
    $secret_answer = $_POST['add_secret_answer'] ?? '';
    $photo = $_FILES['add_photo']['name'] ?? '';
    $restriction = 0; // Default to 0 (no restriction)
    $status = "Available";
    $minLength = 8;
    $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/\d/', $password);

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone_number) || empty($license_number) || empty($password) || empty($secret_question) || empty($secret_answer)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Password validation
    if (((strlen($password)) < $minLength) || (!$hasSpecialChar) || (!$hasUpperCase) || (!$hasDigit)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Password. Password must contain digits, special characters, and alphabets.']);
        exit();
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
        exit();
    }
    if (!preg_match('/^\d{11}$/', $phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
        exit();
    }

    // Check for duplicates
    $duplicateCheckSql = "SELECT COUNT(*) AS count FROM driver WHERE email = ? OR phone_number = ? OR license_number = ?";
    $stmt = $conn->prepare($duplicateCheckSql);
    $stmt->bind_param('sss', $email, $phone_number, $license_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Email, Phone Number, or License Number already exists.']);
        exit;
    }

    // Photo validation
    if (!empty($photo)) {
        $target_dir = "driver_photos/";
        $target_file = $target_dir . basename($photo);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is an actual image or fake image
        $check = getimagesize($_FILES["add_photo"]["tmp_name"]);
        if ($check === false) {
            echo json_encode(['success' => false, 'message' => 'File is not an image.']);
            exit;
        }

        // Check file size (limit to 2MB)
        if ($_FILES["add_photo"]["size"] > 2000000) {
            echo json_encode(['success' => false, 'message' => 'Sorry, your file is too large.']);
            exit;
        }

        // Allow certain file formats
        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            echo json_encode(['success' => false, 'message' => 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.']);
            exit;
        }

        // Check if file already exists in the target directory
        if (file_exists($target_file)) {
            echo json_encode(['success' => false, 'message' => 'Sorry, file already exists.']);
            exit;
        }
    }

    // All validations passed, proceed to insert the record into the database
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $hashed_secret_answer = md5($secret_answer);

    $stmt = $conn->prepare("INSERT INTO driver (firstname, lastname, gender, email, phone_number, address, license_number, vehicle_type, password, secret_question, secret_answer, photo, date_created, date_updated, status, restriction) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)");
    $stmt->bind_param("ssssssssssssss", $first_name, $last_name, $gender, $email, $phone_number, $address, $license_number, $vehicle_type, $hashed_password, $secret_question, $hashed_secret_answer, $photo, $status, $restriction);

    if ($stmt->execute()) {
        // If insert was successful, upload the photo
        if (!empty($photo)) {
            if (move_uploaded_file($_FILES["add_photo"]["tmp_name"], $target_file)) {
                echo json_encode(['success' => true, 'message' => 'Driver has been successfully onboarded and photo uploaded.']);
            } else {
                // If the photo upload fails, rollback the database insertion
                $stmt = $conn->prepare("DELETE FROM driver WHERE id = ?");
                $stmt->bind_param("i", $conn->insert_id);
                $stmt->execute();
                echo json_encode(['success' => false, 'message' => 'Driver record was inserted but photo upload failed. Record has been removed.']);
            }
        } else {
            echo json_encode(['success' => true, 'message' => 'Driver has been successfully onboarded without photo.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Exception occurred: ' . $e->getMessage()]);
}
