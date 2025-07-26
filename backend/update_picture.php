<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include 'config.php';
include 'auth_utils.php';
session_start();
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['unique_id'])) {
    logActivity("null, Profile Photo Update', 'Attempted profile update without login.");
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$admin_id = (int) $_SESSION['unique_id'];

// Fetch current profile picture and secret answer
$sql = "SELECT photo, secret_answer FROM admin_tbl WHERE unique_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$stmt->bind_result($current_photo, $secret_answer);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['photo']) || !isset($_POST['secret_answer'])) {
        logActivity("$admin_id, Profile Photo Update, Missing photo or secret answer.");
        echo json_encode(['success' => false, 'message' => 'Secret answer or file not provided.']);
        exit;
    }

    $user_provided_secret_answer = $_POST['secret_answer'];
    // if (md5($user_provided_secret_answer) !== $secret_answer) {
    //     logActivity("$admin_id, 'Profile Photo Update, Failed secret answer validation.");
    //     echo json_encode(['success' => false, 'message' => 'Secret Answer Validation Failed.']);
    //     exit;
    // }
    if (!verifyAndUpgradeSecretAnswer($conn, $admin_id, $user_provided_secret_answer, $secret_answer)) {
        logActivity("$admin_id, 'Profile Photo Update, Failed secret answer validation.");
        echo json_encode(['success' => false, 'message' => 'Secret Answer Validation Failed.']);
        exit;
    }

    logActivity("$admin_id, 'Profile Photo Update, Secret answer validated.");

    $img_name = $_FILES['photo']['name'];
    $img_type = $_FILES['photo']['type'];
    $tmp_name = $_FILES['photo']['tmp_name'];
    $img_size = $_FILES['photo']['size'];
    $img_error = $_FILES['photo']['error'];

    $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
    $valid_exts = ["jpeg", "png", "jpg"];
    $valid_types = ["image/jpeg", "image/jpg", "image/png"];

    if (!in_array($img_ext, $valid_exts) || !in_array($img_type, $valid_types)) {
        logActivity("$admin_id, 'Profile Photo Update, Unsupported image format: . $img_type");
        echo json_encode(['success' => false, 'message' => 'Unsupported image format.']);
        exit;
    }

    if ($img_size > 500000) {
        logActivity("$admin_id, 'Profile Photo Update, Image size too large: . $img_size");
        echo json_encode(['success' => false, 'message' => 'Image size is too large.']);
        exit;
    }

    if ($img_error !== 0) {
        logActivity("$admin_id, 'Profile Photo Update, File upload error: ' . $img_error");
        echo json_encode(['success' => false, 'message' => 'File upload error. Code: ' . $img_error]);
        exit;
    }

    $upload_dir = 'admin_photos/';
    $file_hash = md5_file($tmp_name);
    $file_name = $file_hash . '.' . $img_ext;
    $upload_path = $upload_dir . $file_name;

    if (file_exists($upload_path)) {
        logActivity("$admin_id, 'Profile Photo Update, Duplicate image upload attempt: . $file_name");
        echo json_encode(['success' => false, 'message' => 'This image has already been uploaded by another user.']);
        exit;
    }

    if (move_uploaded_file($tmp_name, $upload_path)) {
        $sql = "UPDATE admin_tbl SET photo = ? WHERE unique_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $file_name, $admin_id);

        if ($stmt->execute()) {
            if (!empty($current_photo) && $current_photo !== $file_name) {
                $old_file = $upload_dir . $current_photo;
                if (file_exists($old_file)) {
                    if (unlink($old_file)) {
                        logActivity("$admin_id, 'Profile Photo Update, Old photo deleted: . $current_photo");
                    } else {
                        logActivity("$admin_id, 'Profile Photo Update, Failed to delete old photo: ' . $current_photo");
                    }
                }
            }

            logActivity("$admin_id, 'Profile Photo Update, Profile photo updated successfully to: ' . $file_name");
            $response = ['success' => true, 'file' => $file_name];
        } else {
            logActivity("$admin_id, 'Profile Photo Update, Database update failed.");
            $response['message'] = 'Failed to update profile picture.';
        }

        $stmt->close();
    } else {
        logActivity("$admin_id, 'Profile Photo Update', 'Failed to move uploaded file.");
        $response['message'] = 'Failed to move uploaded file.';
    }
} else {
    logActivity("$admin_id, Profile Photo Update, Invalid request method: ");
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
exit;
