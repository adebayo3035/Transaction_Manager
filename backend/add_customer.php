<?php
// Include the database connection file
include 'config.php';

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form inputs from the FormData object
    $first_name = $_POST['add_firstname'];
    $last_name = $_POST['add_lastname'];
    $email = $_POST['add_email'];
    $phone_number = $_POST['add_phone_number'];
    $gender = $_POST['add_gender'];
    $address = $_POST['add_address'];
    $password = $_POST['add_password'];
    $encrypted_password = md5($password);
    $secret_question = $_POST['add_secret_question'];
    $secret_answer = $_POST['add_secret_answer'];
    $encrypted_answer = md5($secret_answer);
    $group_id = $_POST['group'];
    $unit_id = $_POST['unit'];

    // Declare validation variables
    $minLength = 8;
    $hasSpecialChar = preg_match('/[!@#$%^&*(),.?":{}|<>_]/', $password);
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/\d/', $password);

    // Check if any required field is empty
    if (
        empty($first_name) || empty($last_name) || empty($email) || empty($phone_number) || empty($gender) ||
        empty($address) || empty($password) || empty($secret_question) || empty($secret_answer) ||
        empty($group_id) || empty($unit_id)
    ) {
        $response['message'] = 'Please fill in all required fields.';
        echo json_encode($response);
        exit();
    }
    // Validate password strength
    if (strlen($password) < $minLength || !$hasSpecialChar || !$hasUpperCase || !$hasDigit) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid password.']);
        exit();
    }

    // Validate phone number
    if (!preg_match('/^\d{11}$/', $phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = "Invalid E-mail Address.";
        echo json_encode($response);
        exit();
    }

    // Check if email already exists
    $sql_email = $conn->prepare("SELECT * FROM customers WHERE email = ?");
    $sql_email->bind_param('s', $email);
    $sql_email->execute();
    $sql_email_result = $sql_email->get_result();

    if ($sql_email_result->num_rows > 0) {
        $response['message'] = "{$email} already exists. Please use a different email.";
        echo json_encode($response);
        exit();
    }
    $sql_email->close();

    // Check if phone number already exists
    $sql_phone = $conn->prepare("SELECT * FROM customers WHERE mobile_number = ?");
    $sql_phone->bind_param('s', $phone_number);
    $sql_phone->execute();
    $sql_phone_result = $sql_phone->get_result();

    if ($sql_phone_result->num_rows > 0) {
        $response['message'] = "{$phone_number} already exists. Please use a different phone number.";
        echo json_encode($response);
        exit();
    }
    $sql_phone->close();

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
            $time = time(); // Use current time as a unique identifier for the image
            $new_img_name = $time . '_' . $img_name;
            $upload_path = "customer_photos/" . $new_img_name;

            // Check if the image already exists in the directory
            if (file_exists($upload_path)) {
                $response['message'] = 'Image already exists.';
                echo json_encode($response);
                exit();
            } else {
                // Start a transaction
                $conn->begin_transaction();

                try {
                    // Upload image to a specific folder
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        // Generate a unique customer ID
                        $customer_id = rand(time(), 100000000);
                        $status = "Active now";

                        // Insert customer data into the database using a prepared statement
                        $sql = "INSERT INTO customers (customer_id, firstname, lastname, gender, email, password, mobile_number, address, secret_question, secret_answer, photo, group_id, unit_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('issssssssssii', $customer_id, $first_name, $last_name, $gender, $email, $encrypted_password, $phone_number, $address, $secret_question, $encrypted_answer, $new_img_name, $group_id, $unit_id);

                        if ($stmt->execute()) {
                            // Commit the transaction
                            $conn->commit();
                            $response['success'] = true;
                            $response['message'] = 'Customer successfully Onboarded.';
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
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
$conn->close();
