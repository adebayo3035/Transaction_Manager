<?php
include 'config.php';
session_start();

header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = $data['staffID'] ?? null; 
    $restrictionType = $data['restrictionType'] ?? null;

    // Determine column to update based on restriction type
    if ($restrictionType !== 'restrict' && $restrictionType !== 'block') {
        echo json_encode(["success" => false, "message" => "Please Select a Valid Lien Type."]);
        exit();
    } 
    
    $columnToUpdate = ($restrictionType === 'restrict') ? 'restriction_id' : 'block_id';
    
    // Fetch unique_id and role using the identifier (unique_id, email, or phone number)
    $stmt = $conn->prepare("SELECT unique_id, role FROM admin_tbl WHERE unique_id = ? OR email = ? OR phone = ?");
    $stmt->bind_param("iss", $identifier, $identifier, $identifier);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($unique_id, $currentRole);

    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        $uniqueId = $unique_id;
        
        // Check if user is a Super Admin
        if ($currentRole == "Super Admin") {
            echo json_encode(["success" => false, "message" => "Super Admin Account cannot be restricted or blocked."]);
            exit();
        }

        // Check current restriction_id and block_id
        $stmt = $conn->prepare("SELECT restriction_id, block_id FROM admin_tbl WHERE unique_id = ? OR email = ? OR phone = ?");
        $stmt->bind_param("iss", $uniqueId, $uniqueId, $uniqueId);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($currentRestrictionId, $currentBlockId);

        if ($stmt->num_rows > 0) {
            $stmt->fetch();

            // Validate if there is an existing restriction or block
            if ($columnToUpdate == 'restriction_id' && $currentRestrictionId == 1) {
                echo json_encode(["success" => false, "message" => "There is an existing restriction on this account."]);
                exit();
            } else if ($columnToUpdate == 'block_id' && $currentBlockId == 1) {
                echo json_encode(["success" => false, "message" => "There is an existing block on this account."]);
                exit();
            }

            $stmt->close();

            // Update the restriction_id or block_id column
            $updateStmt = $conn->prepare("UPDATE admin_tbl SET $columnToUpdate = 1 WHERE unique_id = ?");
            $updateStmt->bind_param("i", $uniqueId);
            if ($updateStmt->execute()) {
                echo json_encode(["success" => true, "message" => "Account successfully updated."]);
            } else {
                echo json_encode(["success" => false, "message" => "Failed to update account. Please try again."]);
            }
            $updateStmt->close();
        } else {
            echo json_encode(["success" => false, "message" => "No matching account found."]);
        }
        $conn->close();
    } else {
        echo json_encode(["success" => false, "message" => "Account Not Found."]);
    }
}
