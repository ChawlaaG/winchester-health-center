<?php
// Set response headers
header('Content-Type: application/json; charset=utf-8');

// Initialize session
session_start();

// Verify admin permission
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied. Session is invalid or has expired.'
    ]);
    exit;
}

// Include database wrapper
require_once '../db.php';

// Verify database connection is active
if (!isset($pdo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please check db.php connection details.'
    ]);
    exit;
}

// Support parsing both form variables and JSON bodies
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];
} else {
    $data = $_POST;
}

$action = trim($data['action'] ?? '');

// Validation
if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing action parameter.'
    ]);
    exit;
}

try {
    // ══════════════════════════════════════════════════════════════
    // ACTION: CHANGE PASSWORD
    // ══════════════════════════════════════════════════════════════
    if ($action === 'change_password') {
        $old_pass = $data['old_password'] ?? '';
        $new_pass = $data['new_password'] ?? '';
        $username = $_SESSION['admin_username'];

        if (empty($old_pass) || empty($new_pass)) {
            echo json_encode(['success' => false, 'message' => 'Both current and new passwords are required.']);
            exit;
        }

        // Fetch current password hash from database
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($old_pass, $user['password_hash'])) {
            // Hash and update the new password
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
            $updateStmt->execute([$new_hash, $username]);
            
            echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
        }
        exit;
    }

    $id = intval($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing booking ID.']);
        exit;
    }

    // ══════════════════════════════════════════════════════════════
    // ACTION: DELETE BOOKING
    // ══════════════════════════════════════════════════════════════
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Booking request deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete record.'
            ]);
        }
    } 
    // ══════════════════════════════════════════════════════════════
    // ACTION: SAVE ADMIN/STAFF INTERNAL NOTES
    // ══════════════════════════════════════════════════════════════
    elseif ($action === 'save_notes') {
        $admin_notes = isset($data['admin_notes']) ? trim($data['admin_notes']) : '';
        
        $stmt = $pdo->prepare("UPDATE bookings SET admin_notes = ? WHERE id = ?");
        $result = $stmt->execute([$admin_notes, $id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Internal staff notes saved successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save staff notes.'
            ]);
        }
    } 
    // ══════════════════════════════════════════════════════════════
    // ACTIONS: STATUS TRANSITIONS (Confirm, Complete, Cancel)
    // ══════════════════════════════════════════════════════════════
    else {
        $status = 'Pending';
        if ($action === 'confirm') {
            $status = 'Confirmed';
        } elseif ($action === 'complete') {
            $status = 'Completed';
        } elseif ($action === 'cancel') {
            $status = 'Cancelled';
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action parameter provided.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $result = $stmt->execute([$status, $id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Booking status updated successfully to ' . $status . '.',
                'status' => $status
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update record state.'
            ]);
        }
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
