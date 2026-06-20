<?php
// Initialize session
session_start();

// Redirect to login if unauthorized
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
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

// Variables for charts
$chartDays = [];
$chartCounts = [];
$serviceStats = [];

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

        // Retrieve booking counts over last 15 days for bar chart
        // Note: Using standard date logic compatible with MySQL
        $daysQuery = $pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM bookings 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ");
        while ($row = $daysQuery->fetch()) {
            $chartDays[] = date('d M', strtotime($row['date']));
            $chartCounts[] = intval($row['count']);
        }

        // Retrieve service ratios for pie chart
        $serviceQuery = $pdo->query("
            SELECT service, COUNT(*) as count 
            FROM bookings 
            GROUP BY service
        ");
        while ($row = $serviceQuery->fetch()) {
            // Clean up emojis from service names for cleaner chart labels
            $cleanName = preg_replace('/[^\x20-\x7E]/u', '', $row['service']);
            $cleanName = trim(str_replace('&amp;', '&', $cleanName));
            if (empty($cleanName)) {
                $cleanName = trim($row['service']);
            }
            $serviceStats[$cleanName] = intval($row['count']);
        }

        // Fetch all bookings sorted by newest first
        $stmt = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC");
        $bookings = $stmt->fetchAll();
    } catch (PDOException $e) {
        $connectionError = 'Database query failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Winchester Health Centre</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Lora:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
  <!-- Chart.js Library CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* ══════════════════════════════════════
       THEME COLOR TOKENS (Matched to Logo)
       ══════════════════════════════════════ */
    :root {
      --navy:    #002647;
      --navy-dk: #001830;
      --navy-lt: #003869;
      --green:   #004c3a;
      --green-lt:#036951;
      --green-pale:#e0f0ec;
      --sky:     #1f7cb8;
      --sky-lt:  #4b9cd3;
      --sky-pale:#e6f2fa;
      --amber:   #b78e3a;
      --amber-lt:#cca34f;
      --amber-pale:#f7f2e6;
      --white:   #ffffff;
      --off:     #f4f7fa;
      --grey-1:  #eef2f6;
      --grey-3:  #c5d0db;
      --grey-5:  #5e6e7d;
      --text:    #0a1c2a;
      --shadow:  0 4px 20px rgba(0,38,71,0.08);
      --shadow-lg: 0 12px 36px rgba(0,38,71,0.12);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Nunito', sans-serif;
      color: var(--text);
      background: var(--off);
      min-height: 100vh;
    }

    header {
      background: var(--navy-dk);
      color: var(--white);
      padding: 1rem 2rem;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .header-inner {
      max-width: 1440px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      color: var(--white);
    }

    .brand img {
      height: 48px;
      width: auto;
      background: var(--white);
      padding: 4px;
      border-radius: 8px;
    }

    .brand-title h1 {
      font-family: 'Lora', serif;
      font-size: 1.3rem;
      line-height: 1.1;
      font-weight: 500;
    }

    .brand-title span {
      font-size: 0.75rem;
      color: var(--amber-lt);
      letter-spacing: 0.1em;
      text-transform: uppercase;
      font-weight: 800;
      display: block;
    }

    .user-actions {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .user-info {
      font-size: 0.9rem;
      color: rgba(255,255,255,0.75);
      font-weight: 600;
    }

    .user-info strong {
      color: var(--amber-lt);
    }

    .btn-action-header {
      background: rgba(255,255,255,0.08);
      color: var(--white);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 8px;
      padding: 0.5rem 0.8rem;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s;
    }

    .btn-action-header:hover {
      background: rgba(255,255,255,0.18);
      border-color: var(--amber-lt);
    }

    .btn-logout:hover {
      background: #ef4444;
      border-color: #ef4444;
    }

    main {
      max-width: 1440px;
      margin: 2rem auto;
      padding: 0 2rem;
    }

    .page-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .page-title {
      font-family: 'Lora', serif;
      font-size: 2rem;
      color: var(--navy);
      font-weight: 500;
    }

    /* ══════════════════════════════════════
       METRICS CARD STYLES
       ══════════════════════════════════════ */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 1.25rem;
      margin-bottom: 1.5rem;
    }

    .stat-card {
      background: var(--white);
      border-radius: 16px;
      padding: 1.2rem;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-left: 5px solid var(--navy);
      transition: transform 0.2s;
    }

    .stat-card:hover {
      transform: translateY(-2px);
    }

    .stat-card.stat-pending { border-left-color: var(--amber); }
    .stat-card.stat-confirmed { border-left-color: var(--sky); }
    .stat-card.stat-completed { border-left-color: var(--green); }
    .stat-card.stat-cancelled { border-left-color: #ef4444; }

    .stat-label {
      font-size: 0.82rem;
      text-transform: uppercase;
      font-weight: 800;
      color: var(--grey-5);
      letter-spacing: 0.05em;
    }

    .stat-val {
      font-size: 2rem;
      font-weight: 800;
      color: var(--navy);
      line-height: 1.1;
      margin-top: 0.4rem;
      font-family: 'Lora', serif;
    }

    .stat-card.stat-pending .stat-val { color: var(--amber); }
    .stat-card.stat-confirmed .stat-val { color: var(--sky); }
    .stat-card.stat-completed .stat-val { color: var(--green); }
    .stat-card.stat-cancelled .stat-val { color: #ef4444; }

    /* ══════════════════════════════════════
       COLLAPSIBLE ANALYTICS PANEL
       ══════════════════════════════════════ */
    .analytics-panel {
      background: var(--white);
      border-radius: 16px;
      box-shadow: var(--shadow);
      border: 2px solid var(--grey-1);
      margin-bottom: 1.5rem;
      overflow: hidden;
    }

    .analytics-header {
      background: var(--white);
      padding: 1rem 1.5rem;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background 0.2s;
      user-select: none;
    }

    .analytics-header:hover {
      background: var(--off);
    }

    .analytics-header h3 {
      font-size: 1rem;
      font-weight: 800;
      color: var(--navy);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .analytics-toggle-arrow {
      font-size: 0.8rem;
      color: var(--grey-5);
      font-weight: 800;
      transition: transform 0.3s;
    }

    .analytics-panel.collapsed .analytics-content {
      display: none;
    }

    .analytics-panel.collapsed .analytics-toggle-arrow {
      transform: rotate(-90deg);
    }

    .analytics-content {
      padding: 1.5rem;
      border-top: 1.5px solid var(--grey-1);
      display: grid;
      grid-template-columns: 3fr 2fr;
      gap: 2rem;
    }

    .chart-container {
      background: var(--off);
      border-radius: 12px;
      padding: 1rem;
      position: relative;
      height: 250px;
      width: 100%;
      border: 1.5px solid var(--grey-1);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .chart-box h4 {
      font-size: 0.88rem;
      text-transform: uppercase;
      font-weight: 800;
      color: var(--grey-5);
      margin-bottom: 0.5rem;
      letter-spacing: 0.05em;
      text-align: center;
    }

    /* ══════════════════════════════════════
       FILTER AND SEARCH WIDGETS
       ══════════════════════════════════════ */
    .filter-bar {
      background: var(--white);
      border-radius: 16px;
      padding: 1.25rem;
      box-shadow: var(--shadow);
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .search-box {
      flex: 1;
      min-width: 250px;
      position: relative;
    }

    .search-box input {
      width: 100%;
      background: var(--off);
      border: 2px solid var(--grey-1);
      border-radius: 10px;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      font-family: 'Nunito', sans-serif;
      font-size: 0.95rem;
      font-weight: 600;
      outline: none;
      transition: all 0.2s;
    }

    .search-box input:focus {
      border-color: var(--sky);
      background: var(--white);
      box-shadow: 0 0 10px rgba(31,124,184,0.15);
    }

    .search-box::before {
      content: '🔍';
      position: absolute;
      left: 0.9rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 0.95rem;
      opacity: 0.6;
    }

    .filter-group {
      display: flex;
      gap: 0.75rem;
      align-items: center;
    }

    .filter-select {
      background: var(--off);
      border: 2px solid var(--grey-1);
      border-radius: 10px;
      padding: 0.72rem 2.25rem 0.72rem 1rem;
      font-family: 'Nunito', sans-serif;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text);
      outline: none;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235e6e7d' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1rem;
      transition: all 0.2s;
    }

    .filter-select:focus {
      border-color: var(--sky);
      background-color: var(--white);
    }

    .btn-export {
      background: var(--green-pale);
      color: var(--green);
      border: 2px solid rgba(0,76,58,0.25);
      border-radius: 10px;
      padding: 0.72rem 1.25rem;
      font-family: 'Nunito', sans-serif;
      font-size: 0.95rem;
      font-weight: 800;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.2s;
      margin-left: auto;
    }

    .btn-export:hover {
      background: var(--green);
      color: var(--white);
      border-color: var(--green);
      transform: translateY(-1px);
    }

    /* ══════════════════════════════════════
       DATA TABLE STYLES
       ══════════════════════════════════════ */
    .table-container {
      background: var(--white);
      border-radius: 16px;
      box-shadow: var(--shadow);
      overflow: hidden;
      border: 2px solid var(--grey-1);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    th {
      background: var(--navy-dk);
      color: var(--white);
      font-size: 0.85rem;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.05em;
      padding: 1.1rem 1.25rem;
      border-bottom: 2px solid rgba(0,0,0,0.15);
    }

    td {
      padding: 1.1rem 1.25rem;
      border-bottom: 1.5px solid var(--grey-1);
      font-size: 0.95rem;
      color: var(--text);
      vertical-align: middle;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover td {
      background: var(--off);
    }

    .patient-name {
      font-weight: 700;
      color: var(--navy);
      display: block;
    }

    .patient-meta {
      font-size: 0.82rem;
      color: var(--grey-5);
      font-weight: 600;
      display: block;
      margin-top: 0.15rem;
    }

    .contact-item {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.92rem;
      font-weight: 600;
    }

    .contact-item a {
      color: var(--sky);
      text-decoration: none;
    }

    .contact-item a:hover {
      text-decoration: underline;
    }

    .wa-link {
      color: #25d366 !important;
      font-size: 1.05rem;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      transition: transform 0.2s;
    }

    .wa-link:hover {
      transform: scale(1.15);
    }

    .service-badge {
      font-weight: 700;
      font-size: 0.9rem;
      color: var(--navy-lt);
    }

    .preferred-dt {
      font-weight: 700;
      font-size: 0.92rem;
    }

    /* Status Badges */
    .status-pill {
      display: inline-block;
      font-size: 0.75rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      padding: 0.35rem 0.75rem;
      border-radius: 50px;
      text-align: center;
    }

    .status-pill.status-pending { background: var(--amber-pale); color: #d46f05; }
    .status-pill.status-confirmed { background: var(--sky-pale); color: var(--navy-lt); }
    .status-pill.status-completed { background: var(--green-pale); color: var(--green); }
    .status-pill.status-cancelled { background: #fef2f2; color: #ef4444; }

    /* Action triggers */
    .actions-cell {
      white-space: nowrap;
    }

    .action-btn {
      width: 34px;
      height: 34px;
      border-radius: 8px;
      border: 2px solid var(--grey-1);
      background: var(--white);
      cursor: pointer;
      font-size: 0.95rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      margin-right: 0.3rem;
      outline: none;
    }

    .action-btn:last-child {
      margin-right: 0;
    }

    .btn-confirm:hover { background: var(--sky-pale); border-color: var(--sky); color: var(--sky); }
    .btn-complete:hover { background: var(--green-pale); border-color: var(--green); color: var(--green); }
    .btn-cancel:hover { background: #fef2f2; border-color: #fca5a5; color: #ef4444; }
    .btn-delete:hover { background: #fef2f2; border-color: #ef4444; color: #ef4444; }

    /* ══════════════════════════════════════
       EXPANDED ROW & STAFF NOTES STYLES
       ══════════════════════════════════════ */
    .notes-row {
      display: none;
      background: #fafbfc;
    }
    
    .notes-row.expanded {
      display: table-row;
    }

    .notes-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      padding: 0.75rem 1.25rem 1.25rem;
    }

    .notes-box {
      background: var(--white);
      border-radius: 12px;
      padding: 1.25rem;
      border-left: 4px solid var(--sky);
      box-shadow: 0 2px 10px rgba(0,0,0,0.02);
      border-top: 1px solid var(--grey-1);
      border-right: 1px solid var(--grey-1);
      border-bottom: 1px solid var(--grey-1);
      display: flex;
      flex-direction: column;
    }

    .notes-box.admin-notes-box {
      border-left-color: var(--amber);
    }

    .notes-box h5 {
      font-size: 0.82rem;
      text-transform: uppercase;
      font-weight: 800;
      color: var(--grey-5);
      margin-bottom: 0.5rem;
      letter-spacing: 0.05em;
    }

    .notes-box p {
      font-size: 0.96rem;
      line-height: 1.6;
      font-weight: 600;
      color: var(--text);
      white-space: pre-wrap;
      flex: 1;
    }

    .admin-notes-textarea {
      width: 100%;
      height: 90px;
      background: var(--off);
      border: 1.5px solid var(--grey-3);
      border-radius: 8px;
      padding: 0.65rem 0.8rem;
      font-family: 'Nunito', sans-serif;
      font-size: 0.92rem;
      font-weight: 600;
      color: var(--text);
      resize: vertical;
      outline: none;
      margin-bottom: 0.65rem;
      transition: all 0.2s;
    }

    .admin-notes-textarea:focus {
      border-color: var(--amber);
      background: var(--white);
    }

    .btn-save-notes {
      align-self: flex-end;
      background: var(--amber-pale);
      color: #926d1c;
      border: 1.5px solid rgba(183,142,58,0.3);
      border-radius: 8px;
      padding: 0.45rem 1rem;
      font-family: 'Nunito', sans-serif;
      font-size: 0.85rem;
      font-weight: 800;
      cursor: pointer;
      transition: all 0.2s;
      outline: none;
    }

    .btn-save-notes:hover {
      background: var(--amber);
      color: var(--white);
      border-color: var(--amber);
    }

    .btn-expand-notes {
      background: none;
      border: none;
      color: var(--sky);
      font-weight: 800;
      font-size: 0.85rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.2rem;
      outline: none;
    }

    .btn-expand-notes:hover {
      text-decoration: underline;
    }

    .empty-state {
      padding: 4rem 2rem;
      text-align: center;
      color: var(--grey-5);
    }

    .empty-state span {
      font-size: 3rem;
      display: block;
      margin-bottom: 1rem;
    }

    .empty-state h3 {
      font-family: 'Lora', serif;
      font-size: 1.5rem;
      color: var(--navy);
      margin-bottom: 0.5rem;
      font-weight: 500;
    }

    .empty-state p {
      font-weight: 600;
    }

    .db-alert {
      background: #fef2f2;
      border: 2px solid #fca5a5;
      color: #b91c1c;
      padding: 1.25rem 2rem;
      border-radius: 12px;
      margin-bottom: 2rem;
      font-weight: 700;
      font-size: 1rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* ══════════════════════════════════════
       SETTINGS MODAL (Change Password)
       ══════════════════════════════════════ */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,24,48,0.5);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(4px);
    }

    .modal-overlay.show {
      display: flex;
    }

    .modal-card {
      background: var(--white);
      border-radius: 20px;
      width: 420px;
      max-width: 90%;
      padding: 2rem;
      box-shadow: var(--shadow-lg);
      border-top: 6px solid var(--amber);
      animation: modalFadeUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes modalFadeUp {
      from { opacity: 0; transform: translateY(15px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .modal-card h3 {
      font-family: 'Lora', serif;
      font-size: 1.5rem;
      color: var(--navy);
      margin-bottom: 0.5rem;
      font-weight: 500;
    }

    .modal-card p {
      font-size: 0.88rem;
      color: var(--grey-5);
      font-weight: 600;
      margin-bottom: 1.5rem;
    }

    .modal-form-group {
      margin-bottom: 1.15rem;
      text-align: left;
    }

    .modal-form-group label {
      font-size: 0.8rem;
      color: var(--text);
      font-weight: 800;
      display: block;
      margin-bottom: 0.4rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .modal-form-group input {
      width: 100%;
      background: var(--off);
      border: 2px solid var(--grey-1);
      border-radius: 8px;
      padding: 0.7rem 0.9rem;
      font-family: 'Nunito', sans-serif;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text);
      outline: none;
      transition: all 0.2s;
    }

    .modal-form-group input:focus {
      border-color: var(--amber);
      background: var(--white);
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
      margin-top: 1.75rem;
    }

    .btn-modal-cancel {
      background: var(--grey-1);
      color: var(--grey-5);
      border: none;
      border-radius: 8px;
      padding: 0.65rem 1.25rem;
      font-family: 'Nunito', sans-serif;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      outline: none;
    }

    .btn-modal-cancel:hover {
      background: var(--grey-3);
      color: var(--text);
    }

    .btn-modal-save {
      background: var(--amber);
      color: var(--white);
      border: none;
      border-radius: 8px;
      padding: 0.65rem 1.25rem;
      font-family: 'Nunito', sans-serif;
      font-size: 0.9rem;
      font-weight: 800;
      cursor: pointer;
      outline: none;
      transition: background 0.2s;
    }

    .btn-modal-save:hover {
      background: var(--amber-lt);
    }

    @media(max-width: 1024px) {
      .stats-row {
        grid-template-columns: repeat(3, 1fr);
      }
      .analytics-content {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
    }

    @media(max-width: 900px) {
      /* Table Card Transformation */
      table, thead, tbody, th, td, tr {
        display: block;
      }
      thead {
        display: none;
      }
      .table-container {
        background: transparent;
        border: none;
        box-shadow: none;
        overflow: visible;
      }
      tr.booking-row {
        background: var(--white);
        border: 2px solid var(--grey-1);
        border-radius: 16px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow);
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        position: relative;
      }
      tr.booking-row:hover td {
        background: transparent;
      }
      tr.booking-row td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.65rem 0;
        border-bottom: 1px dashed var(--grey-1);
        text-align: right;
      }
      tr.booking-row td:last-child {
        border-bottom: none;
        padding-bottom: 0;
      }
      tr.booking-row td::before {
        content: attr(data-label);
        font-weight: 800;
        color: var(--grey-5);
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0.05em;
        text-align: left;
      }
      
      /* Actions Alignment on Mobile Card */
      .actions-cell {
        justify-content: flex-end;
        gap: 0.5rem;
        padding-top: 0.85rem !important;
      }
      .action-btn {
        margin-right: 0;
      }
      
      /* Notes Row on Mobile */
      tr.notes-row {
        display: none;
        background: #fafbfc;
        border: 2px solid var(--grey-1);
        border-top: none;
        border-radius: 0 0 16px 16px;
        margin-top: -1.75rem;
        margin-bottom: 1.5rem;
        padding: 1.25rem;
        box-shadow: var(--shadow);
      }
      tr.notes-row.expanded {
        display: block;
      }
      tr.notes-row td {
        border: none;
        padding: 0;
      }
      .notes-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
        padding: 0;
      }
    }

    @media(max-width: 768px) {
      .stats-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
      }
      .filter-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
      }
      .btn-export {
        margin-left: 0;
        justify-content: center;
      }
      main {
        padding: 0 1rem;
        margin: 1.5rem auto;
      }
      header {
        padding: 1rem;
      }
    }

    @media(max-width: 600px) {
      .header-inner {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
      }
      .user-actions {
        flex-direction: column;
        gap: 0.65rem;
        width: 100%;
      }
      .user-info {
        font-size: 0.85rem;
      }
      .user-actions .btn-action-header {
        width: 100%;
        text-align: center;
        justify-content: center;
      }
      .page-title {
        font-size: 1.75rem;
        text-align: center;
        width: 100%;
      }
    }

    @media(max-width: 480px) {
      .stats-row {
        grid-template-columns: 1fr;
      }
      .filter-group {
        flex-direction: column;
        align-items: stretch;
      }
      .filter-select {
        width: 100%;
      }
    }
  </style>
</head>
<body>

  <!-- HEADER -->
  <header>
    <div class="header-inner">
      <a href="../index.html" class="brand">
        <img src="../assets/logo.png" alt="Winchester Health Centre Logo">
        <div class="brand-title">
          <h1>Winchester Health</h1>
          <span>Travel Clinic &amp; Pharmacy</span>
        </div>
      </a>
      <div class="user-actions">
        <span class="user-info">Logged in: <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></span>
        <button onclick="toggleSettingsModal(true)" class="btn-action-header" title="Open Security Settings">⚙️ Settings</button>
        <a href="logout.php" class="btn-logout btn-action-header">🚪 Log Out</a>
      </div>
    </div>
  </header>

  <!-- MAIN DASHBOARD -->
  <main>
    <div class="page-header-row">
      <h2 class="page-title">Booking Management</h2>
    </div>

    <?php if (!empty($connectionError)): ?>
      <div class="db-alert">
        <span>⚠️</span>
        <div>
          <p><?php echo htmlspecialchars($connectionError); ?></p>
          <small style="font-weight:600;color:#ef4444;margin-top:0.25rem;display:block">
            Please run the queries in <code>database.sql</code> and <code>migrate.sql</code> via phpMyAdmin to configure your database tables.
          </small>
        </div>
      </div>
    <?php endif; ?>

    <!-- STATS COUNTERS -->
    <div class="stats-row">
      <div class="stat-card">
        <span class="stat-label">Total Bookings</span>
        <span class="stat-val" id="count_total"><?php echo $stats['total']; ?></span>
      </div>
      <div class="stat-card stat-pending">
        <span class="stat-label">Pending</span>
        <span class="stat-val" id="count_pending"><?php echo $stats['pending']; ?></span>
      </div>
      <div class="stat-card stat-confirmed">
        <span class="stat-label">Confirmed</span>
        <span class="stat-val" id="count_confirmed"><?php echo $stats['confirmed']; ?></span>
      </div>
      <div class="stat-card stat-completed">
        <span class="stat-label">Completed</span>
        <span class="stat-val" id="count_completed"><?php echo $stats['completed']; ?></span>
      </div>
      <div class="stat-card stat-cancelled">
        <span class="stat-label">Cancelled</span>
        <span class="stat-val" id="count_cancelled"><?php echo $stats['cancelled']; ?></span>
      </div>
    </div>

    <!-- COLLAPSIBLE ANALYTICS PANEL -->
    <?php if (empty($connectionError) && $stats['total'] > 0): ?>
      <div class="analytics-panel collapsed" id="analyticsPanel">
        <div class="analytics-header" onclick="toggleAnalytics()">
          <h3>📊 Live Interactive Analytics Dashboard</h3>
          <span class="analytics-toggle-arrow" id="analyticsArrow">▼</span>
        </div>
        <div class="analytics-content">
          <div class="chart-box">
            <h4>Daily Booking Volume (Last 15 Days)</h4>
            <div class="chart-container">
              <canvas id="dailyVolumeChart"></canvas>
            </div>
          </div>
          <div class="chart-box">
            <h4>Service Distribution Share</h4>
            <div class="chart-container">
              <canvas id="serviceRatioChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="filter-bar">
      <div class="search-box">
        <input type="text" id="search_input" onkeyup="filterTable()" placeholder="Search bookings by name, phone or email...">
      </div>
      
      <div class="filter-group">
        <select id="filter_service" onchange="filterTable()" class="filter-select">
          <option value="">All Services</option>
          <option value="Travel">✈️ Travel Clinic / Vaccinations</option>
          <option value="Diagnostic">🔬 Diagnostic / Blood Tests</option>
          <option value="Ear">👂 Ear Wax Removal</option>
          <option value="Weight">⚖️ Weight Management</option>
          <option value="Pharmacy First">💊 Pharmacy First</option>
          <option value="Contraception">🚫 Contraception</option>
          <option value="Delivery">🚚 Home Delivery</option>
          <option value="Smoking">🚭 Quit Smoking</option>
          <option value="Discharge">🏥 Discharge Medicine</option>
          <option value="Medicine Service">📋 New Medicine Service</option>
        </select>
        
        <select id="filter_status" onchange="filterTable()" class="filter-select">
          <option value="">All Statuses</option>
          <option value="Pending">Pending</option>
          <option value="Confirmed">Confirmed</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
        </select>
      </div>

      <button onclick="exportCSV()" class="btn-export">📥 Export to CSV</button>
    </div>

    <!-- BOOKINGS DATA TABLE -->
    <div class="table-container">
      <table id="bookings_table">
        <thead>
          <tr>
            <th>Patient Details</th>
            <th>Contact Info</th>
            <th>Service Requested</th>
            <th>Preferred Date</th>
            <th style="text-align: center;">Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bookings)): ?>
            <tr id="empty_row">
              <td colspan="6">
                <div class="empty-state">
                  <span>📅</span>
                  <h3>No Bookings Found</h3>
                  <p>When patients fill out the website booking form, they will appear here.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($bookings as $b): 
              $fullName = htmlspecialchars($b['first_name'] . ($b['last_name'] ? ' ' . $b['last_name'] : ''));
              $phone = htmlspecialchars($b['phone']);
              $email = htmlspecialchars($b['email'] ?? '');
              $dob = $b['dob'] ? date('d M Y', strtotime($b['dob'])) : '';
              $ageStr = $b['dob'] ? ' (Age: ' . date_diff(date_create($b['dob']), date_create('today'))->y . ')' : '';
              $service = htmlspecialchars($b['service']);
              $preferredDate = $b['preferred_date'] ? date('d M Y · H:i', strtotime($b['preferred_date'])) : 'Anytime';
              $notes = htmlspecialchars($b['notes'] ?? '');
              $adminNotes = htmlspecialchars($b['admin_notes'] ?? '');
              $status = htmlspecialchars($b['status']);
              $submitted = date('d M Y · H:i', strtotime($b['created_at']));
            ?>
              <tr class="booking-row" id="row_<?php echo $b['id']; ?>" 
                  data-name="<?php echo strtolower($fullName); ?>"
                  data-phone="<?php echo strtolower($phone); ?>"
                  data-email="<?php echo strtolower($email); ?>"
                  data-service="<?php echo strtolower($service); ?>"
                  data-status="<?php echo $status; ?>">
                
                <!-- Patient Info -->
                <td data-label="Patient">
                  <span class="patient-name"><?php echo $fullName; ?></span>
                  <span class="patient-meta">
                    DOB: <?php echo $dob ? $dob . $ageStr : 'Not provided'; ?>
                  </span>
                  <span class="patient-meta" style="font-size:0.75rem;margin-top:0.2rem">
                    Submitted: <?php echo $submitted; ?>
                  </span>
                </td>
                
                <!-- Contact Info -->
                <td data-label="Contact">
                  <span class="contact-item">
                    📞 <a href="tel:<?php echo $phone; ?>"><?php echo $phone; ?></a>
                    <!-- WhatsApp prefilled template button -->
                    <a href="#" onclick="openWhatsApp('<?php echo addslashes($fullName); ?>', '<?php echo $phone; ?>', '<?php echo addslashes($service); ?>', '<?php echo addslashes($preferredDate); ?>'); event.preventDefault();" class="wa-link" title="Send confirmation message via WhatsApp">💬</a>
                  </span>
                  <?php if (!empty($email)): ?>
                    <span class="contact-item" style="font-size:0.85rem;margin-top:0.2rem">✉️ <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></span>
                  <?php endif; ?>
                </td>
                
                <!-- Service -->
                <td data-label="Service">
                  <span class="service-badge"><?php echo $service; ?></span>
                  <div style="margin-top:0.4rem">
                    <button onclick="toggleNotes(<?php echo $b['id']; ?>)" class="btn-expand-notes" id="notes_btn_<?php echo $b['id']; ?>">
                      👁️ View Panel Notes
                    </button>
                  </div>
                </td>
                
                <!-- Date -->
                <td data-label="Date">
                  <span class="preferred-dt"><?php echo $preferredDate; ?></span>
                </td>
                
                <!-- Status Badge -->
                <td data-label="Status" style="text-align: center;">
                  <span class="status-pill status-<?php echo strtolower($status); ?>" id="status_pill_<?php echo $b['id']; ?>">
                    <?php echo $status; ?>
                  </span>
                </td>
                
                <!-- Actions -->
                <td data-label="Actions" style="text-align: right;" class="actions-cell">
                  <button onclick="updateStatus(<?php echo $b['id']; ?>, 'confirm')" class="action-btn btn-confirm" title="Confirm Appointment">✔️</button>
                  <button onclick="updateStatus(<?php echo $b['id']; ?>, 'complete')" class="action-btn btn-complete" title="Mark as Completed">👍</button>
                  <button onclick="updateStatus(<?php echo $b['id']; ?>, 'cancel')" class="action-btn btn-cancel" title="Cancel Appointment">❌</button>
                  <button onclick="deleteBooking(<?php echo $b['id']; ?>)" class="action-btn btn-delete" title="Delete Booking">🗑️</button>
                </td>
              </tr>
              
              <!-- Expanded notes row -->
              <tr class="notes-row" id="notes_row_<?php echo $b['id']; ?>">
                <td colspan="6">
                  <div class="notes-grid">
                    <!-- Left Column: Patient's original notes -->
                    <div class="notes-box">
                      <h5>Patient's Booking Request Notes</h5>
                      <p><?php echo !empty($notes) ? $notes : '*No patient comments entered.*'; ?></p>
                    </div>
                    
                    <!-- Right Column: Internal Admin comments -->
                    <div class="notes-box notes-box admin-notes-box">
                      <h5>Internal Staff Comments (Admin Only)</h5>
                      <textarea id="admin_notes_<?php echo $b['id']; ?>" class="admin-notes-textarea" placeholder="Add custom staff comments, phone logs, or action tasks here..."><?php echo $adminNotes; ?></textarea>
                      <button onclick="saveAdminNotes(<?php echo $b['id']; ?>)" class="btn-save-notes" id="btn_notes_<?php echo $b['id']; ?>">💾 Save Comments</button>
                    </div>
                  </div>
                </td>
              </tr>
              
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- CHANGE PASSWORD MODAL -->
  <div class="modal-overlay" id="settingsModal" onclick="toggleSettingsModal(false, event)">
    <div class="modal-card">
      <h3>Change Password</h3>
      <p>Securely update your administrator panel login password.</p>
      
      <div id="modal_error" style="display:none;margin-bottom:1rem;color:#ef4444;font-weight:700;font-size:0.9rem"></div>
      
      <div class="modal-form-group">
        <label for="old_password">Current Password</label>
        <input type="password" id="old_password" placeholder="••••••••">
      </div>
      
      <div class="modal-form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" placeholder="Minimum 8 characters">
      </div>
      
      <div class="modal-form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" placeholder="••••••••">
      </div>
      
      <div class="modal-actions">
        <button onclick="toggleSettingsModal(false)" class="btn-modal-cancel">Cancel</button>
        <button onclick="changePassword()" class="btn-modal-save" id="btn_save_password">Save New Password</button>
      </div>
    </div>
  </div>

  <script>
    // Toggle expandable notes section
    function toggleNotes(id) {
      const notesRow = document.getElementById('notes_row_' + id);
      const notesBtn = document.getElementById('notes_btn_' + id);
      if (notesRow.classList.contains('expanded')) {
        notesRow.classList.remove('expanded');
        notesBtn.innerHTML = '👁️ View Panel Notes';
      } else {
        notesRow.classList.add('expanded');
        notesBtn.innerHTML = '🙈 Hide Panel Notes';
      }
    }

    // Client-side filtering logic
    function filterTable() {
      const searchVal = document.getElementById('search_input').value.toLowerCase().trim();
      const serviceVal = document.getElementById('filter_service').value.toLowerCase().trim();
      const statusVal = document.getElementById('filter_status').value.trim();
      
      const rows = document.querySelectorAll('.booking-row');
      let visibleCount = 0;

      rows.forEach(row => {
        const name = row.getAttribute('data-name');
        const phone = row.getAttribute('data-phone');
        const email = row.getAttribute('data-email');
        const service = row.getAttribute('data-service');
        const status = row.getAttribute('data-status');

        const matchesSearch = !searchVal || 
                              name.includes(searchVal) || 
                              phone.includes(searchVal) || 
                              email.includes(searchVal);
                              
        const matchesService = !serviceVal || service.includes(serviceVal);
        const matchesStatus = !statusVal || status === statusVal;

        if (matchesSearch && matchesService && matchesStatus) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
          // Collapse notes row if matching parent is hidden
          const notesRow = document.getElementById('notes_row_' + row.id.replace('row_', ''));
          if (notesRow) {
            notesRow.classList.remove('expanded');
            const notesBtn = document.getElementById('notes_btn_' + row.id.replace('row_', ''));
            if (notesBtn) notesBtn.innerHTML = '👁️ View Panel Notes';
          }
        }
      });

      // Show/hide empty state
      const emptyRow = document.getElementById('empty_row');
      if (emptyRow) {
        if (visibleCount === 0 && rows.length > 0) {
          emptyRow.style.display = '';
          emptyRow.innerHTML = '<td colspan="6"><div class="empty-state"><span>🔍</span><h3>No matching results</h3><p>Try adjusting your search query or status filters.</p></div></td>';
        } else if (rows.length > 0) {
          emptyRow.style.display = 'none';
        }
      }
    }

    // AJAX Call to update status
    async function updateStatus(id, action) {
      if (action === 'cancel' && !confirm('Are you sure you want to cancel this booking request?')) {
        return;
      }
      
      try {
        const response = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, action: action })
        });
        
        const result = await response.json();
        if (result.success) {
          // Update status in the data attribute and table cell
          const row = document.getElementById('row_' + id);
          const statusPill = document.getElementById('status_pill_' + id);
          const oldStatus = row.getAttribute('data-status');
          
          row.setAttribute('data-status', result.status);
          statusPill.className = 'status-pill status-' + result.status.toLowerCase();
          statusPill.innerText = result.status;
          
          // Re-calculate statistics dynamically in the top cards
          updateStatsCount(oldStatus, result.status);
          
          // Trigger filter update
          filterTable();
        } else {
          alert(result.message || 'Failed to update status.');
        }
      } catch (err) {
        console.error(err);
        alert('An error occurred while communicating with the database.');
      }
    }

    // AJAX Call to delete booking
    async function deleteBooking(id) {
      if (!confirm('🚨 WARNING: Are you sure you want to permanently delete this booking? This action CANNOT be undone.')) {
        return;
      }
      
      try {
        const response = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, action: 'delete' })
        });
        
        const result = await response.json();
        if (result.success) {
          const row = document.getElementById('row_' + id);
          const notesRow = document.getElementById('notes_row_' + id);
          const status = row.getAttribute('data-status');
          
          // Decrease statistics
          decreaseStatsCount(status);
          
          // Remove rows from DOM
          row.remove();
          if (notesRow) notesRow.remove();
          
          // Check if table is empty now
          const rows = document.querySelectorAll('.booking-row');
          if (rows.length === 0) {
            location.reload(); // Reload to show default empty state
          } else {
            filterTable();
          }
        } else {
          alert(result.message || 'Failed to delete booking.');
        }
      } catch (err) {
        console.error(err);
        alert('An error occurred while communicating with the database.');
      }
    }

    // AJAX Call to save internal admin notes
    async function saveAdminNotes(id) {
      const notesVal = document.getElementById('admin_notes_' + id).value;
      const btn = document.getElementById('btn_notes_' + id);
      
      btn.disabled = true;
      btn.innerText = 'Saving...';
      
      try {
        const response = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, action: 'save_notes', admin_notes: notesVal })
        });
        
        const result = await response.json();
        if (result.success) {
          btn.innerText = '💾 Saved!';
          setTimeout(() => {
            btn.disabled = false;
            btn.innerText = '💾 Save Comments';
          }, 1500);
        } else {
          alert(result.message || 'Failed to save comments.');
          btn.disabled = false;
          btn.innerText = '💾 Save Comments';
        }
      } catch (err) {
        console.error(err);
        alert('An error occurred while communicating with the database.');
        btn.disabled = false;
        btn.innerText = '💾 Save Comments';
      }
    }

    // Open WhatsApp with prefilled confirmation template
    function openWhatsApp(name, phone, service, date) {
      const msg = `Hi ${name}, this is Winchester Health Centre. We've received your booking request for "${service}" (preferred date/time: ${date}). Please let us know if you'd like to confirm this slot. Thank you!`;
      
      // Clean phone string to numeric digits only
      const cleanPhone = phone.replace(/[^0-9]/g, '');
      
      // Format phone prefix (convert UK leading 0 to country code 44)
      let formattedPhone = cleanPhone;
      if (cleanPhone.startsWith('0')) {
        formattedPhone = '44' + cleanPhone.substring(1);
      }
      
      const url = `https://wa.me/${formattedPhone}?text=${encodeURIComponent(msg)}`;
      window.open(url, '_blank');
    }

    // Open/Close settings change password modal
    function toggleSettingsModal(open, event = null) {
      if (event && event.target !== document.getElementById('settingsModal')) {
        return;
      }
      
      const modal = document.getElementById('settingsModal');
      const errorDiv = document.getElementById('modal_error');
      
      errorDiv.style.display = 'none';
      document.getElementById('old_password').value = '';
      document.getElementById('new_password').value = '';
      document.getElementById('confirm_password').value = '';
      
      if (open) {
        modal.classList.add('show');
      } else {
        modal.classList.remove('show');
      }
    }

    // AJAX Call to Change Admin Password
    async function changePassword() {
      const oldPass = document.getElementById('old_password').value;
      const newPass = document.getElementById('new_password').value;
      const confirmPass = document.getElementById('confirm_password').value;
      const errorDiv = document.getElementById('modal_error');
      const btn = document.getElementById('btn_save_password');
      
      errorDiv.style.display = 'none';
      
      if (!oldPass || !newPass || !confirmPass) {
        errorDiv.innerText = 'All password fields are required.';
        errorDiv.style.display = 'block';
        return;
      }
      
      if (newPass.length < 8) {
        errorDiv.innerText = 'New password must be at least 8 characters long.';
        errorDiv.style.display = 'block';
        return;
      }
      
      if (newPass !== confirmPass) {
        errorDiv.innerText = 'New password and confirmation do not match.';
        errorDiv.style.display = 'block';
        return;
      }
      
      btn.disabled = true;
      btn.innerText = 'Updating...';
      
      try {
        const response = await fetch('update_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'change_password', old_password: oldPass, new_password: newPass })
        });
        
        const result = await response.json();
        if (result.success) {
          alert('Password successfully updated!');
          toggleSettingsModal(false);
        } else {
          errorDiv.innerText = result.message || 'Failed to update password.';
          errorDiv.style.display = 'block';
        }
      } catch (err) {
        console.error(err);
        errorDiv.innerText = 'An error occurred. Please try again.';
        errorDiv.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.innerText = 'Save New Password';
      }
    }

    // Dynamic stats updates
    function updateStatsCount(oldStatus, newStatus) {
      if (oldStatus === newStatus) return;
      
      const cards = {
        'Pending': document.getElementById('count_pending'),
        'Confirmed': document.getElementById('count_confirmed'),
        'Completed': document.getElementById('count_completed'),
        'Cancelled': document.getElementById('count_cancelled')
      };
      
      if (cards[oldStatus]) {
        cards[oldStatus].innerText = Math.max(0, parseInt(cards[oldStatus].innerText) - 1);
      }
      if (cards[newStatus]) {
        cards[newStatus].innerText = parseInt(cards[newStatus].innerText) + 1;
      }
    }

    function decreaseStatsCount(status) {
      const totalCard = document.getElementById('count_total');
      totalCard.innerText = Math.max(0, parseInt(totalCard.innerText) - 1);
      
      const cards = {
        'Pending': document.getElementById('count_pending'),
        'Confirmed': document.getElementById('count_confirmed'),
        'Completed': document.getElementById('count_completed'),
        'Cancelled': document.getElementById('count_cancelled')
      };
      
      if (cards[status]) {
        cards[status].innerText = Math.max(0, parseInt(cards[status].innerText) - 1);
      }
    }

    // Export Table Data as a CSV file
    function exportCSV() {
      const rows = document.querySelectorAll('.booking-row');
      if (rows.length === 0) {
        alert('No data to export.');
        return;
      }
      
      let csv = 'ID,Name,Phone,Email,Service,Preferred Date,Status,Created At\n';
      
      rows.forEach(row => {
        const id = row.id.replace('row_', '');
        const name = row.querySelector('.patient-name').innerText.replace(/"/g, '""');
        const phone = row.querySelector('.contact-item a').innerText;
        const emailEl = row.querySelector('.contact-item[style*="font-size"] a');
        const email = emailEl ? emailEl.innerText : '';
        const service = row.querySelector('.service-badge').innerText.replace(/"/g, '""');
        const date = row.querySelector('.preferred-dt').innerText.replace(/"/g, '""');
        const status = row.getAttribute('data-status');
        const submittedEl = row.querySelector('.patient-meta[style*="font-size"]');
        const submitted = submittedEl ? submittedEl.innerText.replace('Submitted: ', '').replace(/"/g, '""') : '';
        
        csv += `${id},"${name}","${phone}","${email}","${service}","${date}",${status},"${submitted}"\n`;
      });
      
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', 'bookings_export_' + new Date().toISOString().slice(0,10) + '.csv');
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // Toggle Collapsible Analytics Panel
    function toggleAnalytics() {
      const panel = document.getElementById('analyticsPanel');
      if (panel.classList.contains('collapsed')) {
        panel.classList.remove('collapsed');
        document.getElementById('analyticsArrow').innerText = '▲';
      } else {
        panel.classList.add('collapsed');
        document.getElementById('analyticsArrow').innerText = '▼';
      }
    }

    // Initialize charts if database loaded
    <?php if (empty($connectionError) && $stats['total'] > 0): ?>
    document.addEventListener("DOMContentLoaded", function() {
      // 1. Daily Booking Volume Chart (Bar)
      const daysData = <?php echo json_encode($chartDays); ?>;
      const countsData = <?php echo json_encode($chartCounts); ?>;
      
      const ctxVolume = document.getElementById('dailyVolumeChart').getContext('2d');
      new Chart(ctxVolume, {
        type: 'bar',
        data: {
          labels: daysData.length > 0 ? daysData : ['No recent data'],
          datasets: [{
            label: 'Bookings',
            data: countsData.length > 0 ? countsData : [0],
            backgroundColor: '#1f7cb8',
            borderColor: '#1f7cb8',
            borderWidth: 1,
            borderRadius: 6,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { stepSize: 1, precision: 0 }
            }
          }
        }
      });

      // 2. Service Share Distribution Chart (Doughnut)
      const serviceStats = <?php echo json_encode($serviceStats); ?>;
      const serviceLabels = Object.keys(serviceStats);
      const serviceCounts = Object.values(serviceStats);

      const ctxRatio = document.getElementById('serviceRatioChart').getContext('2d');
      new Chart(ctxRatio, {
        type: 'doughnut',
        data: {
          labels: serviceLabels.length > 0 ? serviceLabels : ['No services booked'],
          datasets: [{
            data: serviceCounts.length > 0 ? serviceCounts : [1],
            backgroundColor: [
              '#002647', // Navy
              '#004c3a', // Green
              '#b78e3a', // Gold
              '#1f7cb8', // Sky
              '#cca34f', // Light gold
              '#5e6e7d', // Grey
              '#036951', // Bright Green
              '#4b9cd3', // Light Sky
              '#e06262', // Rust Red
              '#8e44ad', // Purple
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                boxWidth: 12,
                font: { size: 10, family: 'Nunito' }
              }
            }
          }
        }
      });
    });
    <?php endif; ?>
  </script>
</body>
</html>
