<?php
define('MHAVIS_EXEC', true);
$page_title = "Admin Dashboard";
$active_page = "dashboard";
require_once __DIR__ . '/config/init.php';
requireAdmin();

// Get counts from database
$conn = getDBConnection();

// Total patients
$result = $conn->query("SELECT COUNT(*) as count FROM patients");
$totalPatients = $result->fetch_assoc()['count'];

// Total doctors
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'Doctor'");
$totalDoctors = $result->fetch_assoc()['count'];

// Today's appointments
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$today'");
$todayAppointments = $result->fetch_assoc()['count'];

// This month's revenue (Net Revenue: amount - discount_amount) - matching daily_sales.php logic
// Using today as end date to match report_analytics.php "Per Month" period
$firstDayOfMonth = date('Y-m-01');
$today = date('Y-m-d');
$result = $conn->query("SELECT SUM(amount - COALESCE(discount_amount, 0)) as total FROM transactions 
                       WHERE DATE(transaction_date) BETWEEN '$firstDayOfMonth' AND '$today'
                       AND payment_status = 'Completed'");
$monthlyRevenue = $result->fetch_assoc()['total'] ?? 0;

// Get monthly revenue data for chart
$monthlyRevenueData = [];
$monthlyAppointmentsData = [];
for ($i = 0; $i < 6; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    // For current month (i=0), use today; for historical months, use last day of that month
    $monthEnd = ($i === 0) ? $today : date('Y-m-t', strtotime("-$i months"));
    
    // Revenue (Net Revenue: amount - discount_amount) - matching daily_sales.php logic
    $result = $conn->query("SELECT SUM(amount - COALESCE(discount_amount, 0)) as total FROM transactions 
                           WHERE DATE(transaction_date) BETWEEN '$monthStart' AND '$monthEnd'
                           AND payment_status = 'Completed'");
    $monthlyRevenueData[date('M Y', strtotime($month))] = $result->fetch_assoc()['total'] ?? 0;
    
    // Appointments
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments 
                           WHERE appointment_date BETWEEN '$monthStart' AND '$monthEnd'");
    $monthlyAppointmentsData[date('M Y', strtotime($month))] = $result->fetch_assoc()['count'];
}

// Reverse arrays to show oldest to newest
$monthlyRevenueData = array_reverse($monthlyRevenueData);
$monthlyAppointmentsData = array_reverse($monthlyAppointmentsData);

// Prepare data for JavaScript
$revenueLabels = json_encode(array_keys($monthlyRevenueData));
$revenueData = json_encode(array_values($monthlyRevenueData));
$appointmentLabels = json_encode(array_keys($monthlyAppointmentsData));
$appointmentData = json_encode(array_values($monthlyAppointmentsData));

include 'includes/header.php';
?>

<style>
.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.stat-card:active {
    transform: translateY(-1px);
}
.calendar-container {
    min-height: 400px;
}
.chart-wrapper {
    position: relative;
    height: 180px;
    width: 100%;
}
.chart-wrapper canvas {
    max-width: 100%;
    height: auto !important;
}
/* Reduce padding on chart card bodies */
.card-body .chart-wrapper {
    margin: -0.5rem;
}
.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
}
.btn-group .btn {
    margin-right: 2px;
}
.badge {
    font-size: 0.75em;
}
.alert {
    border-radius: 8px;
    border: none;
}
.card {
    border-radius: 10px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.schedule-day { border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 10px; padding: 15px; }
.schedule-day.active { background-color: #e3f2fd; border-color: #2196f3; }
.appointment-date { text-align: center; }
.appointment-date .badge { font-size: 0.75rem; }
.appointment-date .h5 { margin: 0; font-weight: bold; }
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 10px 10px 0 0 !important;
}

.card-header:has(#managementTabs) {
    padding: 0;
    overflow: hidden;
}

.card-header-tabs {
    margin-bottom: 0;
    border-bottom: none;
}
.debug-info {
    position: fixed;
    top: 10px;
    right: 10px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 5px;
    font-size: 12px;
    z-index: 9999;
    max-width: 300px;
    display: none;
}

/* Modal Styles */
.modal-lg {
    max-width: 800px;
}

.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.modal-body .row {
    margin-bottom: 0;
}

.modal-body h6 {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 8px;
    margin-bottom: 15px;
}

.modal-body .text-muted {
    font-size: 0.9rem;
}

.modal-body .badge {
    font-size: 0.8rem;
    padding: 6px 12px;
}

.modal-footer .btn {
    min-width: 120px;
}

/* Responsive adjustments */
@media (max-width: 1199.98px) {
    /* Tablet adjustments */
    .stat-card .fa-2x {
        font-size: 1.5rem !important;
    }
    
    .stat-card h3 {
        font-size: 1.5rem;
    }
}

@media (max-width: 991.98px) {
    /* Tablet portrait adjustments */
    .calendar-container {
        min-height: 350px;
    }
    
    .card-header h5 {
        font-size: 1.1rem;
    }
    
    .stat-card h3 {
        font-size: 1.4rem;
    }
    
    .stat-card .card-body {
        padding: 0.75rem;
    }
    
    .stat-card {
        margin-bottom: 0.75rem;
    }
}

@media (max-width: 768px) {
    .modal-lg {
        max-width: 95%;
        margin: 10px auto;
    }
    
    .modal-body {
        max-height: 60vh;
        padding: 1rem;
    }
    
    .modal-body .col-md-6 {
        margin-bottom: 20px;
    }
    
    .modal-header h5 {
        font-size: 1.1rem;
    }
    
    .modal-footer .btn {
        min-width: auto;
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .modal-footer .btn:last-child {
        margin-bottom: 0;
    }
    
    .calendar-container {
        min-height: 300px;
        padding: 0.5rem;
    }
    
    .chart-wrapper {
        height: 150px;
    }
    
    /* Reduce padding on chart card bodies for tablet */
    .card-body .chart-wrapper {
        margin: -0.375rem;
    }
    
    .card-header {
        padding: 0.75rem 1rem;
    }
    
    .card-header:has(#managementTabs) {
        padding: 0;
    }
    
    .card-header h5 {
        font-size: 1rem;
        line-height: 1.4;
    }
    
    .card-header .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .stat-card {
        margin-bottom: 0.75rem;
    }
    
    .stat-card .card-body {
        padding: 0.625rem 0.5rem;
    }
    
    .stat-card h3 {
        font-size: 1.15rem;
        line-height: 1.2;
        margin-bottom: 0.2rem;
    }
    
    .stat-card .fa-2x {
        font-size: 1.15rem !important;
    }
    
    .stat-card .text-muted {
        font-size: 0.7rem;
        line-height: 1.15;
    }
    
    /* Chart containers */
    .card-body {
        position: relative;
    }
    
    .card-body canvas {
        max-height: 180px;
    }
    
    /* Better touch targets for mobile */
    .btn-sm {
        min-height: 36px;
        touch-action: manipulation;
    }
    
    .debug-info {
        max-width: 90%;
        right: 5%;
        left: 5%;
        top: 60px;
        font-size: 11px;
        padding: 8px;
    }
}

@media (max-width: 575.98px) {
    /* Mobile adjustments */
    /* Reduce row spacing for stat cards */
    .row.mb-4:first-of-type {
        margin-bottom: 0.75rem !important;
    }
    
    .row.mb-4 .mb-4 {
        margin-bottom: 0.5rem !important;
    }
    
    .calendar-container {
        min-height: 250px;
        padding: 0.25rem;
    }
    
    .chart-wrapper {
        height: 130px;
    }
    
    .card-header {
        padding: 0.625rem 0.75rem;
    }
    
    .card-header h5 {
        font-size: 0.95rem;
    }
    
    .card-header .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
    
    .card-body {
        padding: 0.75rem;
    }
    
    /* Reduce padding on chart card bodies for mobile */
    .card-body .chart-wrapper {
        margin: -0.25rem;
    }
    
    .stat-card {
        margin-bottom: 0.5rem;
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    }
    
    .stat-card .card-body {
        padding: 0.5rem 0.4rem;
    }
    
    .stat-card .row {
        margin: 0;
        align-items: center;
    }
    
    .stat-card .col-3 {
        padding-left: 0.15rem;
        padding-right: 0.15rem;
        flex: 0 0 auto;
        width: auto;
    }
    
    .stat-card .col-9 {
        padding-left: 0.3rem;
        padding-right: 0.15rem;
    }
    
    .stat-card h3 {
        font-size: 0.95rem;
        margin-bottom: 0.15rem;
        line-height: 1.1;
        font-weight: 700;
    }
    
    .stat-card .fa-2x {
        font-size: 0.95rem !important;
    }
    
    .stat-card .text-muted {
        font-size: 0.65rem;
        line-height: 1.1;
        margin: 0;
    }
    
    .stat-card .text-center {
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Chart containers on mobile */
    .card-body canvas {
        max-height: 150px;
    }
    
    /* Chart wrapper for responsive height */
    .card-body {
        position: relative;
        min-height: 85px;
    }
    
    /* Better touch targets */
    .btn-sm {
        min-height: 40px;
        padding: 0.5rem 0.75rem;
        touch-action: manipulation;
    }
    
    .modal-lg {
        max-width: 100%;
        margin: 0;
    }
    
    .modal-dialog {
        margin: 0;
    }
    
    .modal-content {
        border-radius: 0;
        min-height: 100vh;
    }
    
    .modal-body {
        max-height: calc(100vh - 200px);
        padding: 0.75rem;
    }
    
    .modal-body h6 {
        font-size: 0.95rem;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
    }
    
    .modal-body .col-md-6 {
        margin-bottom: 1rem;
    }
    
    .modal-body strong {
        font-size: 0.9rem;
    }
    
    .modal-body .text-muted {
        font-size: 0.85rem;
    }
    
    .debug-info {
        max-width: 95%;
        right: 2.5%;
        left: 2.5%;
        font-size: 10px;
        padding: 6px;
    }
    
    /* FullCalendar mobile adjustments */
    .fc-header-toolbar {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .fc-toolbar-chunk {
        width: 100%;
        display: flex;
        justify-content: center;
    }
    
    .fc-button-group {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .fc-button {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
    
    .fc-dayGridMonth-view .fc-day {
        min-height: 60px;
    }
    
    .fc-event-title {
        font-size: 0.7rem;
    }
    
    .fc-col-header-cell {
        font-size: 0.75rem;
        padding: 0.25rem;
    }
}

/* Management Tabs Styles */
#managementTabsContent .tab-pane {
    min-height: 600px;
}

#managementTabsContent iframe {
    width: 100%;
    min-height: 800px;
    border: none;
    border-radius: 8px;
}

/* Tab Navigation Styling - Enhanced Visibility */
#managementTabs {
    border-bottom: 2px solid #dee2e6;
    background-color: #ffffff;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    flex-wrap: nowrap;
    white-space: nowrap;
}

#managementTabs::-webkit-scrollbar {
    height: 6px;
}

#managementTabs::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#managementTabs::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

#managementTabs::-webkit-scrollbar-thumb:hover {
    background: #555;
}

#managementTabs .nav-item {
    flex-shrink: 0;
}

#managementTabs .nav-link {
    color: #495057 !important;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 10px 16px;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    margin-right: 2px;
    background-color: transparent;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
}

#managementTabs .nav-link:hover {
    color: #0d6efd !important;
    border-bottom-color: #0d6efd;
    background-color: #f8f9fa;
}

#managementTabs .nav-link.active {
    color: #0d6efd !important;
    background-color: #ffffff;
    border-bottom-color: #0d6efd;
    font-weight: 600;
}

#managementTabs .nav-link i {
    margin-right: 6px;
    font-size: 0.9rem;
}

@media (max-width: 991.98px) {
    #managementTabs .nav-link {
        padding: 8px 14px;
        font-size: 0.9rem;
    }
    
    #managementTabs .nav-link i {
        margin-right: 5px;
        font-size: 0.85rem;
    }
    
    #managementTabsContent iframe {
        min-height: 600px;
    }
}

@media (max-width: 768px) {
    #managementTabs {
        border-bottom-width: 1px;
    }
    
    #managementTabs .nav-link {
        padding: 8px 12px;
        font-size: 0.85rem;
        border-bottom-width: 2px;
    }
    
    #managementTabs .nav-link i {
        margin-right: 4px;
        font-size: 0.8rem;
    }
    
    #managementTabsContent iframe {
        min-height: 500px;
    }
}

@media (max-width: 575.98px) {
    #managementTabs {
        border-bottom-width: 1px;
    }
    
    #managementTabs .nav-link {
        padding: 7px 10px;
        font-size: 0.8rem;
        border-bottom-width: 2px;
        margin-right: 1px;
    }
    
    #managementTabs .nav-link i {
        margin-right: 3px;
        font-size: 0.75rem;
    }
    
    #managementTabsContent iframe {
        min-height: 450px;
    }
}

@media (max-width: 375px) {
    #managementTabs .nav-link {
        padding: 6px 8px;
        font-size: 0.75rem;
    }
    
    #managementTabs .nav-link i {
        margin-right: 2px;
        font-size: 0.7rem;
    }
    
    #managementTabsContent iframe {
        min-height: 400px;
    }
}

@media (max-width: 375px) {
    /* Small mobile devices */
    /* Further reduce row spacing */
    .row.mb-4:first-of-type {
        margin-bottom: 0.5rem !important;
    }
    
    .row.mb-4 .mb-4 {
        margin-bottom: 0.375rem !important;
    }
    
    .stat-card {
        margin-bottom: 0.375rem;
        border-radius: 5px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    .stat-card .card-body {
        padding: 0.4rem 0.3rem;
    }
    
    .stat-card .row {
        margin: 0;
    }
    
    .stat-card .col-3 {
        padding-left: 0.1rem;
        padding-right: 0.1rem;
        flex: 0 0 auto;
        width: auto;
    }
    
    .stat-card .col-9 {
        padding-left: 0.25rem;
        padding-right: 0.1rem;
    }
    
    .stat-card h3 {
        font-size: 0.9rem;
        margin-bottom: 0.1rem;
        line-height: 1.05;
        font-weight: 700;
    }
    
    .stat-card .fa-2x {
        font-size: 0.9rem !important;
    }
    
    .stat-card .text-muted {
        font-size: 0.6rem;
        line-height: 1.05;
        margin: 0;
    }
    
    .card-body {
        min-height: 150px;
    }
    
    .card-body canvas {
        max-height: 130px;
    }
    
    .calendar-container {
        min-height: 220px;
    }
    
    .chart-wrapper {
        height: 120px;
    }
    
    .fc-button {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
    
    .modal-body {
        font-size: 0.9rem;
    }
}
</style>

<!-- Debug Info Panel (hidden by default) -->
<div id="debugInfo" class="debug-info">
    <strong>Debug Info:</strong><br>
    <span id="debugContent"></span>
    <button onclick="toggleDebug()" style="float: right; margin-top: 5px;">Hide</button>
</div>

<!-- Management Modules Tabs -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="managementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard-content" type="button" role="tab" aria-controls="dashboard-content" aria-selected="true">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="user-management-tab" data-bs-toggle="tab" data-bs-target="#user-management-content" type="button" role="tab" aria-controls="user-management-content" aria-selected="false">
                            <i class="fas fa-users-cog me-2"></i>User Management
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="fee-management-tab" data-bs-toggle="tab" data-bs-target="#fee-management-content" type="button" role="tab" aria-controls="fee-management-content" aria-selected="false">
                            <i class="fas fa-money-bill me-2"></i>Fee Management
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="daily-revenue-tab" data-bs-toggle="tab" data-bs-target="#daily-revenue-content" type="button" role="tab" aria-controls="daily-revenue-content" aria-selected="false">
                            <i class="fas fa-chart-line me-2"></i>Daily Revenue
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="report-analytics-tab" data-bs-toggle="tab" data-bs-target="#report-analytics-content" type="button" role="tab" aria-controls="report-analytics-content" aria-selected="false">
                            <i class="fas fa-chart-bar me-2"></i>Report Analytics
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="managementTabsContent">
                    <!-- Dashboard Overview Tab -->
                    <div class="tab-pane fade show active" id="dashboard-content" role="tabpanel" aria-labelledby="dashboard-tab">
                        <!-- Overview Cards -->
                        <div class="row mb-4">
                            <div class="col-12 col-md-6 col-xl-3 mb-4">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <div class="text-center">
                                                    <i class="fas fa-users fa-2x text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="col-9 text-end">
                                                <h3 class="mb-1 fw-bold"><?php echo $totalPatients; ?></h3>
                                                <div class="text-muted small">Total Patients</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 mb-4">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <div class="text-center">
                                                    <i class="fas fa-user-md fa-2x text-success"></i>
                                                </div>
                                            </div>
                                            <div class="col-9 text-end">
                                                <h3 class="mb-1 fw-bold"><?php echo $totalDoctors; ?></h3>
                                                <div class="text-muted small">Total Doctors</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 mb-4">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <div class="text-center">
                                                    <i class="fas fa-calendar-check fa-2x text-info"></i>
                                                </div>
                                            </div>
                                            <div class="col-9 text-end">
                                                <h3 class="mb-1 fw-bold"><?php echo $todayAppointments; ?></h3>
                                                <div class="text-muted small">Today's Appointments</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 mb-4">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <div class="text-center">
                                                    <i class="fas fa-money-bill-wave fa-2x text-warning"></i>
                                                </div>
                                            </div>
                                            <div class="col-9 text-end">
                                                <h3 class="mb-1 fw-bold"><?php echo formatCurrency($monthlyRevenue); ?></h3>
                                                <div class="text-muted small">Monthly Revenue</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Calendar and Chart Row -->
<div class="row mb-4">
    <div class="col-12 col-xl-7 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                <h5 class="card-title mb-2 mb-md-0">
                    <i class="fas fa-calendar-alt me-2"></i>All Appointments Calendar
                </h5>
                <div class="d-flex flex-wrap gap-2">
                    <button onclick="toggleDebug()" class="btn btn-secondary btn-sm">Debug</button>
                    <a href="appointments.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-list me-1"></i>Manage All
                    </a>
                </div>
            </div>
            <div class="card-body calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5 mb-4">
        <!-- Monthly Revenue Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>Monthly Revenue
                </h5>
            </div>
            <div class="card-body" style="padding: 0.75rem;">
                <div class="chart-wrapper">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        <!-- Monthly Appointments Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Monthly Appointments
                </h5>
            </div>
            <div class="card-body">
                <div class="chart-wrapper">
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
        </div>
        <!-- Appointment Status Overview Chart -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Appointment Status Overview
                </h5>
            </div>
            <div class="card-body">
                <div class="chart-wrapper">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
                    </div>
                    
                    <!-- User Management Tab -->
                    <div class="tab-pane fade" id="user-management-content" role="tabpanel" aria-labelledby="user-management-tab">
                        <iframe src="user_management.php?iframe=1" style="width: 100%; height: 800px; border: none;" id="user-management-iframe"></iframe>
                    </div>
                    
                    <!-- Fee Management Tab -->
                    <div class="tab-pane fade" id="fee-management-content" role="tabpanel" aria-labelledby="fee-management-tab">
                        <iframe src="fees.php?iframe=1" style="width: 100%; height: 800px; border: none;" id="fee-management-iframe"></iframe>
                    </div>
                    
                    <!-- Daily Revenue Tab -->
                    <div class="tab-pane fade" id="daily-revenue-content" role="tabpanel" aria-labelledby="daily-revenue-tab">
                        <iframe src="daily_sales.php?iframe=1" style="width: 100%; height: 800px; border: none;" id="daily-revenue-iframe"></iframe>
                    </div>
                    
                    <!-- Report Analytics Tab -->
                    <div class="tab-pane fade" id="report-analytics-content" role="tabpanel" aria-labelledby="report-analytics-tab">
                        <iframe src="report_analytics.php?iframe=1" style="width: 100%; height: 800px; border: none;" id="report-analytics-iframe"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">
                    <i class="fas fa-calendar-check me-2"></i>Appointment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="appointmentModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading appointment details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="editAppointmentBtn" style="display: none;">
                    <i class="fas fa-edit me-1"></i>Edit Appointment
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-labelledby="editAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAppointmentModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="editAppointmentModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading appointment form...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// DEBUG: Check database connectivity and basic counts
$debugInfo = [];
$debugInfo['current_date'] = date('Y-m-d H:i:s');
$debugInfo['total_appointments'] = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];

// Get calendar events for ALL appointments (admin view) - INCLUDES PAST APPOINTMENTS
$currentDate = date('Y-m-d');

// Debug query for calendar events - Show all appointments (past and future)
$debugCalendarQuery = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.patient_id, a.doctor_id,
                       COALESCE(CONCAT(p.first_name, ' ', p.last_name), 'Unknown Patient') as patient_name,
                       COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown Doctor') as doctor_name,
                       COALESCE(a.notes, '') as notes,
                       p.first_name as patient_first_name, p.last_name as patient_last_name,
                       u.first_name as doctor_first_name, u.last_name as doctor_last_name
                       FROM appointments a
                       LEFT JOIN patients p ON a.patient_id = p.id
                       LEFT JOIN doctors d ON a.doctor_id = d.id
                       LEFT JOIN users u ON d.user_id = u.id
                       WHERE (a.status IS NULL OR UPPER(TRIM(a.status)) NOT IN ('CANCELLED', 'CANCELED'))
                       ORDER BY a.appointment_date, a.appointment_time";

$stmt = $conn->prepare($debugCalendarQuery);
if (!$stmt) {
    $debugInfo['query_error'] = $conn->error;
    $events = json_encode([]);
} else {
    $stmt->execute();
    $result = $stmt->get_result();
    
    $debugInfo['calendar_query_rows'] = $result->num_rows;
    $debugInfo['all_appointments'] = $result->num_rows;
    
    $events = [];
    $eventCount = 0;
    while ($row = $result->fetch_assoc()) {
        $eventCount++;
        
        // Status-based colors - using Bootstrap 5 standard colors for consistency
        $status = strtolower(trim($row['status'] ?? ''));
        $color = match($status) {
            'scheduled' => '#0d6efd', // Bootstrap 5 primary
            'ongoing' => '#fd7e14',   // Orange for ongoing status
            'settled' => '#198754',    // Bootstrap 5 success
            'cancelled', 'canceled' => '#dc3545', // Bootstrap 5 danger
            default => '#6c757d'       // Default gray
        };
        
        $doctorName = $row['doctor_name'] ?? 'Unknown Doctor';
        $patientName = $row['patient_name'] ?? 'Unknown Patient';
        $doctorFirstName = explode(' ', $doctorName)[0];
        
        $eventTitle = $patientName . ' - Dr. ' . $doctorFirstName;
        $eventStart = $row['appointment_date'] . 'T' . $row['appointment_time'];
        
        $events[] = [
            'id' => $row['id'],
            'title' => $eventTitle,
            'start' => $eventStart,
            'color' => $color,
            'extendedProps' => [
                'status' => $row['status'] ?? 'Unknown',
                'notes' => $row['notes'] ?? '',
                'patient' => $patientName,
                'doctor' => $doctorName
            ]
        ];
        
        // Debug info for first few events
        if ($eventCount <= 3) {
            $debugInfo["event_$eventCount"] = [
                'id' => $row['id'],
                'title' => $eventTitle,
                'date' => $row['appointment_date'],
                'time' => $row['appointment_time'],
                'status' => $row['status']
            ];
        }
    }
    
    $debugInfo['events_created'] = count($events);
    $stmt->close();
}

$events = json_encode($events);
$debugInfo['events_json_length'] = strlen($events);

// Check for orphaned appointments
$orphanQuery = "SELECT COUNT(*) as count FROM appointments a 
                LEFT JOIN patients p ON a.patient_id = p.id 
                LEFT JOIN users u ON a.doctor_id = u.id 
                WHERE (p.id IS NULL OR u.id IS NULL) AND a.appointment_date >= '$currentDate'";
$orphanResult = $conn->query($orphanQuery);
$debugInfo['orphaned_appointments'] = $orphanResult->fetch_assoc()['count'];

// Get status distribution for pie chart - ensure all statuses are included
$statusQuery = "SELECT status, COUNT(*) as count FROM appointments 
                WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY status";
$statusResult = $conn->query($statusQuery);

// Initialize with all valid statuses (to ensure settled appears even if count is 0)
$statusCounts = [
    'scheduled' => 0,
    'ongoing' => 0,
    'settled' => 0,
    'cancelled' => 0
];

// Fill in actual counts from database
while ($row = $statusResult->fetch_assoc()) {
    $statusLower = strtolower($row['status']);
    if (isset($statusCounts[$statusLower])) {
        $statusCounts[$statusLower] = (int)$row['count'];
    }
}

// Build ordered arrays for chart (order: scheduled, ongoing, settled, cancelled)
$statusLabels = [];
$statusData = [];
$statusColors = [];

$statusMapping = [
    'scheduled' => ['label' => 'Scheduled', 'color' => '#0d6efd'],
    'ongoing' => ['label' => 'Ongoing', 'color' => '#fd7e14'],
    'settled' => ['label' => 'Settled', 'color' => '#198754'],
    'cancelled' => ['label' => 'Cancelled', 'color' => '#dc3545']
];

// Only show statuses that have data (count > 0)
foreach ($statusMapping as $statusKey => $statusInfo) {
    if ($statusCounts[$statusKey] > 0) {
        $statusLabels[] = $statusInfo['label'];
        $statusData[] = $statusCounts[$statusKey];
        $statusColors[] = $statusInfo['color'];
    }
}

$debugInfoJson = json_encode($debugInfo);
?>

<!-- Chart.js and FullCalendar libraries loaded via CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js"></script>

<script>
// Global debug info
var debugInfo = <?php echo $debugInfoJson; ?>;

function toggleDebug() {
    var debugPanel = document.getElementById('debugInfo');
    var debugContent = document.getElementById('debugContent');
    
    if (debugPanel.style.display === 'none' || debugPanel.style.display === '') {
        debugPanel.style.display = 'block';
        var content = '';
        for (var key in debugInfo) {
            if (typeof debugInfo[key] === 'object') {
                content += key + ': ' + JSON.stringify(debugInfo[key]) + '<br>';
            } else {
                content += key + ': ' + debugInfo[key] + '<br>';
            }
        }
        debugContent.innerHTML = content;
    } else {
        debugPanel.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Debug Info:', debugInfo);
    
    // Initialize Calendar with enhanced error handling
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) {
        console.error('Calendar element not found!');
        return;
    }
    
    var eventsData = <?php echo $events; ?>;
    console.log('Events Data:', eventsData);
    console.log('Number of events loaded:', eventsData.length);
    
    try {
        // Determine if mobile device
        var isMobile = window.innerWidth <= 575.98;
        var isTablet = window.innerWidth <= 991.98 && window.innerWidth > 575.98;
        
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: isMobile ? 'dayGridMonth' : 'dayGridMonth',
            headerToolbar: {
                left: isMobile ? 'prev,next' : 'prev,next today',
                center: 'title',
                right: isMobile ? 'dayGridMonth' : 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: eventsData,
            eventClick: function(info) {
                var appointmentId = info.event.id;
                var modalBody = document.getElementById('appointmentModalBody');
                modalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading appointment details...</p></div>';

                var appointmentModal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                appointmentModal.show();

                // Fetch appointment details
                fetch('get_appointment_details.php?id=' + appointmentId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            modalBody.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + data.error + '</div>';
                            return;
                        }

                        var statusBadge = '<span class="badge bg-' + data.status_class + '">' + data.status + '</span>';
                        
                        var isMobileDevice = window.innerWidth <= 575.98;
                        var colClass = isMobileDevice ? 'col-12' : 'col-md-6';
                        
                        var modalContent = `
                            <div class="row">
                                <div class="${colClass}">
                                    <h6 class="text-primary mb-3"><i class="fas fa-calendar-alt me-2"></i>Appointment Information</h6>
                                    <div class="mb-3">
                                        <strong>Date & Time:</strong><br>
                                        <span class="text-muted">${data.formatted_datetime}</span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Status:</strong><br>
                                        ${statusBadge}
                                    </div>
                                    ${data.reason ? '<div class="mb-3"><strong>Reason:</strong><br><span class="text-muted">' + data.reason + '</span></div>' : ''}
                                    ${data.notes ? '<div class="mb-3"><strong>Notes:</strong><br><span class="text-muted">' + data.notes + '</span></div>' : ''}
                                </div>
                                <div class="${colClass}">
                                    <h6 class="text-success mb-3"><i class="fas fa-user me-2"></i>Patient Information</h6>
                                    <div class="mb-3">
                                        <strong>Name:</strong><br>
                                        <span class="text-muted">${data.patient_name}</span>
                                    </div>
                                    ${data.patient_age ? '<div class="mb-3"><strong>Age:</strong><br><span class="text-muted">' + data.patient_age + '</span></div>' : ''}
                                    ${data.patient_gender ? '<div class="mb-3"><strong>Gender:</strong><br><span class="text-muted">' + data.patient_gender + '</span></div>' : ''}
                                    ${data.patient_phone ? '<div class="mb-3"><strong>Phone:</strong><br><span class="text-muted"><i class="fas fa-phone text-success me-1"></i>' + data.patient_phone + '</span></div>' : ''}
                                    ${data.patient_email ? '<div class="mb-3"><strong>Email:</strong><br><span class="text-muted"><i class="fas fa-envelope text-info me-1"></i>' + data.patient_email + '</span></div>' : ''}
                                    ${data.patient_address ? '<div class="mb-3"><strong>Address:</strong><br><span class="text-muted"><i class="fas fa-map-marker-alt text-danger me-1"></i>' + data.patient_address + '</span></div>' : ''}
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-12">
                                    <h6 class="text-info mb-3"><i class="fas fa-user-md me-2"></i>Doctor Information</h6>
                                    <div class="mb-3">
                                        <strong>Doctor:</strong><br>
                                        <span class="text-muted">${data.doctor_name}</span>
                                    </div>
                                    ${data.doctor_phone ? '<div class="mb-3"><strong>Doctor Phone:</strong><br><span class="text-muted"><i class="fas fa-phone text-success me-1"></i>' + data.doctor_phone + '</span></div>' : ''}
                                    ${data.doctor_email ? '<div class="mb-3"><strong>Doctor Email:</strong><br><span class="text-muted"><i class="fas fa-envelope text-info me-1"></i>' + data.doctor_email + '</span></div>' : ''}
                                </div>
                            </div>
                        `;
                        
                        modalBody.innerHTML = modalContent;
                        
                        // Show edit button and store appointment ID
                        var editBtn = document.getElementById('editAppointmentBtn');
                        if (editBtn) {
                            editBtn.style.display = 'inline-block';
                            editBtn.setAttribute('data-appointment-id', appointmentId);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching appointment details:', error);
                        modalBody.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load appointment details. Please try again.</div>';
                    });
            },
            eventMouseEnter: function(info) {
                var tooltip = info.event.extendedProps.patient + 
                             '\nDoctor: ' + info.event.extendedProps.doctor +
                             '\nStatus: ' + info.event.extendedProps.status + 
                             '\nTime: ' + info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                info.el.setAttribute('title', tooltip);
            },
            height: 'auto',
            dayMaxEvents: isMobile ? 2 : 3,
            eventDisplay: 'block',
            displayEventTime: !isMobile,
            eventTimeFormat: {
                hour: 'numeric',
                minute: '2-digit',
                meridiem: 'short'
            },
            dayMaxEventRows: isMobile ? 2 : 3,
            moreLinkClick: isMobile ? 'popover' : 'week',
            loading: function(bool) {
                console.log('Calendar loading:', bool);
            },
            eventDidMount: function(info) {
                console.log('Event mounted:', info.event.title);
            }
        });
        
        calendar.render();
        console.log('Calendar rendered successfully');
        
        // Handle edit appointment button click
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'editAppointmentBtn') {
                var appointmentId = e.target.getAttribute('data-appointment-id');
                if (appointmentId) {
                    // Close the details modal
                    var appointmentModalEl = document.getElementById('appointmentModal');
                    if (appointmentModalEl) {
                        var appointmentModal = bootstrap.Modal.getInstance(appointmentModalEl);
                        if (appointmentModal) {
                            appointmentModal.hide();
                        }
                    }
                    
                    // Open edit modal and load form
                    var editModalBody = document.getElementById('editAppointmentModalBody');
                    var editModal = new bootstrap.Modal(document.getElementById('editAppointmentModal'));
                    
                    editModalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading appointment form...</p></div>';
                    editModal.show();
                    
                    // Load edit form
                    fetch('edit_appointment.php?id=' + encodeURIComponent(appointmentId))
                        .then(response => response.text())
                        .then(html => {
                            editModalBody.innerHTML = html;
                            
                            // Initialize cancellation reason toggle functionality
                            function initCancellationToggle(formContainer) {
                                const statusSelect = formContainer.querySelector('#status');
                                const cancellationReasonGroup = formContainer.querySelector('#cancellationReasonGroup');
                                const cancellationReasonField = formContainer.querySelector('#cancellation_reason');
                                
                                if (!statusSelect || !cancellationReasonGroup || !cancellationReasonField) {
                                    return;
                                }
                                
                                // Function to toggle cancellation reason field
                                function toggleCancellationReason() {
                                    const selectedStatus = statusSelect.value.toLowerCase();
                                    if (selectedStatus === 'cancelled') {
                                        // Show with animation
                                        cancellationReasonGroup.style.display = 'block';
                                        // Force reflow for animation
                                        cancellationReasonGroup.offsetHeight;
                                        cancellationReasonGroup.style.maxHeight = '500px';
                                        cancellationReasonGroup.style.opacity = '1';
                                        cancellationReasonField.setAttribute('required', 'required');
                                        // Focus on the textarea after a short delay for smooth UX
                                        setTimeout(() => {
                                            if (cancellationReasonField) {
                                                cancellationReasonField.focus();
                                            }
                                        }, 300);
                                    } else {
                                        // Hide with animation
                                        cancellationReasonGroup.style.maxHeight = '0';
                                        cancellationReasonGroup.style.opacity = '0';
                                        cancellationReasonField.removeAttribute('required');
                                        cancellationReasonField.value = '';
                                        setTimeout(() => {
                                            if (statusSelect && statusSelect.value.toLowerCase() !== 'cancelled') {
                                                cancellationReasonGroup.style.display = 'none';
                                            }
                                        }, 300);
                                    }
                                }
                                
                                // Check initial status on load
                                toggleCancellationReason();
                                
                                // Listen for status changes
                                statusSelect.addEventListener('change', toggleCancellationReason);
                            }
                            
                            // Initialize the toggle
                            initCancellationToggle(editModalBody);
                            
                            // Re-initialize form submission handler
                            var form = editModalBody.querySelector('#editAppointmentForm');
                            if (form) {
                                form.addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    var formData = new FormData(form);
                                    var submitBtn = form.querySelector('button[type="submit"]');
                                    var originalBtnText = submitBtn.innerHTML;
                                    
                                    // Get cancellation reason field if it exists
                                    var cancellationReasonField = form.querySelector('#cancellation_reason');
                                    var statusSelect = form.querySelector('#status');
                                    
                                    // Validate cancellation reason if status is cancelled
                                    if (statusSelect && statusSelect.value.toLowerCase() === 'cancelled') {
                                        if (cancellationReasonField && !cancellationReasonField.value.trim()) {
                                            alert('Please provide a reason for cancelling the appointment.');
                                            cancellationReasonField.focus();
                                            return;
                                        }
                                    }
                                    
                                    submitBtn.disabled = true;
                                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
                                    
                                    fetch('edit_appointment.php', {
                                        method: 'POST',
                                        body: formData,
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        }
                                    })
                                    .then(async response => {
                                        const contentType = response.headers.get('content-type');
                                        if (!response.ok) {
                                            // Try to parse JSON error response
                                            if (contentType && contentType.includes('application/json')) {
                                                try {
                                                    const data = await response.json();
                                                    throw new Error(data.error || `Server error (${response.status})`);
                                                } catch (e) {
                                                    if (e instanceof Error) {
                                                        throw e;
                                                    }
                                                    throw new Error(`Server error (${response.status}): ${response.statusText}`);
                                                }
                                            } else {
                                                const text = await response.text();
                                                throw new Error(text || `Server error (${response.status}): ${response.statusText}`);
                                            }
                                        }
                                        // Success response
                                        if (contentType && contentType.includes('application/json')) {
                                            return response.json();
                                        } else {
                                            return { success: true };
                                        }
                                    })
                                    .then(data => {
                                        if (data.success) {
                                            // Close modal and reload page
                                            editModal.hide();
                                            setTimeout(function() {
                                                window.location.reload();
                                            }, 500);
                                        } else {
                                            alert('Error: ' + (data.error || 'Failed to update appointment'));
                                            submitBtn.disabled = false;
                                            submitBtn.innerHTML = originalBtnText;
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('Error: ' + (error.message || 'Failed to update appointment. Please try again.'));
                                        submitBtn.disabled = false;
                                        submitBtn.innerHTML = originalBtnText;
                                    });
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error loading edit form:', error);
                            editModalBody.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load edit form. Please try again.</div>';
                        });
                }
            }
        });
        
        // Hide edit button when appointment modal is closed
        var appointmentModalEl = document.getElementById('appointmentModal');
        if (appointmentModalEl) {
            appointmentModalEl.addEventListener('hidden.bs.modal', function() {
                var editBtn = document.getElementById('editAppointmentBtn');
                if (editBtn) {
                    editBtn.style.display = 'none';
                    editBtn.removeAttribute('data-appointment-id');
                }
            });
        }
        
        // Handle window resize for calendar responsiveness
        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                calendar.updateSize();
            }, 250);
        });
    } catch (error) {
        console.error('Calendar initialization error:', error);
    }

    // Revenue Chart
    var revenueCtx = document.getElementById('revenueChart').getContext('2d');
    var revenueLabels = <?php echo $revenueLabels; ?>;
    var revenueData = <?php echo $revenueData; ?>;
    
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'Monthly Revenue',
                data: revenueData,
                borderColor: '#0c1a6a',
                backgroundColor: 'rgba(12, 26, 106, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: window.innerWidth <= 575.98 ? 10 : 20,
                        font: {
                            size: window.innerWidth <= 575.98 ? 10 : 12
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Revenue Trends (Last 6 Months)',
                    font: {
                        size: window.innerWidth <= 575.98 ? 12 : 14,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        },
                        font: {
                            size: window.innerWidth <= 575.98 ? 10 : 12
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            },
            interaction: {
                intersect: false
            }
        }
    });

    // Appointments Chart
    var appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
    var appointmentLabels = <?php echo $appointmentLabels; ?>;
    var appointmentData = <?php echo $appointmentData; ?>;
    
    new Chart(appointmentsCtx, {
        type: 'bar',
        data: {
            labels: appointmentLabels,
            datasets: [{
                label: 'Monthly Appointments',
                data: appointmentData,
                backgroundColor: '#1a2fa0',
                borderColor: '#0c1a6a',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: window.innerWidth <= 575.98 ? 10 : 20,
                        font: {
                            size: window.innerWidth <= 575.98 ? 10 : 12
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Appointment Volume (Last 6 Months)',
                    font: {
                        size: window.innerWidth <= 575.98 ? 12 : 14,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            }
        }
    });

    // Status Distribution Pie Chart
    var statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($statusLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($statusData); ?>,
                backgroundColor: <?php echo json_encode($statusColors); ?>,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: window.innerWidth <= 575.98 ? 8 : 15,
                        font: {
                            size: window.innerWidth <= 575.98 ? 10 : 12
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Appointment Status (Last 30 Days)',
                    font: {
                        size: window.innerWidth <= 575.98 ? 12 : 14,
                        weight: 'bold'
                    }
                }
            }
        }
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('JavaScript Error:', e.error);
    });
});
</script>



<?php include 'includes/footer.php'; ?>