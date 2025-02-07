<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php';

// Logging function
logActivity("Script execution started.");

$customer_id = $_SESSION['customer_id'];
checkSession($customer_id);
logActivity("Session validated successfully for Customer ID: $customer_id.");

$response = ['success' => false, 'message' => ''];

// Fetch current profile picture and secret answer
$sql = "SELECT photo, secret_answer FROM customers WHERE customer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$stmt->bind_result($current_photo, $secret_answer);
$stmt->fetch();
$stmt->close();
logActivity("Fetched customer profile details for Customer ID: $customer_id.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['photo']) && isset($_POST['secret_answer'])) {
        $user_provided_secret_answer = $_POST['secret_answer'];
        $hashed_user_provided_secret_answer = md5($user_provided_secret_answer);

        logActivity("Validating secret answer for Customer ID: $customer_id.");
        if ($hashed_user_provided_secret_answer !== $secret_answer) {
            logActivity("Secret answer validation failed for Customer ID: $customer_id.");
            echo json_encode(['success' => false, 'message' => 'Secret Answer Validation Failed.']);
            exit;
        }
        logActivity("Secret answer validated successfully.");

        $img_name = $_FILES['photo']['name'];
        $img_type = $_FILES['photo']['type'];
        $tmp_name = $_FILES['photo']['tmp_name'];
        logActivity("Processing uploaded file: $img_name.");

        $img_explode = explode('.', $img_name);
        $img_ext = end($img_explode);

        $extensions = ["jpeg", "png", "jpg"];
        if (in_array($img_ext, $extensions)) {
            $types = ["image/jpeg", "image/jpg", "image/png"];
            if (in_array($img_type, $types)) {
                if ($_FILES["photo"]["size"] > 500000) {
                    logActivity("Image size too large for Customer ID: $customer_id.");
                    echo json_encode(['success' => false, 'message' => 'Image size is too large.']);
                    exit;
                }

                $upload_dir = '../../backend/customer_photos/';
                $file_hash = md5_file($tmp_name);
                $file_name = $file_hash . '.' . $img_ext;
                $upload_file = $upload_dir . $file_name;
                logActivity("Generated unique filename: $file_name.");

                if (file_exists($upload_file)) {
                    logActivity("Duplicate image detected. Upload aborted for Customer ID: $customer_id.");
                    echo json_encode(['success' => false, 'message' => 'This image has already been uploaded by another user.']);
                    exit;
                }

                if (move_uploaded_file($tmp_name, $upload_file)) {
                    logActivity("File uploaded successfully for Customer ID: $customer_id.");

                    $sql = "UPDATE customers SET photo = ? WHERE customer_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('si', $file_name, $customer_id);

                    if ($stmt->execute()) {
                        logActivity("Profile picture updated in database for Customer ID: $customer_id.");

                        $oldPicturePath = $upload_dir . $current_photo;
                        if ($current_photo && file_exists($oldPicturePath)) {
                            unlink($oldPicturePath);
                            logActivity("Old profile picture deleted for Customer ID: $customer_id.");
                        }

                        $response['success'] = true;
                        $response['file'] = $file_name;
                        $response['message'] = 'Profile picture updated successfully.';
                    } else {
                        logActivity("Failed to update profile picture in database for Customer ID: $customer_id.");
                        $response['message'] = 'Failed to update profile picture.';
                    }

                    $stmt->close();
                } else {
                    logActivity("Failed to move uploaded file for Customer ID: $customer_id.");
                    $response['message'] = 'Failed to move uploaded file.';
                }
            } else {
                logActivity("Invalid file type uploaded by Customer ID: $customer_id.");
                $response['message'] = 'Uploaded file is not a valid image.';
            }
        } else {
            logActivity("Invalid file extension uploaded by Customer ID: $customer_id.");
            $response['message'] = 'Invalid file extension.';
        }
    } else {
        logActivity("Secret answer or file not provided by Customer ID: $customer_id.");
        $response['message'] = 'Secret answer or file not provided.';
    }
} else {
    logActivity("Invalid request method used.");
    $response['message'] = 'Invalid request method.';
}

$conn->close();
logActivity("Script execution completed for Customer ID: $customer_id.");
echo json_encode($response);
exit;
