<?php
// ══════════════════════════════════════════════════════════════
// DATABASE & NOTIFICATION CONFIGURATION TEMPLATE
// Copy to db.php and customize values to match your server
// ══════════════════════════════════════════════════════════════
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Default Administrator Account (Self-bootstrapped on empty db)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'Winchester2026!');

// Notification & Email Settings
define('NOTIFICATION_EMAIL', 'winchestertravelcenter@gmail.com'); // Admin alert recipient
define('FROM_EMAIL', 'info@winchestertravelcentre.co.uk');         // Domain-aligned sender (passes SPF/DMARC)
define('FROM_NAME', 'Winchester Travel & Health Centre');
define('REPLY_TO_EMAIL', 'winchestertravelcenter@gmail.com');     // Patient replies route here
define('PATIENT_PORTAL_URL', 'https://my.scripter.digital/appointment/organization/91?location_id=92');

// Optional SMTP Server Settings (Leave SMTP_HOST blank to use optimized PHP mail() with -f envelope)
define('SMTP_HOST', '');       // e.g. 'smtp.hostinger.com'
define('SMTP_PORT', 587);      // 587 (STARTTLS) or 465 (SSL)
define('SMTP_USER', '');       // e.g. 'info@winchestertravelcentre.co.uk'
define('SMTP_PASS', '');       // Mailbox password
define('SMTP_SECURE', 'tls');  // 'tls' or 'ssl'

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
}
