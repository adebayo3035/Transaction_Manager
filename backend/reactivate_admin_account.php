<?php
header('Content-Type: application/json');
require_once 'config.php';
session_start();

$admin_id = $_SESSION['unique_id'];

try {
    logActivity("REACTIVATION_ACTION_START: Incoming request to process staff reactivation");

    if (!isset($_POST['action']) || !isset($_POST['staff_id'])) {
        logActivity("REACTIVATION_ACTION_ERROR: Missing 'action' or 'staff_id' in request");
        echo json_encode([
            'success' => false,
            'message' => 'Missing action or staff_id'
        ]);
        exit;
    }

    $action = $_POST['action'];
    $staff_id = $_POST['staff_id'];

    logActivity("REACTIVATION_ACTION_RECEIVED: Action => $action | Staff ID => $staff_id");

    $conn->begin_transaction();

    if ($action === 'approve') {
        logActivity("REACTIVATION_APPROVE_INIT: Approving request for staff_id = $staff_id");

        $updateRequestQuery = "
            UPDATE account_reactivation_requests 
            SET status = 'Approved', processed_by = ?, processed_at = NOW()
            WHERE user_id = ? AND status = 'Pending'
        ";
        $stmt = $conn->prepare($updateRequestQuery);
        $stmt->bind_param('ii', $admin_id, $staff_id);
        $stmt->execute();
        logActivity("REACTIVATION_APPROVE_UPDATE_REQUEST: Rows affected = " . $stmt->affected_rows);
        $stmt->close();

        $updateStaffQuery = "
            UPDATE admin_tbl 
            SET delete_status = NULL 
            WHERE unique_id = ?
        ";
        $stmt = $conn->prepare($updateStaffQuery);
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        logActivity("REACTIVATION_APPROVE_UPDATE_STAFF: Rows affected = " . $stmt->affected_rows);
        $stmt->close();

        $conn->commit();
        logActivity("REACTIVATION_APPROVE_SUCCESS: Staff $staff_id reactivated successfully");

        echo json_encode([
            'success' => true,
            'message' => 'Staff reactivation approved successfully'
        ]);

    } elseif ($action === 'decline') {
        logActivity("REACTIVATION_DECLINE_INIT: Declining request for staff_id = $staff_id");

        $updateRequestQuery = "
            UPDATE account_reactivation_requests 
            SET status = 'Rejected', processed_by = ?, processed_at = NOW()
            WHERE user_id = ? AND status = 'Pending'
        ";
        $stmt = $conn->prepare($updateRequestQuery);
        $stmt->bind_param('ii', $admin_id, $staff_id);
        $stmt->execute();
        logActivity("REACTIVATION_DECLINE_UPDATE_REQUEST: Rows affected = " . $stmt->affected_rows);
        $stmt->close();

        $conn->commit();
        logActivity("REACTIVATION_DECLINE_SUCCESS: Reactivation request declined for staff_id = $staff_id");

        echo json_encode([
            'success' => true,
            'message' => 'Staff reactivation request declined'
        ]);

    } else {
        logActivity("REACTIVATION_ACTION_ERROR: Invalid action value received: $action");
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
    }

} catch (Exception $e) {
    $conn->rollback();
    logActivity("REACTIVATION_ACTION_EXCEPTION: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process reactivation request',
        'error' => $e->getMessage()
    ]);
}

