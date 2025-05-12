<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file
session_start();

// Check login
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access denied: User not logged in.");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];

if ($loggedInUserRole !== "Super Admin") {
    logActivity("Access denied: User $logged_in_user does not have Super Admin privileges.");
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    logActivity("Invalid JSON input received.");
    echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
    exit();
}

logActivity("Update group request received: " . json_encode($data));

if (isset($data['group_id'])) {
    $groupId = $data['group_id'];
    $group_name = $data['group_name'];

    // Validate required fields
    if (empty($groupId) || empty($group_name)) {
        logActivity("Validation failed: group_id or group_name is missing.");
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Check for Name Duplicates
    $selectRest = "SELECT COUNT(*) AS group_count FROM groups WHERE group_name = ? AND group_id != ?";
    $stmt = $conn->prepare($selectRest);
    if (!$stmt) {
        logActivity("Prepare failed for duplicate check: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Internal error."]);
        exit();
    }

    $stmt->bind_param("si", $group_name, $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['group_count'] > 0) {
        logActivity("Duplicate group name detected: '$group_name' (ID $groupId).");
        echo json_encode(["success" => false, "message" => "Group with the same name already exists."]);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // Update the group
    $sql = "UPDATE groups SET group_name = ? WHERE group_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logActivity("Prepare failed for update query: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Internal error."]);
        exit();
    }

    $stmt->bind_param("si", $group_name, $groupId);

    if ($stmt->execute()) {
        logActivity("Group ID $groupId updated successfully by user $logged_in_user. New name: $group_name");
        echo json_encode(["success" => true, "message" => "Group Record has been Successfully Updated."]);
    } else {
        logActivity("Failed to update group ID $groupId by user $logged_in_user. Error: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Failed to update Group Details."]);
    }

    $stmt->close();
} else {
    logActivity("Update failed: group_id not provided in request.");
    echo json_encode(["success" => false, "message" => "Group ID is missing."]);
}

$conn->close();
logActivity("Database connection closed after group update request.");
