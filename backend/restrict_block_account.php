<?php
include 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
    $restrictionType = mysqli_real_escape_string($conn, $_POST['restrictionType']);

    // Determine column to update based on restriction type
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
        // Check if returned User is a Super Admin terminate transaction
        if($currentRole == "Super Admin"){
            echo json_encode(["success" => false, "message" => "Super Admin Account cannot be Restricted or blocked."]);
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

            // if ($currentRestrictionId != 0 || $currentBlockId != 0) {
            //     echo json_encode(["success" => false, "message" => "The account is already restricted or blocked."]);
            //     exit();
            // }

            if($columnToUpdate == 'restriction_id' && $currentRestrictionId == 1 ){
                echo json_encode(["success" => false, "message" => "There is an existing restriction on this account."]);
               exit();
            }
            else if ($columnToUpdate == 'block_id' && $currentBlockId == 1){
                echo json_encode(["success" => false, "message" => "There is an existing Block on this account."]);
               
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
        // $stmt->close();
        $conn->close();
    }
    else{
        echo json_encode(["success" => false, "message" => "Account Not Found."]);
    }
}
