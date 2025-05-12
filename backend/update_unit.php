<?php
// Include database connection
include('config.php'); // Replace with your actual database connection file
session_start();

logActivity("Update Unit request received.");

// Session and Access Check
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access denied: User not logged in.");
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit();
}

$loggedInUserRole = $_SESSION['role'];
$logged_in_user = $_SESSION['unique_id'];

logActivity("Logged-in User ID: $logged_in_user, Role: $loggedInUserRole");

if ($loggedInUserRole !== "Super Admin") {
    logActivity("Access denied for user $logged_in_user: Role is not Super Admin.");
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);
logActivity("Received request data: " . json_encode($data));

if (isset($data['unit_id'])) {
    $unitId = $data['unit_id'];
    $unit_name = $data['unit_name'];
    $group_id = $data['group_id'];

    // Validate required fields
    if (empty($unitId) || empty($group_id) || empty($unit_name)) {
        logActivity("Validation failed: Missing required fields (unit_id, unit_name, group_id).");
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    logActivity("Validating uniqueness of unit name: '$unit_name' excluding unit_id: $unitId");

    // Check for Name Duplicates
    $selectRest = "SELECT COUNT(*) AS unit_count FROM unit WHERE unit_name = ? and unit_id != ?";
    $stmt = $conn->prepare($selectRest);
    $stmt->bind_param("si", $unit_name, $unitId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['unit_count'] > 0) {
        logActivity("Duplicate unit name found for '$unit_name'. Update aborted.");
        die(json_encode(["success" => false, "message" => "Unit with the same name already exists."]));
    }
    $stmt->close();

    logActivity("No duplicate unit found. Proceeding to update unit ID $unitId with name '$unit_name' and group ID $group_id.");

    // Prepare SQL query to update the unit
    $sql = "UPDATE unit SET unit_name = ?, group_id = ? WHERE unit_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $unit_name, $group_id, $unitId);

    // Execute the statement
    if ($stmt->execute()) {
        logActivity("Unit ID $unitId successfully updated by user $logged_in_user.");
        echo json_encode(["success" => true, "message" => "Unit Record has been Successfully Updated."]);
    } else {
        logActivity("Database error while updating unit ID $unitId: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Failed to update Unit Details."]);
    }

    // Close the statement
    $stmt->close();
} else {
    logActivity("Update failed: Unit ID not provided in the request.");
    echo json_encode(["success" => false, "message" => "Unit ID is missing."]);
}

// Close the database connection
$conn->close();
logActivity("Update Unit request ended.");
