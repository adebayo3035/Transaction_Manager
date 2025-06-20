<?php
include('restriction_checker.php');
header('Content-Type: application/json');

//Ensure admin is authenticated
if (!isset($_SESSION['unique_id'])) {
    logActivity("Access denied: No active session.");
    echo json_encode(['success' => false, 'message' => 'Access denied. Please login first.']);
    exit;
}

$adminId = $_SESSION['unique_id'];
$adminRole = $_SESSION['role'] ?? 'Unknown';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['customer_id'])) {
    // === Extract and sanitize ===
    $customerId = $data['customer_id'];
    $firstname = trim($data['firstname']);
    $lastname = trim($data['lastname']);
    $email = strtolower(trim($data['email']));
    $phone_number = trim($data['phone_number']);
    $gender = trim($data['gender']);
    $address = trim($data['address']);
    $group = $data['group'];
    $unit = $data['unit'];
    $restriction = $data['restriction'] ?? null;

    // === Validate required fields ===
    if (
        empty($firstname) || empty($lastname) || empty($email) || empty($phone_number) ||
        empty($gender) || empty($address) || empty($group) || empty($unit) || empty($customerId)
    ) {
        logActivity("Update Failed: Required fields missing for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logActivity("Update Failed: Invalid email address '$email' for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Invalid E-mail address.']);
        exit;
    }

    if (!preg_match('/^\d{11}$/', $phone_number)) {
        logActivity("Update Failed: Invalid phone number '$phone_number' for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Please input a valid Phone Number.']);
        exit;
    }

    if (!is_numeric($group) || !is_numeric($unit)) {
        logActivity("Invalid group/unit input: Group=$group, Unit=$unit for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Invalid group or unit selection.']);
        exit;
    }

    // === Get current restriction status ===
    $currentRestrictionQuery = "SELECT id, restriction FROM customers WHERE customer_id = ?";
    $currentRestrictionStmt = $conn->prepare($currentRestrictionQuery);
    $currentRestrictionStmt->bind_param("i", $customerId);
    $currentRestrictionStmt->execute();
    $currentRestrictionStmt->bind_result($unique_customerId, $currentRestriction); // ✅ bind both columns
    $currentRestrictionStmt->fetch();
    $currentRestrictionStmt->close();

    // Prevent bypassing restriction removal
    if ($currentRestriction == 1 && isset($data['restriction']) && $data['restriction'] == 0) {
        logActivity("Attempt to unrestrict restricted customer ID $customerId via update endpoint.");
        echo json_encode(['success' => false, 'message' => 'Cannot remove restriction from restricted accounts here.']);
        exit;
    }

    // Validate restriction input
    if (!is_null($restriction) && !in_array((int) $restriction, [0, 1], true)) {
        logActivity("Invalid restriction value '$restriction' for customer ID $customerId.");
        echo json_encode(['success' => false, 'message' => 'Invalid restriction value.']);
        exit;
    } else {
        $restriction = $restriction ?? $currentRestriction;
    }

    // === Check for email/phone duplicates ===
    $checkQuery = "SELECT customer_id, mobile_number, email FROM customers WHERE (mobile_number = ? OR email = ?) AND customer_id != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ssi", $phone_number, $email, $customerId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $existingPhone, $existingEmail);
        $stmt->fetch();

        if ($existingPhone === $phone_number) {
            logActivity("Update Failed: Phone number '$phone_number' already exists for a different customer.");
            echo json_encode(['success' => false, 'message' => 'Phone number already exists.']);
            exit();
        }

        if ($existingEmail === $email) {
            logActivity("Update Failed: Email '$email' already exists for a different customer.");
            echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
            exit();
        }
    }
    $stmt->close();

    // === Check if the customer is locked ===
    $lockQuery = "SELECT status FROM customer_lock_history WHERE customer_id = ? ORDER BY id DESC LIMIT 1";
    $lockStmt = $conn->prepare($lockQuery);
    $lockStmt->bind_param("i", $customerId);
    $lockStmt->execute();
    $lockStmt->bind_result($lockStatus);
    $lockStmt->fetch();
    $lockStmt->close();

    if ($lockStatus === 'locked') {
        logActivity("Update Blocked: Customer ID $customerId is currently locked. Attempted by admin ID $adminId.");
        echo json_encode(['success' => false, 'message' => 'This account is currently locked and cannot be updated.']);
        exit;
    }

    // === Begin transaction ===
    $conn->begin_transaction();

    try {
        // === Audit log if applying a new restriction ===
        if ($restriction == 1 && $currentRestriction != 1) {
            $referenceId = bin2hex(random_bytes(16));
            $logQuery = "INSERT INTO account_restriction_audit_log 
                         (reference_id, account_id, account_type, action_type, initiated_by, initiated_by_role) 
                         VALUES (?, ?, 'CUSTOMERS', 'RESTRICT', ?, ?)";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->bind_param("ssss", $referenceId, $unique_customerId, $adminId, $adminRole);
            if (!$logStmt->execute())
                throw new Exception("Restriction audit log failed.");
            logActivity("Restriction applied to CUSTOMER ID: $customerId by Admin ID: $adminId.");
            $logStmt->close();
        }

        // === Main update ===
        $updateQuery = "UPDATE customers 
                        SET firstname = ?, lastname = ?, email = ?, mobile_number = ?, gender = ?, address = ?, group_id = ?, unit_id = ?, restriction = ? 
                        WHERE customer_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssssssiiis", $firstname, $lastname, $email, $phone_number, $gender, $address, $group, $unit, $restriction, $customerId);
        if (!$stmt->execute())
            throw new Exception("Customer update failed: " . $stmt->error);
        $stmt->close();

        // === Commit ===
        $conn->commit();
        logActivity("SUCCESS: CUSTOMER ID $customerId updated by Admin ID $adminId.");
        echo json_encode(['success' => true, 'message' => 'Customer information updated successfully.']);

    } catch (Exception $e) {
        $conn->rollback();
        logActivity("ROLLBACK: Update failed for CUSTOMER ID $customerId. Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Transaction failed.']);
    }

} else {
    logActivity("Update Attempt Failed: No customer ID provided.");
    echo json_encode(["success" => false, "message" => "Customer ID is required."]);
}

$conn->close();
