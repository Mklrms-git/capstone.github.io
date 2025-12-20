<?php
// View Medical Record Modal
// File: view_medical_record_modal.php

if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}
?>

<!-- View Medical Record Modal -->
<div class="modal fade" id="viewMedicalRecordModal" tabindex="-1" aria-labelledby="viewMedicalRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewMedicalRecordModalLabel">
                    <i class="fas fa-file-medical me-2"></i>Medical Record Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewMedicalRecordModalBody" style="max-height: 70vh; overflow-y: auto;">
                <!-- Content will be populated by JavaScript -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading medical record...</p>
                </div>
            </div>
            <div class="modal-footer">
                <div id="viewMedicalRecordModalActions" style="display: none;">
                    <a href="#" id="editMedicalRecordBtn" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                    <button type="button" class="btn btn-danger" id="deleteMedicalRecordBtn" style="display: none;" onclick="initiateDeleteMedicalRecord();">
                        <i class="fas fa-trash-alt me-1"></i>Delete
                    </button>
                </div>
                <button type="button" class="btn btn-info" onclick="printMedicalRecord()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Password Confirmation Modal for Delete -->
<div class="modal fade" id="deletePasswordConfirmModal" tabindex="-1" aria-labelledby="deletePasswordConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deletePasswordConfirmModalLabel">
                    <i class="fas fa-shield-alt me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Security Verification Required</strong>
                </div>
                <p class="mb-3">Please enter your password to confirm this deletion. This action cannot be undone.</p>
                <form id="deletePasswordForm">
                    <div class="mb-3">
                        <label for="deletePasswordInput" class="form-label">Enter Your Password</label>
                        <input type="password" class="form-control" id="deletePasswordInput" placeholder="Enter your password" required autocomplete="current-password">
                        <div class="invalid-feedback" id="deletePasswordError"></div>
                    </div>
                    <input type="hidden" id="deleteRecordId" value="">
                    <input type="hidden" id="deleteFormAction" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDeleteWithPassword();">
                    <i class="fas fa-trash-alt me-1"></i>Confirm Delete
                </button>
            </div>
        </div>
    </div>
</div>

<style>
#viewMedicalRecordModal .modal-body {
    padding: 1.5rem;
}

#viewMedicalRecordModal .attachment-item {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 8px;
    margin: 5px 0;
}

#viewMedicalRecordModal .vital-signs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

#viewMedicalRecordModal .vital-item {
    background-color: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    text-align: center;
    height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

#viewMedicalRecordModal .vital-label {
    font-size: 0.8em;
    color: #6c757d;
    font-weight: 500;
}

#viewMedicalRecordModal .vital-value {
    font-size: 1.1em;
    font-weight: 600;
    color: #495057;
}

/* Password Confirmation Modal Styles */
#deletePasswordConfirmModal .modal-header {
    background-color: #dc3545;
    color: white;
}

#deletePasswordConfirmModal .alert-warning {
    border-left: 4px solid #ffc107;
}

#deletePasswordConfirmModal .form-control:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

#deletePasswordConfirmModal .btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}

#deletePasswordConfirmModal .btn-danger:hover {
    background-color: #bb2d3b;
    border-color: #b02a37;
}

#deletePasswordConfirmModal .btn-danger:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
</style>

<script>
// Store current record ID for edit/delete actions
let currentRecordId = null;
let pendingDeleteRecordId = null;
let pendingDeleteFormAction = null;
let currentRecordData = null; // Store current record data for printing

// Classify BMI based on gender
function classifyBMI(bmi, gender = '') {
    if (!bmi || bmi <= 0 || isNaN(bmi)) {
        return {status: 'Unknown', class: 'secondary'};
    }
    
    const bmiValue = parseFloat(bmi);
    
    // Standard BMI classification (same for both men and women)
    if (bmiValue < 18.5) {
        return {status: 'Underweight', class: 'info'};
    } else if (bmiValue >= 18.5 && bmiValue < 25.0) {
        return {status: 'Healthy', class: 'success'};
    } else if (bmiValue >= 25.0 && bmiValue < 30.0) {
        return {status: 'Overweight', class: 'warning'};
    } else {
        return {status: 'Obesity', class: 'danger'};
    }
}

// Function to initiate delete process (first confirmation)
function initiateDeleteMedicalRecord() {
    if (!currentRecordId) {
        showAlert('Record ID not available', 'Error', 'error');
        return;
    }
    
    // First confirmation: Show delete confirmation dialog
    confirmDialog(
        'Are you sure you want to delete this medical record? This action cannot be undone.',
        'Continue',
        'Cancel',
        'Confirm Deletion'
    ).then(function(confirmed) {
        if (confirmed) {
            // Store the record ID and redirect info for password confirmation
            pendingDeleteRecordId = currentRecordId;
            const patientId = <?= isset($patient_details) && isset($patient_details['id']) ? (int)$patient_details['id'] : 'null' ?>;
            const yearParam = <?= isset($selected_year) && $selected_year !== null ? (int)$selected_year : 'null' ?>;
            
            // Build delete URL with query parameters
            let deleteUrl = 'delete_medical_record.php';
            if (patientId) {
                deleteUrl += '?patient_id=' + patientId;
                if (yearParam) {
                    deleteUrl += '&year=' + yearParam;
                }
            }
            pendingDeleteFormAction = deleteUrl;
            
            // Close the view modal
            const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewMedicalRecordModal'));
            if (viewModal) {
                viewModal.hide();
            }
            
            // Show password confirmation modal
            showPasswordConfirmModal();
        }
    });
}

// Function to show password confirmation modal
function showPasswordConfirmModal() {
    const passwordModal = new bootstrap.Modal(document.getElementById('deletePasswordConfirmModal'));
    document.getElementById('deleteRecordId').value = pendingDeleteRecordId;
    document.getElementById('deleteFormAction').value = pendingDeleteFormAction;
    document.getElementById('deletePasswordInput').value = '';
    document.getElementById('deletePasswordError').textContent = '';
    document.getElementById('deletePasswordInput').classList.remove('is-invalid');
    
    // Focus on password input when modal is shown
    passwordModal.show();
    setTimeout(() => {
        const passwordInput = document.getElementById('deletePasswordInput');
        passwordInput.focus();
        
        // Handle Enter key in password field (remove old listener first)
        passwordInput.removeEventListener('keypress', handlePasswordEnter);
        passwordInput.addEventListener('keypress', handlePasswordEnter);
    }, 300);
}

// Handle Enter key in password field
function handlePasswordEnter(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        confirmDeleteWithPassword();
    }
}

// Function to confirm delete with password verification
function confirmDeleteWithPassword() {
    const password = document.getElementById('deletePasswordInput').value;
    const passwordInput = document.getElementById('deletePasswordInput');
    const errorDiv = document.getElementById('deletePasswordError');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    if (!password) {
        passwordInput.classList.add('is-invalid');
        errorDiv.textContent = 'Password is required';
        return;
    }
    
    // Disable button and show loading state
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Verifying...';
    
    // Verify password via API
    fetch('verify_delete_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'password=' + encodeURIComponent(password)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Password verified, proceed with deletion
            const recordId = document.getElementById('deleteRecordId').value;
            const formAction = document.getElementById('deleteFormAction').value;
            
            // Update button to show deleting state
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
            
            // Submit delete request
            const formData = new URLSearchParams();
            formData.append('delete_record_id', recordId);
            formData.append('delete_password', password);
            
            // Use the dedicated delete endpoint
            fetch(formAction, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return response.json();
                } else {
                    // If not JSON, try to parse as text to see what we got
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Invalid response format: ' + text.substring(0, 100));
                    });
                }
            })
            .then(deleteData => {
                if (deleteData && deleteData.success) {
                    // Close password modal
                    const passwordModal = bootstrap.Modal.getInstance(document.getElementById('deletePasswordConfirmModal'));
                    if (passwordModal) {
                        passwordModal.hide();
                    }
                    
                    // Show success message and redirect
                    showAlert(deleteData.message || 'Medical record deleted successfully', 'Success', 'success').then(() => {
                        if (deleteData.redirect) {
                            window.location.href = deleteData.redirect;
                        } else {
                            window.location.reload();
                        }
                    });
                } else {
                    // Deletion failed
                    passwordInput.classList.add('is-invalid');
                    errorDiv.textContent = deleteData?.message || 'Failed to delete medical record';
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i>Confirm Delete';
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                passwordInput.classList.add('is-invalid');
                errorDiv.textContent = 'An error occurred while deleting. Please try again.';
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i>Confirm Delete';
            });
        } else {
            // Password incorrect
            passwordInput.classList.add('is-invalid');
            errorDiv.textContent = data.message || 'Incorrect password';
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i>Confirm Delete';
            passwordInput.value = '';
            passwordInput.focus();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        passwordInput.classList.add('is-invalid');
        errorDiv.textContent = 'An error occurred. Please try again.';
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i>Confirm Delete';
    });
}

// Function to populate the view modal with medical record data
function populateViewModal(recordData) {
    const modalBody = document.getElementById('viewMedicalRecordModalBody');
    const recordType = recordData.record_type || 'medical_record';
    
    // Store record ID for edit/delete actions
    currentRecordId = recordData.id || null;
    // Store record data for printing
    currentRecordData = recordData;
    
    // Show/hide edit and delete buttons based on user role
    const editBtn = document.getElementById('editMedicalRecordBtn');
    const deleteBtn = document.getElementById('deleteMedicalRecordBtn');
    const actionsDiv = document.getElementById('viewMedicalRecordModalActions');
    
    // Check if user is admin or doctor by checking session role
    const userRole = '<?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') : '' ?>';
    const isAdminOrDoctor = (userRole === 'Admin' || userRole === 'Doctor');
    const isAdmin = (userRole === 'Admin');
    
    if (isAdminOrDoctor && currentRecordId) {
        if (actionsDiv) {
            actionsDiv.style.display = 'block';
        }
        
        // Show Edit button for admin and doctor
        if (editBtn) {
            editBtn.href = 'edit_medical_record.php?id=' + currentRecordId;
            editBtn.style.display = 'inline-block';
        }
        
        // Show Delete button ONLY for admin (doctors cannot delete)
        if (deleteBtn) {
            if (isAdmin) {
                deleteBtn.style.display = 'inline-block';
            } else {
                deleteBtn.style.display = 'none';
            }
        }
    } else {
        if (actionsDiv) {
            actionsDiv.style.display = 'none';
        }
        if (editBtn) {
            editBtn.style.display = 'none';
        }
        if (deleteBtn) {
            deleteBtn.style.display = 'none';
        }
    }
    
    // Handle different record types
    if (recordType === 'medical_history') {
        populateMedicalHistoryModal(recordData);
        return;
    } else if (recordType === 'vitals') {
        populateVitalsModal(recordData);
        return;
    }
    
    // Format visit date
    const visitDate = recordData.visit_date ? new Date(recordData.visit_date + 'T00:00:00').toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }) : 'Not specified';
    
    // Format recorded time
    const recordedTime = recordData.created_at ? new Date(recordData.created_at).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    }) : 'Not available';
    
    // Format next appointment date
    let nextApptHtml = '';
    if (recordData.next_appointment_date && 
        recordData.next_appointment_date !== '0000-00-00' && 
        recordData.next_appointment_date !== '-0001-11-30') {
        const nextApptDate = new Date(recordData.next_appointment_date + 'T00:00:00');
        if (nextApptDate.getTime() > 0) {
            nextApptHtml = `
                <div class="mb-3">
                    <strong><i class="fas fa-calendar-alt me-1"></i>Next Appointment:</strong>
                    <div class="text-muted">${nextApptDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    })}</div>
                </div>
            `;
        }
    }
    
    // Format vitals (handle both JSON and plain text) - Match admin side format
    let vitalsHtml = '';
    if (recordData.vitals) {
        try {
            // Try to parse as JSON first
            const vitalsData = JSON.parse(recordData.vitals);
            if (typeof vitalsData === 'object' && vitalsData !== null) {
                // It's JSON - display as a list format to match admin side
                const vitalItems = [];
                
                // Map common vital sign keys to display labels
                const vitalLabels = {
                    'blood_pressure': 'BP',
                    'bp': 'BP',
                    'temperature': 'Temperature',
                    'temp': 'Temperature',
                    'heart_rate': 'Heart Rate',
                    'hr': 'Heart Rate',
                    'respiratory_rate': 'Respiratory Rate',
                    'rr': 'Respiratory Rate',
                    'oxygen_saturation': 'O2 Saturation',
                    'o2_saturation': 'O2 Saturation',
                    'o2': 'O2 Saturation',
                    'weight': 'Weight',
                    'height': 'Height',
                    'bmi': 'BMI'
                };
                
                // Process vitals in a specific order to match admin side
                const vitalOrder = ['blood_pressure', 'bp', 'temperature', 'temp', 'heart_rate', 'hr', 
                                  'respiratory_rate', 'rr', 'oxygen_saturation', 'o2_saturation', 'o2',
                                  'weight', 'height', 'bmi'];
                
                // First, collect all vitals
                const allVitals = [];
                for (const [key, value] of Object.entries(vitalsData)) {
                    if (value && value !== '') {
                        const lowerKey = key.toLowerCase();
                        const label = vitalLabels[lowerKey] || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                        allVitals.push({key: lowerKey, label: label, value: value, originalKey: key});
                    }
                }
                
                // Sort by order
                allVitals.sort((a, b) => {
                    const aIndex = vitalOrder.indexOf(a.key);
                    const bIndex = vitalOrder.indexOf(b.key);
                    if (aIndex === -1 && bIndex === -1) return 0;
                    if (aIndex === -1) return 1;
                    if (bIndex === -1) return -1;
                    return aIndex - bIndex;
                });
                
                if (allVitals.length > 0) {
                    // Check if weight and height are present but BMI is not
                    let hasWeight = false, hasHeight = false, hasBMI = false;
                    let weightValue = null, heightValue = null;
                    
                    allVitals.forEach(item => {
                        if (item.key === 'weight') {
                            hasWeight = true;
                            weightValue = parseFloat(item.value);
                        } else if (item.key === 'height') {
                            hasHeight = true;
                            heightValue = parseFloat(item.value);
                        } else if (item.key === 'bmi') {
                            hasBMI = true;
                        }
                    });
                    
                    // Calculate BMI if weight and height are present but BMI is not
                    if (hasWeight && hasHeight && !hasBMI && weightValue && heightValue && heightValue > 0) {
                        const calculatedBMI = (weightValue * 703) / (heightValue * heightValue);
                        const bmiRounded = calculatedBMI.toFixed(1);
                        const bmiClassification = classifyBMI(bmiRounded);
                        allVitals.push({
                            key: 'bmi',
                            label: 'BMI',
                            value: bmiRounded + ' <span class="badge bg-' + bmiClassification.class + '">' + bmiClassification.status + '</span>',
                            originalKey: 'bmi'
                        });
                        // Re-sort to maintain order
                        allVitals.sort((a, b) => {
                            const aIndex = vitalOrder.indexOf(a.key);
                            const bIndex = vitalOrder.indexOf(b.key);
                            if (aIndex === -1 && bIndex === -1) return 0;
                            if (aIndex === -1) return 1;
                            if (bIndex === -1) return -1;
                            return aIndex - bIndex;
                        });
                    }
                    
                    const vitalListItems = allVitals.map(item => {
                        let displayValue = String(item.value);
                        // Add units if not already present
                        if (item.key === 'blood_pressure' || item.key === 'bp') {
                            if (!displayValue.includes('/')) {
                                // Assume it's already formatted
                            }
                        } else if (item.key === 'temperature' || item.key === 'temp') {
                            if (!displayValue.toLowerCase().includes('°f') && !displayValue.toLowerCase().includes('f')) {
                                displayValue += ' °F';
                            }
                        } else if (item.key === 'heart_rate' || item.key === 'hr') {
                            if (!displayValue.toLowerCase().includes('bpm')) {
                                displayValue += ' bpm';
                            }
                        } else if (item.key === 'respiratory_rate' || item.key === 'rr') {
                            if (!displayValue.toLowerCase().includes('/min')) {
                                displayValue += ' /min';
                            }
                        } else if (item.key === 'oxygen_saturation' || item.key === 'o2_saturation' || item.key === 'o2') {
                            if (!displayValue.includes('%')) {
                                displayValue += ' %';
                            }
                        } else if (item.key === 'weight') {
                            if (!displayValue.toLowerCase().includes('lbs') && !displayValue.toLowerCase().includes('kg')) {
                                displayValue += ' lbs';
                            }
                        } else if (item.key === 'height') {
                            if (!displayValue.toLowerCase().includes('in') && !displayValue.toLowerCase().includes('cm')) {
                                displayValue += ' in';
                            }
                        } else if (item.key === 'bmi') {
                            // Add BMI classification if not already added
                            if (!displayValue.includes('<span')) {
                                const bmiValue = parseFloat(displayValue);
                                if (!isNaN(bmiValue)) {
                                    const bmiClassification = classifyBMI(bmiValue);
                                    displayValue += ' <span class="badge bg-' + bmiClassification.class + '">' + bmiClassification.status + '</span>';
                                }
                            }
                        }
                        return `${escapeHtml(item.label)}: ${displayValue}`;
                    });
                    
                    vitalsHtml = `
                        <div class="mb-3">
                            <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                            <div class="text-muted mt-2">
                                <ul class="mb-0" style="list-style-type: none; padding-left: 0;">
                                    ${vitalListItems.map(item => `<li>${item}</li>`).join('')}
                                </ul>
                            </div>
                        </div>
                    `;
                }
            }
        } catch (e) {
            // Not JSON, treat as plain text - but try to extract weight/height to calculate BMI
            let vitalsText = recordData.vitals;
            let weight = null;
            let height = null;
            
            // Try to extract weight (format: "Weight: 165 lbs" or "Weight: 165" - handle bullet separators)
            const weightMatch = vitalsText.match(/Weight\s*:\s*(\d+\.?\d*)\s*(?:lbs|kg)?/i);
            if (weightMatch) {
                weight = parseFloat(weightMatch[1]);
            }
            
            // Try to extract height (format: "Height: 68 in" or "Height: 68" - handle bullet separators)
            const heightMatch = vitalsText.match(/Height\s*:\s*(\d+\.?\d*)\s*(?:in|cm)?/i);
            if (heightMatch) {
                height = parseFloat(heightMatch[1]);
            }
            
            // Calculate and add BMI if weight and height are available
            let bmiText = '';
            if (weight && height && height > 0) {
                const bmi = (weight * 703) / (height * height);
                const bmiRounded = bmi.toFixed(1);
                const bmiClassification = classifyBMI(bmiRounded);
                // Check if BMI is already in the text
                if (!vitalsText.match(/BMI\s*:/i)) {
                    bmiText = ` • BMI: ${bmiRounded} <span class="badge bg-${bmiClassification.class}">${bmiClassification.status}</span>`;
                }
            }
            
            // Parse vitals into list format for better display
            const vitalParts = vitalsText.split('•').map(part => part.trim()).filter(part => part);
            let vitalsListHtml = '';
            if (vitalParts.length > 0) {
                vitalParts.forEach(part => {
                    // Check if this part already has BMI classification
                    if (part.match(/BMI\s*:/i)) {
                        // Extract BMI value and add classification if not present
                        const bmiMatch = part.match(/BMI\s*:\s*(\d+\.?\d*)/i);
                        if (bmiMatch && !part.includes('<span')) {
                            const bmiVal = parseFloat(bmiMatch[1]);
                            const bmiClass = classifyBMI(bmiVal);
                            part = part.replace(/BMI\s*:\s*(\d+\.?\d*)/i, 
                                `BMI: $1 <span class="badge bg-${bmiClass.class}">${bmiClass.status}</span>`);
                        }
                    }
                    vitalsListHtml += `<li>${escapeHtml(part)}</li>`;
                });
                // Add BMI if calculated and not already in list
                if (bmiText && !vitalsText.match(/BMI\s*:/i)) {
                    vitalsListHtml += `<li>BMI: ${bmiText.replace(' • ', '')}</li>`;
                }
                
                vitalsHtml = `
                    <div class="mb-3">
                        <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                        <div class="text-muted mt-2">
                            <ul class="mb-0" style="list-style-type: none; padding-left: 0;">
                                ${vitalsListHtml}
                            </ul>
                        </div>
                    </div>
                `;
            } else {
                // Fallback to plain text if parsing fails
                vitalsHtml = `
                    <div class="mb-3">
                        <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                        <div class="text-muted">${escapeHtml(vitalsText).replace(/\n/g, '<br>')}${bmiText}</div>
                    </div>
                `;
            }
        }
    }
    
    // Format attachments
    let attachmentsHtml = '';
    if (recordData.attachments) {
        try {
            const attachments = typeof recordData.attachments === 'string' ? JSON.parse(recordData.attachments) : recordData.attachments;
            if (Array.isArray(attachments) && attachments.length > 0) {
                attachmentsHtml = '<div class="mb-3"><strong><i class="fas fa-paperclip me-1"></i>Attachments:</strong><div class="mt-2">';
                attachments.forEach(attachment => {
                    const fileSize = attachment.file_size ? (attachment.file_size / 1024).toFixed(1) + ' KB' : '';
                    attachmentsHtml += `
                        <div class="attachment-item" style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; margin: 5px 0;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file me-2" style="color: #6c757d;"></i>
                                <span class="flex-grow-1">${escapeHtml(attachment.original_name || 'Unknown file')}</span>
                                <small class="text-muted me-2">${fileSize}</small>
                                <a href="${escapeHtml(attachment.file_path || '#')}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download me-1"></i>View
                                </a>
                            </div>
                        </div>
                    `;
                });
                attachmentsHtml += '</div></div>';
            }
        } catch (e) {
            console.error('Error parsing attachments:', e);
        }
    }
    
    // Format doctor info
    let doctorHtml = '';
    if (recordData.doctor_first_name) {
        const doctorRole = recordData.doctor_role === 'Admin' ? 
            '<span class="badge bg-warning text-dark me-2">Admin</span>' : 
            '<span class="badge bg-info text-white me-2">Doctor</span>';
        const specialization = recordData.specialization ? 
            ` - <span class="text-muted">${escapeHtml(recordData.specialization)}</span>` : '';
        doctorHtml = `
            <div class="col-md-12 mb-3">
                <div class="card border-primary">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-3">
                            <i class="fas fa-user-md me-2 text-primary"></i>Attending Doctor for This Visit
                        </h6>
                        <div class="d-flex align-items-center">
                            <div class="me-3">${doctorRole}</div>
                            <div>
                                <strong>Dr. ${escapeHtml(recordData.doctor_first_name + ' ' + (recordData.doctor_last_name || ''))}</strong>${specialization}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Format creator info
    let creatorHtml = '';
    if (recordData.creator_first_name) {
        const creatorRole = recordData.creator_role === 'Admin' ? 
            '<span class="badge bg-warning text-dark me-1">Admin</span>' : 
            (recordData.creator_role === 'Doctor' ? 
                '<span class="badge bg-info text-white me-1">Doctor</span>' : 
                '<span class="badge bg-secondary me-1">User</span>');
        creatorHtml = `
            <div class="col-md-6 mb-2">
                <i class="fas fa-user-plus me-1"></i>
                <strong>Recorded by:</strong><br>
                ${creatorRole}
                ${escapeHtml(recordData.creator_first_name + ' ' + (recordData.creator_last_name || ''))}<br>
                <small class="text-muted">on ${recordedTime}</small>
            </div>
        `;
    } else {
        creatorHtml = `
            <div class="col-md-6 mb-2">
                <i class="fas fa-user-plus me-1"></i>
                <strong>Recorded by:</strong><br>
                <span class="text-muted">Not available</span><br>
                <small class="text-muted">on ${recordedTime}</small>
            </div>
        `;
    }
    
    // Format updater info
    let updaterHtml = '';
    if (recordData.updater_first_name && recordData.updated_by && 
        (!recordData.created_by || recordData.updated_by != recordData.created_by)) {
        const updaterRole = recordData.updater_role === 'Admin' ? 
            '<span class="badge bg-warning text-dark me-1">Admin</span>' : 
            '<span class="badge bg-info text-white me-1">Doctor</span>';
        const updatedTime = recordData.updated_at ? new Date(recordData.updated_at).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        }) : '';
        updaterHtml = `
            <div class="col-md-6 mb-2">
                <i class="fas fa-user-edit me-1"></i>
                <strong>Last updated by:</strong><br>
                ${updaterRole}
                ${escapeHtml(recordData.updater_first_name + ' ' + (recordData.updater_last_name || ''))}<br>
                <small class="text-muted">on ${updatedTime}</small>
            </div>
        `;
    } else {
        updaterHtml = `
            <div class="col-md-6 mb-2">
                <i class="fas fa-calendar me-1"></i>
                <strong>Record Date:</strong><br>
                <small class="text-muted">${recordedTime}</small>
            </div>
        `;
    }
    
    // Build the HTML content
    modalBody.innerHTML = `
        <div class="mb-4 pb-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-stethoscope me-2 text-primary"></i>Medical Visit Record
                    </h5>
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>Visit Date: <strong>${visitDate}</strong>
                        ${recordData.created_at ? ` | <i class="fas fa-clock me-1"></i>Recorded: <strong>${new Date(recordData.created_at).toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        })}</strong>` : ''}
                    </small>
                </div>
            </div>
        </div>
        
        ${recordData.diagnosis ? `
            <div class="mb-3">
                <strong><i class="fas fa-notes-medical me-1"></i>Diagnosis:</strong>
                <div class="text-muted">${escapeHtml(recordData.diagnosis).replace(/\n/g, '<br>')}</div>
            </div>
        ` : ''}
        
        ${recordData.treatment ? `
            <div class="mb-3">
                <strong><i class="fas fa-pills me-1"></i>Treatment:</strong>
                <div class="text-muted">${escapeHtml(recordData.treatment).replace(/\n/g, '<br>')}</div>
            </div>
        ` : ''}
        
        ${recordData.prescription ? `
            <div class="mb-3">
                <strong><i class="fas fa-prescription me-1"></i>Prescription:</strong>
                <div class="text-muted">${escapeHtml(recordData.prescription).replace(/\n/g, '<br>')}</div>
            </div>
        ` : ''}
        
        ${recordData.lab_results ? `
            <div class="mb-3">
                <strong><i class="fas fa-flask me-1"></i>Lab Results:</strong>
                <div class="text-muted">${escapeHtml(recordData.lab_results).replace(/\n/g, '<br>')}</div>
            </div>
        ` : ''}
        
        ${vitalsHtml}
        
        ${(function() {
            // Only show related_vitals if the medical record's vitals field is empty
            // This prevents duplication when vitals are stored in the medical record
            if (recordData.vitals && recordData.vitals.trim() !== '') return '';
            if (!recordData.related_vitals || recordData.related_vitals.length === 0) return '';
            const allVitalItems = [];
            recordData.related_vitals.forEach(function(vital) {
                if (vital.blood_pressure) allVitalItems.push('BP: ' + escapeHtml(vital.blood_pressure));
                if (vital.temperature) {
                    let temp = escapeHtml(vital.temperature);
                    if (!temp.toLowerCase().includes('°f') && !temp.toLowerCase().includes('f')) {
                        temp += ' °F';
                    }
                    allVitalItems.push('Temperature: ' + temp);
                }
                if (vital.heart_rate) {
                    let hr = escapeHtml(vital.heart_rate);
                    if (!hr.toLowerCase().includes('bpm')) {
                        hr += ' bpm';
                    }
                    allVitalItems.push('Heart Rate: ' + hr);
                }
                if (vital.respiratory_rate) {
                    let rr = escapeHtml(vital.respiratory_rate);
                    if (!rr.toLowerCase().includes('/min')) {
                        rr += ' /min';
                    }
                    allVitalItems.push('Respiratory Rate: ' + rr);
                }
                if (vital.oxygen_saturation) {
                    let o2 = escapeHtml(vital.oxygen_saturation);
                    if (!o2.includes('%')) {
                        o2 += ' %';
                    }
                    allVitalItems.push('O2 Saturation: ' + o2);
                }
                if (vital.weight) {
                    let weight = escapeHtml(vital.weight);
                    if (!weight.toLowerCase().includes('lbs') && !weight.toLowerCase().includes('kg')) {
                        weight += ' lbs';
                    }
                    allVitalItems.push('Weight: ' + weight);
                }
                if (vital.height) {
                    let height = escapeHtml(vital.height);
                    if (!height.toLowerCase().includes('in') && !height.toLowerCase().includes('cm')) {
                        height += ' in';
                    }
                    allVitalItems.push('Height: ' + height);
                }
                if (vital.bmi) {
                    const bmiValue = parseFloat(vital.bmi);
                    const bmiClassification = classifyBMI(bmiValue);
                    allVitalItems.push('BMI: ' + escapeHtml(vital.bmi) + ' <span class="badge bg-' + bmiClassification.class + '">' + escapeHtml(bmiClassification.status) + '</span>');
                }
            });
            return allVitalItems.length > 0 ? `
                <div class="mb-3">
                    <strong><i class="fas fa-heartbeat me-1"></i>Vitals:</strong>
                    <div class="text-muted mt-2">
                        <ul class="mb-0" style="list-style-type: none; padding-left: 0;">
                            ${allVitalItems.map(item => `<li>${item}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            ` : '';
        })()}
        
        ${(function() {
            if (!recordData.related_medical_history || recordData.related_medical_history.length === 0) return '';
            const historyTypes = {
                'allergies': 'Allergies',
                'medications': 'Medications',
                'past_history': 'Past History',
                'immunization': 'Immunization',
                'procedures': 'Procedures',
                'substance': 'Substance Use',
                'family': 'Family History',
                'menstrual': 'Menstrual History',
                'sexual': 'Sexual History',
                'obstetric': 'Obstetric History',
                'growth': 'Growth History'
            };
            const historyItems = [];
            recordData.related_medical_history.forEach(function(history) {
                const historyType = historyTypes[history.history_type] || (history.history_type ? history.history_type.charAt(0).toUpperCase() + history.history_type.slice(1) : 'Medical History');
                
                // Try to use structured data if available
                let historyHtml = '';
                if (history.structured_data) {
                    try {
                        const structuredData = typeof history.structured_data === 'string' 
                            ? JSON.parse(history.structured_data) 
                            : history.structured_data;
                        historyHtml = formatStructuredData(history.history_type, structuredData);
                    } catch (e) {
                        // Fallback to text details
                        if (history.history_details) {
                            historyHtml = '<div class="text-muted mt-2">' + escapeHtml(history.history_details).replace(/\n/g, '<br>') + '</div>';
                        }
                    }
                } else if (history.history_details) {
                    historyHtml = '<div class="text-muted mt-2">' + escapeHtml(history.history_details).replace(/\n/g, '<br>') + '</div>';
                }
                
                if (historyHtml) {
                    historyItems.push({
                        type: historyType,
                        html: historyHtml
                    });
                }
            });
            return historyItems.length > 0 ? `
                <div class="mb-3">
                    <strong><i class="fas fa-history me-1"></i>Medical History:</strong>
                    ${historyItems.map(item => `
                        <div class="mt-3 mb-3 p-3 border rounded bg-light">
                            <h6 class="text-primary mb-2">
                                <i class="fas fa-${getHistoryIcon(item.type)} me-2"></i>${escapeHtml(item.type)}
                            </h6>
                            ${item.html}
                        </div>
                    `).join('')}
                </div>
            ` : '';
        })()}
        
        ${recordData.notes ? `
            <div class="mb-3">
                <strong><i class="fas fa-sticky-note me-1"></i>Notes:</strong>
                <div class="text-muted">${escapeHtml(recordData.notes).replace(/\n/g, '<br>')}</div>
            </div>
        ` : ''}
        
        ${attachmentsHtml}
        
        ${nextApptHtml}
        
        <div class="mt-4 pt-3 border-top">
            <div class="row">
                ${doctorHtml}
                <div class="col-md-12">
                    <div class="card border-secondary">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-3">
                                <i class="fas fa-user-edit me-2 text-secondary"></i>Record Information
                            </h6>
                            <div class="row">
                                ${creatorHtml}
                                ${updaterHtml}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Function to format structured data for display (from view_medical_history_modal.php)
function formatStructuredData(historyType, data) {
    if (!data || Object.keys(data).length === 0) {
        return '<p class="text-muted fst-italic">No details provided</p>';
    }
    
    let html = '<div class="mt-2">';
    
    switch(historyType) {
        case 'allergies':
            html += formatAllergiesDisplay(data);
            break;
        case 'medications':
            html += formatMedicationsDisplay(data);
            break;
        case 'past_history':
            html += formatPastHistoryDisplay(data);
            break;
        case 'immunization':
            html += formatImmunizationDisplay(data);
            break;
        case 'procedures':
            html += formatProceduresDisplay(data);
            break;
        case 'substance':
            html += formatSubstanceDisplay(data);
            break;
        case 'family':
            html += formatFamilyDisplay(data);
            break;
        case 'menstrual':
            html += formatMenstrualDisplay(data);
            break;
        case 'obstetric':
            html += formatObstetricDisplay(data);
            break;
        case 'growth':
            html += formatGrowthDisplay(data);
            break;
        case 'sexual':
            html += formatSexualDisplay(data);
            break;
        default:
            html += '<pre>' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
    }
    
    html += '</div>';
    return html;
}

function formatAllergiesDisplay(data) {
    let html = '';
    if (data.food && data.food.length > 0) {
        html += `<div class="mb-2"><strong>Food Allergies:</strong> <span class="text-muted">${data.food.join(', ')}</span></div>`;
    }
    if (data.drug && data.drug.length > 0) {
        html += `<div class="mb-2"><strong>Drug Allergies:</strong> <span class="text-muted">${data.drug.join(', ')}</span></div>`;
    }
    if (data.environmental && data.environmental.length > 0) {
        html += `<div class="mb-2"><strong>Environmental Allergies:</strong> <span class="text-muted">${data.environmental.join(', ')}</span></div>`;
    }
    if (data.other && data.other.length > 0) {
        html += `<div class="mb-2"><strong>Other Allergies:</strong> <span class="text-muted">${data.other.join(', ')}</span></div>`;
    }
    if (data.others_text) {
        html += `<div class="mb-2"><strong>Others:</strong> <span class="text-muted">${escapeHtml(data.others_text)}</span></div>`;
    }
    if (data.reaction_type) {
        let reactionTypes = Array.isArray(data.reaction_type) ? data.reaction_type : [data.reaction_type];
        if (reactionTypes.length > 0) {
            html += `<div class="mb-2"><strong>Reaction Type:</strong> <span class="text-muted">${reactionTypes.map(rt => escapeHtml(rt)).join(', ')}</span></div>`;
        }
    }
    if (data.severity) {
        html += `<div class="mb-2"><strong>Severity:</strong> <span class="badge bg-${data.severity === 'Severe' ? 'danger' : data.severity === 'Moderate' ? 'warning' : 'info'}">${escapeHtml(data.severity)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No allergy information provided</p>';
}

function formatMedicationsDisplay(data) {
    if (!Array.isArray(data) || data.length === 0) {
        return '<p class="text-muted fst-italic">No medications recorded</p>';
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
    html += '<thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Purpose</th><th>Type</th></tr></thead><tbody>';
    data.forEach(med => {
        html += `<tr>
            <td>${escapeHtml(med.name || '')}</td>
            <td>${escapeHtml(med.dosage || '')}</td>
            <td>${escapeHtml(med.frequency || '')}</td>
            <td>${escapeHtml(med.purpose || '')}</td>
            <td>${med.maintenance ? '<span class="badge bg-primary">Maintenance</span>' : '<span class="badge bg-secondary">As needed</span>'}</td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    return html;
}

function formatPastHistoryDisplay(data) {
    let html = '';
    if (data.conditions && data.conditions.length > 0) {
        html += `<div class="mb-2"><strong>Conditions:</strong> <span class="text-muted">${data.conditions.join(', ')}</span></div>`;
    }
    if (data.others_text) {
        html += `<div class="mb-2"><strong>Others:</strong> <span class="text-muted">${escapeHtml(data.others_text)}</span></div>`;
    }
    if (data.year_diagnosed) {
        html += `<div class="mb-2"><strong>Year Diagnosed:</strong> <span class="text-muted">${escapeHtml(data.year_diagnosed)}</span></div>`;
    }
    if (data.status) {
        html += `<div class="mb-2"><strong>Status:</strong> <span class="badge bg-${data.status === 'Ongoing' ? 'warning' : 'success'}">${escapeHtml(data.status)}</span></div>`;
    }
    if (data.hospitalized) {
        html += `<div class="mb-2"><strong>Hospitalized:</strong> <span class="text-muted">${escapeHtml(data.hospitalized)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No past medical history provided</p>';
}

function formatImmunizationDisplay(data) {
    let html = '';
    if (data.children && data.children.length > 0) {
        html += `<div class="mb-2"><strong>Children Vaccines (EPI):</strong> <span class="text-muted">${data.children.join(', ')}</span></div>`;
    }
    if (data.adults && data.adults.length > 0) {
        html += `<div class="mb-2"><strong>Adult Vaccines:</strong> <span class="text-muted">${data.adults.join(', ')}</span></div>`;
    }
    if (data.covid_brand) {
        html += `<div class="mb-2"><strong>COVID-19 Brand:</strong> <span class="text-muted">${escapeHtml(data.covid_brand)}</span></div>`;
    }
    if (data.covid_doses) {
        html += `<div class="mb-2"><strong>COVID-19 Doses:</strong> <span class="text-muted">${escapeHtml(data.covid_doses)}</span></div>`;
    }
    if (data.last_dose_date) {
        html += `<div class="mb-2"><strong>Last Dose Date:</strong> <span class="text-muted">${escapeHtml(data.last_dose_date)}</span></div>`;
    }
    if (data.status) {
        html += `<div class="mb-2"><strong>Status:</strong> <span class="badge bg-${data.status === 'Completed' ? 'success' : 'warning'}">${escapeHtml(data.status)}</span></div>`;
    }
    if (data.has_card) {
        html += `<div class="mb-2"><strong>Has Vaccination Card:</strong> <span class="text-muted">${escapeHtml(data.has_card)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No immunization information provided</p>';
}

function formatProceduresDisplay(data) {
    if (!Array.isArray(data) || data.length === 0) {
        return '<p class="text-muted fst-italic">No procedures recorded</p>';
    }
    let html = '<ul class="list-group">';
    data.forEach(proc => {
        html += `<li class="list-group-item">
            <strong>${escapeHtml(proc.name || '')}</strong>
            ${proc.year ? ` (${escapeHtml(proc.year)})` : ''}
            ${proc.hospital ? ` - ${escapeHtml(proc.hospital)}` : ''}
        </li>`;
    });
    html += '</ul>';
    return html;
}

function formatSubstanceDisplay(data) {
    let html = '';
    if (data.smoking_status) {
        html += `<div class="mb-2"><strong>Smoking:</strong> <span class="text-muted">${escapeHtml(data.smoking_status)}${data.smoking_packs_year ? ` (${escapeHtml(data.smoking_packs_year)} packs/year)` : ''}</span></div>`;
    }
    if (data.alcohol_status) {
        html += `<div class="mb-2"><strong>Alcohol:</strong> <span class="text-muted">${escapeHtml(data.alcohol_status)}${data.alcohol_type ? ` - ${escapeHtml(data.alcohol_type)}` : ''}</span></div>`;
    }
    if (data.vaping) {
        html += `<div class="mb-2"><strong>Vaping:</strong> <span class="text-muted">${escapeHtml(data.vaping)}</span></div>`;
    }
    if (data.illicit_drugs) {
        html += `<div class="mb-2"><strong>Illicit Drugs:</strong> <span class="text-muted">${escapeHtml(data.illicit_drugs)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No substance use information provided</p>';
}

function formatFamilyDisplay(data) {
    let html = '';
    if (data.conditions && data.conditions.length > 0) {
        html += `<div class="mb-2"><strong>Conditions:</strong> <span class="text-muted">${data.conditions.join(', ')}</span></div>`;
    }
    if (data.relationship) {
        html += `<div class="mb-2"><strong>Relationship:</strong> <span class="text-muted">${escapeHtml(data.relationship)}</span></div>`;
    }
    if (data.status) {
        html += `<div class="mb-2"><strong>Status:</strong> <span class="badge bg-${data.status === 'Alive' ? 'success' : 'secondary'}">${escapeHtml(data.status)}</span></div>`;
    }
    if (data.cause_of_death) {
        html += `<div class="mb-2"><strong>Cause of Death:</strong> <span class="text-muted">${escapeHtml(data.cause_of_death)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No family history provided</p>';
}

function formatMenstrualDisplay(data) {
    let html = '';
    if (data.menarche_age) {
        html += `<div class="mb-2"><strong>Age of Menarche:</strong> <span class="text-muted">${escapeHtml(data.menarche_age)} years</span></div>`;
    }
    if (data.lmp) {
        html += `<div class="mb-2"><strong>LMP (Last Menstrual Period):</strong> <span class="text-muted">${escapeHtml(data.lmp)}</span></div>`;
    }
    if (data.regularity) {
        html += `<div class="mb-2"><strong>Cycle Regularity:</strong> <span class="text-muted">${escapeHtml(data.regularity)}</span></div>`;
    }
    if (data.duration) {
        html += `<div class="mb-2"><strong>Duration:</strong> <span class="text-muted">${escapeHtml(data.duration)} days</span></div>`;
    }
    if (data.dysmenorrhea) {
        html += `<div class="mb-2"><strong>Dysmenorrhea:</strong> <span class="text-muted">${escapeHtml(data.dysmenorrhea)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No menstrual history provided</p>';
}

function formatObstetricDisplay(data) {
    let html = '';
    if (data.gravida || data.para) {
        html += `<div class="mb-2"><strong>Gravida/Para:</strong> <span class="text-muted">G${escapeHtml(data.gravida || '0')}P${escapeHtml(data.para || '0')}</span></div>`;
    }
    if (data.normal_deliveries) {
        html += `<div class="mb-2"><strong>Normal Deliveries:</strong> <span class="text-muted">${escapeHtml(data.normal_deliveries)}</span></div>`;
    }
    if (data.cs) {
        html += `<div class="mb-2"><strong>Cesarean Sections:</strong> <span class="text-muted">${escapeHtml(data.cs)}</span></div>`;
    }
    if (data.miscarriage) {
        html += `<div class="mb-2"><strong>History of Miscarriage:</strong> <span class="text-muted">${escapeHtml(data.miscarriage)}</span></div>`;
    }
    if (data.last_delivery_date) {
        html += `<div class="mb-2"><strong>Last Delivery Date:</strong> <span class="text-muted">${escapeHtml(data.last_delivery_date)}</span></div>`;
    }
    if (data.prenatal_complications) {
        html += `<div class="mb-2"><strong>Prenatal Complications:</strong> <span class="text-muted">${escapeHtml(data.prenatal_complications)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No obstetric history provided</p>';
}

function formatGrowthDisplay(data) {
    let html = '';
    if (data.birth_history) {
        html += `<div class="mb-2"><strong>Birth History:</strong> <span class="text-muted">${escapeHtml(data.birth_history)}</span></div>`;
    }
    if (data.birth_weight) {
        html += `<div class="mb-2"><strong>Birth Weight:</strong> <span class="text-muted">${escapeHtml(data.birth_weight)} kg</span></div>`;
    }
    if (data.milestones) {
        html += `<div class="mb-2"><strong>Developmental Milestones:</strong> <span class="badge bg-${data.milestones === 'Normal' ? 'success' : 'warning'}">${escapeHtml(data.milestones)}</span></div>`;
    }
    if (data.feeding) {
        html += `<div class="mb-2"><strong>Feeding History:</strong> <span class="text-muted">${escapeHtml(data.feeding)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No growth & development information provided</p>';
}

function formatSexualDisplay(data) {
    let html = '';
    if (data.active) {
        html += `<div class="mb-2"><strong>Sexually Active:</strong> <span class="text-muted">${escapeHtml(data.active)}</span></div>`;
    }
    if (data.multiple_partners) {
        html += `<div class="mb-2"><strong>Multiple Partners:</strong> <span class="text-muted">${escapeHtml(data.multiple_partners)}</span></div>`;
    }
    if (data.sti_history) {
        html += `<div class="mb-2"><strong>STI History:</strong> <span class="text-muted">${escapeHtml(data.sti_history)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No sexual history provided</p>';
}

// Function to show the view modal with record data (make it globally accessible)
window.showViewMedicalRecordModal = function(recordId, recordType) {
    // Get the record data from the page (stored in data attribute)
    const recordCard = document.querySelector(`[data-record-id="${recordId}"]`);
    if (!recordCard) {
        showAlert('Record not found', 'Error', 'error');
        return;
    }
    
    const recordDataJson = recordCard.getAttribute('data-record-json');
    if (!recordDataJson) {
        showAlert('Record data not available', 'Error', 'error');
        return;
    }
    
    // Get record type from data attribute if not provided
    if (!recordType) {
        recordType = recordCard.getAttribute('data-record-type') || 'medical_record';
    }
    
    try {
        const recordData = JSON.parse(recordDataJson);
        recordData.record_type = recordType; // Ensure record type is set
        populateViewModal(recordData);
        
        // Show the modal
        const modalElement = document.getElementById('viewMedicalRecordModal');
        if (modalElement) {
            // Check if modal instance already exists, if so reuse it, otherwise create new one
            let modal = bootstrap.Modal.getInstance(modalElement);
            if (!modal) {
                modal = new bootstrap.Modal(modalElement);
            }
            modal.show();
        } else {
            showAlert('Modal element not found. Please refresh the page and try again.', 'Error', 'error');
            console.error('Modal element with id "viewMedicalRecordModal" not found');
        }
    } catch (e) {
        console.error('Error parsing record data:', e);
        showAlert('Error loading record data. Please try again.', 'Error', 'error');
    }
};

// Helper function to get icon for history type
function getHistoryIcon(historyType) {
    const icons = {
        'Allergies': 'exclamation-triangle',
        'Medications': 'pills',
        'Past History': 'file-medical',
        'Immunization': 'syringe',
        'Procedures': 'procedures',
        'Substance Use': 'smoking-ban',
        'Family History': 'users',
        'Menstrual History': 'calendar-alt',
        'Sexual History': 'heart',
        'Obstetric History': 'baby',
        'Growth History': 'chart-line'
    };
    return icons[historyType] || 'file';
}

// Function to populate medical history modal
function populateMedicalHistoryModal(recordData) {
    const modalBody = document.getElementById('viewMedicalRecordModalBody');
    // Store record data for printing
    currentRecordData = recordData;
    
    const historyTypes = {
        'allergies': 'Allergies',
        'medications': 'Medications',
        'past_history': 'Past History',
        'immunization': 'Immunization',
        'procedures': 'Procedures',
        'substance': 'Substance Use',
        'family': 'Family History',
        'menstrual': 'Menstrual History',
        'sexual': 'Sexual History',
        'obstetric': 'Obstetric History',
        'growth': 'Growth History'
    };
    
    const historyType = historyTypes[recordData.history_type] || 'Medical History';
    const recordedTime = recordData.created_at ? new Date(recordData.created_at).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    }) : 'Not available';
    
    // Format doctor info
    let doctorHtml = '';
    if (recordData.doctor_first_name) {
        const doctorRole = recordData.doctor_role === 'Admin' ? 
            '<span class="badge bg-warning text-dark me-2">Admin</span>' : 
            '<span class="badge bg-info text-white me-2">Doctor</span>';
        const specialization = recordData.specialization ? 
            ` - <span class="text-muted">${escapeHtml(recordData.specialization)}</span>` : '';
        doctorHtml = `
            <div class="mb-3">
                <strong><i class="fas fa-user-md me-1"></i>Recorded by:</strong>
                <div class="mt-1">
                    ${doctorRole}
                    <strong>Dr. ${escapeHtml(recordData.doctor_first_name + ' ' + (recordData.doctor_last_name || ''))}</strong>${specialization}
                </div>
            </div>
        `;
    }
    
    // Format creator info
    let creatorHtml = '';
    if (recordData.creator_first_name) {
        const creatorRole = recordData.creator_role === 'Admin' ? 
            '<span class="badge bg-warning text-dark me-1">Admin</span>' : 
            (recordData.creator_role === 'Doctor' ? 
                '<span class="badge bg-info text-white me-1">Doctor</span>' : 
                '<span class="badge bg-secondary me-1">User</span>');
        creatorHtml = `
            <div class="col-md-6 mb-2">
                <i class="fas fa-user-plus me-1"></i>
                <strong>Created by:</strong><br>
                ${creatorRole}
                ${escapeHtml(recordData.creator_first_name + ' ' + (recordData.creator_last_name || ''))}<br>
                <small class="text-muted">on ${recordedTime}</small>
            </div>
        `;
    }
    
    modalBody.innerHTML = `
        <div class="mb-4 pb-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-history me-2 text-info"></i>${escapeHtml(historyType)}
                    </h5>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>Recorded: <strong>${recordedTime}</strong>
                    </small>
                </div>
                <span class="badge bg-info">Medical History</span>
            </div>
        </div>
        
        ${(function() {
            // Try to use structured data if available
            if (recordData.structured_data) {
                try {
                    const structuredData = typeof recordData.structured_data === 'string' 
                        ? JSON.parse(recordData.structured_data) 
                        : recordData.structured_data;
                    return `
                        <div class="mb-3">
                            <strong><i class="fas fa-file-alt me-1"></i>Details:</strong>
                            ${formatStructuredData(recordData.history_type, structuredData)}
                        </div>
                    `;
                } catch (e) {
                    // Fallback to text details
                }
            }
            // Use text details if structured data not available
            if (recordData.history_details) {
                return `
                    <div class="mb-3">
                        <strong><i class="fas fa-file-alt me-1"></i>Details:</strong>
                        <div class="text-muted mt-2 p-3 bg-light border rounded" style="white-space: pre-wrap;">${escapeHtml(recordData.history_details)}</div>
                    </div>
                `;
            }
            return '';
        })()}
        
        ${recordData.status ? `
            <div class="mb-3">
                <strong><i class="fas fa-info-circle me-1"></i>Status:</strong>
                <span class="badge ${recordData.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                    ${escapeHtml(recordData.status.charAt(0).toUpperCase() + recordData.status.slice(1))}
                </span>
            </div>
        ` : ''}
        
        ${doctorHtml}
        
        <div class="mt-4 pt-3 border-top">
            <div class="row">
                ${creatorHtml}
                <div class="col-md-6 mb-2">
                    <i class="fas fa-calendar me-1"></i>
                    <strong>Record Date:</strong><br>
                    <small class="text-muted">${recordedTime}</small>
                </div>
            </div>
        </div>
    `;
}

// Function to populate vitals modal
function populateVitalsModal(recordData) {
    const modalBody = document.getElementById('viewMedicalRecordModalBody');
    // Store record data for printing
    currentRecordData = recordData;
    
    const visitDate = recordData.visit_date ? new Date(recordData.visit_date + 'T00:00:00').toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }) : 'Not specified';
    
    const recordedTime = recordData.created_at ? new Date(recordData.created_at).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    }) : 'Not available';
    
    // Build vitals display
    const vitalsItems = [];
    
    if (recordData.blood_pressure) {
        vitalsItems.push({label: 'Blood Pressure', value: recordData.blood_pressure, icon: 'fa-heartbeat'});
    }
    if (recordData.heart_rate) {
        vitalsItems.push({label: 'Heart Rate', value: recordData.heart_rate + ' bpm', icon: 'fa-heart'});
    }
    if (recordData.respiratory_rate) {
        vitalsItems.push({label: 'Respiratory Rate', value: recordData.respiratory_rate + ' /min', icon: 'fa-lungs'});
    }
    if (recordData.temperature) {
        vitalsItems.push({label: 'Temperature', value: recordData.temperature + ' °F', icon: 'fa-thermometer-half'});
    }
    if (recordData.oxygen_saturation) {
        vitalsItems.push({label: 'Oxygen Saturation', value: recordData.oxygen_saturation + ' %', icon: 'fa-wind'});
    }
    if (recordData.weight) {
        vitalsItems.push({label: 'Weight', value: recordData.weight + ' lbs', icon: 'fa-weight'});
    }
    if (recordData.height) {
        vitalsItems.push({label: 'Height', value: recordData.height + ' in', icon: 'fa-ruler-vertical'});
    }
    
    // Calculate BMI if weight and height are available, even if BMI is not explicitly provided
    if (recordData.bmi) {
        const bmiValue = parseFloat(recordData.bmi);
        const bmiClassification = classifyBMI(bmiValue);
        vitalsItems.push({
            label: 'BMI', 
            value: recordData.bmi + ' <span class="badge bg-' + bmiClassification.class + ' ms-1">' + bmiClassification.status + '</span>', 
            icon: 'fa-calculator'
        });
    } else if (recordData.weight && recordData.height) {
        // Calculate BMI from weight and height
        const weight = parseFloat(recordData.weight);
        const height = parseFloat(recordData.height);
        if (!isNaN(weight) && !isNaN(height) && height > 0) {
            const bmi = (weight * 703) / (height * height);
            const bmiRounded = bmi.toFixed(1);
            const bmiClassification = classifyBMI(bmiRounded);
            vitalsItems.push({
                label: 'BMI', 
                value: bmiRounded + ' <span class="badge bg-' + bmiClassification.class + ' ms-1">' + bmiClassification.status + '</span>', 
                icon: 'fa-calculator'
            });
        }
    }
    
    let vitalsHtml = '';
    if (vitalsItems.length > 0) {
        vitalsHtml = `
            <div class="mb-3">
                <strong><i class="fas fa-heartbeat me-1"></i>Vital Signs:</strong>
                <div class="vital-signs-grid mt-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                    ${vitalsItems.map(item => `
                        <div class="vital-item" style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #dee2e6; height: 150px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <div style="font-size: 1.5em; color: #007bff; margin-bottom: 8px;">
                                <i class="fas ${item.icon}"></i>
                            </div>
                            <div class="vital-label" style="font-size: 0.85em; color: #6c757d; font-weight: 500; margin-bottom: 5px;">
                                ${escapeHtml(item.label)}
                            </div>
                            <div class="vital-value" style="font-size: 1.2em; font-weight: 600; color: #495057;">
                                ${escapeHtml(String(item.value))}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    modalBody.innerHTML = `
        <div class="mb-4 pb-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-heartbeat me-2 text-success"></i>Vital Signs Record
                    </h5>
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>Visit Date: <strong>${visitDate}</strong>
                        | <i class="fas fa-clock me-1"></i>Recorded: <strong>${recordedTime}</strong>
                    </small>
                </div>
                <span class="badge bg-success">Vitals</span>
            </div>
        </div>
        
        ${vitalsHtml}
        
        ${recordData.notes ? `
            <div class="mb-3">
                <strong><i class="fas fa-sticky-note me-1"></i>Notes:</strong>
                <div class="text-muted mt-2" style="white-space: pre-wrap;">${escapeHtml(recordData.notes)}</div>
            </div>
        ` : ''}
        
        <div class="mt-4 pt-3 border-top">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <i class="fas fa-calendar me-1"></i>
                    <strong>Visit Date:</strong><br>
                    <small class="text-muted">${visitDate}</small>
                </div>
                <div class="col-md-6 mb-2">
                    <i class="fas fa-clock me-1"></i>
                    <strong>Recorded:</strong><br>
                    <small class="text-muted">${recordedTime}</small>
                </div>
            </div>
        </div>
    `;
}

// Function to show medical record modal
function showViewMedicalRecordModal(recordId, recordType) {
    const modal = new bootstrap.Modal(document.getElementById('viewMedicalRecordModal'));
    const modalBody = document.getElementById('viewMedicalRecordModalBody');
    
    // Find the record card with this ID and type
    const recordCard = document.querySelector(`.record-card[data-record-id="${recordId}"][data-record-type="${recordType}"]`);
    
    if (recordCard) {
        // Get record data from data attribute
        const recordJson = recordCard.getAttribute('data-record-json');
        if (recordJson) {
            try {
                const recordData = JSON.parse(recordJson);
                // Ensure record_type is set
                recordData.record_type = recordType;
                populateViewModal(recordData);
                modal.show();
                return;
            } catch (e) {
                console.error('Error parsing record data:', e);
            }
        }
    }
    
    // Fallback: Show loading state and try to fetch
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading medical record...</p>
        </div>
    `;
    
    modal.show();
    
    // Try to fetch record data as fallback
    fetch('get_patient_appointment_details.php?record_id=' + recordId + '&record_type=' + recordType)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.record) {
                // Add record_type to the data
                data.record.record_type = recordType;
                populateViewModal(data.record);
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message || 'Failed to load medical record.'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching medical record:', error);
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    An error occurred while loading the medical record. Please try again.
                </div>
            `;
        });
}

// Function to print medical record
function printMedicalRecord() {
    if (!currentRecordData) {
        showAlert('No record data available to print', 'Error', 'error');
        return;
    }
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Get the logo path (relative to the root)
    // Use absolute path based on current page location
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    const logoPath = window.location.origin + basePath + 'img/logo2.jpeg';
    
    // Format the record data for printing
    const recordType = currentRecordData.record_type || 'medical_record';
    let printContent = '';
    
    // Format visit date
    const visitDate = currentRecordData.visit_date ? new Date(currentRecordData.visit_date + 'T00:00:00').toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }) : 'Not specified';
    
    // Format visit date for document title
    const docDate = currentRecordData.visit_date ? new Date(currentRecordData.visit_date + 'T00:00:00').toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }) : new Date().toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    // Format recorded time
    const recordedTime = currentRecordData.created_at ? new Date(currentRecordData.created_at).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    }) : 'Not available';
    
    // Build header with logo and clinic information
    printContent += `
        <div class="clinic-header">
            <div class="clinic-logo">
                <img src="${logoPath}" alt="Mhavis Logo" onerror="this.style.display='none';">
            </div>
            <div class="clinic-info">
                <h2>MHAVIS MEDICAL & DIAGNOSTIC CENTER</h2>
                <p class="clinic-address">De Ocampo St. Poblacion 3, Indang, Philippines</p>
                <p class="clinic-contact">Phone: 0908 981 4957 | Email: mhavismedicalcenter@gmail.com</p>
                <p class="clinic-contact">Facebook: <a href="https://www.facebook.com/mhaviscenter" target="_blank">https://www.facebook.com/mhaviscenter</a></p>
            </div>
        </div>
        <hr class="header-divider">
        <div class="document-title">
            <h3>MEDICAL RECORD</h3>
            <p class="document-date">${docDate}</p>
        </div>
        <hr class="title-divider">
    `;
    
    // Build content based on record type
    if (recordType === 'medical_record') {
        printContent += buildMedicalRecordPrintContent(currentRecordData, visitDate, recordedTime);
    } else if (recordType === 'vitals') {
        printContent += buildVitalsPrintContent(currentRecordData, visitDate, recordedTime);
    } else if (recordType === 'medical_history') {
        printContent += buildMedicalHistoryPrintContent(currentRecordData, recordedTime);
    }
    
    // Build footer with doctor and record information
    printContent += buildPrintFooter(currentRecordData, recordedTime);
    
    // Create the print document
    const printDocument = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Medical Record - ${docDate}</title>
            <style>
                @media print {
                    @page {
                        margin: 1cm;
                        size: A4;
                    }
                    body {
                        margin: 0;
                        padding: 0;
                    }
                    .no-print {
                        display: none !important;
                    }
                    /* Hide browser-generated headers and footers */
                    @page {
                        margin-top: 0.5cm;
                        margin-bottom: 0.5cm;
                    }
                    /* Remove any browser text at top/bottom */
                    body::before,
                    body::after {
                        display: none !important;
                        content: none !important;
                    }
                }
                /* Hide any browser-generated text */
                body::before,
                body::after {
                    display: none;
                    content: '';
                }
                body {
                    margin: 0;
                    padding: 10px;
                    font-family: 'Arial', 'Helvetica', sans-serif;
                    background: white;
                    font-size: 11px;
                    line-height: 1.4;
                    color: #000;
                }
                .clinic-header {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 6px;
                    padding-bottom: 6px;
                    text-align: center;
                }
                .clinic-logo {
                    margin-right: 12px;
                    flex-shrink: 0;
                }
                .clinic-logo img {
                    height: 60px;
                    width: 60px;
                    border-radius: 50%;
                    object-fit: cover;
                    display: block;
                }
                .clinic-info {
                    text-align: center;
                }
                .clinic-info h2 {
                    margin: 0 0 3px 0;
                    font-size: 14px;
                    font-weight: bold;
                    color: #000;
                    text-transform: uppercase;
                    text-align: center;
                }
                .clinic-address {
                    margin: 1px 0;
                    font-size: 9px;
                    color: #333;
                    text-align: center;
                }
                .clinic-contact {
                    margin: 1px 0;
                    font-size: 8px;
                    color: #666;
                    text-align: center;
                }
                .clinic-contact a {
                    color: #0066cc;
                    text-decoration: none;
                }
                .header-divider {
                    border-top: 2px solid #000;
                    margin: 6px 0;
                    border-bottom: none;
                }
                .document-title {
                    text-align: center;
                    margin: 8px 0;
                }
                .document-title h3 {
                    font-size: 13px;
                    font-weight: bold;
                    margin: 0 0 2px 0;
                    text-transform: uppercase;
                }
                .document-date {
                    font-size: 9px;
                    color: #666;
                    margin: 0;
                }
                .title-divider {
                    border-top: 1px solid #000;
                    margin: 6px 0 8px 0;
                    border-bottom: none;
                }
                .record-section {
                    margin: 8px 0;
                    page-break-inside: avoid;
                }
                .record-section h4 {
                    font-size: 11px;
                    margin: 0 0 3px 0;
                    font-weight: bold;
                    border-bottom: 1px solid #ccc;
                    padding-bottom: 2px;
                    text-transform: uppercase;
                }
                .record-section h5 {
                    font-size: 10px;
                    margin: 4px 0 2px 0;
                    font-weight: bold;
                    color: #333;
                }
                .record-content {
                    margin: 2px 0;
                    text-align: left;
                    white-space: pre-wrap;
                    line-height: 1.4;
                    font-size: 11px;
                }
                .record-section p {
                    margin: 2px 0;
                    text-align: left;
                    line-height: 1.4;
                    font-size: 11px;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 3px 0;
                }
                .info-table td {
                    padding: 2px 4px;
                    border: none;
                    vertical-align: top;
                    font-size: 11px;
                }
                .info-table td:first-child {
                    font-weight: bold;
                    width: 25%;
                    padding-right: 8px;
                    background-color: transparent;
                }
                .vitals-list {
                    margin: 3px 0;
                    line-height: 1.5;
                    column-count: 2;
                    column-gap: 20px;
                }
                .vitals-list p {
                    margin: 2px 0;
                    font-size: 11px;
                    display: block;
                    padding: 0;
                    border: none;
                    background: none;
                    break-inside: avoid;
                }
                .vital-label {
                    font-weight: bold;
                    display: inline;
                }
                .vital-value {
                    display: inline;
                    margin-left: 5px;
                }
                .history-item {
                    margin: 5px 0;
                    padding: 0;
                }
                .history-item h5 {
                    margin: 4px 0 2px 0;
                    font-size: 11px;
                }
                .history-item .record-content {
                    margin-left: 0;
                    margin-top: 2px;
                }
                .related-history-container {
                    column-count: 2;
                    column-gap: 20px;
                    margin: 3px 0;
                }
                .related-history-container .history-item {
                    break-inside: avoid;
                    margin-bottom: 8px;
                }
                .attachment-list {
                    margin: 3px 0;
                    padding-left: 18px;
                }
                .attachment-list li {
                    margin: 1px 0;
                    font-size: 10px;
                }
                .footer-section {
                    margin-top: 12px;
                    padding-top: 8px;
                    border-top: 2px solid #000;
                    page-break-inside: avoid;
                }
                .record-info-section {
                    margin-bottom: 10px;
                }
                .record-info-section h4 {
                    font-size: 11px;
                    font-weight: bold;
                    margin: 0 0 3px 0;
                    text-transform: uppercase;
                }
                .record-info-section p {
                    margin: 2px 0;
                    font-size: 10px;
                }
                .signature-section {
                    margin-top: 12px;
                    text-align: right;
                }
                .signature-line {
                    border-bottom: 1px solid #000;
                    width: 280px;
                    margin: 12px 0 3px auto;
                    height: 1px;
                }
                .signature-info {
                    text-align: right;
                    margin-top: 5px;
                }
                .signature-info p {
                    margin: 1px 0;
                    font-size: 10px;
                }
                .license-placeholder {
                    border-bottom: 1px solid #000;
                    display: inline-block;
                    min-width: 150px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 3px 0;
                    font-size: 10px;
                }
                table td {
                    padding: 2px 4px;
                    border: none;
                    vertical-align: top;
                }
                table td:first-child {
                    font-weight: bold;
                    width: 25%;
                    padding-right: 8px;
                    background-color: transparent;
                }
                table th {
                    padding: 2px 4px;
                    border: none;
                    font-weight: bold;
                    text-align: left;
                    font-size: 10px;
                }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `;
    
    // Write to print window and print
    printWindow.document.write(printDocument);
    printWindow.document.close();
    
    // Wait for images to load before printing
    printWindow.onload = function() {
        setTimeout(function() {
            printWindow.print();
        }, 250);
    };
}

// Function to build medical record print content
function buildMedicalRecordPrintContent(recordData, visitDate, recordedTime) {
    let content = `
        <div class="record-section">
            <h4>Visit Information</h4>
            <table class="info-table">
                <tr><td>Visit Date:</td><td>${visitDate}</td></tr>
                <tr><td>Recorded:</td><td>${recordedTime}</td></tr>
            </table>
        </div>
    `;
    
    // Diagnosis
    if (recordData.diagnosis) {
        content += `
            <div class="record-section">
                <h4>Diagnosis</h4>
                <div class="record-content">${escapeHtml(recordData.diagnosis).replace(/\n/g, '<br>')}</div>
            </div>
        `;
    }
    
    // Treatment
    if (recordData.treatment) {
        content += `
            <div class="record-section">
                <h4>Treatment</h4>
                <div class="record-content">${escapeHtml(recordData.treatment).replace(/\n/g, '<br>')}</div>
            </div>
        `;
    }
    
    // Prescription
    if (recordData.prescription) {
        content += `
            <div class="record-section">
                <h4>Prescription</h4>
                <div class="record-content">${escapeHtml(recordData.prescription).replace(/\n/g, '<br>')}</div>
            </div>
        `;
    }
    
    // Lab Results
    if (recordData.lab_results) {
        content += `
            <div class="record-section">
                <h4>Lab Results</h4>
                <div class="record-content">${escapeHtml(recordData.lab_results).replace(/\n/g, '<br>')}</div>
            </div>
        `;
    }
    
    // Format vitals - check both vitals field and related_vitals
    let vitalsIncluded = false;
    if (recordData.vitals && recordData.vitals.trim() !== '') {
        content += buildVitalsSection(recordData.vitals);
        vitalsIncluded = true;
    }
    
    // Include related vitals if vitals field is empty
    if (!vitalsIncluded && recordData.related_vitals && recordData.related_vitals.length > 0) {
        content += buildRelatedVitalsSection(recordData.related_vitals);
    }
    
    // Related Medical History
    if (recordData.related_medical_history && recordData.related_medical_history.length > 0) {
        content += buildRelatedMedicalHistorySection(recordData.related_medical_history);
    }
    
    // Notes
    if (recordData.notes) {
        content += `
            <div class="record-section">
                <h4>Notes</h4>
                <div class="record-content">${escapeHtml(recordData.notes).replace(/\n/g, '<br>')}</div>
            </div>
        `;
    }
    
    // Attachments
    if (recordData.attachments) {
        content += buildAttachmentsSection(recordData.attachments);
    }
    
    // Next Appointment
    if (recordData.next_appointment_date && 
        recordData.next_appointment_date !== '0000-00-00' && 
        recordData.next_appointment_date !== '-0001-11-30') {
        const nextApptDate = new Date(recordData.next_appointment_date + 'T00:00:00');
        if (nextApptDate.getTime() > 0) {
            content += `
                <div class="record-section">
                    <h4>Next Appointment</h4>
                    <div class="record-content">${nextApptDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    })}</div>
                </div>
            `;
        }
    }
    
    return content;
}

// Function to build vitals print content
function buildVitalsPrintContent(recordData, visitDate, recordedTime) {
    let content = `
        <div class="record-section">
            <h4>Visit Information</h4>
            <p><strong>Visit Date:</strong> ${visitDate}</p>
            <p><strong>Recorded:</strong> ${recordedTime}</p>
        </div>
    `;
    
    content += buildVitalsSection(recordData);
    
    if (recordData.notes) {
        content += `
            <div class="record-section">
                <h4>Notes</h4>
                <p>${escapeHtml(recordData.notes).replace(/\n/g, '<br>')}</p>
            </div>
        `;
    }
    
    return content;
}

// Function to build medical history print content
function buildMedicalHistoryPrintContent(recordData, recordedTime) {
    const historyTypes = {
        'allergies': 'Allergies',
        'medications': 'Medications',
        'past_history': 'Past History',
        'immunization': 'Immunization',
        'procedures': 'Procedures',
        'substance': 'Substance Use',
        'family': 'Family History',
        'menstrual': 'Menstrual History',
        'sexual': 'Sexual History',
        'obstetric': 'Obstetric History',
        'growth': 'Growth History'
    };
    
    const historyType = historyTypes[recordData.history_type] || 'Medical History';
    
    let content = `
        <div class="record-section">
            <h4>Record Information</h4>
            <p><strong>Type:</strong> ${historyType}</p>
            <p><strong>Recorded:</strong> ${recordedTime}</p>
        </div>
    `;
    
    if (recordData.structured_data) {
        try {
            const structuredData = typeof recordData.structured_data === 'string' 
                ? JSON.parse(recordData.structured_data) 
                : recordData.structured_data;
            content += `
                <div class="record-section">
                    <h4>Details</h4>
                    <div>${formatStructuredDataForPrint(recordData.history_type, structuredData)}</div>
                </div>
            `;
        } catch (e) {
            if (recordData.history_details) {
                content += `
                    <div class="record-section">
                        <h4>Details</h4>
                        <p>${escapeHtml(recordData.history_details).replace(/\n/g, '<br>')}</p>
                    </div>
                `;
            }
        }
    } else if (recordData.history_details) {
        content += `
            <div class="record-section">
                <h4>Details</h4>
                <p>${escapeHtml(recordData.history_details).replace(/\n/g, '<br>')}</p>
            </div>
        `;
    }
    
    if (recordData.status) {
        content += `
            <div class="record-section">
                <h4>Status</h4>
                <p>${escapeHtml(recordData.status.charAt(0).toUpperCase() + recordData.status.slice(1))}</p>
            </div>
        `;
    }
    
    return content;
}

// Function to build vitals section
function buildVitalsSection(vitalsData) {
    let content = '<div class="record-section"><h4>Vital Signs</h4>';
    
    try {
        // Try to parse as JSON first
        const vitals = typeof vitalsData === 'string' ? JSON.parse(vitalsData) : vitalsData;
        
        if (typeof vitals === 'object' && vitals !== null) {
            const vitalItems = [];
            
            if (vitals.blood_pressure || vitals.bp) {
                vitalItems.push({label: 'Blood Pressure', value: vitals.blood_pressure || vitals.bp});
            }
            if (vitals.temperature || vitals.temp) {
                let temp = vitals.temperature || vitals.temp;
                if (!temp.toLowerCase().includes('°f') && !temp.toLowerCase().includes('f')) {
                    temp += ' °F';
                }
                vitalItems.push({label: 'Temperature', value: temp});
            }
            if (vitals.heart_rate || vitals.hr) {
                let hr = vitals.heart_rate || vitals.hr;
                if (!hr.toLowerCase().includes('bpm')) {
                    hr += ' bpm';
                }
                vitalItems.push({label: 'Heart Rate', value: hr});
            }
            if (vitals.respiratory_rate || vitals.rr) {
                let rr = vitals.respiratory_rate || vitals.rr;
                if (!rr.toLowerCase().includes('/min')) {
                    rr += ' /min';
                }
                vitalItems.push({label: 'Respiratory Rate', value: rr});
            }
            if (vitals.oxygen_saturation || vitals.o2_saturation || vitals.o2) {
                let o2 = vitals.oxygen_saturation || vitals.o2_saturation || vitals.o2;
                if (!o2.includes('%')) {
                    o2 += ' %';
                }
                vitalItems.push({label: 'O2 Saturation', value: o2});
            }
            if (vitals.weight) {
                let weight = vitals.weight;
                if (!weight.toLowerCase().includes('lbs') && !weight.toLowerCase().includes('kg')) {
                    weight += ' lbs';
                }
                vitalItems.push({label: 'Weight', value: weight});
            }
            if (vitals.height) {
                let height = vitals.height;
                if (!height.toLowerCase().includes('in') && !height.toLowerCase().includes('cm')) {
                    height += ' in';
                }
                vitalItems.push({label: 'Height', value: height});
            }
            if (vitals.bmi) {
                const bmiValue = parseFloat(vitals.bmi);
                const bmiClassification = classifyBMI(bmiValue);
                vitalItems.push({label: 'BMI', value: vitals.bmi + ' (' + bmiClassification.status + ')'});
            }
            
            if (vitalItems.length > 0) {
                content += '<div class="vitals-list">';
                vitalItems.forEach(item => {
                    content += `<p><span class="vital-label">${escapeHtml(item.label)}:</span> <span class="vital-value">${escapeHtml(String(item.value))}</span></p>`;
                });
                content += '</div>';
            }
        }
    } catch (e) {
        // Not JSON, treat as plain text
        if (typeof vitalsData === 'string') {
            content += `<div class="record-content">${escapeHtml(vitalsData).replace(/\n/g, '<br>')}</div>`;
        } else {
            // Handle direct vital properties
            const vitalItems = [];
            if (vitalsData.blood_pressure) vitalItems.push({label: 'Blood Pressure', value: vitalsData.blood_pressure});
            if (vitalsData.temperature) vitalItems.push({label: 'Temperature', value: vitalsData.temperature + ' °F'});
            if (vitalsData.heart_rate) vitalItems.push({label: 'Heart Rate', value: vitalsData.heart_rate + ' bpm'});
            if (vitalsData.respiratory_rate) vitalItems.push({label: 'Respiratory Rate', value: vitalsData.respiratory_rate + ' /min'});
            if (vitalsData.oxygen_saturation) vitalItems.push({label: 'O2 Saturation', value: vitalsData.oxygen_saturation + ' %'});
            if (vitalsData.weight) vitalItems.push({label: 'Weight', value: vitalsData.weight + ' lbs'});
            if (vitalsData.height) vitalItems.push({label: 'Height', value: vitalsData.height + ' in'});
            if (vitalsData.bmi) {
                const bmiValue = parseFloat(vitalsData.bmi);
                const bmiClassification = classifyBMI(bmiValue);
                vitalItems.push({label: 'BMI', value: vitalsData.bmi + ' (' + bmiClassification.status + ')'});
            }
            
            if (vitalItems.length > 0) {
                content += '<div class="vitals-list">';
                vitalItems.forEach(item => {
                    content += `<p><span class="vital-label">${escapeHtml(item.label)}:</span> <span class="vital-value">${escapeHtml(String(item.value))}</span></p>`;
                });
                content += '</div>';
            }
        }
    }
    
    content += '</div>';
    return content;
}

// Function to build related vitals section
function buildRelatedVitalsSection(relatedVitals) {
    if (!relatedVitals || relatedVitals.length === 0) return '';
    
    let content = '<div class="record-section"><h4>Vital Signs</h4>';
    const allVitalItems = [];
    
    relatedVitals.forEach(function(vital) {
        if (vital.blood_pressure) {
            allVitalItems.push({label: 'Blood Pressure', value: escapeHtml(vital.blood_pressure)});
        }
        if (vital.temperature) {
            let temp = escapeHtml(vital.temperature);
            if (!temp.toLowerCase().includes('°f') && !temp.toLowerCase().includes('f')) {
                temp += ' °F';
            }
            allVitalItems.push({label: 'Temperature', value: temp});
        }
        if (vital.heart_rate) {
            let hr = escapeHtml(vital.heart_rate);
            if (!hr.toLowerCase().includes('bpm')) {
                hr += ' bpm';
            }
            allVitalItems.push({label: 'Heart Rate', value: hr});
        }
        if (vital.respiratory_rate) {
            let rr = escapeHtml(vital.respiratory_rate);
            if (!rr.toLowerCase().includes('/min')) {
                rr += ' /min';
            }
            allVitalItems.push({label: 'Respiratory Rate', value: rr});
        }
        if (vital.oxygen_saturation) {
            let o2 = escapeHtml(vital.oxygen_saturation);
            if (!o2.includes('%')) {
                o2 += ' %';
            }
            allVitalItems.push({label: 'O2 Saturation', value: o2});
        }
        if (vital.weight) {
            let weight = escapeHtml(vital.weight);
            if (!weight.toLowerCase().includes('lbs') && !weight.toLowerCase().includes('kg')) {
                weight += ' lbs';
            }
            allVitalItems.push({label: 'Weight', value: weight});
        }
        if (vital.height) {
            let height = escapeHtml(vital.height);
            if (!height.toLowerCase().includes('in') && !height.toLowerCase().includes('cm')) {
                height += ' in';
            }
            allVitalItems.push({label: 'Height', value: height});
        }
        if (vital.bmi) {
            const bmiValue = parseFloat(vital.bmi);
            const bmiClassification = classifyBMI(bmiValue);
            allVitalItems.push({label: 'BMI', value: escapeHtml(vital.bmi) + ' (' + bmiClassification.status + ')'});
        }
    });
    
    if (allVitalItems.length > 0) {
        content += '<div class="vitals-list">';
        allVitalItems.forEach(item => {
            content += `<p><span class="vital-label">${item.label}:</span> <span class="vital-value">${item.value}</span></p>`;
        });
        content += '</div>';
    }
    
    content += '</div>';
    return content;
}

// Function to build related medical history section
function buildRelatedMedicalHistorySection(relatedHistory) {
    if (!relatedHistory || relatedHistory.length === 0) return '';
    
    const historyTypes = {
        'allergies': 'Allergies',
        'medications': 'Medications',
        'past_history': 'Past History',
        'immunization': 'Immunization',
        'procedures': 'Procedures',
        'substance': 'Substance Use',
        'family': 'Family History',
        'menstrual': 'Menstrual History',
        'sexual': 'Sexual History',
        'obstetric': 'Obstetric History',
        'growth': 'Growth History'
    };
    
    let content = '<div class="record-section"><h4>Related Medical History</h4>';
    content += '<div class="related-history-container">';
    
    relatedHistory.forEach(function(history) {
        const historyType = historyTypes[history.history_type] || 
            (history.history_type ? history.history_type.charAt(0).toUpperCase() + history.history_type.slice(1) : 'Medical History');
        
        content += `<div class="history-item">`;
        content += `<h5>${escapeHtml(historyType)}</h5>`;
        
        if (history.structured_data) {
            try {
                const structuredData = typeof history.structured_data === 'string' 
                    ? JSON.parse(history.structured_data) 
                    : history.structured_data;
                content += formatStructuredDataForPrint(history.history_type, structuredData);
            } catch (e) {
                if (history.history_details) {
                    content += `<div class="record-content">${escapeHtml(history.history_details).replace(/\n/g, '<br>')}</div>`;
                }
            }
        } else if (history.history_details) {
            content += `<div class="record-content">${escapeHtml(history.history_details).replace(/\n/g, '<br>')}</div>`;
        }
        
        content += `</div>`;
    });
    
    content += '</div></div>';
    return content;
}

// Function to build attachments section
function buildAttachmentsSection(attachments) {
    if (!attachments) return '';
    
    try {
        const attachmentList = typeof attachments === 'string' ? JSON.parse(attachments) : attachments;
        if (Array.isArray(attachmentList) && attachmentList.length > 0) {
            let content = '<div class="record-section"><h4>Attachments</h4><ul class="attachment-list">';
            attachmentList.forEach(attachment => {
                const fileName = escapeHtml(attachment.original_name || 'Unknown file');
                const fileSize = attachment.file_size ? (attachment.file_size / 1024).toFixed(1) + ' KB' : '';
                content += `<li>${fileName}${fileSize ? ' (' + fileSize + ')' : ''}</li>`;
            });
            content += '</ul></div>';
            return content;
        }
    } catch (e) {
        console.error('Error parsing attachments:', e);
    }
    
    return '';
}

// Function to build print footer
function buildPrintFooter(recordData, recordedTime) {
    let footer = '<div class="footer-section">';
    
    // Record Information
    footer += '<div class="record-info-section">';
    footer += '<h4>Record Information</h4>';
    
    // Creator information
    if (recordData.creator_first_name) {
        const creatorRole = recordData.creator_role === 'Admin' ? 'Admin' : 
            (recordData.creator_role === 'Doctor' ? 'Doctor' : 'User');
        footer += `
            <p><strong>Recorded by:</strong> ${creatorRole} ${escapeHtml(recordData.creator_first_name + ' ' + (recordData.creator_last_name || ''))} on ${recordedTime}</p>
        `;
    } else {
        footer += `<p><strong>Recorded on:</strong> ${recordedTime}</p>`;
    }
    
    // Updater information
    if (recordData.updater_first_name && recordData.updated_by && 
        (!recordData.created_by || recordData.updated_by != recordData.created_by)) {
        const updatedTime = recordData.updated_at ? new Date(recordData.updated_at).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        }) : '';
        const updaterRole = recordData.updater_role === 'Admin' ? 'Admin' : 'Doctor';
        footer += `
            <p><strong>Last updated by:</strong> ${updaterRole} ${escapeHtml(recordData.updater_first_name + ' ' + (recordData.updater_last_name || ''))} on ${updatedTime}</p>
        `;
    }
    
    footer += '</div>';
    
    // Doctor Signature Section
    if (recordData.doctor_first_name) {
        const doctorRole = recordData.doctor_role === 'Admin' ? 'Admin' : 'Doctor';
        const specialization = recordData.specialization ? escapeHtml(recordData.specialization) : '';
        const doctorName = escapeHtml(recordData.doctor_first_name + ' ' + (recordData.doctor_last_name || ''));
        
        footer += `
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-info">
                    <p><strong>Dr. ${doctorName}</strong></p>
                    ${specialization ? `<p>${specialization}</p>` : ''}
                    <p>Attending Physician</p>
                    <p>License No.: <span class="license-placeholder">____________________</span></p>
                </div>
            </div>
        `;
    }
    
    footer += '</div>';
    return footer;
}

// Function to format structured data for print
function formatStructuredDataForPrint(historyType, data) {
    if (!data || Object.keys(data).length === 0) {
        return '<p class="record-content">No details provided</p>';
    }
    
    let html = '<div class="record-content">';
    
    switch(historyType) {
        case 'allergies':
            if (data.food && data.food.length > 0) {
                html += `<p><strong>Food Allergies:</strong> ${data.food.join(', ')}</p>`;
            }
            if (data.drug && data.drug.length > 0) {
                html += `<p><strong>Drug Allergies:</strong> ${data.drug.join(', ')}</p>`;
            }
            if (data.environmental && data.environmental.length > 0) {
                html += `<p><strong>Environmental Allergies:</strong> ${data.environmental.join(', ')}</p>`;
            }
            if (data.other && data.other.length > 0) {
                html += `<p><strong>Other Allergies:</strong> ${data.other.join(', ')}</p>`;
            }
            if (data.others_text) {
                html += `<p><strong>Others:</strong> ${escapeHtml(data.others_text)}</p>`;
            }
            if (data.reaction_type) {
                let reactionTypes = Array.isArray(data.reaction_type) ? data.reaction_type : [data.reaction_type];
                if (reactionTypes.length > 0) {
                    html += `<p><strong>Reaction Type:</strong> ${reactionTypes.map(rt => escapeHtml(rt)).join(', ')}</p>`;
                }
            }
            if (data.severity) {
                html += `<p><strong>Severity:</strong> ${escapeHtml(data.severity)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        case 'medications':
            if (Array.isArray(data) && data.length > 0) {
                html += '<table><thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Purpose</th><th>Type</th></tr></thead><tbody>';
                data.forEach(med => {
                    html += `<tr>
                        <td>${escapeHtml(med.name || '')}</td>
                        <td>${escapeHtml(med.dosage || '')}</td>
                        <td>${escapeHtml(med.frequency || '')}</td>
                        <td>${escapeHtml(med.purpose || '')}</td>
                        <td>${med.maintenance ? 'Maintenance' : 'As needed'}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
            }
            break;
        case 'past_history':
            if (data.conditions && data.conditions.length > 0) {
                html += `<p><strong>Conditions:</strong> ${data.conditions.join(', ')}</p>`;
            }
            if (data.others_text) {
                html += `<p><strong>Others:</strong> ${escapeHtml(data.others_text)}</p>`;
            }
            if (data.year_diagnosed) {
                html += `<p><strong>Year Diagnosed:</strong> ${escapeHtml(data.year_diagnosed)}</p>`;
            }
            if (data.status) {
                html += `<p><strong>Status:</strong> ${escapeHtml(data.status)}</p>`;
            }
            if (data.hospitalized) {
                html += `<p><strong>Hospitalized:</strong> ${escapeHtml(data.hospitalized)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        case 'immunization':
            if (data.children && data.children.length > 0) {
                html += `<p><strong>Children Vaccines (EPI):</strong> ${data.children.join(', ')}</p>`;
            }
            if (data.adults && data.adults.length > 0) {
                html += `<p><strong>Adult Vaccines:</strong> ${data.adults.join(', ')}</p>`;
            }
            if (data.covid_brand) {
                html += `<p><strong>COVID-19 Brand:</strong> ${escapeHtml(data.covid_brand)}</p>`;
            }
            if (data.covid_doses) {
                html += `<p><strong>COVID-19 Doses:</strong> ${escapeHtml(data.covid_doses)}</p>`;
            }
            if (data.last_dose_date) {
                html += `<p><strong>Last Dose Date:</strong> ${escapeHtml(data.last_dose_date)}</p>`;
            }
            if (data.status) {
                html += `<p><strong>Status:</strong> ${escapeHtml(data.status)}</p>`;
            }
            if (data.has_card) {
                html += `<p><strong>Has Vaccination Card:</strong> ${escapeHtml(data.has_card)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        case 'procedures':
            if (Array.isArray(data) && data.length > 0) {
                html += '<ul>';
                data.forEach(proc => {
                    html += `<li><strong>${escapeHtml(proc.name || '')}</strong>`;
                    if (proc.year) html += ` (${escapeHtml(proc.year)})`;
                    if (proc.hospital) html += ` - ${escapeHtml(proc.hospital)}`;
                    html += '</li>';
                });
                html += '</ul>';
            }
            break;
        case 'substance':
            if (data.smoking_status) {
                html += `<p><strong>Smoking:</strong> ${escapeHtml(data.smoking_status)}`;
                if (data.smoking_packs_year) html += ` (${escapeHtml(data.smoking_packs_year)} packs/year)`;
                html += '</p>';
            }
            if (data.alcohol_status) {
                html += `<p><strong>Alcohol:</strong> ${escapeHtml(data.alcohol_status)}`;
                if (data.alcohol_type) html += ` - ${escapeHtml(data.alcohol_type)}`;
                html += '</p>';
            }
            if (data.vaping) {
                html += `<p><strong>Vaping:</strong> ${escapeHtml(data.vaping)}</p>`;
            }
            if (data.illicit_drugs) {
                html += `<p><strong>Illicit Drugs:</strong> ${escapeHtml(data.illicit_drugs)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        case 'family':
            if (data.conditions && data.conditions.length > 0) {
                html += `<p><strong>Conditions:</strong> ${data.conditions.join(', ')}</p>`;
            }
            if (data.relationship) {
                html += `<p><strong>Relationship:</strong> ${escapeHtml(data.relationship)}</p>`;
            }
            if (data.status) {
                html += `<p><strong>Status:</strong> ${escapeHtml(data.status)}</p>`;
            }
            if (data.cause_of_death) {
                html += `<p><strong>Cause of Death:</strong> ${escapeHtml(data.cause_of_death)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        case 'menstrual':
            if (data.menarche_age) {
                html += `<p><strong>Age of Menarche:</strong> ${escapeHtml(data.menarche_age)} years</p>`;
            }
            if (data.lmp) {
                html += `<p><strong>LMP (Last Menstrual Period):</strong> ${escapeHtml(data.lmp)}</p>`;
            }
            if (data.regularity) {
                html += `<p><strong>Cycle Regularity:</strong> ${escapeHtml(data.regularity)}</p>`;
            }
            if (data.duration) {
                html += `<p><strong>Duration:</strong> ${escapeHtml(data.duration)} days</p>`;
            }
            if (data.dysmenorrhea) {
                html += `<p><strong>Dysmenorrhea:</strong> ${escapeHtml(data.dysmenorrhea)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        case 'obstetric':
            if (data.gravida || data.para) {
                html += `<p><strong>Gravida/Para:</strong> G${escapeHtml(data.gravida || '0')}P${escapeHtml(data.para || '0')}</p>`;
            }
            if (data.normal_deliveries) {
                html += `<p><strong>Normal Deliveries:</strong> ${escapeHtml(data.normal_deliveries)}</p>`;
            }
            if (data.cs) {
                html += `<p><strong>Cesarean Sections:</strong> ${escapeHtml(data.cs)}</p>`;
            }
            if (data.miscarriage) {
                html += `<p><strong>History of Miscarriage:</strong> ${escapeHtml(data.miscarriage)}</p>`;
            }
            if (data.last_delivery_date) {
                html += `<p><strong>Last Delivery Date:</strong> ${escapeHtml(data.last_delivery_date)}</p>`;
            }
            if (data.prenatal_complications) {
                html += `<p><strong>Prenatal Complications:</strong> ${escapeHtml(data.prenatal_complications)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        case 'growth':
            if (data.birth_history) {
                html += `<p><strong>Birth History:</strong> ${escapeHtml(data.birth_history)}</p>`;
            }
            if (data.birth_weight) {
                html += `<p><strong>Birth Weight:</strong> ${escapeHtml(data.birth_weight)} kg</p>`;
            }
            if (data.milestones) {
                html += `<p><strong>Developmental Milestones:</strong> ${escapeHtml(data.milestones)}</p>`;
            }
            if (data.feeding) {
                html += `<p><strong>Feeding History:</strong> ${escapeHtml(data.feeding)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        case 'sexual':
            if (data.active) {
                html += `<p><strong>Sexually Active:</strong> ${escapeHtml(data.active)}</p>`;
            }
            if (data.multiple_partners) {
                html += `<p><strong>Multiple Partners:</strong> ${escapeHtml(data.multiple_partners)}</p>`;
            }
            if (data.sti_history) {
                html += `<p><strong>STI History:</strong> ${escapeHtml(data.sti_history)}</p>`;
            }
            if (data.notes) {
                html += `<p><strong>Notes:</strong> ${escapeHtml(data.notes)}</p>`;
            }
            break;
        default:
            html += '<pre style="white-space: pre-wrap; font-size: 11px;">' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
    }
    
    html += '</div>';
    return html || '<p class="record-content">No details provided</p>';
}
</script>

