<?php
include 'config.php';
session_start();

header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staffID = $data['staffID'] ?? null;
    $unblockType = $data['unblockType'] ?? null;

    // Determine column to update based on unblockType
    $columnToUpdate = ($unblockType === 'unrestrict') ? 'restriction_id' : 'block_id';

    // Check current restriction_id and block_id
    $stmt = $conn->prepare("SELECT restriction_id, block_id FROM admin_tbl WHERE unique_id = ?");
    $stmt->bind_param("i", $staffID);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($currentRestrictionId, $currentBlockId);

    if ($stmt->num_rows > 0) {
        $stmt->fetch();

        // Check if there's already no restriction/block
        if ($columnToUpdate == 'restriction_id' && $currentRestrictionId == 0) {
            echo json_encode(["success" => false, "message" => "There is no restriction on this account."]);
            exit();
        } else if ($columnToUpdate == 'block_id' && $currentBlockId == 0) {
            echo json_encode(["success" => false, "message" => "There is no block on this account."]);
            exit();
        }

        // Update the restriction_id or block_id column
        $updateStmt = $conn->prepare("UPDATE admin_tbl SET $columnToUpdate = 0 WHERE unique_id = ?");
        $updateStmt->bind_param("i", $staffID);
        if ($updateStmt->execute()) {
            echo json_encode(["success" => true, "message" => "Account successfully updated."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update account. Please try again."]);
        }
        $updateStmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "No matching account found."]);
    }
    $stmt->close();
}
