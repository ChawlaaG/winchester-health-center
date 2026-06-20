<?php
// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');

// Include database wrapper
require_once 'db.php';

// Validate connection exists
if (!isset($pdo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Critical Error: Database connection is not available. Please verify db.php settings.'
    ]);
    exit;
}

// Handle request validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Check and parse raw JSON input or post variables
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];
} else {
    $data = $_POST;
}

// Sanitize inputs
$first_name = '';
$last_name = null;

if (isset($data['name']) && !empty(trim($data['name']))) {
    // Quick Booking Form (Single Name field)
    $fullName = trim($data['name']);
    $parts = explode(' ', $fullName, 2);
    $first_name = $parts[0];
    if (isset($parts[1]) && !empty(trim($parts[1]))) {
        $last_name = trim($parts[1]);
    }
} else {
    // Detailed Booking Form (First Name / Last Name fields)
    $first_name = trim($data['first_name'] ?? '');
    $last_name = isset($data['last_name']) && !empty(trim($data['last_name'])) ? trim($data['last_name']) : null;
}

$phone = trim($data['phone'] ?? '');
$email = isset($data['email']) && !empty(trim($data['email'])) ? trim($data['email']) : null;
$dob = isset($data['dob']) && !empty(trim($data['dob'])) ? trim($data['dob']) : null;
$service = trim($data['service'] ?? '');
$preferred_date = isset($data['preferred_date']) && !empty(trim($data['preferred_date'])) ? trim($data['preferred_date']) : null;
$notes = isset($data['notes']) && !empty(trim($data['notes'])) ? trim($data['notes']) : null;

// Convert datetime-local format from browser (YYYY-MM-DDTHH:MM) to MySQL datetime (YYYY-MM-DD HH:MM:SS)
if ($preferred_date) {
    $preferred_date = str_replace('T', ' ', $preferred_date);
    // Append seconds if missing
    if (strlen($preferred_date) == 16) {
        $preferred_date .= ':00';
    }
}

// Validation rules
if (empty($first_name)) {
    echo json_encode(['success' => false, 'message' => 'First name or full name is required.']);
    exit;
}
if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
    exit;
}
if (empty($service) || strpos($service, 'Choose a service') !== false || strpos($service, 'Select a service') !== false) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid healthcare service.']);
    exit;
}

try {
    // Prepare SQL Insert statement
    $stmt = $pdo->prepare("
        INSERT INTO bookings (first_name, last_name, phone, email, dob, service, preferred_date, notes, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");
    
    $result = $stmt->execute([
        $first_name,
        $last_name,
        $phone,
        $email,
        $dob,
        $service,
        $preferred_date,
        $notes
    ]);

    if ($result) {
        // Send email alert to owner
        $to = defined('NOTIFICATION_EMAIL') ? NOTIFICATION_EMAIL : 'info@winchesterhealthcentre.co.uk';
        $from = defined('FROM_EMAIL') ? FROM_EMAIL : 'no-reply@winchesterhealthcentre.co.uk';
        $patient_full_name = htmlspecialchars($first_name . ($last_name ? ' ' . $last_name : ''));
        $subject = '🔔 New Booking Request: ' . $patient_full_name;
        
        $isVoiceAi = ($notes !== null && strpos($notes, '[Booked via Voice AI Assistant]') !== false);
        $sourceName = $isVoiceAi ? 'Voice AI Assistant' : 'Website Form';
        
        // Colors matching the booking source (amber for voice, teal for web)
        $sourceBg = $isVoiceAi ? '#fef3c7' : '#ccfbf1';
        $sourceFg = $isVoiceAi ? '#b45309' : '#0f766e';
        
        $safe_email = $email ? htmlspecialchars($email) : 'Not provided';
        $safe_dob = $dob ? htmlspecialchars($dob) : 'Not provided';
        $safe_date = $preferred_date ? htmlspecialchars($preferred_date) : 'Anytime / As soon as possible';
        $safe_notes = $notes ? nl2br(htmlspecialchars($notes)) : 'None provided';
        $safe_service = htmlspecialchars($service);
        $safe_phone = htmlspecialchars($phone);

        $body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>New Appointment Request</title>
</head>
<body style=\"margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155; -webkit-font-smoothing: antialiased;\">
    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f1f5f9; padding: 20px 0;'>
        <tr>
            <td align='center'>
                <table border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);'>
                    <!-- Header -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); padding: 30px; text-align: center;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;'>Winchester Health Centre</h1>
                            <p style='color: #ccfbf1; margin: 5px 0 0 0; font-size: 14px; font-weight: 500;'>New Appointment Notification</p>
                        </td>
                    </tr>
                    
                    <!-- Content Body -->
                    <tr>
                        <td style='padding: 40px 30px;'>
                            <div style='text-align: center; margin-bottom: 25px;'>
                                <span style='background-color: {$sourceBg}; color: {$sourceFg}; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 6px 16px; border-radius: 50px; display: inline-block;'>
                                    Received via {$sourceName}
                                </span>
                            </div>
                            
                            <p style='font-size: 16px; line-height: 1.6; margin: 0 0 25px 0; color: #475569; text-align: center;'>
                                A new booking request has been submitted by a patient. Here are the details:
                            </p>
                            
                            <!-- Details Table -->
                            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='border-collapse: collapse; margin-bottom: 30px;'>
                                <tr>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #64748b; width: 35%; font-size: 14px;'>Patient Name</td>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px; font-weight: 500;'>{$patient_full_name}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #64748b; font-size: 14px;'>Phone Number</td>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px; font-weight: 600;'>
                                        <a href='tel:{$safe_phone}' style='color: #0d9488; text-decoration: none;'>{$safe_phone}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #64748b; font-size: 14px;'>Email Address</td>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px;'>{$safe_email}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #64748b; font-size: 14px;'>Date of Birth</td>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px;'>{$safe_dob}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #64748b; font-size: 14px;'>Requested Service</td>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #0f766e; font-size: 14px; font-weight: 600;'>{$safe_service}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #64748b; font-size: 14px;'>Preferred Time</td>
                                    <td style='padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px; font-weight: 500;'>{$safe_date}</td>
                                </tr>
                                <tr>
                                    <td style='vertical-align: top; padding: 12px 15px; font-weight: 600; color: #64748b; font-size: 14px;'>Patient Notes</td>
                                    <td style='padding: 12px 15px; color: #475569; font-size: 14px; line-height: 1.5; font-style: italic; background-color: #f8fafc; border-radius: 6px;'>{$safe_notes}</td>
                                </tr>
                            </table>
                            
                            <!-- Call to Action -->
                            <div style='text-align: center; margin-top: 35px;'>
                                <a href='https://olivedrab-hare-950327.hostingersite.com/admin/' style='background-color: #0d9488; color: #ffffff; text-decoration: none; padding: 14px 30px; font-size: 15px; font-weight: 600; border-radius: 8px; display: inline-block;'>
                                    Manage Booking in Dashboard
                                </a>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='background-color: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #f1f5f9;'>
                            <p style='margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.5;'>
                                Winchester Health Centre &bull; Travel Clinic & Health Services<br>
                                This is an automated notification. Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . $from . "\r\n";
        
        @mail($to, $subject, $body, $headers);

        echo json_encode([
            'success' => true,
            'message' => 'Booking request submitted successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to write booking details into the database.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database operation error: ' . $e->getMessage()
    ]);
}
