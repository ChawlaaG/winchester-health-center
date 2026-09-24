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

// Include centralized mailer & database wrapper
require_once __DIR__ . '/../mailer.php';

// Verify database connection is active
if (!isset($pdo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please check db.php connection details.'
    ]);
    exit;
}

// Sends the patient an email reflecting the new status of their booking
function send_status_update_email($email, $first_name, $service, $preferred_date, $status) {
    $from = defined('FROM_EMAIL') ? FROM_EMAIL : 'info@winchestertravelcentre.co.uk';
    $portalUrl = defined('PATIENT_PORTAL_URL') ? PATIENT_PORTAL_URL : 'https://my.scripter.digital/appointment/organization/91?location_id=92';

    $statusCopy = [
        'Confirmed' => [
            'subject' => 'Your Appointment is Confirmed - Winchester Travel Centre',
            'heading' => 'Your Appointment is Confirmed! ✅',
            'color'   => '#0d9488',
            'message' => 'Great news — your appointment has been confirmed. We look forward to seeing you.',
        ],
        'Completed' => [
            'subject' => 'Appointment Completed - Winchester Travel Centre',
            'heading' => 'Thank You for Visiting Us',
            'color'   => '#0d9488',
            'message' => 'Your appointment has now been marked as completed. Thank you for choosing Winchester Travel Centre.',
        ],
        'Cancelled' => [
            'subject' => 'Appointment Cancelled - Winchester Travel Centre',
            'heading' => 'Your Appointment Has Been Cancelled',
            'color'   => '#dc2626',
            'message' => 'Your appointment request has been cancelled. If this is unexpected or you would like to rebook, please get in touch.',
        ],
    ];

    if (!isset($statusCopy[$status])) {
        return;
    }
    $copy = $statusCopy[$status];

    $safe_service = htmlspecialchars($service);
    $safe_date = $preferred_date ? htmlspecialchars($preferred_date) : 'To be arranged';
    $safe_name = htmlspecialchars($first_name);

    $preVisitSection = '';
    if ($status === 'Confirmed') {
        $preVisitSection = "
                            <div style='margin-top: 25px; margin-bottom: 25px; padding: 24px; background: #f0fdfa; border: 2px solid #0d9488; border-radius: 12px; text-align: center;'>
                                <div style='display: inline-block; background-color: #0d9488; color: #ffffff; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; padding: 4px 14px; border-radius: 20px; margin-bottom: 12px;'>
                                    Action Required Before Your Visit 📋
                                </div>
                                <h3 style='margin: 0 0 10px 0; color: #0f766e; font-size: 18px; font-weight: 700;'>
                                    Complete Your Pre-Visit Details Online
                                </h3>
                                <p style='margin: 0 0 18px 0; font-size: 14px; line-height: 1.6; color: #334155;'>
                                    Before visiting the clinic, please fill in your details on our patient portal using the link below so our clinical team can prepare for your visit:
                                </p>
                                <a href='{$portalUrl}' target='_blank' style='background-color: #0d9488; color: #ffffff; text-decoration: none; padding: 14px 28px; font-size: 15px; font-weight: 700; border-radius: 8px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(13, 148, 136, 0.25);'>
                                    Complete Pre-Visit Form &rarr;
                                </a>
                                <p style='margin: 14px 0 0 0; font-size: 12px; color: #64748b; word-break: break-all;'>
                                    Or visit: <a href='{$portalUrl}' style='color: #0d9488;'>{$portalUrl}</a>
                                </p>
                                <div style='margin-top: 16px; padding-top: 14px; border-top: 1px dashed #99f6e4; font-size: 13px; line-height: 1.5; color: #334155; text-align: left;'>
                                    📞 <strong>Can't complete this online or have questions?</strong><br>
                                    If you are unable to fill out the form online or have any trouble, please call our clinic directly on <a href='tel:+441962854725' style='color: #0f766e; font-weight: 700; text-decoration: none;'>01962 854 725</a>. Our friendly staff will be delighted to assist you over the phone or help you in person when you arrive.
                                </div>
                            </div>";
    }

    $body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$copy['subject']}</title>
</head>
<body style=\"margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155;\">
    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f1f5f9; padding: 20px 0;'>
        <tr>
            <td align='center'>
                <table border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);'>
                    <tr>
                        <td style='background: {$copy['color']}; padding: 30px; text-align: center;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;'>Winchester Travel Centre</h1>
                            <p style='color: #ffffff; opacity: .85; margin: 5px 0 0 0; font-size: 14px; font-weight: 500;'>{$copy['heading']}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 40px 30px;'>
                            <h2 style='color: {$copy['color']}; margin-top: 0; font-size: 20px;'>Hello {$safe_name},</h2>
                            <p style='font-size: 16px; line-height: 1.6; color: #475569;'>{$copy['message']}</p>

                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='border-collapse: collapse; margin: 25px 0;'>
                                <tr>
                                    <td style='padding: 10px 0; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600; width: 40%; font-size: 14px;'>Service</td>
                                    <td style='padding: 10px 0; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-weight: 600; font-size: 14px;'>{$safe_service}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px 0; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600; font-size: 14px;'>Appointment Date</td>
                                    <td style='padding: 10px 0; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px;'>{$safe_date}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px 0; color: #64748b; font-weight: 600; font-size: 14px;'>Status</td>
                                    <td style='padding: 10px 0; color: {$copy['color']}; font-weight: 700; font-size: 14px;'>{$status}</td>
                                </tr>
                            </table>

                            {$preVisitSection}

                            <p style='font-size: 14px; line-height: 1.6; color: #64748b; background-color: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid {$copy['color']}; margin-top: 25px;'>
                                <strong>Questions or need to make changes?</strong> Contact us at <strong>01962 854 725</strong> or reply directly to this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style='background-color: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #f1f5f9;'>
                            <p style='margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.5;'>
                                Winchester Travel Centre &bull; Travel Clinic &amp; Health Services<br>
                                Winchester, Hampshire
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";

    $res = send_system_mail($email, $copy['subject'], $body);
    if (!$res['success']) {
        error_log("[Status Update Mail] Failed sending status email to {$email}: " . ($res['error'] ?? 'Unknown error'));
    }
}

/**
 * Sends the patient an email notifying them that their appointment has been rescheduled.
 */
function send_reschedule_email($email, $first_name, $service, $old_date, $new_date, $reschedule_reason = null) {
    $subject = 'Your Appointment Has Been Rescheduled - Winchester Travel Centre';
    $from = defined('FROM_EMAIL') ? FROM_EMAIL : 'info@winchestertravelcentre.co.uk';
    $portalUrl = defined('PATIENT_PORTAL_URL') ? PATIENT_PORTAL_URL : 'https://my.scripter.digital/appointment/organization/91?location_id=92';

    $safe_name = htmlspecialchars($first_name);
    $safe_service = htmlspecialchars($service);

    // Format human-readable dates (e.g. Wednesday, 24 September 2026 at 2:30 PM)
    $formatted_old_date = 'Not previously scheduled';
    if (!empty($old_date)) {
        $oldTime = strtotime($old_date);
        $formatted_old_date = $oldTime ? date('l, j F Y \a\t g:i A', $oldTime) : htmlspecialchars($old_date);
    }

    $formatted_new_date = 'To be arranged';
    if (!empty($new_date)) {
        $newTime = strtotime($new_date);
        $formatted_new_date = $newTime ? date('l, j F Y \a\t g:i A', $newTime) : htmlspecialchars($new_date);
    }

    $reasonSection = '';
    if (!empty($reschedule_reason)) {
        $safe_reason = nl2br(htmlspecialchars($reschedule_reason));
        $reasonSection = "
            <div style='margin: 22px 0; padding: 16px 20px; background-color: #f8fafc; border-left: 4px solid #0d9488; border-radius: 6px;'>
                <p style='margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: #0f766e; text-transform: uppercase; letter-spacing: 0.6px;'>Message From Our Clinic Team</p>
                <p style='margin: 0; font-size: 14px; color: #334155; line-height: 1.6;'>{$safe_reason}</p>
            </div>
        ";
    }

    $body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$subject}</title>
</head>
<body style=\"margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155;\">
    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f1f5f9; padding: 20px 0;'>
        <tr>
            <td align='center'>
                <table border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);'>
                    <!-- Header -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); padding: 30px; text-align: center;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;'>Winchester Travel Centre</h1>
                            <p style='color: #ccfbf1; margin: 6px 0 0 0; font-size: 15px; font-weight: 500;'>Appointment Rescheduled 🗓️</p>
                        </td>
                    </tr>
                    
                    <!-- Content Body -->
                    <tr>
                        <td style='padding: 35px 30px;'>
                            <h2 style='color: #0f766e; margin-top: 0; font-size: 20px;'>Hello {$safe_name},</h2>
                            <p style='font-size: 15px; line-height: 1.6; color: #475569; margin-bottom: 22px;'>
                                Please note that your appointment date and time has been updated. Below are the revised details for your upcoming visit:
                            </p>

                            <!-- Highlighted Reschedule Box -->
                            <div style='background: #f0fdfa; border: 2px solid #0d9488; border-radius: 10px; padding: 20px; margin-bottom: 22px;'>
                                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='border-collapse: collapse;'>
                                    <tr>
                                        <td style='padding: 6px 0; color: #64748b; font-size: 13px; font-weight: 600; width: 35%;'>Service:</td>
                                        <td style='padding: 6px 0; color: #0f766e; font-size: 15px; font-weight: 700;'>{$safe_service}</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 8px 0; color: #94a3b8; font-size: 13px; font-weight: 600;'>Previous Slot:</td>
                                        <td style='padding: 8px 0; color: #94a3b8; font-size: 14px; text-decoration: line-through;'>{$formatted_old_date}</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 10px 0; color: #0f766e; font-size: 14px; font-weight: 700; border-top: 1px dashed #99f6e4;'>NEW Date &amp; Time:</td>
                                        <td style='padding: 10px 0; color: #0d9488; font-size: 16px; font-weight: 800; border-top: 1px dashed #99f6e4;'>
                                            ✨ {$formatted_new_date}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 6px 0; color: #64748b; font-size: 13px; font-weight: 600;'>Location:</td>
                                        <td style='padding: 6px 0; color: #1e293b; font-size: 14px;'>Winchester Travel Centre, Hampshire</td>
                                    </tr>
                                </table>
                            </div>

                            {$reasonSection}

                            <!-- Pre-Visit Notice -->
                            <div style='background-color: #fafbfc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin-bottom: 22px;'>
                                <h4 style='margin: 0 0 6px 0; color: #1e293b; font-size: 14px;'>📋 Complete Your Pre-Visit Details Online</h4>
                                <p style='margin: 0 0 12px 0; font-size: 13px; line-height: 1.5; color: #64748b;'>
                                    Before visiting our clinic, please ensure your details are submitted on our patient portal so our clinical team can prepare for your visit:
                                </p>
                                <a href='{$portalUrl}' target='_blank' style='background-color: #0d9488; color: #ffffff; text-decoration: none; padding: 10px 22px; font-size: 13px; font-weight: 700; border-radius: 6px; display: inline-block;'>
                                    Complete Pre-Visit Form &rarr;
                                </a>
                            </div>

                            <!-- Change slot notice -->
                            <p style='font-size: 14px; line-height: 1.6; color: #64748b; background-color: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #0d9488; margin-top: 20px;'>
                                <strong>Does this new time not work for you?</strong><br>
                                If you need to adjust this slot or have any questions, please call our clinic directly at <a href='tel:+441962854725' style='color: #0f766e; font-weight: 700; text-decoration: none;'>01962 854 725</a> or simply reply to this email.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='background-color: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #f1f5f9;'>
                            <p style='margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.5;'>
                                Winchester Travel Centre &bull; Travel Clinic &amp; Health Services<br>
                                Winchester, Hampshire
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";

    $res = send_system_mail($email, $subject, $body);
    if (!$res['success']) {
        error_log("[Reschedule Mail] Failed sending reschedule email to {$email}: " . ($res['error'] ?? 'Unknown error'));
        return false;
    }
    return true;
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

    // ══════════════════════════════════════════════════════════════
    // ACTION: CREATE BOOKING (DIRECT APPOINTMENT FROM ADMIN)
    // ══════════════════════════════════════════════════════════════
    if ($action === 'create_booking') {
        $first_name = trim($data['first_name'] ?? '');
        $last_name = isset($data['last_name']) && !empty(trim($data['last_name'])) ? trim($data['last_name']) : null;
        $phone = trim($data['phone'] ?? '');
        $email = isset($data['email']) && !empty(trim($data['email'])) ? trim($data['email']) : null;
        $dob = isset($data['dob']) && !empty(trim($data['dob'])) ? trim($data['dob']) : null;
        $service = trim($data['service'] ?? 'General Consultation');
        $preferred_date = isset($data['preferred_date']) && !empty(trim($data['preferred_date'])) ? trim($data['preferred_date']) : null;
        $notes = isset($data['notes']) && !empty(trim($data['notes'])) ? trim($data['notes']) : null;
        $admin_notes = isset($data['admin_notes']) && !empty(trim($data['admin_notes'])) ? trim($data['admin_notes']) : null;
        $status = in_array($data['status'] ?? '', ['Pending', 'Confirmed', 'Completed', 'Cancelled']) ? $data['status'] : 'Confirmed';

        if (empty($first_name)) {
            echo json_encode(['success' => false, 'message' => 'Patient first name or full name is required.']);
            exit;
        }
        if (empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Patient phone number is required.']);
            exit;
        }

        if ($preferred_date) {
            $preferred_date = str_replace('T', ' ', trim($preferred_date));
            if (strlen($preferred_date) === 10) {
                $preferred_date .= ' 09:00:00';
            } elseif (strlen($preferred_date) === 16) {
                $preferred_date .= ':00';
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO bookings (first_name, last_name, phone, email, dob, service, preferred_date, notes, admin_notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $first_name,
            $last_name,
            $phone,
            $email,
            $dob,
            $service,
            $preferred_date,
            $notes,
            $admin_notes,
            $status
        ]);

        if ($result) {
            $newId = $pdo->lastInsertId();
            if (!empty($email) && $status === 'Confirmed') {
                send_status_update_email($email, $first_name, $service, $preferred_date, $status);
            }
            echo json_encode([
                'success' => true,
                'message' => 'Appointment created successfully.',
                'id' => $newId
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create appointment in database.']);
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
    // ACTION: RESCHEDULE BOOKING
    // ══════════════════════════════════════════════════════════════
    elseif ($action === 'reschedule') {
        $preferred_date = trim($data['preferred_date'] ?? '');
        $reschedule_reason = isset($data['reschedule_reason']) ? trim($data['reschedule_reason']) : null;
        $notify_customer = isset($data['notify_customer']) ? filter_var($data['notify_customer'], FILTER_VALIDATE_BOOLEAN) : true;

        if (empty($preferred_date)) {
            echo json_encode(['success' => false, 'message' => 'A new appointment date and time is required.']);
            exit;
        }

        // Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL DATETIME (YYYY-MM-DD HH:MM:SS)
        $preferred_date = str_replace('T', ' ', $preferred_date);
        if (strlen($preferred_date) === 10) {
            $preferred_date .= ' 09:00:00';
        } elseif (strlen($preferred_date) === 16) {
            $preferred_date .= ':00';
        }

        // Lookup current booking details
        $lookupStmt = $pdo->prepare("SELECT first_name, last_name, phone, email, service, preferred_date, admin_notes, status FROM bookings WHERE id = ?");
        $lookupStmt->execute([$id]);
        $booking = $lookupStmt->fetch();

        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Appointment record not found.']);
            exit;
        }

        $old_date = $booking['preferred_date'];
        
        // Log an audit trail entry in admin_notes
        $timestamp = date('d M Y H:i');
        $oldDateStr = $old_date ? date('d M Y H:i', strtotime($old_date)) : 'Flexible';
        $newDateStr = date('d M Y H:i', strtotime($preferred_date));
        $auditEntry = "[Rescheduled on {$timestamp}: {$oldDateStr} ➔ {$newDateStr}" . ($reschedule_reason ? " | Note: {$reschedule_reason}" : "") . "]";
        
        $currentNotes = trim($booking['admin_notes'] ?? '');
        $updated_admin_notes = empty($currentNotes) ? $auditEntry : $currentNotes . "\n" . $auditEntry;

        // Optionally update status if specified or keep existing
        $newStatus = isset($data['status']) && in_array($data['status'], ['Pending', 'Confirmed', 'Completed', 'Cancelled'])
            ? $data['status']
            : ($booking['status'] === 'Pending' ? 'Confirmed' : $booking['status']);

        $updateStmt = $pdo->prepare("UPDATE bookings SET preferred_date = ?, admin_notes = ?, status = ? WHERE id = ?");
        $result = $updateStmt->execute([$preferred_date, $updated_admin_notes, $newStatus, $id]);

        if ($result) {
            $mailSent = false;
            $mailNotice = '';
            if ($notify_customer) {
                if (!empty($booking['email'])) {
                    $mailSent = send_reschedule_email($booking['email'], $booking['first_name'], $booking['service'], $old_date, $preferred_date, $reschedule_reason);
                    $mailNotice = $mailSent ? ' and customer notified via email.' : ' (email notification could not be delivered).';
                } else {
                    $mailNotice = ' (customer has no email on file).';
                }
            } else {
                $mailNotice = ' (customer email notification was skipped).';
            }

            echo json_encode([
                'success' => true,
                'message' => 'Appointment rescheduled to ' . $newDateStr . $mailNotice,
                'preferred_date' => $preferred_date,
                'admin_notes' => $updated_admin_notes,
                'status' => $newStatus,
                'mail_sent' => $mailSent
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update appointment record in database.']);
        }
        exit;
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

        // Fetch the booking's contact details before updating so we can notify the patient
        $lookupStmt = $pdo->prepare("SELECT first_name, last_name, email, service, preferred_date FROM bookings WHERE id = ?");
        $lookupStmt->execute([$id]);
        $booking = $lookupStmt->fetch();

        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $result = $stmt->execute([$status, $id]);

        if ($result) {
            if ($booking && !empty($booking['email'])) {
                send_status_update_email($booking['email'], $booking['first_name'], $booking['service'], $booking['preferred_date'], $status);
            }

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
