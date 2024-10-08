<?php
header('Content-Type: application/json');
include 'config.php';
session_start();
$data = json_decode(file_get_contents('php://input'), true);

// Check if the user is a Super Admin
if ($_SESSION['role'] !== 'Super Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized! Contact your Admin.']);
    exit();
}
$adminID = $_SESSION['unique_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($data['userID'])) {
        $user_id = $data['userID'];
        $account_type = $data['accountType'];
        $max_attempts = 3;
        handleAccountUnlock($conn, $max_attempts, $user_id, $adminID, $account_type);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Access Denied! Invalid method.']);
    exit();
}

function handleAccountUnlock($conn, $max_attempts, $username, $adminID, $account_type)
{
    if ($account_type === "Staff") {
        // Step 1: Fetch the user's unique_id based on either email, phone, or unique_id
        $stmt = $conn->prepare("SELECT unique_id FROM admin_tbl WHERE email = ? OR phone = ? OR unique_id = ?");
        $stmt->bind_param("ssi", $username, $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $unique_id = $row['unique_id'];  // Get the user's unique ID

            // Check if an entry exists for this unique_id in login attempts
            $stmtCheckAttempts = $conn->prepare("SELECT attempts FROM admin_login_attempts WHERE unique_id = ?");
            $stmtCheckAttempts->bind_param("i", $unique_id);
            $stmtCheckAttempts->execute();
            $resultCheckAttempts = $stmtCheckAttempts->get_result();

            if ($resultCheckAttempts->num_rows > 0) {
                $row = $resultCheckAttempts->fetch_assoc();
                $attempts = $row['attempts'];

                if ($attempts >= $max_attempts) {
                    // Reset attempts and unblock the account
                    $stmtResetAttempts = $conn->prepare("DELETE FROM admin_login_attempts WHERE unique_id = ?");
                    $stmtResetAttempts->bind_param("i", $unique_id);


                    // Update block_id = 0 to unblock the account
                    $block_id = 0;
                    $stmtUnlock = $conn->prepare("UPDATE admin_tbl SET block_id = ? WHERE unique_id = ?");
                    $stmtUnlock->bind_param("ii", $block_id, $unique_id);


                    $status = 'unlocked'; // New status to indicate the account is unlocked
                    $unlock_method = 'Manual unlock by Super Admin'; // Method used to unlock the account

                    $stmtUnlockHistory = $conn->prepare("UPDATE admin_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE unique_id = ? AND status = 'locked'");
                    $stmtUnlockHistory->bind_param("sisi", $status, $adminID, $unlock_method, $unique_id);
                    $stmtResetAttempts->execute();
                    $stmtUnlock->execute();
                    $stmtUnlockHistory->execute();
                    echo json_encode(["success" => true, "message" => "Staff account has been unlocked successfully."]);
                } else {
                    $attempts_left = $max_attempts - $attempts;
                    $attempts_message = ($attempts_left === 1)
                        ? "You still have 1 more attempt."
                        : "You still have $attempts_left more attempts.";

                    echo json_encode(["success" => false, "message" => $attempts_message]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "No record found for this account."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Staff ID does not exist."]);
        }
        exit();
    } else if ($account_type === "Customer") {
        // Step 1: Fetch the user's unique_id based on either email, phone, or unique_id
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ? OR mobile_number = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $customer_id = $row['customer_id'];  // Get the user's unique ID

            // Check if an entry exists for this unique_id in login attempts
            $stmtCheckAttempts = $conn->prepare("SELECT attempts FROM customer_login_attempts WHERE customer_id = ?");
            $stmtCheckAttempts->bind_param("i", $customer_id);
            $stmtCheckAttempts->execute();
            $resultCheckAttempts = $stmtCheckAttempts->get_result();

            if ($resultCheckAttempts->num_rows > 0) {
                $row = $resultCheckAttempts->fetch_assoc();
                $attempts = $row['attempts'];

                if ($attempts >= $max_attempts) {
                    // Reset attempts and unblock the account
                    $stmtResetAttempts = $conn->prepare("DELETE FROM customer_login_attempts WHERE customer_id = ?");
                    $stmtResetAttempts->bind_param("i", $customer_id);


                    $status = 'unlocked'; // New status to indicate the account is unlocked
                    $unlock_method = 'Manual unlock by Super Admin'; // Method used to unlock the account

                    $stmtUnlockHistory = $conn->prepare("UPDATE customer_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE customer_id = ? AND status = 'locked'");
                    $stmtUnlockHistory->bind_param("sisi", $status, $adminID, $unlock_method, $customer_id);
                    $stmtResetAttempts->execute();
                    $stmtUnlockHistory->execute();
                    echo json_encode(["success" => true, "message" => "Customer account has been unlocked successfully."]);
                } else {
                    $attempts_left = $max_attempts - $attempts;
                    $attempts_message = ($attempts_left === 1)
                        ? "You still have 1 more attempt."
                        : "You still have $attempts_left more attempts.";

                    echo json_encode(["success" => false, "message" => $attempts_message]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "No record found for this account."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Staff ID does not exist."]);
        }
        exit();
    } else if ($account_type === "Driver") {
        // Step 1: Fetch the user's unique_id based on either email, phone, or unique_id
        $stmt = $conn->prepare("SELECT id FROM driver WHERE email = ? OR phone_number = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $driver_id = $row['id'];  // Get the user's unique ID

            // Check if an entry exists for this unique_id in login attempts
            $stmtCheckAttempts = $conn->prepare("SELECT attempts FROM driver_login_attempts WHERE driver_id = ?");
            $stmtCheckAttempts->bind_param("i", $driver_id);
            $stmtCheckAttempts->execute();
            $resultCheckAttempts = $stmtCheckAttempts->get_result();

            if ($resultCheckAttempts->num_rows > 0) {
                $row = $resultCheckAttempts->fetch_assoc();
                $attempts = $row['attempts'];

                if ($attempts >= $max_attempts) {
                    // Reset attempts and unblock the account
                    $stmtResetAttempts = $conn->prepare("DELETE FROM driver_login_attempts WHERE driver_id = ?");
                    $stmtResetAttempts->bind_param("i", $driver_id);

                    $status = 'unlocked'; // New status to indicate the account is unlocked
                    $unlock_method = 'Manual unlock by Super Admin'; // Method used to unlock the account

                    $stmtUnlockHistory = $conn->prepare("UPDATE driver_lock_history SET status = ?, unlocked_by = ?, unlock_method = ?, unlocked_at = NOW() WHERE driver_id = ? AND status = 'locked'");
                    $stmtUnlockHistory->bind_param("sisi", $status, $adminID, $unlock_method, $driver_id);
                    $stmtResetAttempts->execute();

                    $stmtUnlockHistory->execute();
                    echo json_encode(["success" => true, "message" => "Driver's account has been unlocked successfully."]);
                } else {
                    $attempts_left = $max_attempts - $attempts;
                    $attempts_message = ($attempts_left === 1)
                        ? "You still have 1 more attempt."
                        : "You still have $attempts_left more attempts.";

                    echo json_encode(["success" => false, "message" => $attempts_message]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "No record found for this account."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Staff ID does not exist."]);
        }
        exit();
    }
    else{
        echo json_encode(["success" => false, "message" => "Invalid account Type."]);
        exit();
    }

}