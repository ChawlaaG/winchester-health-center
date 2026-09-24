<?php
// Initialize session
session_start();

// Redirect to login if unauthorized
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Include database connection
require_once '../db.php';

$connectionError = '';
$bookings = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$uniqueServices = [];
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

if (!isset($pdo)) {
    $connectionError = 'Database connection error: Could not reach MySQL database. Check db.php connection details.';
} else {
    try {
        // Retrieve count summaries
        $stats['total'] = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
        $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Pending'")->fetchColumn();
        $stats['confirmed'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Confirmed'")->fetchColumn();
        $stats['completed'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Completed'")->fetchColumn();
        $stats['cancelled'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Cancelled'")->fetchColumn();

        // Fetch all bookings sorted by date / created_at
        $stmt = $pdo->query("SELECT * FROM bookings ORDER BY COALESCE(preferred_date, created_at) DESC, id DESC");
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Gather unique services for dynamic filter
        $servicesQuery = $pdo->query("SELECT DISTINCT service FROM bookings WHERE service IS NOT NULL AND service != '' ORDER BY service ASC");
        while ($s = $servicesQuery->fetchColumn()) {
            if (!empty(trim($s))) {
                $uniqueServices[] = trim($s);
            }
        }
    } catch (PDOException $e) {
        $connectionError = 'Database query failed: ' . $e->getMessage();
    }
}

// Default standard pharmacy services
$standardServices = [
    'Travel Clinic & Vaccinations',
    'Pharmacy First Consultation',
    'Oral Contraception Service',
    'Ear Wax Removal (Microsuction)',
    'Diagnostic & Blood Tests',
    'Weight Management Consultation',
    'Blood Pressure Screening',
    'Prescription Home Delivery',
    'New Medicine Service (NMS)',
    'Smoking Cessation Support',
    'General Health Check'
];

foreach ($standardServices as $std) {
    if (!in_array($std, $uniqueServices)) {
        $uniqueServices[] = $std;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointments &amp; Clinical Dashboard | Winchester Health Centre</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Lora:wght@500;600&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --bg-app:        #f4f6f8;
      --bg-sidebar:    #ffffff;
      --bg-card:       #ffffff;
      --border:        #e2e8f0;
      --border-light:  #edf2f7;
      --border-dark:   #cbd5e1;

      --text-main:     #1e293b;
      --text-muted:    #64748b;
      --text-subtle:   #94a3b8;
      --text-dark:     #0f172a;

      --primary:       #0d9488;
      --primary-dark:  #0f766e;
      --primary-light: #ccfbf1;
      --primary-tint:  #f0fdfa;

      --sky:           #0284c7;
      --sky-light:     #e0f2fe;
      --sky-dark:      #0369a1;

      --amber:         #d97706;
      --amber-light:   #fef3c7;
      --amber-text:    #92400e;

      --green:         #16a34a;
      --green-light:   #dcfce7;
      --green-text:    #166534;

      --red:           #dc2626;
      --red-light:     #fee2e2;
      --red-text:      #991b1b;

      --purple:        #7c3aed;
      --purple-light:  #ede9fe;

      --sidebar-w:     250px;
      --header-h:      64px;
      --shadow-sm:     0 1px 3px rgba(0,0,0,0.05);
      --shadow-md:     0 4px 12px rgba(0,0,0,0.07);
      --shadow-lg:     0 10px 30px rgba(0,0,0,0.12);
      --radius-sm:     6px;
      --radius-md:     10px;
      --radius-lg:     14px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg-app);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      font-size: 14px;
      line-height: 1.5;
      overflow-x: hidden;
    }

    /* ══════════════════════════════════════════════════════════════
       SIDEBAR NAVIGATION
       ══════════════════════════════════════════════════════════════ */
    .sidebar {
      width: var(--sidebar-w);
      min-width: var(--sidebar-w);
      background: var(--bg-sidebar);
      border-right: 1px solid var(--border);
      height: 100vh;
      position: sticky;
      top: 0;
      display: flex;
      flex-direction: column;
      z-index: 120;
      transition: width 0.2s ease;
    }

    .sidebar-header {
      height: var(--header-h);
      padding: 0 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      border-bottom: 1px solid var(--border-light);
      cursor: pointer;
    }

    .sidebar-logo-icon {
      width: 34px;
      height: 34px;
      border-radius: 8px;
      background: linear-gradient(135deg, #0d9488, #0284c7);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-weight: 800;
      font-size: 1.1rem;
      flex-shrink: 0;
    }

    .sidebar-brand-name {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-dark);
      white-space: nowrap;
    }

    .sidebar-brand-sub {
      font-size: 0.72rem;
      color: var(--text-muted);
      font-weight: 500;
      display: block;
    }

    .sidebar-menu {
      flex: 1;
      padding: 1rem 0.75rem;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    .nav-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.65rem 0.85rem;
      border-radius: var(--radius-md);
      color: var(--text-muted);
      text-decoration: none;
      font-weight: 500;
      font-size: 0.88rem;
      transition: all 0.15s ease;
      cursor: pointer;
      border: 1px solid transparent;
      user-select: none;
    }

    .nav-item:hover {
      background: #f1f5f9;
      color: var(--text-dark);
    }

    .nav-item.active {
      background: #e6f4f2;
      color: #0f766e;
      font-weight: 700;
      border: 1px solid #c7ece6;
    }

    .nav-item-left {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .nav-icon {
      font-size: 1.15rem;
      width: 22px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .nav-chevron {
      font-size: 0.7rem;
      color: var(--text-subtle);
      transition: transform 0.2s;
    }

    .nav-submenu {
      display: none;
      flex-direction: column;
      padding-left: 2.25rem;
      gap: 0.2rem;
      margin-top: 0.15rem;
      margin-bottom: 0.35rem;
    }

    .nav-submenu.open {
      display: flex;
    }

    .nav-subitem {
      padding: 0.4rem 0.65rem;
      border-radius: 6px;
      font-size: 0.82rem;
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.15s;
    }

    .nav-subitem:hover {
      color: var(--primary-dark);
      background: #f0fdfa;
      font-weight: 600;
    }

    .sidebar-footer {
      padding: 0.85rem 0.75rem;
      border-top: 1px solid var(--border-light);
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    /* ══════════════════════════════════════════════════════════════
       MAIN CONTENT WRAPPER
       ══════════════════════════════════════════════════════════════ */
    .main-wrapper {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      background: #f8fafc;
    }

    .top-navbar {
      height: var(--header-h);
      background: #ffffff;
      border-bottom: 1px solid var(--border);
      padding: 0 1.75rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .top-left-group {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .user-pill-greeting {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      font-size: 0.88rem;
      color: var(--text-main);
    }

    .user-avatar-circle {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #e2e8f0;
      color: #334155;
      font-weight: 700;
      font-size: 0.8rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .change-user-link {
      font-size: 0.8rem;
      color: var(--sky);
      text-decoration: none;
      margin-left: 0.25rem;
      cursor: pointer;
    }
    .change-user-link:hover { text-decoration: underline; }

    .top-right-group {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .location-selector {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-dark);
      background: #f1f5f9;
      padding: 0.45rem 0.85rem;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
    }

    .global-search-input-box {
      position: relative;
      width: 220px;
    }

    .global-search-input-box input {
      width: 100%;
      padding: 0.45rem 0.85rem 0.45rem 2.2rem;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      background: #ffffff;
      font-size: 0.85rem;
      color: var(--text-main);
      outline: none;
      transition: all 0.15s ease;
    }

    .global-search-input-box input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
      width: 280px;
    }

    .global-search-input-box span {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-subtle);
      font-size: 0.85rem;
      pointer-events: none;
    }

    .page-content {
      padding: 1.5rem 1.75rem 2.5rem;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }

    .page-title-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .page-title {
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--text-dark);
      letter-spacing: -0.02em;
    }

    /* ══════════════════════════════════════════════════════════════
       FILTER PILL BAR
       ══════════════════════════════════════════════════════════════ */
    .filter-pills-bar {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.65rem;
    }

    .filter-label {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text-muted);
      margin-right: 0.25rem;
    }

    .filter-pill-select-wrapper { position: relative; }

    .filter-pill-select {
      appearance: none;
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.45rem 1.85rem 0.45rem 0.75rem;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-main);
      cursor: pointer;
      outline: none;
      transition: all 0.15s ease;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.55rem center;
      background-size: 0.8rem;
    }

    .filter-pill-select:hover {
      border-color: var(--border-dark);
      background-color: #f8fafc;
    }

    .filter-pill-select:focus,
    .filter-pill-select.active-filter {
      border-color: var(--primary);
      background-color: #f0fdfa;
      color: var(--primary-dark);
    }

    .filter-badge-counter {
      background: #0d9488;
      color: #fff;
      font-size: 0.7rem;
      font-weight: 700;
      padding: 0.15rem 0.45rem;
      border-radius: 50px;
      margin-left: 0.35rem;
    }

    .btn-clear-filters {
      background: none;
      border: 1px dashed var(--border-dark);
      padding: 0.42rem 0.75rem;
      border-radius: var(--radius-sm);
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--text-muted);
      cursor: pointer;
      display: none;
      align-items: center;
      gap: 0.35rem;
      transition: all 0.15s;
    }

    .btn-clear-filters:hover {
      background: #fee2e2;
      color: #991b1b;
      border-color: #fca5a5;
    }

    /* ══════════════════════════════════════════════════════════════
       SUB-TABS AND ACTION BAR
       ══════════════════════════════════════════════════════════════ */
    .subtabs-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--border);
      padding-bottom: 0.25rem;
      margin-top: 0.25rem;
    }

    .subtabs-left {
      display: flex;
      align-items: center;
      gap: 1.75rem;
    }

    .subtab-btn {
      background: none;
      border: none;
      padding: 0.65rem 0;
      font-size: 0.92rem;
      font-weight: 600;
      color: var(--text-muted);
      cursor: pointer;
      position: relative;
      transition: color 0.15s;
      outline: none;
    }

    .subtab-btn:hover { color: var(--text-dark); }

    .subtab-btn.active {
      color: var(--primary-dark);
      font-weight: 700;
    }

    .subtab-btn.active::after {
      content: '';
      position: absolute;
      bottom: -0.3rem;
      left: 0;
      right: 0;
      height: 2.5px;
      background: var(--primary);
      border-radius: 3px;
    }

    .subtabs-right-actions {
      display: flex;
      align-items: center;
      gap: 0.65rem;
    }

    .btn-sub-action {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.42rem 0.85rem;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-main);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.15s;
    }

    .btn-sub-action:hover {
      background: #f8fafc;
      border-color: var(--border-dark);
    }

    /* ══════════════════════════════════════════════════════════════
       CALENDAR NAVIGATION & VIEW SWITCHER BAR
       ══════════════════════════════════════════════════════════════ */
    .calendar-controls-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
      padding: 0.75rem 0;
    }

    .cal-nav-group {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-nav-step {
      width: 32px;
      height: 32px;
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: var(--text-main);
      font-weight: 700;
      font-size: 0.95rem;
      transition: all 0.15s;
    }

    .btn-nav-step:hover {
      background: #f1f5f9;
      border-color: var(--border-dark);
    }

    .btn-nav-today {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0 0.85rem;
      height: 32px;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-main);
      cursor: pointer;
      transition: all 0.15s;
    }

    .btn-nav-today:hover { background: #f1f5f9; }

    .view-switcher-group {
      display: flex;
      align-items: center;
      background: #e2e8f0;
      padding: 3px;
      border-radius: var(--radius-sm);
      gap: 2px;
    }

    .btn-view-pill {
      background: none;
      border: none;
      padding: 0.35rem 0.85rem;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-muted);
      border-radius: 4px;
      cursor: pointer;
      transition: all 0.15s ease;
      outline: none;
    }

    .btn-view-pill:hover { color: var(--text-dark); }

    .btn-view-pill.active {
      background: #0f766e;
      color: #ffffff;
      font-weight: 700;
      box-shadow: var(--shadow-sm);
    }

    .current-date-range-text {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--text-dark);
      min-width: 170px;
      text-align: center;
    }

    .cal-actions-right {
      display: flex;
      align-items: center;
      gap: 0.65rem;
    }

    .btn-block-time {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.45rem 0.95rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-main);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.15s;
    }

    .btn-block-time:hover {
      background: #f8fafc;
      border-color: var(--border-dark);
    }

    .btn-create-appointment {
      background: #0d9488;
      border: 1px solid #0f766e;
      border-radius: var(--radius-sm);
      padding: 0.45rem 1.1rem;
      font-size: 0.85rem;
      font-weight: 700;
      color: #ffffff;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.15s;
      box-shadow: 0 1px 3px rgba(13, 148, 136, 0.3);
    }

    .btn-create-appointment:hover { background: #0f766e; }

    .timezone-indicator {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--text-muted);
      background: #e2e8f0;
      padding: 0.4rem 0.65rem;
      border-radius: var(--radius-sm);
    }

    /* ══════════════════════════════════════════════════════════════
       CALENDAR WEEK VIEW GRID
       ══════════════════════════════════════════════════════════════ */
    .calendar-grid-card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      overflow-x: auto;
      position: relative;
    }

    .week-calendar-container {
      min-width: 960px;
      display: flex;
      flex-direction: column;
    }

    .week-header-row {
      display: grid;
      grid-template-columns: 80px repeat(7, 1fr);
      border-bottom: 1px solid var(--border);
      background: #ffffff;
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .week-time-gutter-header {
      border-right: 1px solid var(--border);
      background: #fafbfc;
    }

    .week-day-col-header {
      padding: 0.75rem 0.5rem;
      text-align: center;
      border-right: 1px solid var(--border);
      color: var(--text-muted);
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .week-day-col-header:last-child { border-right: none; }

    .week-day-col-header .day-number {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-left: 0.35rem;
    }

    .week-day-col-header.is-today {
      background: #f0fdfa;
      color: var(--primary-dark);
    }

    .week-day-col-header.is-today .day-number {
      color: var(--primary-dark);
      background: var(--primary-light);
      padding: 0.15rem 0.45rem;
      border-radius: 50px;
    }

    .week-grid-body {
      display: flex;
      flex-direction: column;
      position: relative;
    }

    .week-hour-row {
      display: grid;
      grid-template-columns: 80px repeat(7, 1fr);
      min-height: 68px;
      border-bottom: 1px solid #f1f5f9;
      position: relative;
    }

    .week-hour-row:hover { background: #fbfcfd; }

    .week-time-label-cell {
      border-right: 1px solid var(--border);
      padding: 0.5rem 0.65rem;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--text-muted);
      text-align: right;
      background: #fafbfc;
      user-select: none;
    }

    .week-slot-cell {
      border-right: 1px solid #f1f5f9;
      position: relative;
      padding: 3px 4px;
      min-height: 68px;
      transition: background 0.1s;
      cursor: pointer;
    }

    .week-slot-cell:hover { background: rgba(13, 148, 136, 0.03); }
    .week-slot-cell:last-child { border-right: none; }
    .week-slot-cell.is-today { background: rgba(240, 253, 250, 0.35); }

    .current-time-indicator-line {
      position: absolute;
      left: 0;
      right: 0;
      height: 2px;
      background: #ef4444;
      z-index: 8;
      pointer-events: none;
    }

    .current-time-dot {
      position: absolute;
      left: -4px;
      top: -4px;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #ef4444;
      box-shadow: 0 0 0 2px #ffffff;
    }

    /* ══════════════════════════════════════════════════════════════
       APPOINTMENT CARDS
       ══════════════════════════════════════════════════════════════ */
    .appt-card {
      background: #dbeefd;
      border: 1px solid #b9ddf9;
      border-left: 3.5px solid #0284c7;
      border-radius: 6px;
      padding: 5px 8px;
      margin-bottom: 4px;
      font-size: 0.75rem;
      line-height: 1.25;
      cursor: pointer;
      transition: all 0.15s ease;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
      position: relative;
      z-index: 5;
    }

    .appt-card:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      border-color: #0284c7;
    }

    .appt-card.status-pending {
      background: #fef3c7;
      border-color: #fde68a;
      border-left-color: #d97706;
    }

    .appt-card.status-completed {
      background: #dcfce7;
      border-color: #bbf7d0;
      border-left-color: #16a34a;
    }

    .appt-card.status-cancelled {
      background: #f1f5f9;
      border-color: #e2e8f0;
      border-left-color: #94a3b8;
      opacity: 0.75;
      text-decoration: line-through;
    }

    .appt-card-top-row {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      margin-bottom: 2px;
    }

    .appt-card-time {
      font-weight: 800;
      color: #0f172a;
      font-size: 0.78rem;
    }

    .appt-tag-clinical {
      background: #0284c7;
      color: #ffffff;
      font-size: 0.65rem;
      font-weight: 800;
      padding: 1px 4px;
      border-radius: 3px;
      display: inline-flex;
      align-items: center;
      gap: 2px;
    }

    .appt-tag-private {
      background: #64748b;
      color: #ffffff;
      font-size: 0.65rem;
      font-weight: 800;
      padding: 1px 4px;
      border-radius: 3px;
    }

    .appt-card-service {
      font-weight: 600;
      color: #334155;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
    }

    .appt-card-patient {
      font-weight: 700;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
      font-size: 0.8rem;
    }

    /* ══════════════════════════════════════════════════════════════
       MONTH VIEW GRID
       ══════════════════════════════════════════════════════════════ */
    .month-calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      background: #ffffff;
      overflow: hidden;
    }

    .month-day-col-header {
      background: #f8fafc;
      padding: 0.75rem 0.5rem;
      text-align: center;
      font-weight: 700;
      font-size: 0.8rem;
      color: var(--text-muted);
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--border);
    }
    .month-day-col-header:last-child { border-right: none; }

    .month-cell {
      min-height: 110px;
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--border);
      padding: 6px;
      position: relative;
      cursor: pointer;
      transition: background 0.15s;
    }

    .month-cell:hover { background: #f8fafc; }
    .month-cell:nth-child(7n) { border-right: none; }
    .month-cell.is-outside-month { background: #fafbfc; opacity: 0.45; }
    .month-cell.is-today { background: #f0fdfa; }

    .month-date-number {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 4px;
      display: inline-block;
    }

    .month-cell.is-today .month-date-number {
      background: var(--primary);
      color: #fff;
      padding: 1px 6px;
      border-radius: 50px;
    }

    .month-appt-chip {
      background: #e0f2fe;
      border-left: 3px solid #0284c7;
      border-radius: 4px;
      padding: 2px 5px;
      font-size: 0.72rem;
      margin-bottom: 3px;
      font-weight: 600;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
    }

    /* ══════════════════════════════════════════════════════════════
       LIST / DATA TABLE
       ══════════════════════════════════════════════════════════════ */
    .list-table-card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      overflow-x: auto;
    }

    table.data-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    table.data-table th {
      background: #f8fafc;
      color: var(--text-muted);
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 0.85rem 1.25rem;
      border-bottom: 1px solid var(--border);
    }

    table.data-table td {
      padding: 0.95rem 1.25rem;
      border-bottom: 1px solid var(--border-light);
      font-size: 0.88rem;
      color: var(--text-main);
      vertical-align: middle;
    }

    table.data-table tr:hover td { background: #fafbfc; }

    .patient-cell-name {
      font-weight: 700;
      color: var(--text-dark);
      display: block;
      font-size: 0.92rem;
    }

    .patient-cell-sub {
      font-size: 0.78rem;
      color: var(--text-muted);
      display: block;
      margin-top: 2px;
    }

    .contact-cell-row {
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    .contact-cell-row a {
      color: var(--sky);
      text-decoration: none;
      font-weight: 600;
    }

    .wa-btn-icon {
      color: #25d366;
      font-size: 1.1rem;
      display: inline-flex;
      align-items: center;
      transition: transform 0.15s;
    }

    .wa-btn-icon:hover { transform: scale(1.2); }

    .service-pill-tag {
      font-weight: 600;
      color: var(--text-dark);
      font-size: 0.85rem;
    }

    .status-badge-pill {
      display: inline-block;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      padding: 0.25rem 0.65rem;
      border-radius: 50px;
      text-align: center;
    }

    .status-badge-pill.status-pending { background: var(--amber-light); color: var(--amber-text); }
    .status-badge-pill.status-confirmed { background: var(--sky-light); color: var(--sky-dark); }
    .status-badge-pill.status-completed { background: var(--green-light); color: var(--green-text); }
    .status-badge-pill.status-cancelled { background: var(--red-light); color: var(--red-text); }

    .actions-btn-group {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.35rem;
    }

    .btn-row-action {
      width: 30px;
      height: 30px;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: #ffffff;
      cursor: pointer;
      font-size: 0.88rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.15s;
    }

    .btn-row-action:hover {
      background: #f1f5f9;
      border-color: var(--border-dark);
    }

    .btn-row-action.act-confirm:hover { background: #e0f2fe; color: #0284c7; border-color: #0284c7; }
    .btn-row-action.act-reschedule:hover { background: #fef3c7; color: #b45309; border-color: #f59e0b; }
    .btn-row-action.act-complete:hover { background: #dcfce7; color: #16a34a; border-color: #16a34a; }
    .btn-row-action.act-cancel:hover { background: #fee2e2; color: #dc2626; border-color: #dc2626; }

    /* Orders Queue Controls & Status Tabs */
    .orders-controls-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1.25rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border);
    }
    .orders-status-tabs {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .orders-tab-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.45rem 0.85rem;
      border-radius: 50px;
      font-size: 0.82rem;
      font-weight: 600;
      border: 1px solid var(--border);
      background: #ffffff;
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.15s ease;
      user-select: none;
    }
    .orders-tab-btn:hover {
      background: #f8fafc;
      color: var(--text-dark);
      border-color: var(--border-dark);
    }
    .orders-tab-btn.active {
      background: #0f766e;
      color: #ffffff;
      border-color: #0f766e;
      box-shadow: 0 2px 4px rgba(15, 118, 110, 0.2);
    }
    .orders-tab-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.1rem 0.45rem;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 700;
      background: #f1f5f9;
      color: #475569;
    }
    .orders-tab-btn.active .orders-tab-badge {
      background: rgba(255, 255, 255, 0.25);
      color: #ffffff;
    }
    .orders-search-wrapper {
      position: relative;
      min-width: 280px;
      max-width: 360px;
      flex: 1;
    }
    .orders-search-input {
      width: 100%;
      padding: 0.5rem 2rem 0.5rem 2.2rem;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 0.85rem;
      outline: none;
      transition: all 0.15s ease;
      box-sizing: border-box;
    }
    .orders-search-input:focus {
      border-color: #0d9488;
      box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
    }
    .orders-search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 0.85rem;
      color: var(--text-muted);
      pointer-events: none;
    }
    .orders-search-clear {
      position: absolute;
      right: 0.6rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      font-size: 0.9rem;
      display: none;
    }
    .voice-ai-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #fde68a;
      font-size: 0.68rem;
      font-weight: 700;
      padding: 1px 6px;
      border-radius: 4px;
      margin-top: 3px;
    }

    /* ══════════════════════════════════════════════════════════════
       GENERIC CARD & SECTION STYLES
       ══════════════════════════════════════════════════════════════ */
    .dashboard-panel-card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 1.5rem;
      box-shadow: var(--shadow-sm);
    }

    .panel-header-flex {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.25rem;
    }

    /* ══════════════════════════════════════════════════════════════
       MODALS & DIALOGS
       ══════════════════════════════════════════════════════════════ */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(4px);
    }

    .modal-backdrop.show { display: flex; }

    .modal-panel {
      background: #ffffff;
      border-radius: var(--radius-lg);
      width: 520px;
      max-width: 92%;
      max-height: 90vh;
      overflow-y: auto;
      padding: 1.75rem;
      box-shadow: var(--shadow-lg);
      animation: modalSlide 0.2s ease-out;
    }

    @keyframes modalSlide {
      from { opacity: 0; transform: translateY(12px) scale(0.98); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.25rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid var(--border-light);
    }

    .modal-title {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--text-dark);
    }

    .modal-close-btn {
      background: none;
      border: none;
      font-size: 1.25rem;
      color: var(--text-muted);
      cursor: pointer;
      line-height: 1;
    }

    .form-group-item { margin-bottom: 1rem; }

    .form-group-item label {
      display: block;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 0.35rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .form-input-field,
    .form-select-field,
    .form-textarea-field {
      width: 100%;
      padding: 0.65rem 0.85rem;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: inherit;
      font-size: 0.88rem;
      color: var(--text-dark);
      background: #ffffff;
      outline: none;
      transition: border-color 0.15s;
    }

    .form-input-field:focus,
    .form-select-field:focus,
    .form-textarea-field:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
    }

    .form-grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
    }

    .modal-actions-row {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.75rem;
      margin-top: 1.5rem;
      padding-top: 1rem;
      border-top: 1px solid var(--border-light);
    }

    .btn-modal-secondary {
      background: #f1f5f9;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.6rem 1.1rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-muted);
      cursor: pointer;
    }

    .btn-modal-primary {
      background: var(--primary);
      border: 1px solid var(--primary-dark);
      border-radius: var(--radius-sm);
      padding: 0.6rem 1.25rem;
      font-size: 0.85rem;
      font-weight: 700;
      color: #ffffff;
      cursor: pointer;
    }

    /* Print Stylesheet */
    @media print {
      .sidebar, .top-navbar, .filter-pills-bar, .subtabs-bar, .cal-actions-right, .cal-nav-group, .actions-btn-group {
        display: none !important;
      }
      body, .main-wrapper, .page-content {
        background: #fff !important;
        padding: 0 !important;
      }
      .calendar-grid-card, .list-table-card {
        box-shadow: none !important;
        border: none !important;
      }
    }
  </style>
</head>
<body>

  <!-- ══════════════════════════════════════════════════════════════
       LEFT SIDEBAR
       ══════════════════════════════════════════════════════════════ -->
  <aside class="sidebar">
    <div class="sidebar-header" onclick="selectSidebarView('appointments')">
      <div class="sidebar-logo-icon">W</div>
      <div>
        <div class="sidebar-brand-name">Winchester Health</div>
        <span class="sidebar-brand-sub">Practice Console</span>
      </div>
    </div>

    <div class="sidebar-menu">
      <!-- 1. Orders -->
      <a class="nav-item" id="side_nav_orders" onclick="selectSidebarView('orders')" title="Orders &amp; Prescriptions">
        <div class="nav-item-left">
          <span class="nav-icon">🛒</span>
          <span class="nav-item-title">Orders</span>
        </div>
        <span class="orders-tab-badge" id="side_badge_orders" style="display: <?php echo $stats['pending'] > 0 ? 'inline-flex' : 'none'; ?>; background: var(--amber-light); color: var(--amber-text);"><?php echo $stats['pending']; ?></span>
      </a>

      <!-- 2. Communications -->
      <a class="nav-item" id="side_nav_communications" onclick="selectSidebarView('communications')" title="Patient Communications">
        <div class="nav-item-left">
          <span class="nav-icon">💬</span>
          <span class="nav-item-title">Communications</span>
        </div>
      </a>

      <!-- 3. Appointments (Default Active) -->
      <a class="nav-item active" id="side_nav_appointments" onclick="selectSidebarView('appointments')" title="Appointments Calendar">
        <div class="nav-item-left">
          <span class="nav-icon">📅</span>
          <span class="nav-item-title">Appointments</span>
        </div>
      </a>

      <!-- 4. Patients -->
      <a class="nav-item" id="side_nav_patients" onclick="selectSidebarView('patients')" title="Patients Directory">
        <div class="nav-item-left">
          <span class="nav-icon">👥</span>
          <span class="nav-item-title">Patients</span>
        </div>
      </a>

      <!-- 5. Campaigns -->
      <a class="nav-item" id="side_nav_campaigns" onclick="selectSidebarView('campaigns')" title="Clinical Campaigns">
        <div class="nav-item-left">
          <span class="nav-icon">📢</span>
          <span class="nav-item-title">Campaigns</span>
        </div>
      </a>

      <!-- 6. Services (Accordion & Direct View) -->
      <div>
        <a class="nav-item" id="side_nav_services" onclick="toggleSidebarAccordion('submenu_services', 'services')" title="Clinic Services">
          <div class="nav-item-left">
            <span class="nav-icon">💼</span>
            <span class="nav-item-title">Services</span>
          </div>
          <span class="nav-chevron" id="chevron_services">▼</span>
        </a>
        <div class="nav-submenu" id="submenu_services">
          <div class="nav-subitem" onclick="filterBySpecificService('Travel Clinic & Vaccinations')">✈️ Travel Clinic</div>
          <div class="nav-subitem" onclick="filterBySpecificService('Pharmacy First Consultation')">💊 Pharmacy First</div>
          <div class="nav-subitem" onclick="filterBySpecificService('Oral Contraception Service')">🩺 Contraception</div>
          <div class="nav-subitem" onclick="filterBySpecificService('Ear Wax Removal')">👂 Ear Microsuction</div>
          <div class="nav-subitem" onclick="filterBySpecificService('')">📋 View All Services</div>
        </div>
      </div>

      <!-- 7. Pharmacy Admin -->
      <div>
        <a class="nav-item" id="side_nav_pharmacy" onclick="toggleSidebarAccordion('submenu_pharmacy', 'pharmacy')" title="Pharmacy Admin">
          <div class="nav-item-left">
            <span class="nav-icon">🏥</span>
            <span class="nav-item-title">Pharmacy Admin</span>
          </div>
          <span class="nav-chevron" id="chevron_pharmacy">▼</span>
        </a>
        <div class="nav-submenu" id="submenu_pharmacy">
          <div class="nav-subitem" onclick="selectSidebarView('pharmacy_dispensing')">💊 Dispensing Log</div>
          <div class="nav-subitem" onclick="selectSidebarView('pharmacy_register')">📋 Responsible Pharmacist</div>
        </div>
      </div>

      <!-- 9. Reports -->
      <div>
        <a class="nav-item" id="side_nav_reports" onclick="toggleSidebarAccordion('submenu_reports', 'analytics')" title="Reports &amp; Analytics">
          <div class="nav-item-left">
            <span class="nav-icon">📊</span>
            <span class="nav-item-title">Reports</span>
          </div>
          <span class="nav-chevron" id="chevron_reports">▼</span>
        </a>
        <div class="nav-submenu" id="submenu_reports">
          <div class="nav-subitem" onclick="selectSidebarView('analytics')">📈 Volume &amp; Demand</div>
          <div class="nav-subitem" onclick="exportCSVData()">📤 Export Audit CSV</div>
        </div>
      </div>

      <!-- 10. Labels Printing -->
      <a class="nav-item" id="side_nav_labels" onclick="selectSidebarView('labels')" title="Labels Printing Station">
        <div class="nav-item-left">
          <span class="nav-icon">🖨️</span>
          <span class="nav-item-title">Labels Printing</span>
        </div>
      </a>
    </div>

    <div class="sidebar-footer">
      <a class="nav-item" onclick="openSettingsModal()" title="Security Settings">
        <div class="nav-item-left">
          <span class="nav-icon">⚙️</span>
          <span class="nav-item-title">Settings</span>
        </div>
      </a>
      <a href="logout" class="nav-item" title="Sign Out">
        <div class="nav-item-left">
          <span class="nav-icon">🚪</span>
          <span class="nav-item-title">Sign Out</span>
        </div>
      </a>
    </div>
  </aside>

  <!-- ══════════════════════════════════════════════════════════════
       MAIN CONTENT
       ══════════════════════════════════════════════════════════════ -->
  <div class="main-wrapper">

    <!-- TOP NAVBAR -->
    <header class="top-navbar">
      <div class="top-left-group">
        <div class="user-pill-greeting">
          <div class="user-avatar-circle"><?php echo strtoupper(substr($adminUsername, 0, 2)); ?></div>
          <span>Welcome, <strong><?php echo htmlspecialchars($adminUsername); ?></strong>!</span>
          <a href="logout" class="change-user-link">change user</a>
        </div>
      </div>

      <div class="top-right-group">
        <div class="location-selector" title="Selected Practice Location">
          <span>📍</span>
          <span>Winchester Health Centre</span>
        </div>

        <form role="search" class="global-search-input-box" onsubmit="event.preventDefault();" autocomplete="off">
          <span>🔍</span>
          <input type="search" id="global_search_input" name="site_filter_search_term" placeholder="Search..." oninput="handleSearchInput(this.value)" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" readonly onfocus="this.removeAttribute('readonly');" data-lpignore="true" data-form-type="other">
        </form>
      </div>
    </header>

    <!-- PAGE MAIN CONTENT -->
    <main class="page-content">

      <!-- Page Title -->
      <div class="page-title-row">
        <h1 class="page-title" id="main_view_title">Appointments</h1>
      </div>

      <!-- FILTER PILL BAR -->
      <div class="filter-pills-bar" id="top_filter_pills_bar">
        <span class="filter-label">Filters:</span>

        <div class="filter-pill-select-wrapper">
          <select id="filter_package" class="filter-pill-select" onchange="applyAllFilters()">
            <option value="">Package: All</option>
            <option value="Single Consultation">Package: Single Consultation</option>
            <option value="Travel Vaccine Course">Package: Travel Vaccine Course</option>
            <option value="Annual Health Check">Package: Annual Health Check</option>
          </select>
        </div>

        <div class="filter-pill-select-wrapper">
          <select id="filter_service" class="filter-pill-select" onchange="applyAllFilters()">
            <option value="">Services: All</option>
            <?php foreach ($uniqueServices as $srv): ?>
              <option value="<?php echo htmlspecialchars($srv); ?>">Service: <?php echo htmlspecialchars($srv); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-pill-select-wrapper">
          <select id="filter_room" class="filter-pill-select" onchange="applyAllFilters()">
            <option value="">Rooms: All</option>
            <option value="Room 1">Rooms: Consultation Room 1</option>
            <option value="Room 2">Rooms: Travel Clinic Room 2</option>
            <option value="Room 3">Rooms: Diagnostics Room 3</option>
          </select>
        </div>

        <div class="filter-pill-select-wrapper">
          <select id="filter_type" class="filter-pill-select" onchange="applyAllFilters()">
            <option value="">Type: All</option>
            <option value="Clinical">Type: Clinical</option>
            <option value="Private">Type: Private</option>
          </select>
        </div>

        <div class="filter-pill-select-wrapper">
          <select id="filter_status" class="filter-pill-select" onchange="applyAllFilters()">
            <option value="">Status: All</option>
            <option value="Pending">Status: Pending</option>
            <option value="Confirmed">Status: Confirmed</option>
            <option value="Completed">Status: Completed</option>
            <option value="Cancelled">Status: Cancelled</option>
          </select>
        </div>

        <button id="btn_clear_filters" class="btn-clear-filters" onclick="resetAllFilters()">
          ✕ Clear Filters <span id="active_filters_count" class="filter-badge-counter">0</span>
        </button>
      </div>

      <!-- SUB-TABS (Appointments | Intake Forms | Patients | Reports) -->
      <div class="subtabs-bar" id="subtabs_nav_bar">
        <div class="subtabs-left">
          <button class="subtab-btn active" id="tab_btn_appointments" onclick="selectSidebarView('appointments')">Appointments</button>
          <button class="subtab-btn" id="tab_btn_intake" onclick="selectSidebarView('intake')">Intake Forms</button>
          <button class="subtab-btn" id="tab_btn_patients" onclick="selectSidebarView('patients')">Patients</button>
          <button class="subtab-btn" id="tab_btn_analytics" onclick="selectSidebarView('analytics')">Reports &amp; Stats</button>
        </div>

        <div class="subtabs-right-actions">
          <button class="btn-sub-action" onclick="window.print()">🖨️ Print</button>
          <button class="btn-sub-action" onclick="exportCSVData()">📤 Export CSV</button>
          <button class="btn-sub-action" onclick="switchViewMode('list')">👁️ View</button>
        </div>
      </div>

      <!-- CALENDAR CONTROLS BAR -->
      <div class="calendar-controls-row" id="calendar_controls_bar">
        <div class="cal-nav-group">
          <button class="btn-nav-step" onclick="navigateDateStep(-1)" title="Previous">&lsaquo;</button>
          <button class="btn-nav-today" id="btn_nav_today" onclick="navigateToToday()">This week</button>
          <button class="btn-nav-step" onclick="navigateDateStep(1)" title="Next">&rsaquo;</button>
        </div>

        <div class="view-switcher-group">
          <button class="btn-view-pill" id="pill_month" onclick="switchViewMode('month')">Month</button>
          <button class="btn-view-pill active" id="pill_week" onclick="switchViewMode('week')">Week</button>
          <button class="btn-view-pill" id="pill_day" onclick="switchViewMode('day')">Day</button>
          <button class="btn-view-pill" id="pill_list" onclick="switchViewMode('list')">List</button>
          <button class="btn-view-pill" id="pill_custom" onclick="openCustomDateModal()">Custom Range</button>
        </div>

        <div class="current-date-range-text" id="display_date_range_title">
          Aug 24 – 30, 2026
        </div>

        <div class="cal-actions-right">
          <button class="btn-block-time" onclick="openBlockTimeModal()">⏱️ Block Time</button>
          <button class="btn-create-appointment" onclick="openCreateAppointmentModal()">+ Create Appointment</button>
          <span class="timezone-indicator">BST ▾</span>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════
           VIEW CONTAINERS
           ══════════════════════════════════════════════════════════════ -->

      <!-- 1. Week Calendar -->
      <div id="week_view_container" class="calendar-grid-card">
        <div class="week-calendar-container">
          <div class="week-header-row" id="week_headers_row">
            <div class="week-time-gutter-header"></div>
          </div>
          <div class="week-grid-body" id="week_grid_body"></div>
        </div>
      </div>

      <!-- 2. Day Calendar -->
      <div id="day_view_container" class="calendar-grid-card" style="display: none;">
        <div class="week-calendar-container" style="min-width: 600px;">
          <div class="week-header-row" id="day_headers_row" style="grid-template-columns: 80px 1fr;">
            <div class="week-time-gutter-header"></div>
            <div class="week-day-col-header" id="day_single_header">TODAY</div>
          </div>
          <div class="week-grid-body" id="day_grid_body"></div>
        </div>
      </div>

      <!-- 3. Month Calendar -->
      <div id="month_view_container" class="month-calendar-grid" style="display: none;">
        <div class="month-day-col-header">MON</div>
        <div class="month-day-col-header">TUE</div>
        <div class="month-day-col-header">WED</div>
        <div class="month-day-col-header">THU</div>
        <div class="month-day-col-header">FRI</div>
        <div class="month-day-col-header">SAT</div>
        <div class="month-day-col-header">SUN</div>
      </div>

      <!-- 4. List Table View -->
      <div id="list_view_container" class="list-table-card" style="display: none;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Patient Details</th>
              <th>Contact Info</th>
              <th>Service</th>
              <th>Appointment Date</th>
              <th style="text-align: center;">Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody id="list_table_tbody"></tbody>
        </table>
      </div>

      <!-- 5. Intake Forms View -->
      <div id="intake_tab_container" style="display: none;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.25rem;" id="intake_reviews_grid"></div>
      </div>

      <!-- 6. Patients Directory View -->
      <div id="patients_tab_container" class="list-table-card" style="display: none;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a;">All Registered Patients</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">View patient contact records, booking history, and quick schedule.</span>
          </div>
          <button class="btn-create-appointment" onclick="openCreateAppointmentModal()">+ Register &amp; Book Patient</button>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Patient Name</th>
              <th>Phone</th>
              <th>Email</th>
              <th>Date of Birth</th>
              <th>Latest Booking</th>
              <th style="text-align: right;">Quick Actions</th>
            </tr>
          </thead>
          <tbody id="patients_table_tbody"></tbody>
        </table>
      </div>

      <!-- 7. Analytics & Reports View -->
      <div id="analytics_tab_container" style="display: none;">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
          <div class="dashboard-panel-card" style="border-left: 4px solid #0d9488;">
            <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Total Bookings</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: #0f766e; margin-top: 4px;"><?php echo $stats['total']; ?></div>
          </div>
          <div class="dashboard-panel-card" style="border-left: 4px solid var(--amber);">
            <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Pending Review</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--amber); margin-top: 4px;"><?php echo $stats['pending']; ?></div>
          </div>
          <div class="dashboard-panel-card" style="border-left: 4px solid var(--sky);">
            <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Confirmed Slots</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--sky); margin-top: 4px;"><?php echo $stats['confirmed']; ?></div>
          </div>
          <div class="dashboard-panel-card" style="border-left: 4px solid var(--green);">
            <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Completed Visits</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--green); margin-top: 4px;"><?php echo $stats['completed']; ?></div>
          </div>
        </div>

        <div class="dashboard-panel-card">
          <h4 style="margin-bottom: 1rem; color: #0f172a; font-size: 1rem;">Service Demand Distribution</h4>
          <div id="service_stats_bars" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
        </div>
      </div>

      <!-- 8. Appointments & Customer Orders Queue View -->
      <div id="orders_tab_container" class="dashboard-panel-card" style="display: none;">
        <div class="panel-header-flex">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
              <span>🛒 Customer Orders &amp; Bookings Queue</span>
            </h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Real-time queue of all patient bookings, prescription requests, and clinic orders.</span>
          </div>
          <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
            <button class="btn-sub-action" onclick="openCreateAppointmentModal()" style="background: #0f766e; color: #fff; border-color: #0f766e; display: inline-flex; align-items: center; gap: 0.35rem;">➕ New Order / Booking</button>
            <button class="btn-sub-action" onclick="exportOrdersCSV()">📥 Export Orders CSV</button>
          </div>
        </div>

        <!-- Orders Filtering Controls Bar -->
        <div class="orders-controls-bar">
          <div class="orders-status-tabs">
            <button class="orders-tab-btn active" id="btn_order_filter_all" onclick="setOrdersStatusFilter('all')">
              All Orders <span class="orders-tab-badge" id="orders_count_all">0</span>
            </button>
            <button class="orders-tab-btn" id="btn_order_filter_pending" onclick="setOrdersStatusFilter('Pending')">
              ⏳ Pending <span class="orders-tab-badge" id="orders_count_pending" style="background: var(--amber-light); color: var(--amber-text);">0</span>
            </button>
            <button class="orders-tab-btn" id="btn_order_filter_confirmed" onclick="setOrdersStatusFilter('Confirmed')">
              ✅ Confirmed <span class="orders-tab-badge" id="orders_count_confirmed" style="background: var(--sky-light); color: var(--sky-dark);">0</span>
            </button>
            <button class="orders-tab-btn" id="btn_order_filter_completed" onclick="setOrdersStatusFilter('Completed')">
              🎉 Completed <span class="orders-tab-badge" id="orders_count_completed" style="background: var(--green-light); color: var(--green-text);">0</span>
            </button>
            <button class="orders-tab-btn" id="btn_order_filter_cancelled" onclick="setOrdersStatusFilter('Cancelled')">
              ❌ Cancelled <span class="orders-tab-badge" id="orders_count_cancelled" style="background: var(--red-light); color: var(--red-text);">0</span>
            </button>
          </div>

          <div class="orders-search-wrapper">
            <span class="orders-search-icon">🔍</span>
            <input type="text" id="orders_search_input" class="orders-search-input" placeholder="Search ref, patient, phone, service..." oninput="onOrdersSearchInput(this.value)">
            <button id="orders_search_clear_btn" class="orders-search-clear" onclick="clearOrdersSearch()" title="Clear search">✕</button>
          </div>
        </div>

        <div style="overflow-x: auto;">
          <table class="data-table" id="orders_table_element">
            <thead>
              <tr>
                <th style="width: 105px;">Order Ref</th>
                <th style="min-width: 190px;">Patient Details</th>
                <th style="min-width: 220px;">Requested Service &amp; Notes</th>
                <th style="width: 155px;">Preferred Date</th>
                <th style="width: 120px;">Submitted</th>
                <th style="width: 100px; text-align: center;">Status</th>
                <th style="width: 165px; text-align: right;">Quick Actions</th>
              </tr>
            </thead>
            <tbody id="orders_table_tbody"></tbody>
          </table>
        </div>

        <div id="orders_empty_state" style="display: none; text-align: center; padding: 3.5rem 1.5rem;">
          <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">📦</div>
          <h4 style="font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">No orders match this filter</h4>
          <p style="font-size: 0.85rem; color: var(--text-muted); max-width: 400px; margin: 0 auto 1.25rem;">There are no booking orders matching your current filter criteria or search query.</p>
          <button class="btn-sub-action" onclick="resetOrdersFilter()">Reset Order Filters</button>
        </div>

        <div id="orders_table_footer" style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: var(--text-muted); flex-wrap: wrap; gap: 0.5rem;">
          <span id="orders_summary_text">Showing 0 orders</span>
          <span>Tip: Click <strong>👁️</strong> to view full patient details, record clinical notes, or print receipts.</span>
        </div>
      </div>

      <!-- 9. Communications View -->
      <div id="communications_tab_container" class="dashboard-panel-card" style="display: none;">
        <div class="panel-header-flex">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a;">Patient Communications Hub</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Send prefilled WhatsApp reminders, SMS confirmations, and clinic updates.</span>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.5rem; margin-top: 1rem;">
          <div>
            <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: #0f172a;">Direct WhatsApp / SMS Dispatch</h4>
            <div class="form-group-item">
              <label>Select Patient</label>
              <select id="comm_patient_select" class="form-select-field" onchange="populateCommFields(this.value)"></select>
            </div>
            <div class="form-group-item">
              <label>Message Template</label>
              <select id="comm_template_select" class="form-select-field" onchange="applyCommTemplate(this.value)">
                <option value="confirm">Appointment Confirmation &amp; Portal Instructions</option>
                <option value="reminder">24-Hour Visit Reminder</option>
                <option value="prescription_ready">Prescription / Medication Ready for Collection</option>
                <option value="followup">Post-Treatment Follow-up Check</option>
              </select>
            </div>
            <div class="form-group-item">
              <label>Message Text</label>
              <textarea id="comm_message_text" class="form-textarea-field" style="height: 110px;"></textarea>
            </div>
            <button class="btn-modal-primary" style="background:#25d366;border-color:#1ebe5a;display:inline-flex;align-items:center;gap:0.4rem;" onclick="dispatchQuickWhatsApp()">
              💬 Send via WhatsApp
            </button>
          </div>

          <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem;">
            <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: #0f172a;">Recent Message Logs</h4>
            <div id="comm_logs_container" style="display: flex; flex-direction: column; gap: 0.65rem; font-size: 0.82rem;"></div>
          </div>
        </div>
      </div>

      <!-- 10. Campaigns View -->
      <div id="campaigns_tab_container" class="dashboard-panel-card" style="display: none;">
        <div class="panel-header-flex">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a;">Clinical Health Campaigns</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Active seasonal outreach and patient health awareness campaigns.</span>
          </div>
          <button class="btn-modal-primary" onclick="alert('Campaign created and saved to practice calendar!')">+ Create Campaign</button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.25rem; margin-top: 1rem;">
          <div style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; background: #f0fdfa;">
            <span style="background:#0d9488;color:#fff;font-size:0.7rem;font-weight:800;padding:2px 6px;border-radius:4px;">ACTIVE</span>
            <h4 style="margin: 0.5rem 0 0.25rem; color: #0f766e;">Autumn Travel Vaccinations 2026</h4>
            <p style="font-size: 0.82rem; color: #334155;">Yellow fever, Typhoid, Hepatitis A &amp; B protection for international travellers.</p>
            <div style="margin-top: 0.75rem; font-size: 0.8rem; font-weight: 700; color: #0d9488;">Engaged Patients: 142</div>
          </div>

          <div style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; background: #e0f2fe;">
            <span style="background:#0284c7;color:#fff;font-size:0.7rem;font-weight:800;padding:2px 6px;border-radius:4px;">ACTIVE</span>
            <h4 style="margin: 0.5rem 0 0.25rem; color: #0369a1;">Pharmacy First Walk-ins</h4>
            <p style="font-size: 0.82rem; color: #334155;">Assessment &amp; prescription medication for 7 common minor conditions without GP queue.</p>
            <div style="margin-top: 0.75rem; font-size: 0.8rem; font-weight: 700; color: #0284c7;">Engaged Patients: 89</div>
          </div>

          <div style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; background: #fef3c7;">
            <span style="background:#d97706;color:#fff;font-size:0.7rem;font-weight:800;padding:2px 6px;border-radius:4px;">ACTIVE</span>
            <h4 style="margin: 0.5rem 0 0.25rem; color: #92400e;">Ear Microsuction Clinic</h4>
            <p style="font-size: 0.82rem; color: #334155;">Safe ultrasonic ear wax removal performed by trained clinicians in Room 2.</p>
            <div style="margin-top: 0.75rem; font-size: 0.8rem; font-weight: 700; color: #d97706;">Engaged Patients: 64</div>
          </div>
        </div>
      </div>

      <!-- 11. Labels Printing Station -->
      <div id="labels_tab_container" class="dashboard-panel-card" style="display: none;">
        <div class="panel-header-flex">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a;">🖨️ Label Printing Station</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Generate compliant pharmacy dispensing labels and appointment slips.</span>
          </div>
          <button class="btn-modal-primary" onclick="window.print()">Print Labels Sheet</button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1rem;">
          <div>
            <div class="form-group-item">
              <label>Select Patient Appointment</label>
              <select id="label_patient_select" class="form-select-field" onchange="renderLabelPreview(this.value)"></select>
            </div>
            <div class="form-group-item">
              <label>Label Type</label>
              <select id="label_type_select" class="form-select-field" onchange="renderLabelPreview()">
                <option value="rx">Pharmacy Dispensing Label</option>
                <option value="appt">Patient Appointment Card Slip</option>
                <option value="vaccine">Vaccination Record Card</option>
              </select>
            </div>
          </div>

          <div>
            <label style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Print Preview</label>
            <div id="label_preview_card" style="background: #fff; border: 2px dashed #94a3b8; border-radius: 8px; padding: 1.25rem; font-family: monospace; font-size: 0.85rem; line-height: 1.4;">
              <!-- Dynamic label preview -->
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>

  <!-- ══════════════════════════════════════════════════════════════
       MODALS
       ══════════════════════════════════════════════════════════════ -->

  <!-- Modal 1: Appointment Details -->
  <div class="modal-backdrop" id="apptDetailsModal" onclick="closeModalOnBackdrop(event, 'apptDetailsModal')">
    <div class="modal-panel" style="width: 580px;">
      <div class="modal-header">
        <div>
          <h3 class="modal-title" id="detail_patient_name">Appointment Details</h3>
          <span style="font-size: 0.8rem; color: var(--text-muted);" id="detail_created_at"></span>
        </div>
        <button class="modal-close-btn" onclick="closeModal('apptDetailsModal')">✕</button>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem; background: #f8fafc; padding: 1rem; border-radius: 8px;">
        <div>
          <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Service</span>
          <div style="font-weight: 700; color: var(--text-dark);" id="detail_service"></div>
        </div>
        <div>
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Scheduled Date &amp; Time</span>
            <button onclick="openRescheduleModal(selectedBookingId)" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a;border-radius:4px;padding:2px 8px;font-size:0.75rem;cursor:pointer;font-weight:700;" title="Reschedule Date/Time">🗓️ Reschedule</button>
          </div>
          <div style="font-weight: 700; color: #0d9488; margin-top: 2px;" id="detail_datetime"></div>
        </div>
        <div>
          <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Contact Phone</span>
          <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;" id="detail_phone_wrapper">
            <a href="#" id="detail_phone_link"></a>
            <button onclick="triggerDetailWhatsApp()" style="background:#25d366;color:#fff;border:none;border-radius:4px;padding:2px 6px;font-size:0.75rem;cursor:pointer;font-weight:700;">WhatsApp 💬</button>
          </div>
        </div>
        <div>
          <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Email / DOB</span>
          <div style="font-size: 0.85rem; color: var(--text-main);" id="detail_email_dob"></div>
        </div>
      </div>

      <div class="form-group-item">
        <label>Patient Request Notes</label>
        <div style="background: #fafbfc; border: 1px solid var(--border); border-radius: 6px; padding: 0.75rem; font-size: 0.85rem; color: #334155; min-height: 48px;" id="detail_patient_notes">
          <em>None provided</em>
        </div>
      </div>

      <div class="form-group-item">
        <label>Internal Staff Notes (Admin Only)</label>
        <textarea id="detail_admin_notes" class="form-textarea-field" style="height: 70px;" placeholder="Add internal consultation notes, phone logs, or action tasks here..."></textarea>
        <div style="display: flex; justify-content: flex-end; margin-top: 0.35rem;">
          <button class="btn-save-inline-note" id="btn_save_detail_notes" onclick="saveActiveDetailNotes()">💾 Save Comments</button>
        </div>
      </div>

      <div style="margin-top: 1.25rem;">
        <label style="display:block;font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.5rem;">Update Appointment Status</label>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
          <button class="btn-modal-secondary" style="background:#e0f2fe;color:#0284c7;border-color:#bae6fd;" onclick="updateActiveDetailStatus('confirm')">✔️ Confirm</button>
          <button class="btn-modal-secondary" style="background:#fef3c7;color:#b45309;border-color:#fde68a;" onclick="openRescheduleModal(selectedBookingId)">🗓️ Reschedule</button>
          <button class="btn-modal-secondary" style="background:#dcfce7;color:#16a34a;border-color:#bbf7d0;" onclick="updateActiveDetailStatus('complete')">👍 Mark Completed</button>
          <button class="btn-modal-secondary" style="background:#fee2e2;color:#dc2626;border-color:#fecaca;" onclick="updateActiveDetailStatus('cancel')">❌ Cancel Slot</button>
          <button class="btn-modal-secondary" style="background:#fff;color:#dc2626;border-color:#fca5a5;margin-left:auto;" onclick="deleteActiveDetailBooking()">🗑️ Delete</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal 2: Create Appointment -->
  <div class="modal-backdrop" id="createAppointmentModal" onclick="closeModalOnBackdrop(event, 'createAppointmentModal')">
    <div class="modal-panel">
      <div class="modal-header">
        <h3 class="modal-title">+ Create New Appointment</h3>
        <button class="modal-close-btn" onclick="closeModal('createAppointmentModal')">✕</button>
      </div>

      <div id="create_modal_error" style="display:none;background:#fee2e2;color:#991b1b;padding:0.65rem;border-radius:6px;font-size:0.85rem;font-weight:600;margin-bottom:1rem;"></div>

      <div class="form-grid-2">
        <div class="form-group-item">
          <label>First Name *</label>
          <input type="text" id="new_first_name" class="form-input-field" placeholder="e.g. Katherine" required>
        </div>
        <div class="form-group-item">
          <label>Last Name</label>
          <input type="text" id="new_last_name" class="form-input-field" placeholder="e.g. Boyd">
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group-item">
          <label>Phone Number *</label>
          <input type="tel" id="new_phone" class="form-input-field" placeholder="e.g. 07123456789" required>
        </div>
        <div class="form-group-item">
          <label>Email Address</label>
          <input type="email" id="new_email" class="form-input-field" placeholder="patient@example.com">
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group-item">
          <label>Service *</label>
          <select id="new_service" class="form-select-field">
            <?php foreach ($uniqueServices as $srv): ?>
              <option value="<?php echo htmlspecialchars($srv); ?>"><?php echo htmlspecialchars($srv); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group-item">
          <label>Date &amp; Time *</label>
          <input type="datetime-local" id="new_preferred_date" class="form-input-field" required>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group-item">
          <label>Initial Status</label>
          <select id="new_status" class="form-select-field">
            <option value="Confirmed">Confirmed</option>
            <option value="Pending">Pending</option>
          </select>
        </div>
        <div class="form-group-item">
          <label>Date of Birth</label>
          <input type="date" id="new_dob" class="form-input-field">
        </div>
      </div>

      <div class="form-group-item">
        <label>Staff Notes / Comments</label>
        <textarea id="new_admin_notes" class="form-textarea-field" style="height: 55px;" placeholder="Optional notes for clinic records..."></textarea>
      </div>

      <div class="modal-actions-row">
        <button class="btn-modal-secondary" onclick="closeModal('createAppointmentModal')">Cancel</button>
        <button class="btn-modal-primary" id="btn_submit_create_appt" onclick="submitCreateAppointment()">Create Appointment</button>
      </div>
    </div>
  </div>

  <!-- Modal 3: Block Time -->
  <div class="modal-backdrop" id="blockTimeModal" onclick="closeModalOnBackdrop(event, 'blockTimeModal')">
    <div class="modal-panel" style="width: 440px;">
      <div class="modal-header">
        <h3 class="modal-title">⏱️ Block Schedule Time</h3>
        <button class="modal-close-btn" onclick="closeModal('blockTimeModal')">✕</button>
      </div>

      <div class="form-group-item">
        <label>Reason for Block</label>
        <input type="text" id="block_reason" class="form-input-field" placeholder="e.g. Staff Lunch Break / Meeting / Clinic Maintenance" value="Staff Break / Unavailable">
      </div>

      <div class="form-group-item">
        <label>Date &amp; Time</label>
        <input type="datetime-local" id="block_datetime" class="form-input-field">
      </div>

      <div class="form-group-item">
        <label>Room / Location</label>
        <select id="block_room" class="form-select-field">
          <option value="All Rooms">All Rooms</option>
          <option value="Room 1">Consultation Room 1</option>
          <option value="Room 2">Travel Clinic Room 2</option>
          <option value="Room 3">Diagnostics Room 3</option>
        </select>
      </div>

      <div class="modal-actions-row">
        <button class="btn-modal-secondary" onclick="closeModal('blockTimeModal')">Cancel</button>
        <button class="btn-modal-primary" onclick="submitBlockTime()">Block Slot</button>
      </div>
    </div>
  </div>

  <!-- Modal 4: Settings -->
  <div class="modal-backdrop" id="settingsModal" onclick="closeModalOnBackdrop(event, 'settingsModal')">
    <div class="modal-panel" style="width: 420px;">
      <div class="modal-header">
        <h3 class="modal-title">⚙️ Practice Settings</h3>
        <button class="modal-close-btn" onclick="closeModal('settingsModal')">✕</button>
      </div>

      <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">Update your administrator panel access credentials.</p>

      <div id="settings_modal_error" style="display:none;background:#fee2e2;color:#991b1b;padding:0.65rem;border-radius:6px;font-size:0.85rem;font-weight:600;margin-bottom:1rem;"></div>

      <form id="settings_password_form" onsubmit="event.preventDefault(); submitChangePassword();" autocomplete="off">
        <!-- Hidden username field so browser password managers pair credentials strictly with this form -->
        <input type="text" name="username" value="<?php echo htmlspecialchars($adminUsername); ?>" autocomplete="username" style="display:none;" aria-hidden="true" tabindex="-1">

        <div class="form-group-item">
          <label>Current Password</label>
          <input type="password" id="old_password" name="old_password" class="form-input-field" placeholder="••••••••" autocomplete="current-password">
        </div>

        <div class="form-group-item">
          <label>New Password</label>
          <input type="password" id="new_password" name="new_password" class="form-input-field" placeholder="Min 8 characters" autocomplete="new-password">
        </div>

        <div class="form-group-item">
          <label>Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-input-field" placeholder="••••••••" autocomplete="new-password">
        </div>

        <div class="modal-actions-row">
          <button type="button" class="btn-modal-secondary" onclick="closeModal('settingsModal')">Cancel</button>
          <button type="submit" class="btn-modal-primary" id="btn_save_password">Save Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal 5: Reschedule Appointment -->
  <div class="modal-backdrop" id="rescheduleModal" onclick="closeModalOnBackdrop(event, 'rescheduleModal')">
    <div class="modal-panel" style="width: 520px;">
      <div class="modal-header">
        <div>
          <h3 class="modal-title">🗓️ Reschedule Appointment</h3>
          <span style="font-size: 0.8rem; color: var(--text-muted);" id="reschedule_modal_subtitle"></span>
        </div>
        <button class="modal-close-btn" onclick="closeModal('rescheduleModal')">✕</button>
      </div>

      <div id="reschedule_modal_error" style="display:none;background:#fee2e2;color:#991b1b;padding:0.65rem;border-radius:6px;font-size:0.85rem;font-weight:600;margin-bottom:1rem;"></div>

      <div style="background: #f0fdfa; border: 1px solid #ccfbf1; padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #0f766e; margin-bottom: 0.35rem;">Current Appointment</div>
        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.25rem;">
          <span style="color: #64748b;">Patient:</span>
          <strong style="color: #1e293b;" id="reschedule_patient_name">—</strong>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.25rem;">
          <span style="color: #64748b;">Service:</span>
          <strong style="color: #0f766e;" id="reschedule_service_name">—</strong>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
          <span style="color: #64748b;">Current Scheduled Time:</span>
          <strong style="color: #d97706;" id="reschedule_current_datetime">—</strong>
        </div>
      </div>

      <div class="form-group-item">
        <label>New Appointment Date &amp; Time *</label>
        <input type="datetime-local" id="reschedule_datetime" class="form-input-field" required>
        <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">Select the revised date and time slot for this patient.</span>
      </div>

      <div class="form-group-item">
        <label>Reason / Note for Patient (Optional)</label>
        <textarea id="reschedule_reason" class="form-textarea-field" style="height: 65px;" placeholder="e.g. Schedule adjusted upon patient phone request, or clinic availability change..."></textarea>
        <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">This note will appear inside the patient's notification email and be recorded in staff internal notes.</span>
      </div>

      <div class="form-group-item" id="reschedule_notify_wrapper" style="background:#f8fafc;padding:0.75rem 1rem;border-radius:6px;border:1px solid #e2e8f0;">
        <label style="display: flex; align-items: flex-start; gap: 0.65rem; cursor: pointer; margin: 0; font-size: 0.85rem; font-weight: 600; color: #1e293b;">
          <input type="checkbox" id="reschedule_notify_customer" checked style="margin-top: 0.15rem; width: 16px; height: 16px; accent-color: #0d9488;">
          <div>
            <div>Notify patient of revised date/time via email</div>
            <span style="font-size: 0.75rem; font-weight: 400; color: #64748b;" id="reschedule_patient_email_display">Sending to: patient@example.com</span>
          </div>
        </label>
      </div>

      <div class="modal-actions-row" style="margin-top: 1.25rem;">
        <button type="button" class="btn-modal-secondary" onclick="closeModal('rescheduleModal')">Cancel</button>
        <button type="button" class="btn-modal-primary" id="btn_submit_reschedule" onclick="submitReschedule()">🗓️ Confirm Reschedule</button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════════
       JAVASCRIPT APP LOGIC
       ══════════════════════════════════════════════════════════════ -->
  <script>
    let allBookings = <?php echo json_encode($bookings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    let currentViewMode = 'week';
    let currentActiveNav = 'appointments';
    let activeDate = new Date();
    let filteredBookings = [...allBookings];
    let selectedBookingId = null;

    let filterValues = {
      search: '',
      package: '',
      service: '',
      room: '',
      type: '',
      status: ''
    };

    document.addEventListener("DOMContentLoaded", function() {
      // Clear global search input on load to prevent browser credential manager autofill
      const searchInput = document.getElementById('global_search_input');
      if (searchInput) {
        searchInput.value = '';
        [50, 150, 300, 500, 1000].forEach(delay => {
          setTimeout(() => {
            if (searchInput.value.toLowerCase() === 'admin') {
              searchInput.value = '';
              applyAllFilters();
            }
          }, delay);
        });
      }
      applyAllFilters();
      updateOrdersCounters();
      populateCommunicationsPatients();
      populateLabelPatients();
    });

    window.addEventListener("pageshow", function() {
      const searchInput = document.getElementById('global_search_input');
      if (searchInput && searchInput.value.toLowerCase() === 'admin') {
        searchInput.value = '';
        applyAllFilters();
      }
      updateOrdersCounters();
    });

    // ══════════════════════════════════════════════════════════════
    // SIDEBAR NAVIGATION & ROUTING
    // ══════════════════════════════════════════════════════════════
    function selectSidebarView(viewKey) {
      currentActiveNav = viewKey;

      // Update sidebar nav active classes
      document.querySelectorAll('.sidebar-menu .nav-item').forEach(el => el.classList.remove('active'));
      const activeNavEl = document.getElementById(`side_nav_${viewKey.split('_')[0]}`);
      if (activeNavEl) activeNavEl.classList.add('active');

      // Update subtab buttons
      document.querySelectorAll('.subtab-btn').forEach(btn => btn.classList.remove('active'));
      const activeSubtab = document.getElementById(`tab_btn_${viewKey}`);
      if (activeSubtab) activeSubtab.classList.add('active');

      // Titles & visibility map
      const titles = {
        'appointments': 'Appointments',
        'intake': 'Patient Intake Forms & Notes',
        'patients': 'Patients Directory',
        'analytics': 'Practice Reports & Analytics',
        'orders': 'Customer Orders & Bookings',
        'communications': 'Patient Communications Hub',
        'campaigns': 'Clinical Health Campaigns',
        'services': 'Clinic Services Directory',
        'pharmacy': 'Pharmacy Administration',
        'pharmacy_dispensing': 'Prescription Dispensing Log',
        'pharmacy_register': 'Responsible Pharmacist Register',
        'labels': 'Labels & Prescription Printing Station'
      };

      document.getElementById('main_view_title').innerText = titles[viewKey] || 'Practice Console';

      // Visibility toggles
      const isApptCalendar = (viewKey === 'appointments');
      document.getElementById('top_filter_pills_bar').style.display = (isApptCalendar || viewKey === 'patients' || viewKey === 'intake') ? 'flex' : 'none';
      document.getElementById('subtabs_nav_bar').style.display = (isApptCalendar || viewKey === 'patients' || viewKey === 'intake' || viewKey === 'analytics') ? 'flex' : 'none';
      document.getElementById('calendar_controls_bar').style.display = isApptCalendar ? 'flex' : 'none';

      // Hide all main containers
      document.getElementById('week_view_container').style.display = 'none';
      document.getElementById('day_view_container').style.display = 'none';
      document.getElementById('month_view_container').style.display = 'none';
      document.getElementById('list_view_container').style.display = 'none';
      document.getElementById('intake_tab_container').style.display = 'none';
      document.getElementById('patients_tab_container').style.display = 'none';
      document.getElementById('analytics_tab_container').style.display = 'none';
      document.getElementById('orders_tab_container').style.display = 'none';
      document.getElementById('communications_tab_container').style.display = 'none';
      document.getElementById('campaigns_tab_container').style.display = 'none';
      document.getElementById('labels_tab_container').style.display = 'none';

      // Show targeted view
      if (viewKey === 'appointments') {
        if (currentViewMode === 'week') document.getElementById('week_view_container').style.display = 'block';
        else if (currentViewMode === 'day') document.getElementById('day_view_container').style.display = 'block';
        else if (currentViewMode === 'month') document.getElementById('month_view_container').style.display = 'grid';
        else if (currentViewMode === 'list') document.getElementById('list_view_container').style.display = 'block';
        renderCurrentView();
      } else if (viewKey === 'patients') {
        document.getElementById('patients_tab_container').style.display = 'block';
        renderPatientsDirectory();
      } else if (viewKey === 'intake') {
        document.getElementById('intake_tab_container').style.display = 'block';
        renderIntakeReviewTab();
      } else if (viewKey === 'analytics') {
        document.getElementById('analytics_tab_container').style.display = 'block';
        renderAnalyticsBars();
      } else if (viewKey === 'orders') {
        document.getElementById('orders_tab_container').style.display = 'block';
        renderOrdersQueue();
      } else if (viewKey === 'communications') {
        document.getElementById('communications_tab_container').style.display = 'block';
        renderCommunicationsHub();
      } else if (viewKey === 'campaigns') {
        document.getElementById('campaigns_tab_container').style.display = 'block';
      } else if (viewKey.startsWith('pharmacy')) {
        document.getElementById('orders_tab_container').style.display = 'block';
        renderOrdersQueue();
      } else if (viewKey === 'labels') {
        document.getElementById('labels_tab_container').style.display = 'block';
        renderLabelPreview();
      } else if (viewKey === 'services') {
        selectSidebarView('appointments');
        document.getElementById('filter_service').focus();
      }
    }

    function toggleSidebarAccordion(submenuId, viewKey) {
      const menu = document.getElementById(submenuId);
      const chevron = document.getElementById(`chevron_${viewKey}`);
      if (menu) {
        menu.classList.toggle('open');
        if (chevron) {
          chevron.style.transform = menu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
      }
      selectSidebarView(viewKey);
    }

    function filterBySpecificService(serviceName) {
      document.getElementById('filter_service').value = serviceName;
      selectSidebarView('appointments');
      applyAllFilters();
    }

    // ══════════════════════════════════════════════════════════════
    // UNIFIED FILTERING ENGINE
    // ══════════════════════════════════════════════════════════════
    function handleSearchInput(val) {
      filterValues.search = (val || '').toLowerCase().trim();
      applyAllFilters();
    }

    function applyAllFilters() {
      filterValues.package = document.getElementById('filter_package').value;
      filterValues.service = document.getElementById('filter_service').value;
      filterValues.room = document.getElementById('filter_room').value;
      filterValues.type = document.getElementById('filter_type').value;
      filterValues.status = document.getElementById('filter_status').value;
      filterValues.search = document.getElementById('global_search_input').value.toLowerCase().trim();

      let activeCount = 0;
      if (filterValues.package) activeCount++;
      if (filterValues.service) activeCount++;
      if (filterValues.room) activeCount++;
      if (filterValues.type) activeCount++;
      if (filterValues.status) activeCount++;
      if (filterValues.search) activeCount++;

      const clearBtn = document.getElementById('btn_clear_filters');
      const counterEl = document.getElementById('active_filters_count');
      if (activeCount > 0) {
        clearBtn.style.display = 'inline-flex';
        counterEl.innerText = activeCount;
      } else {
        clearBtn.style.display = 'none';
      }

      filteredBookings = allBookings.filter(b => {
        const fullName = ((b.first_name || '') + ' ' + (b.last_name || '')).toLowerCase();
        const phone = (b.phone || '').toLowerCase();
        const email = (b.email || '').toLowerCase();
        const service = (b.service || '').toLowerCase();
        const status = b.status || 'Pending';
        const notes = (b.notes || '').toLowerCase();
        const adminNotes = (b.admin_notes || '').toLowerCase();

        if (filterValues.search) {
          const s = filterValues.search;
          const matches = fullName.includes(s) || phone.includes(s) || email.includes(s) || service.includes(s) || notes.includes(s) || adminNotes.includes(s);
          if (!matches) return false;
        }

        if (filterValues.service) {
          const chosenSrv = filterValues.service.toLowerCase();
          if (!service.includes(chosenSrv) && !chosenSrv.includes(service)) return false;
        }

        if (filterValues.status && status !== filterValues.status) return false;

        if (filterValues.type) {
          const isClinical = service.includes('pharmacy first') || service.includes('contraception') || service.includes('nms') || service.includes('screening');
          if (filterValues.type === 'Clinical' && !isClinical) return false;
          if (filterValues.type === 'Private' && isClinical) return false;
        }

        return true;
      });

      renderCurrentView();
      if (currentActiveNav === 'patients') renderPatientsDirectory();
      if (currentActiveNav === 'orders' || currentActiveNav.startsWith('pharmacy')) renderOrdersQueue();
      updateOrdersCounters();
    }

    function resetAllFilters() {
      document.getElementById('filter_package').value = '';
      document.getElementById('filter_service').value = '';
      document.getElementById('filter_room').value = '';
      document.getElementById('filter_type').value = '';
      document.getElementById('filter_status').value = '';
      document.getElementById('global_search_input').value = '';
      clearOrdersSearch();
      applyAllFilters();
    }

    // ══════════════════════════════════════════════════════════════
    // CALENDAR VIEW MODES
    // ══════════════════════════════════════════════════════════════
    function switchViewMode(mode) {
      currentViewMode = mode;
      document.querySelectorAll('.btn-view-pill').forEach(b => b.classList.remove('active'));
      const activePill = document.getElementById(`pill_${mode}`);
      if (activePill) activePill.classList.add('active');

      selectSidebarView('appointments');
    }

    function renderCurrentView() {
      if (currentViewMode === 'week') renderWeekCalendar();
      else if (currentViewMode === 'day') renderDayCalendar();
      else if (currentViewMode === 'month') renderMonthCalendar();
      else if (currentViewMode === 'list') renderListTable();
    }

    function navigateDateStep(delta) {
      if (currentViewMode === 'week') activeDate.setDate(activeDate.getDate() + (delta * 7));
      else if (currentViewMode === 'day') activeDate.setDate(activeDate.getDate() + delta);
      else if (currentViewMode === 'month') activeDate.setMonth(activeDate.getMonth() + delta);
      else activeDate.setDate(activeDate.getDate() + (delta * 7));
      renderCurrentView();
    }

    function navigateToToday() {
      activeDate = new Date();
      renderCurrentView();
    }

    function getWeekDays(refDate) {
      const d = new Date(refDate);
      const day = d.getDay();
      const diff = d.getDate() - day + (day === 0 ? -6 : 1);
      const monday = new Date(d.setDate(diff));
      const week = [];
      for (let i = 0; i < 7; i++) {
        const next = new Date(monday);
        next.setDate(monday.getDate() + i);
        week.push(next);
      }
      return week;
    }

    function formatDateKey(dateObj) {
      const y = dateObj.getFullYear();
      const m = String(dateObj.getMonth() + 1).padStart(2, '0');
      const d = String(dateObj.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    }

    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const dayNames = ["MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN"];

    // ══════════════════════════════════════════════════════════════
    // WEEK CALENDAR RENDER
    // ══════════════════════════════════════════════════════════════
    function renderWeekCalendar() {
      const weekDays = getWeekDays(activeDate);
      const todayKey = formatDateKey(new Date());

      const startM = monthNames[weekDays[0].getMonth()];
      const endM = monthNames[weekDays[6].getMonth()];
      const startD = weekDays[0].getDate();
      const endD = weekDays[6].getDate();
      const yearStr = weekDays[6].getFullYear();

      let rangeTitle = `${startM} ${startD} – ${endD}, ${yearStr}`;
      if (startM !== endM) rangeTitle = `${startM} ${startD} – ${endM} ${endD}, ${yearStr}`;
      document.getElementById('display_date_range_title').innerText = rangeTitle;

      const headersRow = document.getElementById('week_headers_row');
      headersRow.innerHTML = '<div class="week-time-gutter-header"></div>';

      weekDays.forEach((dayObj, index) => {
        const key = formatDateKey(dayObj);
        const isToday = (key === todayKey);
        const col = document.createElement('div');
        col.className = `week-day-col-header ${isToday ? 'is-today' : ''}`;
        col.innerHTML = `${dayNames[index]} <span class="day-number">${dayObj.getDate()}</span>`;
        headersRow.appendChild(col);
      });

      const gridBody = document.getElementById('week_grid_body');
      gridBody.innerHTML = '';

      const slotMap = {};
      filteredBookings.forEach(b => {
        const dtStr = b.preferred_date || b.created_at;
        if (!dtStr) return;
        const bDate = new Date(dtStr.replace(' ', 'T'));
        if (isNaN(bDate.getTime())) return;
        const dateKey = formatDateKey(bDate);
        const hour = bDate.getHours();
        const slotKey = `${dateKey}_${hour}`;
        if (!slotMap[slotKey]) slotMap[slotKey] = [];
        slotMap[slotKey].push(b);
      });

      for (let h = 8; h <= 19; h++) {
        const hourRow = document.createElement('div');
        hourRow.className = 'week-hour-row';

        const hour12 = h % 12 === 0 ? 12 : h % 12;
        const ampm = h >= 12 ? 'PM' : 'AM';
        const labelCell = document.createElement('div');
        labelCell.className = 'week-time-label-cell';
        labelCell.innerText = `${hour12} ${ampm}`;
        hourRow.appendChild(labelCell);

        weekDays.forEach(dayObj => {
          const dateKey = formatDateKey(dayObj);
          const isToday = (dateKey === todayKey);
          const slotCell = document.createElement('div');
          slotCell.className = `week-slot-cell ${isToday ? 'is-today' : ''}`;

          slotCell.onclick = (e) => {
            if (e.target === slotCell) {
              openCreateAppointmentModal(`${dateKey}T${String(h).padStart(2,'0')}:00`);
            }
          };

          const slotKey = `${dateKey}_${h}`;
          const items = slotMap[slotKey] || [];
          items.forEach(b => {
            const card = document.createElement('div');
            const statusClass = (b.status || 'Pending').toLowerCase();
            card.className = `appt-card status-${statusClass}`;

            const isClinical = (b.service || '').toLowerCase().includes('contraception') || (b.service || '').toLowerCase().includes('pharmacy first') || (b.service || '').toLowerCase().includes('nms');
            const timeStr = b.preferred_date ? b.preferred_date.substring(11, 16) : `${h}:00`;
            const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');

            card.innerHTML = `
              <div class="appt-card-top-row">
                <span class="appt-card-time">${timeStr}</span>
                <span class="${isClinical ? 'appt-tag-clinical' : 'appt-tag-private'}">${isClinical ? 'Clinical' : 'Private'}</span>
              </div>
              <span class="appt-card-service">${escapeHtml(b.service || 'Consultation')}</span>
              <span class="appt-card-patient">${escapeHtml(fullName)}</span>
            `;

            card.onclick = (e) => {
              e.stopPropagation();
              openAppointmentDetails(b.id);
            };

            slotCell.appendChild(card);
          });

          if (isToday) {
            const now = new Date();
            if (now.getHours() === h) {
              const mins = now.getMinutes();
              const topPercent = (mins / 60) * 100;
              const line = document.createElement('div');
              line.className = 'current-time-indicator-line';
              line.style.top = `${topPercent}%`;
              line.innerHTML = '<div class="current-time-dot"></div>';
              slotCell.appendChild(line);
            }
          }

          hourRow.appendChild(slotCell);
        });

        gridBody.appendChild(hourRow);
      }
    }

    // ══════════════════════════════════════════════════════════════
    // DAY CALENDAR RENDER
    // ══════════════════════════════════════════════════════════════
    function renderDayCalendar() {
      const todayKey = formatDateKey(new Date());
      const activeKey = formatDateKey(activeDate);
      const isToday = (activeKey === todayKey);

      const dayName = activeDate.toLocaleDateString('en-GB', { weekday: 'long' });
      const formattedDate = activeDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

      document.getElementById('display_date_range_title').innerText = `${dayName}, ${formattedDate}`;
      document.getElementById('day_single_header').innerHTML = `${dayName.toUpperCase()} <span class="day-number">${activeDate.getDate()}</span>`;
      document.getElementById('day_single_header').className = `week-day-col-header ${isToday ? 'is-today' : ''}`;

      const gridBody = document.getElementById('day_grid_body');
      gridBody.innerHTML = '';

      for (let h = 8; h <= 19; h++) {
        const hourRow = document.createElement('div');
        hourRow.className = 'week-hour-row';
        hourRow.style.gridTemplateColumns = '80px 1fr';

        const hour12 = h % 12 === 0 ? 12 : h % 12;
        const ampm = h >= 12 ? 'PM' : 'AM';
        const labelCell = document.createElement('div');
        labelCell.className = 'week-time-label-cell';
        labelCell.innerText = `${hour12} ${ampm}`;
        hourRow.appendChild(labelCell);

        const slotCell = document.createElement('div');
        slotCell.className = `week-slot-cell ${isToday ? 'is-today' : ''}`;

        slotCell.onclick = (e) => {
          if (e.target === slotCell) {
            openCreateAppointmentModal(`${activeKey}T${String(h).padStart(2,'0')}:00`);
          }
        };

        filteredBookings.forEach(b => {
          const dtStr = b.preferred_date || b.created_at;
          if (!dtStr) return;
          const bDate = new Date(dtStr.replace(' ', 'T'));
          if (formatDateKey(bDate) === activeKey && bDate.getHours() === h) {
            const card = document.createElement('div');
            card.className = `appt-card status-${(b.status || 'Pending').toLowerCase()}`;
            const isClinical = (b.service || '').toLowerCase().includes('pharmacy first') || (b.service || '').toLowerCase().includes('contraception') || (b.service || '').toLowerCase().includes('nms');
            const timeStr = b.preferred_date ? b.preferred_date.substring(11, 16) : `${h}:00`;
            const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');

            card.innerHTML = `
              <div class="appt-card-top-row">
                <span class="appt-card-time">${timeStr}</span>
                <span class="${isClinical ? 'appt-tag-clinical' : 'appt-tag-private'}">${isClinical ? 'Clinical' : 'Private'}</span>
              </div>
              <span class="appt-card-service">${escapeHtml(b.service)}</span>
              <span class="appt-card-patient">${escapeHtml(fullName)} - 📞 ${escapeHtml(b.phone)}</span>
            `;
            card.onclick = (e) => { e.stopPropagation(); openAppointmentDetails(b.id); };
            slotCell.appendChild(card);
          }
        });

        hourRow.appendChild(slotCell);
        gridBody.appendChild(hourRow);
      }
    }

    // ══════════════════════════════════════════════════════════════
    // MONTH CALENDAR RENDER
    // ══════════════════════════════════════════════════════════════
    function renderMonthCalendar() {
      const year = activeDate.getFullYear();
      const month = activeDate.getMonth();
      const monthTitle = activeDate.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
      document.getElementById('display_date_range_title').innerText = monthTitle;

      const container = document.getElementById('month_view_container');
      container.innerHTML = `
        <div class="month-day-col-header">MON</div>
        <div class="month-day-col-header">TUE</div>
        <div class="month-day-col-header">WED</div>
        <div class="month-day-col-header">THU</div>
        <div class="month-day-col-header">FRI</div>
        <div class="month-day-col-header">SAT</div>
        <div class="month-day-col-header">SUN</div>
      `;

      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const startingDay = (firstDay.getDay() === 0) ? 6 : firstDay.getDay() - 1;
      const totalDays = lastDay.getDate();
      const todayKey = formatDateKey(new Date());

      const prevMonthLastDay = new Date(year, month, 0).getDate();
      for (let i = startingDay - 1; i >= 0; i--) {
        const cell = document.createElement('div');
        cell.className = 'month-cell is-outside-month';
        cell.innerHTML = `<span class="month-date-number">${prevMonthLastDay - i}</span>`;
        container.appendChild(cell);
      }

      for (let d = 1; d <= totalDays; d++) {
        const dObj = new Date(year, month, d);
        const key = formatDateKey(dObj);
        const isToday = (key === todayKey);

        const cell = document.createElement('div');
        cell.className = `month-cell ${isToday ? 'is-today' : ''}`;
        cell.innerHTML = `<span class="month-date-number">${d}</span>`;

        filteredBookings.forEach(b => {
          const dtStr = b.preferred_date || b.created_at;
          if (!dtStr) return;
          const bDate = new Date(dtStr.replace(' ', 'T'));
          if (formatDateKey(bDate) === key) {
            const chip = document.createElement('div');
            chip.className = 'month-appt-chip';
            const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
            chip.innerText = `${fullName} (${b.service || 'Appt'})`;
            chip.onclick = (e) => { e.stopPropagation(); openAppointmentDetails(b.id); };
            cell.appendChild(chip);
          }
        });

        cell.onclick = () => {
          activeDate = new Date(year, month, d);
          switchViewMode('day');
        };

        container.appendChild(cell);
      }
    }

    // ══════════════════════════════════════════════════════════════
    // LIST TABLE RENDER
    // ══════════════════════════════════════════════════════════════
    function renderListTable() {
      document.getElementById('display_date_range_title').innerText = `Filtered (${filteredBookings.length} Bookings)`;
      const tbody = document.getElementById('list_table_tbody');
      tbody.innerHTML = '';

      if (filteredBookings.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
              <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">🔍</span>
              <strong>No matching appointments found</strong>
              <p style="font-size: 0.82rem; margin-top: 0.25rem;">Try adjusting search terms or clearing active filters.</p>
            </td>
          </tr>
        `;
        return;
      }

      filteredBookings.forEach(b => {
        const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
        const phone = b.phone || '';
        const email = b.email || '';
        const dob = b.dob ? new Date(b.dob).toLocaleDateString('en-GB') : '';
        const service = b.service || 'General Service';
        const prefDate = b.preferred_date ? new Date(b.preferred_date.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Flexible';
        const status = b.status || 'Pending';
        const submitted = b.created_at ? new Date(b.created_at.replace(' ', 'T')).toLocaleDateString('en-GB') : '';

        const tr = document.createElement('tr');
        tr.id = `row_${b.id}`;
        tr.innerHTML = `
          <td>
            <span class="patient-cell-name">${escapeHtml(fullName)}</span>
            <span class="patient-cell-sub">DOB: ${dob || 'Not provided'} &bull; Submitted: ${submitted}</span>
          </td>
          <td>
            <div class="contact-cell-row">
              📞 <a href="tel:${escapeHtml(phone)}">${escapeHtml(phone)}</a>
              <a href="#" class="wa-btn-icon" title="Send WhatsApp Confirmation" onclick="openWhatsAppMsg('${escapeJs(fullName)}', '${escapeJs(phone)}', '${escapeJs(service)}', '${escapeJs(prefDate)}'); return false;">💬</a>
            </div>
            ${email ? `<div class="patient-cell-sub" style="margin-top:2px;">✉️ <a href="mailto:${escapeHtml(email)}">${escapeHtml(email)}</a></div>` : ''}
          </td>
          <td><span class="service-pill-tag">${escapeHtml(service)}</span></td>
          <td><span style="font-weight: 700; color: #0d9488;">${prefDate}</span></td>
          <td style="text-align: center;">
            <span class="status-badge-pill status-${status.toLowerCase()}">${status}</span>
          </td>
          <td style="text-align: right;">
            <div class="actions-btn-group">
              <button class="btn-row-action act-confirm" title="Confirm" onclick="updateDirectStatus(${b.id}, 'confirm')">✔️</button>
              <button class="btn-row-action act-reschedule" title="Reschedule" onclick="openRescheduleModal(${b.id})">🗓️</button>
              <button class="btn-row-action act-complete" title="Complete" onclick="updateDirectStatus(${b.id}, 'complete')">👍</button>
              <button class="btn-row-action act-cancel" title="Cancel" onclick="updateDirectStatus(${b.id}, 'cancel')">❌</button>
              <button class="btn-row-action" title="View Details" onclick="openAppointmentDetails(${b.id})">👁️</button>
            </div>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    // ══════════════════════════════════════════════════════════════
    // PATIENTS DIRECTORY RENDER
    // ══════════════════════════════════════════════════════════════
    function renderPatientsDirectory() {
      const tbody = document.getElementById('patients_table_tbody');
      tbody.innerHTML = '';

      const patientMap = {};
      const sourceList = (filterValues.search || filterValues.service) ? filteredBookings : allBookings;

      sourceList.forEach(b => {
        const key = (b.phone || '') + '_' + (b.first_name || '');
        if (!patientMap[key]) {
          patientMap[key] = {
            id: b.id,
            name: (b.first_name || '') + (b.last_name ? ' ' + b.last_name : ''),
            phone: b.phone || '',
            email: b.email || '',
            dob: b.dob || '',
            latestService: b.service || 'Travel Clinic & Vaccinations',
            count: 1
          };
        } else {
          patientMap[key].count++;
        }
      });

      const patientsList = Object.values(patientMap);
      if (patientsList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">No matching patients found.</td></tr>';
        return;
      }

      patientsList.forEach(p => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>
            <strong class="patient-cell-name">${escapeHtml(p.name)}</strong>
            <span class="patient-cell-sub">${p.count} appointment(s) on file</span>
          </td>
          <td>
            <a href="tel:${escapeHtml(p.phone)}" style="color:var(--sky);font-weight:600;">${escapeHtml(p.phone)}</a>
            <a href="#" class="wa-btn-icon" style="margin-left:6px;" onclick="openWhatsAppMsg('${escapeJs(p.name)}', '${escapeJs(p.phone)}', '${escapeJs(p.latestService)}', 'Upcoming'); return false;">💬</a>
          </td>
          <td>${p.email ? `<a href="mailto:${escapeHtml(p.email)}" style="color:var(--sky);">${escapeHtml(p.email)}</a>` : '—'}</td>
          <td>${p.dob ? new Date(p.dob).toLocaleDateString('en-GB') : '—'}</td>
          <td><span style="font-weight:600;color:#0f172a;">${escapeHtml(p.latestService)}</span></td>
          <td style="text-align: right;">
            <button class="btn-sub-action" onclick="openCreateAppointmentModal('', '${escapeJs(p.name)}', '${escapeJs(p.phone)}', '${escapeJs(p.email)}')">+ Book New</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    // ══════════════════════════════════════════════════════════════
    // ORDERS QUEUE MANAGEMENT
    // ══════════════════════════════════════════════════════════════
    let currentOrdersStatusFilter = 'all';
    let currentOrdersSearch = '';

    function setOrdersStatusFilter(status) {
      currentOrdersStatusFilter = status;
      document.querySelectorAll('.orders-tab-btn').forEach(btn => btn.classList.remove('active'));
      const activeBtnId = status === 'all' ? 'btn_order_filter_all' : `btn_order_filter_${status.toLowerCase()}`;
      const activeBtn = document.getElementById(activeBtnId);
      if (activeBtn) activeBtn.classList.add('active');
      renderOrdersQueue();
    }

    function onOrdersSearchInput(val) {
      currentOrdersSearch = (val || '').trim().toLowerCase();
      const clearBtn = document.getElementById('orders_search_clear_btn');
      if (clearBtn) clearBtn.style.display = currentOrdersSearch ? 'block' : 'none';
      renderOrdersQueue();
    }

    function clearOrdersSearch() {
      const input = document.getElementById('orders_search_input');
      if (input) input.value = '';
      currentOrdersSearch = '';
      const clearBtn = document.getElementById('orders_search_clear_btn');
      if (clearBtn) clearBtn.style.display = 'none';
      renderOrdersQueue();
    }

    function resetOrdersFilter() {
      clearOrdersSearch();
      setOrdersStatusFilter('all');
    }

    function updateOrdersCounters() {
      const total = allBookings.length;
      let pending = 0, confirmed = 0, completed = 0, cancelled = 0;

      allBookings.forEach(b => {
        const s = b.status || 'Pending';
        if (s === 'Pending') pending++;
        else if (s === 'Confirmed') confirmed++;
        else if (s === 'Completed') completed++;
        else if (s === 'Cancelled') cancelled++;
      });

      const setBadge = (id, count) => {
        const el = document.getElementById(id);
        if (el) el.innerText = count;
      };

      setBadge('orders_count_all', total);
      setBadge('orders_count_pending', pending);
      setBadge('orders_count_confirmed', confirmed);
      setBadge('orders_count_completed', completed);
      setBadge('orders_count_cancelled', cancelled);

      // Sync sidebar orders badge
      const sideBadge = document.getElementById('side_badge_orders');
      if (sideBadge) {
        sideBadge.innerText = pending;
        sideBadge.style.display = pending > 0 ? 'inline-flex' : 'none';
      }
    }

    function renderOrdersQueue() {
      const tbody = document.getElementById('orders_table_tbody');
      if (!tbody) return;
      tbody.innerHTML = '';

      updateOrdersCounters();

      // Effective search term: check local orders search first, then global search input
      const searchNeedle = currentOrdersSearch || (filterValues.search || '').trim().toLowerCase();

      let list = allBookings.filter(b => {
        const status = b.status || 'Pending';

        // 1. Status Filter
        if (currentOrdersStatusFilter !== 'all' && status !== currentOrdersStatusFilter) {
          return false;
        }

        // 2. Search Filter
        if (searchNeedle) {
          const fullName = ((b.first_name || '') + ' ' + (b.last_name || '')).toLowerCase();
          const phone = (b.phone || '').toLowerCase();
          const email = (b.email || '').toLowerCase();
          const service = (b.service || '').toLowerCase();
          const notes = (b.notes || '').toLowerCase();
          const adminNotes = (b.admin_notes || '').toLowerCase();
          const orderRef = ('ord-' + (10000 + parseInt(b.id || 0))).toLowerCase();

          const matches = fullName.includes(searchNeedle) ||
                          phone.includes(searchNeedle) ||
                          email.includes(searchNeedle) ||
                          service.includes(searchNeedle) ||
                          notes.includes(searchNeedle) ||
                          adminNotes.includes(searchNeedle) ||
                          orderRef.includes(searchNeedle);
          if (!matches) return false;
        }

        return true;
      });

      // Sort by newest submission / ID descending (Newest orders first!)
      list.sort((a, b) => {
        const idA = parseInt(a.id) || 0;
        const idB = parseInt(b.id) || 0;
        return idB - idA;
      });

      const tableEl = document.getElementById('orders_table_element');
      const emptyStateEl = document.getElementById('orders_empty_state');
      const summaryText = document.getElementById('orders_summary_text');

      if (summaryText) {
        summaryText.innerText = `Showing ${list.length} of ${allBookings.length} total orders`;
      }

      if (list.length === 0) {
        if (tableEl) tableEl.style.display = 'none';
        if (emptyStateEl) emptyStateEl.style.display = 'block';
        return;
      }

      if (tableEl) tableEl.style.display = 'table';
      if (emptyStateEl) emptyStateEl.style.display = 'none';

      list.forEach(b => {
        const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
        const orderRef = '#ORD-' + (10000 + parseInt(b.id || 0));
        const phone = b.phone || '';
        const email = b.email || '';
        const dob = b.dob ? new Date(b.dob).toLocaleDateString('en-GB') : '';
        const service = b.service || 'General Service';
        const status = b.status || 'Pending';

        const prefDate = b.preferred_date
          ? new Date(b.preferred_date.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
          : 'Flexible / Not set';

        let submitted = 'Recently';
        if (b.created_at) {
          const d = new Date(b.created_at.replace(' ', 'T'));
          submitted = !isNaN(d.getTime()) ? d.toLocaleDateString('en-GB') : b.created_at.substring(0, 10);
        }

        const isVoiceAi = (b.notes && b.notes.includes('[Booked via Voice AI Assistant]'));
        const cleanNotes = b.notes ? b.notes.replace('[Booked via Voice AI Assistant]', '').trim() : '';

        const tr = document.createElement('tr');
        tr.id = `order_row_${b.id}`;
        tr.innerHTML = `
          <td>
            <strong style="color: #0f766e; cursor: pointer; text-decoration: underline;" onclick="openAppointmentDetails(${b.id})" title="Click to review order">${orderRef}</strong>
          </td>
          <td>
            <div class="patient-cell-name" style="font-weight: 700; color: #0f172a;">${escapeHtml(fullName || 'Guest Patient')}</div>
            <div class="contact-cell-row" style="margin-top: 3px;">
              📞 <a href="tel:${escapeHtml(phone)}">${escapeHtml(phone)}</a>
              ${phone ? `<a href="#" class="wa-btn-icon" title="Send WhatsApp Message" onclick="openWhatsAppMsg('${escapeJs(fullName)}', '${escapeJs(phone)}', '${escapeJs(service)}', '${escapeJs(prefDate)}'); return false;">💬</a>` : ''}
            </div>
            ${email ? `<div class="patient-cell-sub" style="margin-top: 2px;">✉️ <a href="mailto:${escapeHtml(email)}">${escapeHtml(email)}</a></div>` : ''}
            ${dob ? `<div class="patient-cell-sub" style="margin-top: 1px; color: #94a3b8;">DOB: ${dob}</div>` : ''}
          </td>
          <td>
            <span class="service-pill-tag">${escapeHtml(service)}</span>
            ${isVoiceAi ? `<div class="voice-ai-badge">🤖 Voice AI Booking</div>` : ''}
            ${cleanNotes ? `<div style="font-size: 0.76rem; color: #64748b; margin-top: 4px; font-style: italic; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(cleanNotes)}">📝 ${escapeHtml(cleanNotes)}</div>` : ''}
          </td>
          <td>
            <span style="font-weight: 600; color: #0f766e; font-size: 0.84rem;">📅 ${prefDate}</span>
          </td>
          <td>
            <span style="font-size: 0.8rem; color: #64748b;">${submitted}</span>
          </td>
          <td style="text-align: center;">
            <span class="status-badge-pill status-${status.toLowerCase()}">${status}</span>
          </td>
          <td style="text-align: right;">
            <div class="actions-btn-group">
              <button class="btn-row-action act-confirm" title="Confirm Booking" onclick="updateDirectStatus(${b.id}, 'confirm')">✔️</button>
              <button class="btn-row-action act-reschedule" title="Reschedule Booking" onclick="openRescheduleModal(${b.id})">🗓️</button>
              <button class="btn-row-action act-complete" title="Mark Completed" onclick="updateDirectStatus(${b.id}, 'complete')">👍</button>
              <button class="btn-row-action act-cancel" title="Cancel Booking" onclick="updateDirectStatus(${b.id}, 'cancel')">❌</button>
              <button class="btn-row-action" title="Review Full Details &amp; Notes" onclick="openAppointmentDetails(${b.id})">👁️</button>
            </div>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    function exportOrdersCSV() {
      const searchNeedle = currentOrdersSearch || (filterValues.search || '').trim().toLowerCase();
      let list = allBookings.filter(b => {
        const status = b.status || 'Pending';
        if (currentOrdersStatusFilter !== 'all' && status !== currentOrdersStatusFilter) return false;
        if (searchNeedle) {
          const fullName = ((b.first_name || '') + ' ' + (b.last_name || '')).toLowerCase();
          const phone = (b.phone || '').toLowerCase();
          const email = (b.email || '').toLowerCase();
          const service = (b.service || '').toLowerCase();
          const notes = (b.notes || '').toLowerCase();
          const orderRef = ('ord-' + (10000 + parseInt(b.id || 0))).toLowerCase();
          return fullName.includes(searchNeedle) || phone.includes(searchNeedle) || email.includes(searchNeedle) || service.includes(searchNeedle) || notes.includes(searchNeedle) || orderRef.includes(searchNeedle);
        }
        return true;
      });

      if (list.length === 0) {
        alert('No orders available to export under current filter.');
        return;
      }

      let csv = 'Order Ref,Patient Name,Phone,Email,DOB,Service,Preferred Date,Submitted,Status,Notes,Staff Notes\n';
      list.forEach(b => {
        const orderRef = '#ORD-' + (10000 + parseInt(b.id || 0));
        const fullName = ((b.first_name || '') + ' ' + (b.last_name || '')).replace(/"/g, '""');
        const phone = (b.phone || '').replace(/"/g, '""');
        const email = (b.email || '').replace(/"/g, '""');
        const dob = b.dob || '';
        const service = (b.service || '').replace(/"/g, '""');
        const date = (b.preferred_date || '').replace(/"/g, '""');
        const submitted = (b.created_at || '').replace(/"/g, '""');
        const status = b.status || 'Pending';
        const notes = (b.notes || '').replace(/"/g, '""');
        const adminNotes = (b.admin_notes || '').replace(/"/g, '""');
        csv += `"${orderRef}","${fullName}","${phone}","${email}","${dob}","${service}","${date}","${submitted}",${status},"${notes}","${adminNotes}"\n`;
      });

      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `Winchester_Orders_${formatDateKey(new Date())}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    // ══════════════════════════════════════════════════════════════
    // COMMUNICATIONS HUB
    // ══════════════════════════════════════════════════════════════
    function populateCommunicationsPatients() {
      const sel = document.getElementById('comm_patient_select');
      if (!sel) return;
      sel.innerHTML = '<option value="">-- Choose Patient --</option>';
      allBookings.forEach(b => {
        const name = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
        sel.innerHTML += `<option value="${b.id}">${escapeHtml(name)} (📞 ${escapeHtml(b.phone)})</option>`;
      });
      applyCommTemplate('confirm');
    }

    function populateCommFields(bookingId) {
      applyCommTemplate(document.getElementById('comm_template_select').value);
    }

    function applyCommTemplate(type) {
      const sel = document.getElementById('comm_patient_select');
      const bId = sel ? sel.value : null;
      const b = allBookings.find(item => item.id == bId) || allBookings[0] || {};
      const name = (b.first_name || 'Patient');
      const service = b.service || 'Appointment';
      const date = b.preferred_date || 'your scheduled date';

      let text = '';
      if (type === 'confirm') {
        text = `Hello ${name}, your booking for "${service}" at Winchester Health Centre on ${date} is confirmed. Please let us know if you have any questions!`;
      } else if (type === 'reminder') {
        text = `Hi ${name}, friendly reminder of your appointment for "${service}" tomorrow at ${date} at Winchester Health Centre. See you soon!`;
      } else if (type === 'prescription_ready') {
        text = `Hi ${name}, your prescription/medication is now ready for collection at Winchester Health Centre pharmacy.`;
      } else {
        text = `Hello ${name}, thank you for visiting Winchester Health Centre. Please let us know how your recovery is progressing.`;
      }

      document.getElementById('comm_message_text').value = text;
    }

    function dispatchQuickWhatsApp() {
      const sel = document.getElementById('comm_patient_select');
      const bId = sel ? sel.value : null;
      const b = allBookings.find(item => item.id == bId);
      const text = document.getElementById('comm_message_text').value;

      if (!b || !b.phone) {
        alert('Please select a valid patient with a phone number.');
        return;
      }

      let phone = b.phone.replace(/[^0-9]/g, '');
      if (phone.startsWith('0')) phone = '44' + phone.substring(1);
      window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank');

      // Log
      const logContainer = document.getElementById('comm_logs_container');
      if (logContainer) {
        const item = document.createElement('div');
        item.style.padding = '6px';
        item.style.background = '#fff';
        item.style.borderRadius = '4px';
        item.innerHTML = `<strong>Sent to ${escapeHtml(b.first_name)}:</strong> ${escapeHtml(text.substring(0, 50))}... <span style="color:#0d9488;">✓</span>`;
        logContainer.prepend(item);
      }
    }

    function renderCommunicationsHub() {
      populateCommunicationsPatients();
      const logContainer = document.getElementById('comm_logs_container');
      if (logContainer && logContainer.children.length === 0) {
        logContainer.innerHTML = `
          <div style="padding:6px;background:#fff;border-radius:4px;"><strong>Sent to David Baker:</strong> Appointment Confirmed for Travel Clinic... <span style="color:#0d9488;">✓</span></div>
          <div style="padding:6px;background:#fff;border-radius:4px;"><strong>Sent to Mia Judd:</strong> Pre-visit Portal Link Dispatched... <span style="color:#0d9488;">✓</span></div>
        `;
      }
    }

    // ══════════════════════════════════════════════════════════════
    // LABELS PRINTING STATION
    // ══════════════════════════════════════════════════════════════
    function populateLabelPatients() {
      const sel = document.getElementById('label_patient_select');
      if (!sel) return;
      sel.innerHTML = '';
      allBookings.forEach(b => {
        const name = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
        sel.innerHTML += `<option value="${b.id}">${escapeHtml(name)} - ${escapeHtml(b.service)}</option>`;
      });
      renderLabelPreview();
    }

    function renderLabelPreview() {
      const sel = document.getElementById('label_patient_select');
      const bId = sel ? sel.value : null;
      const b = allBookings.find(item => item.id == bId) || allBookings[0] || {};
      const type = document.getElementById('label_type_select') ? document.getElementById('label_type_select').value : 'rx';
      const preview = document.getElementById('label_preview_card');
      if (!preview) return;

      const name = (b.first_name || 'PATIENT') + ' ' + (b.last_name || '');
      const date = b.preferred_date || new Date().toISOString().slice(0, 10);
      const dob = b.dob || 'NOT RECORDED';

      if (type === 'rx') {
        preview.innerHTML = `
          <div style="text-align:center;font-weight:bold;border-bottom:1px solid #000;padding-bottom:4px;margin-bottom:8px;">WINCHESTER HEALTH CENTRE PHARMACY</div>
          <div><strong>PATIENT:</strong> ${escapeHtml(name.toUpperCase())}</div>
          <div><strong>DOB:</strong> ${dob} &bull; <strong>DATE:</strong> ${date.substring(0,10)}</div>
          <div style="margin-top:6px;"><strong>SERVICE / RX:</strong> ${escapeHtml(b.service || 'Travel Medication')}</div>
          <div style="margin-top:4px;font-size:0.75rem;color:#475569;">Take / use as directed by the clinician. Keep out of reach of children.</div>
          <div style="margin-top:8px;font-size:0.75rem;text-align:right;">DISPENSED BY: SP / R.PH</div>
        `;
      } else {
        preview.innerHTML = `
          <div style="text-align:center;font-weight:bold;border-bottom:1px solid #000;padding-bottom:4px;margin-bottom:8px;">APPOINTMENT CARD &bull; WINCHESTER HEALTH</div>
          <div><strong>PATIENT:</strong> ${escapeHtml(name.toUpperCase())}</div>
          <div><strong>APPOINTMENT DATE:</strong> ${date}</div>
          <div><strong>CLINIC:</strong> ${escapeHtml(b.service || 'Health Clinic')} (Room 1)</div>
          <div style="margin-top:8px;font-size:0.75rem;">Please arrive 5 minutes prior to scheduled time. Call 01962 854 725 to amend.</div>
        `;
      }
    }

    // ══════════════════════════════════════════════════════════════
    // INTAKE & ANALYTICS RENDERING
    // ══════════════════════════════════════════════════════════════
    function renderIntakeReviewTab() {
      const container = document.getElementById('intake_reviews_grid');
      container.innerHTML = '';

      filteredBookings.forEach(b => {
        const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
        const card = document.createElement('div');
        card.style.background = '#fff';
        card.style.border = '1px solid var(--border)';
        card.style.borderRadius = '8px';
        card.style.padding = '1.25rem';
        card.innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.65rem;">
            <h4 style="color:#0f172a;">${escapeHtml(fullName)}</h4>
            <span class="status-badge-pill status-${(b.status || 'Pending').toLowerCase()}">${b.status || 'Pending'}</span>
          </div>
          <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem;">
            <strong>Service:</strong> ${escapeHtml(b.service)} &bull; <strong>Scheduled:</strong> ${b.preferred_date || 'Anytime'}
          </div>
          <div style="background:#f8fafc;padding:0.75rem;border-radius:6px;font-size:0.85rem;margin-bottom:0.75rem;">
            <strong style="display:block;font-size:0.72rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">Patient Request Notes:</strong>
            ${escapeHtml(b.notes || 'None entered')}
          </div>
          <div>
            <strong style="display:block;font-size:0.72rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">Internal Staff Notes:</strong>
            <textarea id="intake_staff_note_${b.id}" class="form-textarea-field" style="height:55px;">${escapeHtml(b.admin_notes || '')}</textarea>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.75rem;padding-top:0.65rem;border-top:1px solid #f1f5f9;">
            <a href="tel:${escapeHtml(b.phone)}" style="color:var(--sky);font-weight:600;font-size:0.85rem;">📞 ${escapeHtml(b.phone)}</a>
            <button class="btn-modal-primary" style="padding:0.35rem 0.75rem;font-size:0.8rem;" onclick="saveIntakeNote(${b.id})">💾 Save Staff Note</button>
          </div>
        `;
        container.appendChild(card);
      });
    }

    function renderAnalyticsBars() {
      const container = document.getElementById('service_stats_bars');
      if (!container) return;
      container.innerHTML = '';

      const serviceCounts = {};
      let maxCount = 1;
      allBookings.forEach(b => {
        const s = b.service || 'Other';
        serviceCounts[s] = (serviceCounts[s] || 0) + 1;
        if (serviceCounts[s] > maxCount) maxCount = serviceCounts[s];
      });

      Object.entries(serviceCounts).sort((a,b) => b[1] - a[1]).forEach(([srv, count]) => {
        const percent = Math.round((count / maxCount) * 100);
        const item = document.createElement('div');
        item.innerHTML = `
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;font-weight:600;margin-bottom:3px;">
            <span>${escapeHtml(srv)}</span>
            <span style="color:#0d9488;font-weight:700;">${count} bookings</span>
          </div>
          <div style="background:#e2e8f0;height:8px;border-radius:4px;overflow:hidden;">
            <div style="background:#0d9488;height:100%;width:${percent}%;border-radius:4px;"></div>
          </div>
        `;
        container.appendChild(item);
      });
    }

    // ══════════════════════════════════════════════════════════════
    // MODALS & AJAX
    // ══════════════════════════════════════════════════════════════
    function openAppointmentDetails(id) {
      const b = allBookings.find(item => item.id == id);
      if (!b) return;

      selectedBookingId = id;
      const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
      document.getElementById('detail_patient_name').innerText = fullName;
      document.getElementById('detail_created_at').innerText = 'Submitted on ' + (b.created_at || 'Recently');
      document.getElementById('detail_service').innerText = b.service || 'General Service';
      document.getElementById('detail_datetime').innerText = b.preferred_date ? new Date(b.preferred_date.replace(' ', 'T')).toLocaleString('en-GB') : 'Flexible';

      const phoneLink = document.getElementById('detail_phone_link');
      phoneLink.href = 'tel:' + (b.phone || '');
      phoneLink.innerText = b.phone || '';

      const dobStr = b.dob ? `DOB: ${new Date(b.dob).toLocaleDateString('en-GB')}` : '';
      document.getElementById('detail_email_dob').innerText = (b.email || 'No email') + (dobStr ? ` | ${dobStr}` : '');
      document.getElementById('detail_patient_notes').innerText = b.notes || 'None entered.';
      document.getElementById('detail_admin_notes').value = b.admin_notes || '';

      document.getElementById('apptDetailsModal').classList.add('show');
    }

    function triggerDetailWhatsApp() {
      const b = allBookings.find(item => item.id == selectedBookingId);
      if (!b) return;
      const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
      openWhatsAppMsg(fullName, b.phone, b.service, b.preferred_date || 'Flexible');
    }

    async function saveActiveDetailNotes() {
      if (!selectedBookingId) return;
      const notesVal = document.getElementById('detail_admin_notes').value;
      const btn = document.getElementById('btn_save_detail_notes');
      btn.innerText = 'Saving...';
      btn.disabled = true;

      try {
        const res = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: selectedBookingId, action: 'save_notes', admin_notes: notesVal })
        });
        const data = await res.json();
        if (data.success) {
          const b = allBookings.find(item => item.id == selectedBookingId);
          if (b) b.admin_notes = notesVal;
          btn.innerText = '💾 Saved!';
          setTimeout(() => { btn.innerText = '💾 Save Comments'; btn.disabled = false; }, 1200);
        } else {
          alert(data.message || 'Error saving notes');
          btn.disabled = false;
        }
      } catch (err) {
        alert('Communication error with server');
        btn.disabled = false;
      }
    }

    async function saveIntakeNote(id) {
      const notesVal = document.getElementById(`intake_staff_note_${id}`).value;
      try {
        const res = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, action: 'save_notes', admin_notes: notesVal })
        });
        const data = await res.json();
        if (data.success) {
          const b = allBookings.find(item => item.id == id);
          if (b) b.admin_notes = notesVal;
          alert('Staff note saved successfully!');
        } else {
          alert(data.message || 'Failed to save note');
        }
      } catch (e) {
        alert('Server communication error');
      }
    }

    async function updateActiveDetailStatus(action) {
      if (!selectedBookingId) return;
      if (action === 'cancel' && !confirm('Are you sure you want to cancel this booking slot?')) return;
      await performStatusUpdate(selectedBookingId, action);
      closeModal('apptDetailsModal');
    }

    async function updateDirectStatus(id, action) {
      if (action === 'cancel' && !confirm('Are you sure you want to cancel this booking slot?')) return;
      await performStatusUpdate(id, action);
    }

    async function performStatusUpdate(id, action) {
      try {
        const res = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, action: action })
        });
        const data = await res.json();
        if (data.success) {
          const b = allBookings.find(item => item.id == id);
          if (b) b.status = data.status;
          applyAllFilters();
          renderOrdersQueue();
          updateOrdersCounters();
        } else {
          alert(data.message || 'Status update failed');
        }
      } catch (e) {
        alert('Server error occurred');
      }
    }

    async function deleteActiveDetailBooking() {
      if (!selectedBookingId) return;
      if (!confirm('🚨 Permanently delete this appointment record?')) return;

      try {
        const res = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: selectedBookingId, action: 'delete' })
        });
        const data = await res.json();
        if (data.success) {
          allBookings = allBookings.filter(item => item.id != selectedBookingId);
          closeModal('apptDetailsModal');
          applyAllFilters();
          renderOrdersQueue();
          updateOrdersCounters();
        } else {
          alert(data.message || 'Failed to delete');
        }
      } catch (e) {
        alert('Server error');
      }
    }

    // ══════════════════════════════════════════════════════════════
    // RESCHEDULE APPOINTMENT LOGIC & MODAL
    // ══════════════════════════════════════════════════════════════
    let activeRescheduleId = null;

    function openRescheduleModal(id) {
      const b = allBookings.find(item => item.id == id);
      if (!b) return;

      activeRescheduleId = id;
      const fullName = (b.first_name || '') + (b.last_name ? ' ' + b.last_name : '');
      document.getElementById('reschedule_modal_subtitle').innerText = `Appointment #${b.id}`;
      document.getElementById('reschedule_patient_name').innerText = fullName || 'Unknown Patient';
      document.getElementById('reschedule_service_name').innerText = b.service || 'General Service';
      
      const currentFormatted = b.preferred_date 
        ? new Date(b.preferred_date.replace(' ', 'T')).toLocaleString('en-GB', { dateStyle: 'full', timeStyle: 'short' })
        : 'Flexible / Not scheduled';
      document.getElementById('reschedule_current_datetime').innerText = currentFormatted;

      // Prefill input datetime (YYYY-MM-DDTHH:MM)
      if (b.preferred_date) {
        const cleanDt = b.preferred_date.replace(' ', 'T').substring(0, 16);
        document.getElementById('reschedule_datetime').value = cleanDt;
      } else {
        const now = new Date();
        now.setMinutes(0, 0, 0);
        now.setHours(now.getHours() + 1);
        const localISO = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
        document.getElementById('reschedule_datetime').value = localISO;
      }

      document.getElementById('reschedule_reason').value = '';
      document.getElementById('reschedule_modal_error').style.display = 'none';

      // Check customer email status
      const notifyCheckbox = document.getElementById('reschedule_notify_customer');
      const emailDisplay = document.getElementById('reschedule_patient_email_display');
      if (b.email && b.email.trim()) {
        notifyCheckbox.checked = true;
        notifyCheckbox.disabled = false;
        emailDisplay.innerText = `Will send notification to: ${b.email}`;
        emailDisplay.style.color = '#0f766e';
      } else {
        notifyCheckbox.checked = false;
        notifyCheckbox.disabled = true;
        emailDisplay.innerText = `⚠️ No email address recorded for this patient.`;
        emailDisplay.style.color = '#b45309';
      }

      document.getElementById('rescheduleModal').classList.add('show');
    }

    async function submitReschedule() {
      if (!activeRescheduleId) return;

      const newDateVal = document.getElementById('reschedule_datetime').value;
      const reasonVal = document.getElementById('reschedule_reason').value.trim();
      const notifyVal = document.getElementById('reschedule_notify_customer').checked;
      const errDiv = document.getElementById('reschedule_modal_error');
      const btn = document.getElementById('btn_submit_reschedule');

      errDiv.style.display = 'none';
      if (!newDateVal) {
        errDiv.innerText = 'Please select a new date and time.';
        errDiv.style.display = 'block';
        return;
      }

      btn.disabled = true;
      btn.innerText = 'Rescheduling & Notifying...';

      try {
        const res = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'reschedule',
            id: activeRescheduleId,
            preferred_date: newDateVal,
            reschedule_reason: reasonVal,
            notify_customer: notifyVal
          })
        });

        const data = await res.json();
        if (data.success) {
          const b = allBookings.find(item => item.id == activeRescheduleId);
          if (b) {
            b.preferred_date = data.preferred_date;
            if (data.admin_notes) b.admin_notes = data.admin_notes;
            if (data.status) b.status = data.status;
          }

          // If detail modal is open for this booking, update its fields
          if (selectedBookingId == activeRescheduleId) {
            document.getElementById('detail_datetime').innerText = new Date(data.preferred_date.replace(' ', 'T')).toLocaleString('en-GB');
            document.getElementById('detail_admin_notes').value = data.admin_notes || '';
          }

          closeModal('rescheduleModal');
          applyAllFilters();
          renderOrdersQueue();
          updateOrdersCounters();

          alert(data.message || 'Appointment rescheduled successfully!');
        } else {
          errDiv.innerText = data.message || 'Failed to reschedule appointment.';
          errDiv.style.display = 'block';
        }
      } catch (err) {
        errDiv.innerText = 'Server communication error occurred.';
        errDiv.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.innerText = '🗓️ Confirm Reschedule';
      }
    }

    function openCreateAppointmentModal(defaultDateTime = '', name = '', phone = '', email = '') {
      document.getElementById('create_modal_error').style.display = 'none';

      if (name) {
        const parts = name.split(' ');
        document.getElementById('new_first_name').value = parts[0] || '';
        document.getElementById('new_last_name').value = parts.slice(1).join(' ') || '';
      } else {
        document.getElementById('new_first_name').value = '';
        document.getElementById('new_last_name').value = '';
      }

      document.getElementById('new_phone').value = phone || '';
      document.getElementById('new_email').value = email || '';
      document.getElementById('new_dob').value = '';
      document.getElementById('new_admin_notes').value = '';

      if (defaultDateTime) {
        document.getElementById('new_preferred_date').value = defaultDateTime;
      } else {
        const now = new Date();
        now.setMinutes(0, 0, 0);
        now.setHours(now.getHours() + 1);
        const localISO = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
        document.getElementById('new_preferred_date').value = localISO;
      }

      document.getElementById('createAppointmentModal').classList.add('show');
    }

    async function submitCreateAppointment() {
      const firstName = document.getElementById('new_first_name').value.trim();
      const lastName = document.getElementById('new_last_name').value.trim();
      const phone = document.getElementById('new_phone').value.trim();
      const email = document.getElementById('new_email').value.trim();
      const service = document.getElementById('new_service').value;
      const prefDate = document.getElementById('new_preferred_date').value;
      const status = document.getElementById('new_status').value;
      const dob = document.getElementById('new_dob').value;
      const adminNotes = document.getElementById('new_admin_notes').value.trim();
      const errorDiv = document.getElementById('create_modal_error');

      errorDiv.style.display = 'none';
      if (!firstName || !phone || !prefDate) {
        errorDiv.innerText = 'Please provide First Name, Phone Number, and Date/Time.';
        errorDiv.style.display = 'block';
        return;
      }

      const btn = document.getElementById('btn_submit_create_appt');
      btn.disabled = true;
      btn.innerText = 'Creating...';

      try {
        const res = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create_booking',
            first_name: firstName,
            last_name: lastName,
            phone: phone,
            email: email,
            service: service,
            preferred_date: prefDate,
            status: status,
            dob: dob,
            admin_notes: adminNotes
          })
        });

        const data = await res.json();
        if (data.success) {
          const newBooking = {
            id: data.id,
            first_name: firstName,
            last_name: lastName,
            phone: phone,
            email: email,
            service: service,
            preferred_date: prefDate.replace('T', ' ') + ':00',
            status: status,
            dob: dob,
            admin_notes: adminNotes,
            created_at: new Date().toISOString()
          };
          allBookings.unshift(newBooking);
          closeModal('createAppointmentModal');
          applyAllFilters();
          renderOrdersQueue();
          updateOrdersCounters();
          populateCommunicationsPatients();
          populateLabelPatients();
        } else {
          errorDiv.innerText = data.message || 'Failed to create appointment';
          errorDiv.style.display = 'block';
        }
      } catch (err) {
        errorDiv.innerText = 'Server error occurred.';
        errorDiv.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.innerText = 'Create Appointment';
      }
    }

    function openBlockTimeModal() {
      const now = new Date();
      now.setMinutes(0, 0, 0);
      const localISO = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
      document.getElementById('block_datetime').value = localISO;
      document.getElementById('blockTimeModal').classList.add('show');
    }

    function submitBlockTime() {
      const reason = document.getElementById('block_reason').value;
      const dt = document.getElementById('block_datetime').value;
      const room = document.getElementById('block_room').value;

      const blockBooking = {
        id: 'block_' + Date.now(),
        first_name: '⛔ ' + reason,
        last_name: `(${room})`,
        phone: '—',
        email: '',
        service: 'Blocked Time / Staff Break',
        preferred_date: dt.replace('T', ' ') + ':00',
        status: 'Cancelled',
        created_at: new Date().toISOString()
      };
      allBookings.unshift(blockBooking);
      closeModal('blockTimeModal');
      applyAllFilters();
    }

    function openSettingsModal() {
      document.getElementById('old_password').value = '';
      document.getElementById('new_password').value = '';
      document.getElementById('confirm_password').value = '';
      document.getElementById('settings_modal_error').style.display = 'none';
      document.getElementById('settingsModal').classList.add('show');
    }

    async function submitChangePassword() {
      const oldP = document.getElementById('old_password').value;
      const newP = document.getElementById('new_password').value;
      const confP = document.getElementById('confirm_password').value;
      const errDiv = document.getElementById('settings_modal_error');

      errDiv.style.display = 'none';
      if (!oldP || !newP || !confP) {
        errDiv.innerText = 'All fields are required.';
        errDiv.style.display = 'block';
        return;
      }
      if (newP.length < 8) {
        errDiv.innerText = 'New password must be at least 8 characters.';
        errDiv.style.display = 'block';
        return;
      }
      if (newP !== confP) {
        errDiv.innerText = 'New password and confirmation do not match.';
        errDiv.style.display = 'block';
        return;
      }

      const btn = document.getElementById('btn_save_password');
      btn.disabled = true;
      btn.innerText = 'Saving...';

      try {
        const res = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'change_password', old_password: oldP, new_password: newP })
        });
        const data = await res.json();
        if (data.success) {
          alert('Password successfully changed!');
          closeModal('settingsModal');
        } else {
          errDiv.innerText = data.message || 'Failed to update password';
          errDiv.style.display = 'block';
        }
      } catch (err) {
        errDiv.innerText = 'Server error occurred.';
        errDiv.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.innerText = 'Save Password';
      }
    }

    function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    function closeModalOnBackdrop(event, id) { if (event.target === document.getElementById(id)) closeModal(id); }

    function openCustomDateModal() {
      const customDate = prompt('Enter date to view (YYYY-MM-DD):', formatDateKey(activeDate));
      if (customDate) {
        const d = new Date(customDate);
        if (!isNaN(d.getTime())) {
          activeDate = d;
          renderCurrentView();
        }
      }
    }

    function openWhatsAppMsg(name, phone, service, date) {
      const msg = `Hi ${name}, this is Winchester Health Centre regarding your "${service}" on ${date}. Please let us know if you need any assistance. Thank you!`;
      let cleanPhone = (phone || '').replace(/[^0-9]/g, '');
      if (cleanPhone.startsWith('0')) cleanPhone = '44' + cleanPhone.substring(1);
      window.open(`https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`, '_blank');
    }

    function exportCSVData() {
      if (filteredBookings.length === 0) {
        alert('No data available to export.');
        return;
      }
      let csv = 'ID,Patient Name,Phone,Email,DOB,Service,Date,Status,Notes\n';
      filteredBookings.forEach(b => {
        const fullName = ((b.first_name || '') + ' ' + (b.last_name || '')).replace(/"/g, '""');
        const phone = (b.phone || '').replace(/"/g, '""');
        const email = (b.email || '').replace(/"/g, '""');
        const dob = b.dob || '';
        const service = (b.service || '').replace(/"/g, '""');
        const date = (b.preferred_date || '').replace(/"/g, '""');
        const status = b.status || 'Pending';
        const notes = (b.notes || '').replace(/"/g, '""');
        csv += `${b.id},"${fullName}","${phone}","${email}","${dob}","${service}","${date}",${status},"${notes}"\n`;
      });

      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `Winchester_Appointments_${formatDateKey(new Date())}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function escapeJs(str) {
      if (!str) return '';
      return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
    }
  </script>
</body>
</html>
