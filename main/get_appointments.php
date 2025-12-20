<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
requireLogin();

header('Content-Type: application/json');

$conn = getDBConnection();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'doctor';

// Optional doctor filter (for admin view)
$filterDoctorId = isset($_GET['doctor_id']) && $_GET['doctor_id'] !== '' ? intval($_GET['doctor_id']) : null;

try {
    // Base query
    $query = "
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            dept.name as department_name,
            dept.color as department_color
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN departments dept ON a.department_id = dept.id
        WHERE 1
    ";

    $params = [];
    $types  = '';

    if ($userRole === 'doctor') {
        // Only appointments for this doctor
        $query .= " AND a.doctor_id = ? ";
        $params[] = $userId;
        $types   .= 'i';
    } elseif ($filterDoctorId) {
        // Admin filtered by a specific doctor
        $query .= " AND a.doctor_id = ? ";
        $params[] = $filterDoctorId;
        $types   .= 'i';
    }

    $query .= " ORDER BY a.appointment_date, a.appointment_time";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        // Status-based colors - using Bootstrap 5 standard colors for consistency
        $statusLower = strtolower($row['status']);
        $color = match($statusLower) {
            'scheduled' => '#0d6efd', // Bootstrap 5 primary
            'ongoing' => '#fd7e14',   // Orange for ongoing status
            'settled' => '#198754',    // Bootstrap 5 success
            'cancelled', 'canceled' => '#dc3545', // Bootstrap 5 danger
            default => '#6c757d'       // Default gray
        };

        // Override with department color if available
        if (!empty($row['department_color'])) {
            $color = $row['department_color'];
        }

        // Format time for display
        $timeFormatted = date('g:i A', strtotime($row['appointment_time']));
        
        $events[] = [
            'id' => $row['id'],
            'title' => $row['patient_name'] . ' (' . $timeFormatted . ')',
            'start' => $row['appointment_date'] . 'T' . $row['appointment_time'],
            'color' => $color,
            'borderColor' => $color,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'patient' => $row['patient_name'],
                'doctor' => $row['doctor_name'],
                'department' => $row['department_name'],
                'status' => $row['status'],
                'notes' => $row['notes'],
                'time_formatted' => $timeFormatted
            ]
        ];
    }

    // Return events or an empty array if none found
    echo json_encode($events);
} catch (Exception $e) {
    error_log("Calendar appointments error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load appointments: ' . $e->getMessage()]);
}
