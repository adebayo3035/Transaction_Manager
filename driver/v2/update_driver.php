<?php
include('config.php'); // DB connection
include 'auth_utils.php';
session_start();

header('Content-Type: application/json');

// Validate session
$driver_Id = $_SESSION['driver_id'] ?? null;

if (!$driver_Id) {
    logActivity("Driver session not found.");
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized: Session not found."]);
    exit();
}

checkDriverSession($driver_Id);
logActivity("Session validated for Driver ID: $driver_Id.");

// Get request body
$data = json_decode(file_get_contents("php://input"), true);
logActivity("Incoming update request payload: " . json_encode([
    'id' => $data['id'] ?? 'null',
    'email' => $data['email'] ?? 'null',
    'phone' => $data['phone_number'] ?? 'null',
    'gender' => $data['gender'] ?? 'null',
    'vehicle_type' => $data['vehicle_type'] ?? 'null'
]));

if (isset($data['id'])) {
    $driverId = $data['id'];
    $email = $data['email'];
    $phone_number = $data['phone_number'];
    $gender = $data['gender'];
    $address = $data['address'];
    $vehicle_type = $data['vehicle_type'];
    $vehicle_type_others = $data['vehicle_type_others'] ?? null;
    $secret_answer = $data['secret_answer'];

    logActivity("Processing driver update for Driver ID: $driverId");

    if (
        empty($email) || empty($phone_number) || empty($gender) || empty($address) || empty($vehicle_type) ||
        ($vehicle_type === "Others" && empty($vehicle_type_others))
    ) {
        logActivity("Validation failed: Missing fields for Driver ID: $driverId");
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
        exit();
    }

    if ($driverId != $driver_Id) {
        logActivity("Driver ID mismatch. Payload ID: $driverId, Session ID: $driver_Id");
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Error Validating Driver Identity."]);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logActivity("Invalid email format for Driver ID: $driverId");
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid E-mail address."]);
        exit();
    }

    if (!preg_match('/^\d{11}$/', $phone_number)) {
        logActivity("Invalid phone number format for Driver ID: $driverId");
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please input a valid Phone Number."]);
        exit();
    }

    // Check uniqueness
    $checkQuery = "SELECT id FROM driver WHERE (phone_number = ? OR email = ?) AND id != ?";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        logActivity("SQL prepare error on uniqueness check: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error (prepare).']);
        exit();
    }
    $stmt->bind_param("ssi", $phone_number, $email, $driverId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        logActivity("Duplicate phone/email found during update for Driver ID: $driverId");
        $stmt->close();
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Phone number or email already exists for another driver."]);
        exit();
    }
    $stmt->close();

    // Validate secret answer
    $secretCheckQuery = "SELECT secret_answer FROM driver WHERE id = ?";
    $stmt = $conn->prepare($secretCheckQuery);
    if (!$stmt) {
        logActivity("SQL prepare error on secret answer check: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Server error (secret check)."]);
        exit();
    }

    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($stored_secret_answer);
        $stmt->fetch();
        $stmt->close();

        $encrypted_answer = password_hash($secret_answer, PASSWORD_DEFAULT);

        if (!verifyAndUpgradeSecretAnswer($conn, $driverId, $secret_answer, $stored_secret_answer)) {
            logActivity("Secret answer mismatch for Driver ID: $driverId");
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Account Validation Failed."]);
            exit();
        }

        logActivity("Secret answer verified for Driver ID: $driverId");
    }

    // Replace vehicle_type if "Others"
    if ($vehicle_type === "Others") {
        $vehicle_type = $vehicle_type_others;
    }

    $updateQuery = "UPDATE driver SET 
                        email = ?, 
                        phone_number = ?, 
                        gender = ?, 
                        address = ?, 
                        vehicle_type = ?, 
                        secret_answer = ? 
                    WHERE id = ?";

    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        logActivity("SQL prepare error during update for Driver ID: $driverId - " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to prepare update query."]);
        exit();
    }

    $stmt->bind_param("ssssssi", $email, $phone_number, $gender, $address, $vehicle_type, $encrypted_answer, $driverId);

    if ($stmt->execute()) {
        logActivity("Driver record successfully updated for Driver ID: $driverId");
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Your Record has been Successfully Updated."]);
    } else {
        logActivity("Update failed for Driver ID: $driverId - Error: " . $stmt->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to update driver record."]);
    }

    $stmt->close();
} else {
    logActivity("Driver update request missing 'id' field in payload.");
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Driver ID is required."]);
}

$conn->close();
