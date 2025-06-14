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
$adminID = $_SESSION['unique_id']; // assuming this is the Super Admin's ID

if ($role !== "Super Admin") {
    logActivity("Access Denied! User with role '$role' tried to access restricted endpoint.");
    echo json_encode(["success" => false, "message" => "Access Denied! Permission not granted."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID = $data['userID'] ?? null;
    $accountType = $data['accountType'] ?? null;
    $providedAnswer = $data['secretAnswer'] ?? '';

    logActivity("POST request to unrestrict $accountType account for userID: {$userID}");

    // === Validate inputs ===
    if (!is_numeric($userID) || empty($providedAnswer)) {
        echo json_encode(["success" => false, "message" => "Missing or invalid input."]);
        exit();
    }

    $allowedTables = ['customers', 'driver'];
    if (!in_array($accountType, $allowedTables)) {
        logActivity("Invalid accountType: {$accountType}");
        echo json_encode(["success" => false, "message" => "Invalid account type."]);
        exit();
    }

    // === Step 1: Verify Secret Answer from appropriate table ===
    $checkSecret = $conn->prepare("SELECT secret_answer FROM admin_tbl WHERE unique_id = ?");
    $checkSecret->bind_param("i", $adminID);
    $checkSecret->execute();
    $checkSecret->store_result();
    $checkSecret->bind_result($storedHash);

    if ($checkSecret->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "User not found."]);
        exit();
    }

    $checkSecret->fetch();
    $checkSecret->close();

    $isValid = false;
    $isValid = md5($providedAnswer) === $storedHash;

    if (!$isValid) {
        logActivity("Invalid secret answer for $accountType ID $userID");
        echo json_encode(["success" => false, "message" => "Invalid secret answer."]);
        exit();
    }

    // === Step 2: Check current restriction status ===
    $checkSql = "SELECT restriction FROM $accountType WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $userID);
    $checkStmt->execute();
    $checkStmt->store_result();
    $checkStmt->bind_result($currentRestriction);

    if ($checkStmt->num_rows > 0) {
        $checkStmt->fetch();

        if ($currentRestriction == 0) {
            logActivity("No restriction found on $accountType account for userID: {$userID}");
            echo json_encode(["success" => false, "message" => "This account is not restricted."]);
            exit();
        }

        // === Step 3: Lift restriction ===
        $updateSql = "UPDATE $accountType SET restriction = 0 WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $userID);

        if ($updateStmt->execute()) {
            logActivity("Restriction lifted on $accountType account (userID: $userID) by Super Admin ID: $adminID");
            echo json_encode(["success" => true, "message" => "Restriction successfully removed."]);
        } else {
            logActivity("Failed to update restriction for $accountType userID: {$userID}");
            echo json_encode(["success" => false, "message" => "Failed to update restriction. Try again."]);
        }
        $updateStmt->close();
    } else {
        logActivity("No $accountType account found for userID: {$userID}");
        echo json_encode(["success" => false, "message" => "Account not found."]);
    }

    $checkStmt->close();
    $conn->close();
} else {
    logActivity("Invalid request method on unrestriction endpoint.");
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

