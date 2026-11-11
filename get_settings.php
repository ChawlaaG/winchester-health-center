<?php
// ══════════════════════════════════════════════════════════════
// WINCHESTER HEALTH CENTRE - PUBLIC CLINIC SETTINGS API
// Returns dynamic clinic contact numbers, opening hours & timings
// ══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');

$default_settings = [
    'phone_display'  => '01962 422 180',
    'phone_clean'    => '+441962422180',
    'whatsapp'       => '',
    'email'          => 'winchestertravelcenter@gmail.com',
    'address'        => 'Winchester Health Centre, Winchester, Hampshire',
    'hours_weekday'  => 'Mon–Fri: 9:30am – 5:00pm',
    'hours_sat'      => 'Saturday: 9:30am – 3:00pm',
    'hours_sun'      => 'Sunday: Closed',
    'topbar_ticker'  => 'Mon–Fri 9:30am–5pm · Sat 9:30am–3pm · Sun Closed',
    'booking_notice' => 'Appointment slots available: Monday to Saturday, 11:30 AM – 4:30 PM (30 min duration).',
    'slot_start'     => '11:30',
    'slot_end'       => '16:30'
];

$json_file = __DIR__ . '/clinic_settings.json';

// 1. Try reading cached JSON file
if (file_exists($json_file)) {
    $raw = @file_get_contents($json_file);
    if ($raw) {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && !empty($parsed)) {
            echo json_encode(array_merge($default_settings, $parsed));
            exit;
        }
    }
}

// 2. Fallback to Database if JSON is not present
try {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM clinic_settings");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!empty($rows)) {
                $merged = array_merge($default_settings, $rows);
                @file_put_contents($json_file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                echo json_encode($merged);
                exit;
            }
        }
    }
} catch (Throwable $e) {
    // Graceful fallback to defaults
}

echo json_encode($default_settings);

