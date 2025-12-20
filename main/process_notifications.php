<?php
if (!defined('MHAVIS_EXEC')) {
    define('MHAVIS_EXEC', true);
}
require_once 'config/init.php';

// Email configuration
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'noreply.mhavis@gmail.com'); // Change this to your email
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'hwlx chqz fweg pudj'); // Change this to your app password
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'noreply.mhavis@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Mhavis Medical & Diagnostic Center Indang Cavite');

// Process email queue
function processEmailQueue() {
    $conn = getDBConnection();
    
    // Get pending emails
    $stmt = $conn->prepare("SELECT * FROM email_queue WHERE status = 'Pending' AND attempts < max_attempts ORDER BY scheduled_at ASC LIMIT 10");
    $stmt->execute();
    $emails = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($emails as $email) {
        $success = sendEmail($email['to_email'], $email['to_name'], $email['subject'], $email['body'], $email['body_type']);
        
        if ($success) {
            // Mark as sent
            $stmt = $conn->prepare("UPDATE email_queue SET status = 'Sent', sent_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $email['id']);
            $stmt->execute();
        } else {
            // Increment attempts and log error
            $error_msg = "Failed to send email to " . $email['to_email'];
            $stmt = $conn->prepare("UPDATE email_queue SET attempts = attempts + 1, last_attempt = NOW(), error_message = ? WHERE id = ?");
            $stmt->bind_param("si", $error_msg, $email['id']);
            $stmt->execute();
            
            // Mark as failed if max attempts reached
            if ($email['attempts'] + 1 >= $email['max_attempts']) {
                $stmt = $conn->prepare("UPDATE email_queue SET status = 'Failed' WHERE id = ?");
                $stmt->bind_param("i", $email['id']);
                $stmt->execute();
            }
        }
    }
}

// Send email using PHPMailer if available, otherwise fall back to mail()
function sendEmail($to_email, $to_name, $subject, $body, $body_type = 'html') {
    // Try PHPMailer if class is available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mailer->isSMTP();
            $mailer->Host = SMTP_HOST;
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USERNAME;
            $mailer->Password = SMTP_PASSWORD;
            $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = SMTP_PORT;
            
            // Disable SSL verification for localhost development (remove in production)
            $mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mailer->addAddress($to_email, $to_name ?: $to_email);

            // Content
            $mailer->isHTML($body_type === 'html');
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            if ($body_type !== 'html') {
                $mailer->AltBody = $body;
            }

            $mailer->send();
            error_log("Email sent successfully via PHPMailer to: $to_email");
            return true;
        } catch (Throwable $e) {
            // Log the error with more details
            error_log('PHPMailer send failed to ' . $to_email . ': ' . $e->getMessage());
            // Don't fall back to mail() - return false instead
            return false;
        }
    } else {
        // PHPMailer not available - log this critical error
        error_log('CRITICAL: PHPMailer class not found! Check autoloader in config/init.php');
        return false;
    }

}

// Send appointment reminders
function sendAppointmentReminders() {
    $conn = getDBConnection();
    
    // Check if reminder_sent column exists
    $column_check = $conn->query("SHOW COLUMNS FROM appointments LIKE 'reminder_sent'");
    $reminder_column_exists = $column_check && $column_check->num_rows > 0;
    
    // Get appointments for tomorrow and day after tomorrow
    $sql = "SELECT a.*, p.first_name, p.last_name, p.phone, p.email, 
                           u.first_name as doctor_first_name, u.last_name as doctor_last_name
                           FROM appointments a
                           JOIN patients p ON a.patient_id = p.id
                           JOIN users u ON a.doctor_id = u.id
                           WHERE a.appointment_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
                           AND a.status = 'scheduled'";
    
    if ($reminder_column_exists) {
        $sql .= " AND a.reminder_sent = FALSE";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($appointments as $appointment) {
        $appointment_date = date('M j, Y', strtotime($appointment['appointment_date']));
        $appointment_time = date('g:i A', strtotime($appointment['appointment_time']));
        
        // Send email reminder
        $subject = "Appointment Reminder - Mhavis Medical Center";
        $email_body = "Dear {$appointment['first_name']},\n\n" .
                     "This is a reminder for your upcoming appointment:\n\n" .
                     "Doctor: Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']}\n" .
                     "Date: {$appointment_date}\n" .
                     "Time: {$appointment_time}\n\n" .
                     "Please arrive 15 minutes early for your appointment.\n\n" .
                     "If you need to reschedule or cancel, please contact us at least 24 hours in advance.\n\n" .
                     "Best regards,\nMhavis Medical & Diagnostic Center Indang Cavite";
        
        sendEmailNotification($appointment['email'], $appointment['first_name'] . ' ' . $appointment['last_name'], 
                            $subject, $email_body, 'text');
        
        // Mark reminder as sent (only if column exists)
        if ($reminder_column_exists) {
            $stmt = $conn->prepare("UPDATE appointments SET reminder_sent = TRUE WHERE id = ?");
            $stmt->bind_param("i", $appointment['id']);
            $stmt->execute();
        }
        
        // Create notification
        $patient_user_stmt = $conn->prepare("SELECT id FROM patient_users WHERE patient_id = ?");
        $patient_user_stmt->bind_param("i", $appointment['patient_id']);
        $patient_user_stmt->execute();
        $patient_user_result = $patient_user_stmt->get_result();
        
        if ($patient_user_result->num_rows > 0) {
            $patient_user = $patient_user_result->fetch_assoc();
            createNotification('Patient', $patient_user['id'], 'Appointment_Reminder', 
                'Appointment Reminder', "You have an appointment with Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']} on {$appointment_date} at {$appointment_time}", 'Email');
        }
    }
}

// Run the notification processor only when executed directly (not when included)
$executedDirectly = php_sapi_name() === 'cli' || (
    isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])
);

if ($executedDirectly) {
    if (php_sapi_name() === 'cli') {
        // CLI: full processing (emails, reminders)
        processEmailQueue();
        sendAppointmentReminders();
        echo "Notification processing completed.\n";
    } else {
        // Web direct access: process once
        processEmailQueue();
        echo "Notifications processed.";
    }
}

