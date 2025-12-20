<?php
define('MHAVIS_EXEC', true);
require_once 'config/init.php';
require_once 'config/patient_auth.php';

// Require patient login
requirePatientLogin();

$department_id = $_GET['department_id'] ?? '';

if (!$department_id) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$doctors = getDoctorsByDepartment($department_id);

header('Content-Type: application/json');
echo json_encode($doctors);
