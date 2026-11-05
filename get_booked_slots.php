<?php
// ══════════════════════════════════════════════════════════════
// WINCHESTER HEALTH CENTRE - REAL-TIME SLOT AVAILABILITY API
// Returns list of booked / unavailable time slots for a specific date
// ══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/db.php';

$date = trim($_GET['date'] ?? '');

if (empty($date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing date parameter.'
    ]);
    exit;
}

// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format. Expected YYYY-MM-DD.'
    ]);
    exit;
}

$timestamp = strtotime($date);
if ($timestamp === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date value.'
    ]);
    exit;
}

$dayOfWeek = date('w', $timestamp); // 0 = Sunday
$isSunday = ($dayOfWeek == '0');

$booked_slots = [];

// Query database for all active bookings on this date (exclude cancelled)
try {
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(preferred_date, '%H:%i') AS slot_time
        FROM bookings 
        WHERE DATE(preferred_date) = ? 
          AND status != 'Cancelled'
    ");
    $stmt->execute([$date]);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($results as $slot) {
        if (!empty($slot)) {
            $booked_slots[] = $slot;
        }
    }
} catch (Throwable $e) {
    error_log("[Slot API] Database query error: " . $e->getMessage());
}

// If requested date is today (clinic local time, Europe/London or server time), block slots that have already passed
date_default_timezone_set('Europe/London');
$todayDate = date('Y-m-d');
$nowTime = date('H:i');
if ($date === $todayDate) {
    $standardSlots = ['11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'];
    foreach ($standardSlots as $s) {
        if ($s <= $nowTime && !in_array($s, $booked_slots)) {
            $booked_slots[] = $s;
        }
    }
}

$booked_slots = array_values(array_unique($booked_slots));

echo json_encode([
    'success' => true,
    'date' => $date,
    'is_sunday' => $isSunday,
    'booked_slots' => $booked_slots
]);
