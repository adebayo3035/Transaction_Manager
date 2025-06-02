<?php
include 'config.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access Denied! No session found.");
    echo json_encode(["success" => false, "message" => "Access Denied! Kindly login first."]);
    exit();
}

$role = $_SESSION['role'];
if ($role !== "Super Admin") {
    logActivity("Access Denied! User with role '$role' tried to access restricted endpoint.");
    echo json_encode(["success" => false, "message" => "Access Denied! Permission not granted."]);
    exit();
}

// === Parse input ===
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffID = $data['staffID'] ?? null;
    $unblockType = $data['unblockType'] ?? null;

    logActivity("POST request received to update admin_tbl for staffID: {$staffID}, unblockType: {$unblockType}");

    // === Validate inputs ===
    if (!is_numeric($staffID)) {
        logActivity("Invalid staff ID provided: {$staffID}");
        echo json_encode(["success" => false, "message" => "Invalid staff ID."]);
        exit();
    }

    $allowedTypes = ['unrestrict', 'unblock'];
    if (!in_array($unblockType, $allowedTypes)) {
        logActivity("Invalid unblockType value: {$unblockType}");
        echo json_encode(["success" => false, "message" => "Invalid unblock type."]);
        exit();
    }

    $columnToUpdate = $unblockType === 'unrestrict' ? 'restriction_id' : 'block_id';

    // === Fetch current block/restriction state ===
    $stmt = $conn->prepare("SELECT restriction_id, block_id FROM admin_tbl WHERE unique_id = ?");
    $stmt->bind_param("i", $staffID);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($currentRestrictionId, $currentBlockId);

    if ($stmt->num_rows > 0) {
        $stmt->fetch();

        if ($columnToUpdate === 'restriction_id' && $currentRestrictionId == 0) {
            logActivity("No restriction found for staffID: {$staffID}");
            echo json_encode(["success" => false, "message" => "There is no restriction on this account."]);
            exit();
        } elseif ($columnToUpdate === 'block_id' && $currentBlockId == 0) {
            logActivity("No block found for staffID: {$staffID}");
            echo json_encode(["success" => false, "message" => "There is no block on this account."]);
            exit();
        }

        // === Update the status ===
        $updateSql = "UPDATE admin_tbl SET {$columnToUpdate} = 0 WHERE unique_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $staffID);
        if ($updateStmt->execute()) {
            logActivity("Successfully updated {$columnToUpdate} for staffID: {$staffID}");
            echo json_encode(["success" => true, "message" => "Account successfully updated."]);
        } else {
            logActivity("Failed to update {$columnToUpdate} for staffID: {$staffID}");
            echo json_encode(["success" => false, "message" => "Failed to update account. Please try again."]);
        }
        $updateStmt->close();
    } else {
        logActivity("No matching account found for staffID: {$staffID}");
        echo json_encode(["success" => false, "message" => "No matching account found."]);
    }

    $stmt->close();
    $conn->close();
} else {
    logActivity("Invalid request method attempted on unblock endpoint.");
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}
