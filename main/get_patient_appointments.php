<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';

// Require patient login
requirePatientLogin();

$patient_user = getCurrentPatientUser();

// Get patient appointments (all appointments - past and future)
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT a.*, 
                       u.first_name as doctor_first_name, 
                       u.last_name as doctor_last_name, 
                       COALESCE(
                           dept_appt.name,
                           dept_doc.name,
                           dept_user.name,
                           'General'
                       ) as department_name 
                       FROM appointments a 
                       LEFT JOIN doctors doc ON a.doctor_id = doc.id 
                       LEFT JOIN users u ON doc.user_id = u.id 
                       LEFT JOIN departments dept_appt ON a.department_id = dept_appt.id 
                       LEFT JOIN departments dept_doc ON doc.department_id = dept_doc.id 
                       LEFT JOIN departments dept_user ON u.department_id = dept_user.id 
                       WHERE a.patient_id = ? 
                       ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$stmt->bind_param("i", $patient_user['patient_id']);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Format appointments for FullCalendar
$events = [];
foreach ($appointments as $appointment) {
    // Build doctor name
    $doctor_name = trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''));
    $doctor_display = $doctor_name ? 'Dr. ' . $doctor_name : 'Unknown Doctor';
    
    // Determine color based on status - using Bootstrap 5 standard colors
    $backgroundColor = '#6c757d'; // default gray
    $borderColor = '#6c757d';
    
    $statusLower = strtolower($appointment['status']);
    switch ($statusLower) {
        case 'scheduled':
            $backgroundColor = '#0d6efd'; // Bootstrap 5 primary
            $borderColor = '#0d6efd';
            break;
        case 'ongoing':
            $backgroundColor = '#fd7e14'; // Orange for ongoing status
            $borderColor = '#fd7e14';
            break;
        case 'settled':
            $backgroundColor = '#198754'; // Bootstrap 5 success
            $borderColor = '#198754';
            break;
        case 'cancelled':
        case 'canceled':
            $backgroundColor = '#dc3545'; // Bootstrap 5 danger
            $borderColor = '#dc3545';
            break;
    }
    
    // Check if appointment is in the past
    $appointment_datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
    $is_past = strtotime($appointment_datetime) < time();
    if ($is_past && $statusLower === 'scheduled') {
        $backgroundColor = '#6c757d';
        $borderColor = '#6c757d';
    }
    
    $events[] = [
        'id' => $appointment['id'],
        'title' => $doctor_display,
        'start' => $appointment['appointment_date'] . 'T' . $appointment['appointment_time'],
        'backgroundColor' => $backgroundColor,
        'borderColor' => $borderColor,
        'textColor' => '#ffffff',
        'extendedProps' => [
            'doctor_name' => $doctor_display,
            'doctor_first_name' => $appointment['doctor_first_name'] ?? '',
            'doctor_last_name' => $appointment['doctor_last_name'] ?? '',
            'department' => $appointment['department_name'] ?? 'General',
            'reason' => $appointment['reason'] ?? '',
            'notes' => $appointment['notes'] ?? '',
            'status' => $appointment['status'],
            'appointment_date' => $appointment['appointment_date'],
            'appointment_time' => $appointment['appointment_time'],
            'is_past' => $is_past
        ]
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
