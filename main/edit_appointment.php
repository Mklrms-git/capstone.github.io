<?php
define('MHAVIS_EXEC', true);
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/patient_auth.php'; // For notification functions
require_once __DIR__ . '/process_notifications.php'; // For email queue processing
requireLogin();

// Check if user is admin or doctor
if (!isAdmin() && !isDoctor()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to update appointments.']);
    } else {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>You do not have permission to edit appointments.</div>';
    }
    exit;
}

// Admin can manage appointments

$conn = getDBConnection();

// Function to get actual ENUM values from database
function getStatusEnumValues($conn) {
    $result = $conn->query("SHOW COLUMNS FROM appointments WHERE Field = 'status'");
    if ($result && $row = $result->fetch_assoc()) {
        // Extract ENUM values from Type string like "enum('Scheduled','Completed','Cancelled','No Show')"
        preg_match("/enum\((.*)\)/i", $row['Type'], $matches);
        if (isset($matches[1])) {
            $enum_string = $matches[1];
            // Remove quotes and split by comma
            $enum_values = array_map(function($val) {
                return trim($val, " '");
            }, explode(',', $enum_string));
            return $enum_values;
        }
    }
    // Fallback to common values if we can't determine
    return ['scheduled', 'ongoing', 'settled', 'cancelled', 'Scheduled', 'Completed', 'Cancelled', 'No Show'];
}

// Function to map lowercase status to actual database status (case-insensitive)
function mapStatusToDatabase($status, $conn) {
    $enum_values = getStatusEnumValues($conn);
    $status_lower = strtolower(trim($status));
    
    // First try exact match (case-sensitive)
    foreach ($enum_values as $db_status) {
        if ($db_status === $status) {
            return $db_status;
        }
    }
    
    // Then try case-insensitive match
    foreach ($enum_values as $db_status) {
        if (strtolower($db_status) === $status_lower) {
            return $db_status;
        }
    }
    
    // Map common lowercase values to likely database values
    $status_mapping = [
        'scheduled' => ['scheduled', 'Scheduled', 'pending', 'Pending'],
        'ongoing' => ['ongoing', 'Ongoing', 'In Progress', 'in progress'],
        'settled' => ['settled', 'Settled', 'Completed', 'completed'],
        'cancelled' => ['cancelled', 'Cancelled', 'No Show', 'no show']
    ];
    
    if (isset($status_mapping[$status_lower])) {
        foreach ($status_mapping[$status_lower] as $possible_value) {
            if (in_array($possible_value, $enum_values)) {
                return $possible_value;
            }
        }
    }
    
    // Return original status as fallback
    return $status;
}

// Handle POST request (update appointment)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to prevent any accidental output before JSON
    ob_start();
    
    // Support both appointment_id and appointmentId for backward compatibility
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : (isset($_POST['appointmentId']) ? (int)$_POST['appointmentId'] : 0);
    $new_status = isset($_POST['status']) ? sanitize($_POST['status']) : '';
    $cancellation_reason = isset($_POST['cancellation_reason']) ? trim(sanitize($_POST['cancellation_reason'])) : '';
    
    // Validate cancellation reason if status is cancelled
    if (strtolower(trim($new_status)) === 'cancelled' && empty($cancellation_reason)) {
        ob_clean(); // Clear any output
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Please provide a reason for cancelling the appointment.']);
            ob_end_flush();
            exit;
        } else {
            ob_end_clean();
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Please provide a reason for cancelling the appointment.</div>';
            exit;
        }
    }
    
    // Map the status to the actual database ENUM value (case-sensitive)
    $db_status = mapStatusToDatabase($new_status, $conn);
    
    // Validate that the mapped status exists in database
    $valid_statuses = getStatusEnumValues($conn);
    if (!in_array($db_status, $valid_statuses)) {
        ob_clean(); // Clear any output
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Invalid status provided. Allowed values: ' . implode(', ', $valid_statuses)]);
            ob_end_flush();
            exit;
        } else {
            ob_end_clean();
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Invalid status provided. Allowed values: ' . implode(', ', $valid_statuses) . '</div>';
            exit;
        }
    }
    
    // Get appointment details before update (to get old status and other info)
    $stmt = $conn->prepare("SELECT a.*, 
                            p.id as patient_id, p.first_name as patient_first_name, p.last_name as patient_last_name, p.email as patient_email,
                            d.id as doctor_table_id, d.user_id as doctor_user_id,
                            u.first_name as doctor_first_name, u.last_name as doctor_last_name
                            FROM appointments a
                            LEFT JOIN patients p ON a.patient_id = p.id
                            LEFT JOIN doctors d ON a.doctor_id = d.id
                            LEFT JOIN users u ON d.user_id = u.id
                            WHERE a.id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $appointment_result = $stmt->get_result();
    
    if ($appointment_result->num_rows === 0) {
        ob_clean(); // Clear any output
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
            ob_end_flush();
            exit;
        } else {
            ob_end_clean();
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Appointment not found.</div>';
            exit;
        }
    }
    
    $appointment_data = $appointment_result->fetch_assoc();
    $old_status = $appointment_data['status'];
    $existing_notes = $appointment_data['notes'] ?? '';
    
    // Prepare notes update - if cancelled, add cancellation reason
    $updated_notes = $existing_notes;
    if (strtolower(trim($db_status)) === 'cancelled' && !empty($cancellation_reason)) {
        $cancellation_note = "\n\n[CANCELLED] Reason: " . $cancellation_reason . " (Cancelled on " . date('Y-m-d H:i:s') . " by Admin)";
        $updated_notes = $existing_notes . $cancellation_note;
    }
    
    // Update appointment status and notes
    $stmt = $conn->prepare("UPDATE appointments SET status = ?, notes = ? WHERE id = ?");
    if (!$stmt) {
        $error_msg = $conn->error ?: 'Failed to prepare update statement';
        error_log("Appointment update prepare failed: " . $error_msg);
        ob_clean(); // Clear any output
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $error_msg]);
            ob_end_flush();
            exit;
        }
        ob_end_clean();
        $_SESSION['error'] = "Error preparing update: " . $error_msg;
        header('Location: doctors.php');
        exit;
    }
    
    $stmt->bind_param("ssi", $db_status, $updated_notes, $appointment_id);
    
    if ($stmt->execute()) {
        // Check if any rows were actually updated
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Appointment status updated successfully.";
            
            // Only send notifications if status actually changed and user is admin
            if (strtolower(trim($old_status)) !== strtolower(trim($db_status)) && isAdmin()) {
                // Suppress any output from notification functions
                ob_start();
                
                try {
                    // Get patient user ID if exists
                    $patient_user_id = null;
                    $patient_user_stmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ? LIMIT 1");
                    $patient_user_stmt->bind_param("i", $appointment_data['patient_id']);
                    $patient_user_stmt->execute();
                    $patient_user_result = $patient_user_stmt->get_result();
                    if ($patient_user_result->num_rows > 0) {
                        $patient_user_row = $patient_user_result->fetch_assoc();
                        $patient_user_id = $patient_user_row['id'];
                    }
                    $patient_user_stmt->close();
                    
                    // Format appointment details
                    $formatted_date = date('l, F j, Y', strtotime($appointment_data['appointment_date']));
                    $formatted_time = date('g:i A', strtotime($appointment_data['appointment_time']));
                    $patient_name = trim(($appointment_data['patient_first_name'] ?? '') . ' ' . ($appointment_data['patient_last_name'] ?? ''));
                    $doctor_name = trim(($appointment_data['doctor_first_name'] ?? '') . ' ' . ($appointment_data['doctor_last_name'] ?? ''));
                    
                    // Get status label for display
                    $status_labels = [
                        'scheduled' => 'Scheduled',
                        'ongoing' => 'Ongoing',
                        'settled' => 'Settled',
                        'completed' => 'Settled',
                        'cancelled' => 'Cancelled'
                    ];
                    $status_label = $status_labels[strtolower($db_status)] ?? ucfirst($db_status);
                    
                    // Send email notification to patient (if email exists)
                    if (!empty($appointment_data['patient_email']) && function_exists('sendEmailNotification')) {
                    $email_subject = "Appointment Status Updated - Mhavis Medical Center";
                    
                    // Determine email color based on status
                    $header_color = '#4CAF50'; // Green for scheduled/ongoing
                    $status_icon = '✅';
                    if (strtolower($db_status) === 'cancelled') {
                        $header_color = '#f44336'; // Red for cancelled
                        $status_icon = '❌';
                    } elseif (strtolower($db_status) === 'settled' || strtolower($db_status) === 'completed') {
                        $header_color = '#2196F3'; // Blue for settled
                        $status_icon = '✓';
                    }
                    
                    // Include cancellation reason if status is cancelled
                    $cancellation_section = '';
                    if (strtolower($db_status) === 'cancelled' && !empty($cancellation_reason)) {
                        $cancellation_section = "
                        <div style='background-color: #ffebee; padding: 20px; margin: 20px 0; border-left: 4px solid #f44336; border-radius: 4px;'>
                            <h3 style='margin-top: 0; color: #c62828;'>❌ Cancellation Reason</h3>
                            <p style='margin: 0; font-size: 14px; color: #555; line-height: 1.6;'>" . nl2br(htmlspecialchars($cancellation_reason)) . "</p>
                        </div>
                        <div style='background-color: #e3f2fd; padding: 15px; margin: 20px 0; border-left: 4px solid #2196F3; border-radius: 4px;'>
                            <p style='margin: 0; font-size: 14px; color: #1565c0;'>
                                💡 <strong>Want to reschedule?</strong> You can book a new appointment through your patient account or by contacting us directly.
                            </p>
                        </div>
                        ";
                    }
                    
                    $email_body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                        <div style='background-color: {$header_color}; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                            <h2 style='margin: 0;'>{$status_icon} Appointment Status Updated</h2>
                        </div>
                        <div style='background-color: white; padding: 30px; border-radius: 0 0 5px 5px;'>
                            <p style='font-size: 16px; color: #333;'>Dear <strong>{$patient_name}</strong>,</p>
                            
                            <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                Your appointment status has been updated by our administration team.
                            </p>
                            
                            <div style='background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid {$header_color}; border-radius: 4px;'>
                                <h3 style='margin-top: 0; color: #333;'>📅 Appointment Details</h3>
                                <p style='margin: 10px 0;'><strong>Doctor:</strong> Dr. {$doctor_name}</p>
                                <p style='margin: 10px 0;'><strong>Date:</strong> {$formatted_date}</p>
                                <p style='margin: 10px 0;'><strong>Time:</strong> {$formatted_time}</p>
                                <p style='margin: 10px 0;'><strong>Status:</strong> <span style='color: {$header_color}; font-weight: bold;'>{$status_label}</span></p>
                            </div>
                            
                            {$cancellation_section}
                            
                            <p style='font-size: 14px; color: #555; line-height: 1.6;'>
                                If you have any questions or concerns about this status change, please contact us.
                            </p>
                            
                            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                            
                            <p style='font-size: 13px; color: #777; text-align: center; margin: 0;'>
                                Best regards,<br>
                                <strong>Mhavis Medical & Diagnostic Center</strong><br>
                                Healthcare Team
                            </p>
                        </div>
                    </div>
                    ";
                    
                        sendEmailNotification($appointment_data['patient_email'], $patient_name, $email_subject, $email_body, 'html');
                        
                        // Process email queue immediately (suppress output)
                        if (function_exists('processEmailQueue')) {
                            @processEmailQueue();
                        }
                    }
                    
                    // Create in-app notification for patient
                    if ($patient_user_id && function_exists('createNotification')) {
                        $patient_title = "Appointment Status Updated";
                        $patient_message = "Your appointment with Dr. {$doctor_name} on {$formatted_date} at {$formatted_time} has been updated to: {$status_label}";
                        if (strtolower($db_status) === 'cancelled' && !empty($cancellation_reason)) {
                            $patient_message .= "\n\nCancellation Reason: " . $cancellation_reason;
                            $patient_message .= "\n\nYou can book a new appointment through your account or by contacting us.";
                        }
                        @createNotification('Patient', $patient_user_id, 'Appointment_Status_Update', $patient_title, $patient_message, 'System');
                    }
                    
                    // Create in-app notification for doctor
                    if (!empty($appointment_data['doctor_user_id']) && function_exists('createNotification')) {
                        $doctor_title = "Appointment Status Updated";
                        $doctor_message = "Appointment with {$patient_name} on {$formatted_date} at {$formatted_time} has been updated to: {$status_label} by an administrator.";
                        if (strtolower($db_status) === 'cancelled' && !empty($cancellation_reason)) {
                            $doctor_message .= "\n\nCancellation Reason: " . $cancellation_reason;
                        }
                        @createNotification('Doctor', $appointment_data['doctor_user_id'], 'Appointment_Status_Update', $doctor_title, $doctor_message, 'System');
                    }
                    
                    // Create in-app notification for admin (the one who made the change)
                    if (isset($_SESSION['user_id']) && isAdmin() && function_exists('createNotification')) {
                        $admin_title = "Appointment Status Updated";
                        $admin_message = "You have updated the appointment status for {$patient_name} with Dr. {$doctor_name} on {$formatted_date} at {$formatted_time} to: {$status_label}";
                        if (strtolower($db_status) === 'cancelled' && !empty($cancellation_reason)) {
                            $admin_message .= "\n\nCancellation Reason: " . $cancellation_reason;
                        }
                        @createNotification('Admin', $_SESSION['user_id'], 'Appointment_Status_Update', $admin_title, $admin_message, 'System');
                    }
                } catch (Exception $e) {
                    // Log error but don't fail the update
                    error_log("Notification error: " . $e->getMessage());
                } finally {
                    // Discard any output from notification functions
                    ob_end_clean();
                }
            }
        } else {
            $_SESSION['error'] = "No changes were made. Status may already be set to this value.";
        }
        
        // Check if this is an AJAX request
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            ob_clean(); // Clear any output
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Appointment status updated successfully.']);
            ob_end_flush();
            exit;
        }
        
        // Handle redirect for non-AJAX requests
        $redirect_patient_id = isset($_POST['redirect_patient_id']) ? (int)$_POST['redirect_patient_id'] : 0;
        $redirect_tab = isset($_POST['redirect_tab']) ? sanitize($_POST['redirect_tab']) : (isset($_GET['tab']) ? sanitize($_GET['tab']) : 'appointments');
        
        if ($redirect_patient_id) {
            // Redirect to patient page, staying on the same tab (or appointments tab if not specified)
            header("Location: patients.php?patient_id=$redirect_patient_id&tab=" . urlencode($redirect_tab));
        } else {
            // Redirect back to doctors page with the same doctor and tab
            $doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
            $tab = isset($_GET['tab']) ? $_GET['tab'] : 'appointments';
            
            $redirect_url = "doctors.php";
            if ($doctor_id) {
                $redirect_url .= "?doctor_id=$doctor_id&tab=$tab";
            }
            header("Location: $redirect_url");
        }
        exit;
    } else {
        // Return actual database error for debugging
        $error_msg = $conn->error ?: 'Unknown database error';
        error_log("Appointment status update failed: " . $error_msg);
        $_SESSION['error'] = "Error updating appointment status: " . $error_msg;
        
        // Check if this is an AJAX request
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            ob_clean(); // Clear any output
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Error updating appointment status: ' . $error_msg]);
            ob_end_flush();
            exit;
        }
        
        ob_end_clean(); // Clean output buffer for non-AJAX requests
        
        // Redirect for non-AJAX requests
        header('Location: doctors.php');
        exit;
    }
}

// Handle GET request (display edit form)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Invalid appointment ID.</div>';
    exit;
}

$appointmentId = (int)$_GET['id'];

// Fetch appointment details
$query = "SELECT a.*, 
          COALESCE(CONCAT(p.first_name, ' ', p.last_name), 'Unknown Patient') as patient_name,
          COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown Doctor') as doctor_name
          FROM appointments a
          LEFT JOIN patients p ON a.patient_id = p.id
          LEFT JOIN doctors d ON a.doctor_id = d.id
          LEFT JOIN users u ON d.user_id = u.id
          WHERE a.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Database error occurred.</div>';
    exit;
}

$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Appointment not found.</div>';
    exit;
}

$appointment = $result->fetch_assoc();

// Get patient_id from URL parameter or from appointment
$redirect_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : (int)$appointment['patient_id'];
$redirect_tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'appointments';

// Get actual status ENUM values from database
$statusOptions = getStatusEnumValues($conn);

// Map to user-friendly labels while keeping database values
function getStatusLabel($status) {
    $status_lower = strtolower(trim($status));
    $label_map = [
        'scheduled' => 'Scheduled',
        'pending' => 'Scheduled',
        'confirmed' => 'Scheduled',
        'ongoing' => 'Ongoing',
        'in progress' => 'Ongoing',
        'settled' => 'Settled',
        'completed' => 'Settled',
        'cancelled' => 'Cancelled',
        'no show' => 'Cancelled'
    ];
    
    if (isset($label_map[$status_lower])) {
        return $label_map[$status_lower];
    }
    
    // Return capitalized version if no mapping found
    return ucfirst($status);
}

// Get current status and normalize for comparison
$currentStatus = trim($appointment['status'] ?? 'scheduled');
$currentStatusLower = strtolower($currentStatus);
?>

<div id="editAppointmentAlert" class="alert d-none"></div>

<form id="editAppointmentForm">
    <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($appointment['id']); ?>">
    <input type="hidden" name="redirect_patient_id" id="redirectPatientId" value="<?= htmlspecialchars($redirect_patient_id); ?>">
    <input type="hidden" name="redirect_tab" value="<?= htmlspecialchars($redirect_tab); ?>">
    
    <div class="mb-3">
        <label class="form-label"><strong>Patient:</strong></label>
        <div class="form-control-plaintext"><?= htmlspecialchars($appointment['patient_name']); ?></div>
    </div>
    
    <div class="mb-3">
        <label class="form-label"><strong>Doctor:</strong></label>
        <div class="form-control-plaintext"><?= htmlspecialchars($appointment['doctor_name']); ?></div>
    </div>
    
    <div class="mb-3">
        <label class="form-label"><strong>Date:</strong></label>
        <div class="form-control-plaintext"><?= formatDate($appointment['appointment_date']); ?></div>
    </div>
    
    <div class="mb-3">
        <label class="form-label"><strong>Time:</strong></label>
        <div class="form-control-plaintext"><?= formatTime($appointment['appointment_time']); ?></div>
    </div>
    
    <div class="mb-3">
        <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
        <select class="form-select" name="status" id="status" required>
            <?php 
            // Standard status options (will be mapped to database values)
            $standard_statuses = [
                'scheduled' => 'Scheduled',
                'ongoing' => 'Ongoing',
                'settled' => 'Settled',
                'cancelled' => 'Cancelled'
            ];
            
            foreach ($standard_statuses as $status_value => $status_label):
                // Normalize current status for comparison
                $current_normalized = strtolower(trim($currentStatus));
                
                // Determine if this option should be selected
                $selected = false;
                
                // Direct match (case-insensitive)
                if ($current_normalized === strtolower($status_value)) {
                    $selected = true;
                }
                // Map variations to standard statuses
                else {
                    $status_mappings = [
                        'scheduled' => ['scheduled', 'pending', 'confirmed'],
                        'ongoing' => ['ongoing', 'in progress', 'in_progress'],
                        'settled' => ['settled', 'completed'],
                        'cancelled' => ['cancelled', 'no show', 'no_show', 'declined']
                    ];
                    
                    if (isset($status_mappings[$status_value])) {
                        foreach ($status_mappings[$status_value] as $variant) {
                            if ($current_normalized === strtolower($variant)) {
                                $selected = true;
                                break;
                            }
                        }
                    }
                }
                
                $selected_attr = $selected ? 'selected' : '';
            ?>
                <option value="<?= htmlspecialchars($status_value); ?>" <?= $selected_attr; ?>><?= htmlspecialchars($status_label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- Cancellation Reason Field (shown only when status is cancelled) -->
    <div class="mb-3" id="cancellationReasonGroup" style="display: none; overflow: hidden; transition: all 0.3s ease-in-out;">
        <div class="card border-warning" style="background-color: #fff3cd;">
            <div class="card-body">
                <label class="form-label fw-bold" for="cancellation_reason">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>Cancellation Reason <span class="text-danger">*</span>
                </label>
                <small class="text-muted d-block mb-2">Please provide a reason for cancelling this appointment. The patient will be notified of this reason.</small>
                <textarea class="form-control" name="cancellation_reason" id="cancellation_reason" rows="4" placeholder="Enter the reason for cancellation (e.g., Patient requested, Doctor unavailable, Emergency situation, etc.)" required></textarea>
                <div class="form-text mt-2">
                    <i class="fas fa-info-circle me-1"></i>This reason will be included in the email and notification sent to the patient.
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i>Save Changes
        </button>
    </div>
</form>

<script>
(function() {
    // Function to initialize cancellation form
    function initCancellationForm() {
        const form = document.getElementById('editAppointmentForm');
        const alertEl = document.getElementById('editAppointmentAlert');
        const statusSelect = document.getElementById('status');
        const cancellationReasonGroup = document.getElementById('cancellationReasonGroup');
        const cancellationReasonField = document.getElementById('cancellation_reason');
        
        if (!form || !statusSelect || !cancellationReasonGroup || !cancellationReasonField) {
            return false; // Elements not ready
        }
        
        // Mark as initialized to prevent duplicate initialization
        if (form.dataset.cancellationInitialized === 'true') {
            return true;
        }
        form.dataset.cancellationInitialized = 'true';
        
        // Show/hide cancellation reason field based on status selection
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
        
        return true;
    }
    
    // Try to initialize immediately
    if (!initCancellationForm()) {
        // If elements not found, wait for DOM or retry
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initCancellationForm, 100);
            });
        } else {
            setTimeout(initCancellationForm, 100);
        }
    }
    
    // Also watch for dynamic form loading (for modals)
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            const form = document.getElementById('editAppointmentForm');
            if (form && form.dataset.cancellationInitialized !== 'true') {
                setTimeout(initCancellationForm, 50);
            }
        });
        
        if (document.body) {
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }
    
    // Get form elements for submit handler
    const form = document.getElementById('editAppointmentForm');
    const alertEl = document.getElementById('editAppointmentAlert');
    const statusSelect = document.getElementById('status');
    const cancellationReasonField = document.getElementById('cancellation_reason');
    
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate cancellation reason if status is cancelled
        const selectedStatus = statusSelect.value.toLowerCase();
        if (selectedStatus === 'cancelled') {
            const cancellationReason = cancellationReasonField.value.trim();
            if (!cancellationReason) {
                alertEl.classList.remove('d-none', 'alert-success');
                alertEl.classList.add('alert-danger');
                alertEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please provide a reason for cancelling the appointment.';
                cancellationReasonField.focus();
                return;
            }
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        
        const formData = new FormData(form);
        
        // Get patient_id from hidden field (already set in form)
        // Page will reload after successful update to show the updated status
        
        // Submit to the same file
        fetch('edit_appointment.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
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
                        throw new Error(data.error || 'Failed to update appointment');
                    } catch (e) {
                        if (e instanceof Error && e.message !== 'Failed to update appointment') {
                            throw e;
                        }
                        throw new Error(`Server error (${response.status}): ${response.statusText}`);
                    }
                } else {
                    // Not JSON, get text response
                    const text = await response.text();
                    throw new Error(text || `Server error (${response.status}): ${response.statusText}`);
                }
            }
            // Success response
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                // If not JSON, assume success (legacy redirect response)
                return { success: true };
            }
        })
        .then(data => {
            // Show success message briefly
            alertEl.classList.remove('d-none', 'alert-danger');
            alertEl.classList.add('alert-success');
            alertEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>Appointment updated successfully!';
            
            // Close the modal and reload the page to show updated status
            setTimeout(() => {
                const modalElement = document.querySelector('#editAppointmentModal');
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                }
                
                // Reload the page to show the updated appointment status
                // Get patient_id and tab from hidden form fields or URL parameters
                const formData = new FormData(form);
                const patientId = formData.get('redirect_patient_id') || new URLSearchParams(window.location.search).get('patient_id');
                const currentTab = formData.get('redirect_tab') || new URLSearchParams(window.location.search).get('tab') || 'appointments';
                
                // Reload the page with same parameters to refresh the appointments list
                if (patientId) {
                    window.location.href = 'patients.php?patient_id=' + patientId + '&tab=' + encodeURIComponent(currentTab);
                } else {
                    // If not on patients page, reload current page
                    window.location.reload();
                }
            }, 800);
        })
        .catch(error => {
            console.error('Error:', error);
            alertEl.classList.remove('d-none', 'alert-success');
            alertEl.classList.add('alert-danger');
            alertEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + (error.message || 'Failed to update appointment. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Save Changes';
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCancellationForm);
    } else {
        initCancellationForm();
    }
    
    // Also initialize if form is loaded dynamically (for modals)
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            const form = document.getElementById('editAppointmentForm');
            if (form && !form.dataset.initialized) {
                form.dataset.initialized = 'true';
                setTimeout(initCancellationForm, 50);
            }
        });
        
        const targetNode = document.body;
        observer.observe(targetNode, {
            childList: true,
            subtree: true
        });
    }
})();
</script>
