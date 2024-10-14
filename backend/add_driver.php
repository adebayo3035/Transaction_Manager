<?php
header('Content-Type: application/json');

// Include database connection file
include('config.php');

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
    // $photo = $_FILES['add_photo']['name'] ?? '';
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
    $stmt->close();
    // Handle photo upload
    if (isset($_FILES['add_photo'])) {
        $img_name = $_FILES['add_photo']['name'];
        $img_type = $_FILES['add_photo']['type'];
        $tmp_name = $_FILES['add_photo']['tmp_name'];

        // Split the file name to get the extension
        $img_explode = explode('.', $img_name);
        $img_ext = end($img_explode);

        // Allowed extensions
        $allowed_extensions = ["jpeg", "png", "jpg"];
        $allowed_types = ["image/jpeg", "image/jpg", "image/png"];

        // Validate the image file
        if (in_array($img_ext, $allowed_extensions) && in_array($img_type, $allowed_types)) {
            // Specify Upload Directory
            $upload_dir = 'driver_photos/';

            // Generate a unique hash of the file's contents to check for duplicates
            $file_hash = md5_file($tmp_name);
            $file_name = $file_hash . '.' . $img_ext;
            $upload_file = $upload_dir . $file_name;

            // Check if the file already exists in the directory
            if (file_exists($upload_file)) {
                echo json_encode(['success' => false, 'message' => 'This image has already been uploaded by another user.']);
                exit;
            } else {
                // Start a transaction
                $conn->begin_transaction();

                try {
                    // Upload image to a specific folder
                    if (move_uploaded_file($tmp_name, "driver_photos/" . $file_name)) {
                        // Check file size (limit to 500KB)
                        if ($_FILES["add_photo"]["size"] > 500000) {
                            echo json_encode(['success' => false, 'message' => 'Sorry, your file is too large.']);
                            exit;
                        }

                        // All validations passed, proceed to insert the record into the database
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $hashed_secret_answer = md5($secret_answer);

                        // Insert Staff data into the database using a prepared statement
                        $stmt = $conn->prepare("INSERT INTO driver (firstname, lastname, gender, email, phone_number, address, license_number, vehicle_type, password, secret_question, secret_answer, photo, date_created, date_updated, status, restriction) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)");
                        $stmt->bind_param("ssssssssssssss", $first_name, $last_name, $gender, $email, $phone_number, $address, $license_number, $vehicle_type, $hashed_password, $secret_question, $hashed_secret_answer, $file_name, $status, $restriction);

                        if ($stmt->execute()) {
                            // Commit the transaction
                            $conn->commit();
                            $response['success'] = true;
                            $response['message'] = 'Driver has been successfully Onboarded.';
                        } else {
                            throw new Exception('Database error: ' . $conn->error);
                        }

                        $stmt->close();
                    } else {
                        throw new Exception('Failed to upload the image.');
                    }
                } catch (Exception $e) {
                    // Rollback the transaction if an error occurs
                    $conn->rollback();

                    // Remove the uploaded file if it was uploaded but not inserted into the database
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }

                    $response['message'] = $e->getMessage();
                }
            }
        } else {
            $response['message'] = 'Invalid image file type. Only JPEG, PNG, and JPG are allowed.';
        }
    } else {
        $response['message'] = 'Please upload a photo.';
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Exception occurred: ' . $e->getMessage()]);
}
echo json_encode($response);
$conn->close();