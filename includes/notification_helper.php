<?php
/**
 * Notification Helper Functions
 * Helper functions to easily send notifications to patients and staff
 */

if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

/**
 * Send a notification to a user
 * 
 * @param int $user_id The ID of the user to send notification to
 * @param string $user_type 'patient' or 'staff'
 * @param string $type Type of notification (e.g., 'appointment_confirmed', 'prescription', etc.)
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool True on success, false on failure
 */
function sendNotification($user_id, $user_type, $type, $title, $message) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_type, type, title, message, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())");
    
    if (!$stmt) {
        error_log("Failed to prepare notification statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("issss", $user_id, $user_type, $type, $title, $message);
    
    if ($stmt->execute()) {
        return true;
    } else {
        error_log("Failed to send notification: " . $stmt->error);
        return false;
    }
}

/**
 * Send appointment confirmation notification
 * 
 * @param int $patient_id Patient ID
 * @param string $doctor_name Doctor's name
 * @param string $date Appointment date
 * @param string $time Appointment time
 * @return bool
 */
function sendAppointmentConfirmation($patient_id, $doctor_name, $date, $time) {
    $formatted_date = date('M j, Y', strtotime($date));
    $formatted_time = date('g:i A', strtotime($time));
    
    $title = "Appointment Confirmed";
    $message = "Your appointment with $doctor_name on $formatted_date at $formatted_time has been confirmed.";
    
    return sendNotification($patient_id, 'patient', 'appointment_confirmed', $title, $message);
}

/**
 * Send appointment cancellation notification
 * 
 * @param int $patient_id Patient ID
 * @param string $doctor_name Doctor's name
 * @param string $date Appointment date
 * @param string $time Appointment time
 * @param string $reason Reason for cancellation (optional)
 * @return bool
 */
function sendAppointmentCancellation($patient_id, $doctor_name, $date, $time, $reason = '') {
    $formatted_date = date('M j, Y', strtotime($date));
    $formatted_time = date('g:i A', strtotime($time));
    
    $title = "Appointment Cancelled";
    $message = "Your appointment with $doctor_name on $formatted_date at $formatted_time has been cancelled.";
    
    if (!empty($reason)) {
        $message .= " Reason: $reason";
    }
    
    return sendNotification($patient_id, 'patient', 'appointment_cancelled', $title, $message);
}

/**
 * Send appointment reminder notification
 * 
 * @param int $patient_id Patient ID
 * @param string $doctor_name Doctor's name
 * @param string $date Appointment date
 * @param string $time Appointment time
 * @return bool
 */
function sendAppointmentReminder($patient_id, $doctor_name, $date, $time) {
    $formatted_date = date('M j, Y', strtotime($date));
    $formatted_time = date('g:i A', strtotime($time));
    
    $title = "Appointment Reminder";
    $message = "Reminder: You have an appointment with $doctor_name on $formatted_date at $formatted_time.";
    
    return sendNotification($patient_id, 'patient', 'appointment_reminder', $title, $message);
}

/**
 * Send appointment rejection notification
 * 
 * @param int $patient_id Patient ID
 * @param string $date Requested appointment date
 * @param string $time Requested appointment time
 * @param string $reason Reason for rejection
 * @return bool
 */
function sendAppointmentRejection($patient_id, $date, $time, $reason = '') {
    $formatted_date = date('M j, Y', strtotime($date));
    $formatted_time = date('g:i A', strtotime($time));
    
    $title = "Appointment Request Rejected";
    $message = "Your appointment request for $formatted_date at $formatted_time has been rejected.";
    
    if (!empty($reason)) {
        $message .= " Reason: $reason";
    }
    
    return sendNotification($patient_id, 'patient', 'appointment_rejected', $title, $message);
}

/**
 * Send new prescription notification
 * 
 * @param int $patient_id Patient ID
 * @param string $doctor_name Doctor's name
 * @param string $prescription_name Prescription name (optional)
 * @return bool
 */
function sendPrescriptionNotification($patient_id, $doctor_name, $prescription_name = '') {
    $title = "New Prescription Available";
    
    if (!empty($prescription_name)) {
        $message = "A new prescription for $prescription_name has been added to your account by $doctor_name.";
    } else {
        $message = "A new prescription has been added to your account by $doctor_name.";
    }
    
    return sendNotification($patient_id, 'patient', 'prescription', $title, $message);
}

/**
 * Send medical record update notification
 * 
 * @param int $patient_id Patient ID
 * @param string $record_type Type of record updated
 * @return bool
 */
function sendMedicalRecordUpdate($patient_id, $record_type = 'medical records') {
    $title = "Medical Records Updated";
    $message = "Your $record_type have been updated. Please review the changes in your patient dashboard.";
    
    return sendNotification($patient_id, 'patient', 'medical_record', $title, $message);
}

/**
 * Send general notification
 * 
 * @param int $user_id User ID
 * @param string $user_type 'patient' or 'staff'
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool
 */
function sendGeneralNotification($user_id, $user_type, $title, $message) {
    return sendNotification($user_id, $user_type, 'general', $title, $message);
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id User ID
 * @param string $user_type 'patient' or 'staff'
 * @return int Number of unread notifications
 */
function getUnreadNotificationCount($user_id, $user_type) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications 
                           WHERE user_id = ? AND user_type = ? AND is_read = 0");
    $stmt->bind_param("is", $user_id, $user_type);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] ?? 0;
}
?>

