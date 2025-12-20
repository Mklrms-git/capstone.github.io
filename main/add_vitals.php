<?php
// File: add_vitals.php

define('MHAVIS_EXEC', true);
require_once 'config/init.php';
requireLogin();

$conn = getDBConnection();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: patients.php");
    exit();
}

// Get form data
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$visit_date = isset($_POST['visit_date']) ? $_POST['visit_date'] : date('Y-m-d');

// Combine systolic and diastolic into blood_pressure field
$systolic = isset($_POST['systolic']) ? (int)$_POST['systolic'] : 0;
$diastolic = isset($_POST['diastolic']) ? (int)$_POST['diastolic'] : 0;
$blood_pressure = $systolic . '/' . $diastolic;

$heart_rate = isset($_POST['heart_rate']) ? (int)$_POST['heart_rate'] : 0;
$respiratory_rate = isset($_POST['respiratory_rate']) ? (int)$_POST['respiratory_rate'] : 20; // Default value
$temperature = isset($_POST['temperature']) ? (float)$_POST['temperature'] : 0.0;
$oxygen_saturation = isset($_POST['oxygen_saturation']) ? (int)$_POST['oxygen_saturation'] : 98; // Default value
$weight = isset($_POST['weight']) ? (float)$_POST['weight'] : 0.0;
$height = isset($_POST['height']) ? (float)$_POST['height'] : 0.0;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate inputs
$errors = [];
if ($patient_id <= 0) $errors[] = 'Invalid patient ID';
if ($systolic <= 0) $errors[] = 'Systolic blood pressure is required';
if ($diastolic <= 0) $errors[] = 'Diastolic blood pressure is required';
if ($heart_rate <= 0) $errors[] = 'Heart rate must be positive';
if ($temperature <= 0) $errors[] = 'Temperature must be positive';
if ($weight <= 0) $errors[] = 'Weight must be positive';
if ($height <= 0) $errors[] = 'Height must be positive';

// Calculate BMI
$bmi = ($height > 0) ? round(($weight * 703) / ($height * $height), 1) : 0;

// Redirect URL
$redirect_url = "patients.php?id=" . $patient_id;

if (empty($errors)) {
    try {
        // Insert into database
        $query = "INSERT INTO patient_vitals 
            (patient_id, visit_date, blood_pressure, heart_rate, respiratory_rate, temperature, oxygen_saturation, weight, height, bmi, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("issiiidddds",
            $patient_id,        // i - integer
            $visit_date,        // s - string
            $blood_pressure,    // s - string (e.g., "120/80")
            $heart_rate,        // i - integer
            $respiratory_rate,  // i - integer
            $temperature,       // d - double/float
            $oxygen_saturation, // i - integer
            $weight,           // d - double/float
            $height,           // d - double/float
            $bmi,              // d - double/float
            $notes             // s - string
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: $redirect_url&message=" . urlencode("Vitals saved successfully"));
            exit();
        } else {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }

    } catch (Exception $e) {
        error_log("Error saving vitals: " . $e->getMessage());
        header("Location: $redirect_url&error=" . urlencode("Error saving vitals: " . $e->getMessage()));
        exit();
    }
} else {
    $error_string = implode(', ', $errors);
    header("Location: $redirect_url&error=" . urlencode($error_string));
    exit();
}
?>