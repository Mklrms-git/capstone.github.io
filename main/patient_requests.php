<?php if (!defined('MHAVIS_EXEC')) { die('Direct access not permitted'); } ?>

<style>
.form-container {
    margin-top: 20px;
}

.medical-form {
    background: white;
    border: 2px solid #000;
    padding: 20px;
    font-family: 'Times New Roman', serif;
    font-size: 12px;
    line-height: 1.4;
    margin: 0 auto;
    max-width: 100%;
}

.form-header {
    text-align: center;
    margin-bottom: 12px;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
}

.clinic-logo-container {
    margin-bottom: 10px;
}

.clinic-logo-img {
    max-width: 70px;
    max-height: 70px;
    height: auto;
    width: auto;
    object-fit: contain;
}

.patient-name-field {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    pointer-events: none;
    cursor: default;
}

.address-field {
    word-wrap: break-word !important;
    white-space: normal !important;
    overflow-wrap: break-word !important;
    word-break: break-word !important;
}

.signature-area {
    border-bottom: 1px solid #000;
    height: 40px;
    margin: 8px 0;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin: 10px 0;
}

.checkbox-item {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
    font-size: 12px;
}

.checkbox-item input[type="checkbox"] {
    margin-right: 8px;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .medical-form {
        border: 2px solid #000 !important;
        margin: 0 auto !important;
        padding: 10px 15px !important;
        max-width: 100% !important;
        page-break-inside: avoid !important;
    }
    
    .form-header {
        text-align: center !important;
        margin-bottom: 8px !important;
        padding-bottom: 8px !important;
    }
    
    .form-header h4 {
        margin: 5px 0 !important;
        font-size: 16pt !important;
    }
    
    .form-header p {
        margin: 2px 0 !important;
        font-size: 10pt !important;
    }
    
    .clinic-logo-container {
        margin-bottom: 5px !important;
    }
    
    .clinic-logo-img {
        max-width: 60px !important;
        max-height: 60px !important;
        height: auto !important;
        width: auto !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    h5, h6 {
        text-align: center !important;
        margin: 8px 0 !important;
        font-size: 12pt !important;
    }
    
    .row {
        margin: 0 !important;
        display: flex !important;
        flex-wrap: wrap !important;
    }
    
    .row.mb-2, .row.mb-3, .row.mb-4 {
        margin-bottom: 8px !important;
    }
    
    .mb-2, .mb-3, .mb-4 {
        margin-bottom: 8px !important;
    }
    
    .mt-1, .mt-3, .mt-4 {
        margin-top: 8px !important;
    }
    
    input, select, textarea {
        border: none !important;
        border-bottom: 1px solid #000 !important;
        background: transparent !important;
        font-size: 10pt !important;
        padding: 1px 3px !important;
    }
    
    label {
        font-size: 10pt !important;
        margin-bottom: 2px !important;
    }
    
    .address-field {
        word-wrap: break-word !important;
        white-space: normal !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        overflow: visible !important;
        text-overflow: clip !important;
        max-width: 100% !important;
        display: block !important;
        width: 100% !important;
    }
    
    .patient-name-field {
        border-bottom: 1px solid #000 !important;
        display: inline-block !important;
        padding: 1px 3px !important;
        font-size: 10pt !important;
    }
    
    .checkbox-grid {
        margin: 8px 0 !important;
        gap: 5px !important;
    }
    
    .checkbox-item {
        margin-bottom: 4px !important;
        font-size: 9pt !important;
    }
    
    .signature-area {
        height: 35px !important;
        margin: 5px 0 !important;
    }
    
    textarea {
        font-size: 9pt !important;
        padding: 3px !important;
        line-height: 1.3 !important;
    }
    
    /* Radio button alignment for E.R. Record Sheet */
    input[type="radio"] {
        margin-right: 5px !important;
        vertical-align: middle !important;
    }
    
    label[for^="er_new"], label[for^="er_old"] {
        margin: 0 !important;
        vertical-align: middle !important;
        display: inline !important;
    }
    
    /* Ensure proper spacing for patient type section */
    .col-md-2 label {
        display: block !important;
        margin-bottom: 5px !important;
    }
    
    @page {
        margin: 0.3in !important;
        size: A4;
    }
}
</style>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Patient Requests</h5>
    </div>
    <div class="card-body">
        <p>This section includes requests related to the patient such as:</p>
        <ul>
            <li>Laboratory Requests</li>
            <li>Emergency Room Requests</li>
            <li>Medical Certificate Requests</li>
            <li>Other Form Requests</li>
        </ul>
        
        <!-- Form Selection Buttons -->
        <div class="row mb-3">
            <div class="col-md-3">
                <button class="btn btn-primary w-100 mb-2" onclick="showForm('medical-certificate')">
                    <i class="fas fa-file-medical-alt me-1"></i> Medical Certificate
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100 mb-2" onclick="showForm('laboratory-request')">
                    <i class="fas fa-flask me-1"></i> Laboratory Request
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100 mb-2" onclick="showForm('request-form')">
                    <i class="fas fa-clipboard-list me-1"></i> General Request Form
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100 mb-2" onclick="showForm('er-record')">
                    <i class="fas fa-ambulance me-1"></i> E.R. Record Sheet
                </button>
            </div>
        </div>

        <!-- Medical Certificate Form -->
        <div id="medical-certificate" class="form-container" style="display: none;">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center no-print">
                    <h6 class="mb-0">Medical Certificate</h6>
                    <button class="btn btn-light btn-sm" onclick="printForm('medical-certificate-content')">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
                <div class="card-body">
                    <div id="medical-certificate-content" class="medical-form">
                        <div class="form-header">
                            <div class="clinic-logo-container">
                                <img src="img/logo.png" alt="Clinic Logo" class="clinic-logo-img">
                            </div>
                            <h4>MHAVIS MEDICAL CENTER</h4>
                            <p>(Mhavis Medical & Diagnostic Center)</p>
                            <p>Pub-3 Ibabang Cantic<br>(046) 415-1386, 09488419847</p>
                        </div>
                        
                        <h5 style="text-align: center; text-decoration: underline; margin: 20px 0;">MEDICAL CERTIFICATE</h5>
                        
                        <div style="text-align: right; margin-bottom: 20px;">
                            <strong>Date:</strong> <input type="date" id="mc_date" value="<?php echo date('Y-m-d'); ?>" style="border: none; border-bottom: 1px solid #000; width: 150px;">
                        </div>
                        
                        <div style="text-align: justify;">
                            <p>
                                This is to certify that Ms./Mr./Mrs. 
                                <span id="mc_patient_name" class="patient-name-field" style="border-bottom: 1px solid #000; min-width: 200px; display: inline-block; padding: 2px 5px;">
                                    <?php echo $patient_details ? htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']) : '____________________'; ?>
                                </span>, 
                                <input type="number" id="mc_age" style="border: none; border-bottom: 1px solid #000; width: 50px;" 
                                       value="<?php 
                                       if ($patient_details) {
                                           $birthDate = new DateTime($patient_details['date_of_birth']);
                                           $today = new DateTime();
                                           echo $today->diff($birthDate)->y;
                                       }
                                       ?>">
                                years old, 
                                <select id="mc_gender" style="border: none; border-bottom: 1px solid #000; width: 80px;">
                                    <option value="">-</option>
                                    <option value="male" <?php echo ($patient_details && $patient_details['sex'] == 'male') ? 'selected' : ''; ?>>male</option>
                                    <option value="female" <?php echo ($patient_details && $patient_details['sex'] == 'female') ? 'selected' : ''; ?>>female</option>
                                </select>
                                of <input type="text" id="mc_address" class="address-field" style="border: none; border-bottom: 1px solid #000; min-width: 200px; max-width: 100%; word-wrap: break-word; white-space: normal;" 
                                         value="<?php echo $patient_details ? htmlspecialchars($patient_details['address']) : ''; ?>">
                                was seen, examined, and treated on 
                                <input type="date" id="mc_treatment_date" style="border: none; border-bottom: 1px solid #000; width: 120px;">
                                / admitted on 
                                <input type="date" id="mc_admission_date" style="border: none; border-bottom: 1px solid #000; width: 120px;">
                                to 
                                <input type="date" id="mc_discharge_date" style="border: none; border-bottom: 1px solid #000; width: 120px;">,  
                                with chief complaint of 
                                <input type="text" id="mc_complaint" style="border: none; border-bottom: 1px solid #000; width: 250px;">
                                and working diagnosis of 
                                <input type="text" id="mc_diagnosis" style="border: none; border-bottom: 1px solid #000; width: 250px;">.
                            </p>
                        </div>
                        
                        <div style="margin: 30px 0;">
                            <p><strong>REMARKS:</strong></p>
                            <textarea id="mc_remarks" style="border: 1px solid #000; width: 100%; height: 80px; padding: 10px; font-family: inherit;"></textarea>
                        </div>
                        
                        <p style="margin-top: 30px;">
                            This certification is issued upon the request of the patient for whatever purpose it may serve except medicolegal services.
                        </p>
                        
                        <div style="margin-top: 60px; text-align: right;">
                            <div class="signature-area"></div>
                            <div style="text-align: center; margin-top: 5px;">
                                Physician's Signature over Printed Name
                            </div>
                            <div style="margin-top: 15px;">
                                <strong>License No.</strong> <input type="text" id="mc_license" style="border: none; border-bottom: 1px solid #000; width: 150px;">
                            </div>
                            <div style="margin-top: 10px;">
                                <strong>PTR. No.</strong> <input type="text" id="mc_ptr" style="border: none; border-bottom: 1px solid #000; width: 150px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laboratory Request Form -->
        <div id="laboratory-request" class="form-container" style="display: none;">
            <div class="card border-success">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center no-print">
                    <h6 class="mb-0">Laboratory Request</h6>
                    <button class="btn btn-light btn-sm" onclick="printForm('laboratory-request-content')">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
                <div class="card-body">
                    <div id="laboratory-request-content" class="medical-form">
                        <div class="form-header">
                            <div class="clinic-logo-container">
                                <img src="img/logo.png" alt="Clinic Logo" class="clinic-logo-img">
                            </div>
                            <h4>MHAVIS MEDICAL & DIAGNOSTIC CENTER</h4>
                            <p>DE OCAMPO ST. POB 3 INDANG CAVITE</p>
                            <p>TEL NO. (046) 415-1396</p>
                        </div>
                        
                        <h5 style="text-align: center; text-decoration: underline; margin: 20px 0;">LABORATORY REQUEST</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Name:</strong></label>
                                <span id="lr_name" class="patient-name-field form-control" style="display: inline-block; border-bottom: 1px solid #000; padding: 5px; background: transparent;">
                                    <?php echo $patient_details ? htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']) : '____________________'; ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Date:</strong></label>
                                <input type="date" class="form-control" id="lr_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label><strong>Address:</strong></label>
                                <input type="text" class="form-control address-field" id="lr_address" 
                                       value="<?php echo $patient_details ? htmlspecialchars($patient_details['address']) : ''; ?>"
                                       style="word-wrap: break-word; white-space: normal; overflow-wrap: break-word;">
                            </div>
                            <div class="col-md-4">
                                <label><strong>Age/Sex:</strong></label>
                                <input type="text" class="form-control" id="lr_age_sex" 
                                       value="<?php 
                                       if ($patient_details) {
                                           $birthDate = new DateTime($patient_details['date_of_birth']);
                                           $today = new DateTime();
                                           $age = $today->diff($birthDate)->y;
                                           echo $age . '/' . ucfirst($patient_details['sex']);
                                       }
                                       ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Contact no:</strong></label>
                                <input type="text" class="form-control" id="lr_contact" 
                                       value="<?php echo $patient_details ? htmlspecialchars(phoneToInputFormat($patient_details['phone'])) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label><strong>Date of Birth:</strong></label>
                                <input type="date" class="form-control" id="lr_dob" 
                                       value="<?php echo $patient_details ? $patient_details['date_of_birth'] : ''; ?>">
                            </div>
                        </div>
                        
                        <h6><strong>ROUTINE TESTS</strong></h6>
                        <div class="checkbox-grid">
                            <div class="checkbox-item"><input type="checkbox" id="cbc"> <label for="cbc">CBC PC</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="urinalysis"> <label for="urinalysis">URINALYSIS</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="fecalysis"> <label for="fecalysis">FECALYSIS</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="ctbt"> <label for="ctbt">CT/BT</label></div>
                        </div>
                        
                        <h6><strong>BLOOD CHEMISTRY</strong></h6>
                        <div class="checkbox-grid">
                            <div class="checkbox-item"><input type="checkbox" id="fbs"> <label for="fbs">FBS</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="bun"> <label for="bun">BUN</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="creatinine"> <label for="creatinine">CREATININE</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="uric_acid"> <label for="uric_acid">URIC ACID</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="cholesterol"> <label for="cholesterol">CHOLESTEROL</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="triglyceride"> <label for="triglyceride">TRIGLYCERIDE</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="hdl_ldl"> <label for="hdl_ldl">HDL/LDL</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="sgpt"> <label for="sgpt">SGPT</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="sgot"> <label for="sgot">SGOT</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="sodium"> <label for="sodium">SODIUM</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="potassium"> <label for="potassium">POTASSIUM</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="chloride"> <label for="chloride">CHLORIDE</label></div>
                        </div>
                        
                        <div class="mb-3">
                            <h6><strong>PACKAGES</strong></h6>
                            <div class="checkbox-item"><input type="checkbox" id="kalusugan"> <label for="kalusugan">KALUSUGAN PACKAGE 2022</label></div>
                            <div class="checkbox-item"><input type="checkbox" id="buntis"> <label for="buntis">BUNTIS PACKAGE (1500)</label></div>
                        </div>
                        
                        <div class="mb-3">
                            <label><strong>Others:</strong></label>
                            <textarea class="form-control" rows="3" id="lr_others" placeholder="Specify other tests..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label><strong>TOTAL AMOUNT:</strong></label>
                            <input type="text" class="form-control" id="lr_total" style="width: 200px;" placeholder="₱0.00">
                        </div>
                        
                        <div style="margin-top: 40px;">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Requesting Physician:</strong></p>
                                    <div class="signature-area"></div>
                                    <p style="text-align: center;">Signature over Printed Name</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>License No.:</strong> ____________________</p>
                                    <p><strong>Date:</strong> <?php echo date('M d, Y'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- General Request Form -->
        <div id="request-form" class="form-container" style="display: none;">
            <div class="card border-info">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center no-print">
                    <h6 class="mb-0">General Request Form</h6>
                    <button class="btn btn-light btn-sm" onclick="printForm('request-form-content')">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
                <div class="card-body">
                    <div id="request-form-content" class="medical-form">
                        <div class="form-header">
                            <div class="clinic-logo-container">
                                <img src="img/logo.png" alt="Clinic Logo" class="clinic-logo-img">
                            </div>
                            <h4>MHAVIS MEDICAL & DIAGNOSTIC CENTER</h4>
                            <p>De Ocampo St., Pob 3 Indang Cavite</p>
                            <p>(046) 415-1396</p>
                        </div>
                        
                        <h5 style="text-align: center; text-decoration: underline; margin: 20px 0;">REQUEST FORM</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Name:</strong></label>
                                <span id="rf_name" class="patient-name-field form-control" style="display: inline-block; border-bottom: 1px solid #000; padding: 5px; background: transparent;">
                                    <?php echo $patient_details ? htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']) : '____________________'; ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Date:</strong></label>
                                <input type="date" class="form-control" id="rf_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label><strong>Address:</strong></label>
                                <input type="text" class="form-control address-field" id="rf_address" 
                                       value="<?php echo $patient_details ? htmlspecialchars($patient_details['address']) : ''; ?>"
                                       style="word-wrap: break-word; white-space: normal; overflow-wrap: break-word;">
                            </div>
                            <div class="col-md-4">
                                <label><strong>Age/Sex:</strong></label>
                                <input type="text" class="form-control" id="rf_age_sex" 
                                       value="<?php 
                                       if ($patient_details) {
                                           $birthDate = new DateTime($patient_details['date_of_birth']);
                                           $today = new DateTime();
                                           $age = $today->diff($birthDate)->y;
                                           echo $age . '/' . ucfirst($patient_details['sex']);
                                       }
                                       ?>">
                            </div>
                        </div>
                        
                        <h6><strong>REQUEST TYPE</strong></h6>
                        <div class="mb-3">
                            <div class="checkbox-item">
                                <input type="checkbox" id="rf_xray">
                                <label for="rf_xray"><strong>X-RAY</strong></label>
                            </div>
                            <input type="text" class="form-control mt-1" placeholder="X-Ray details..." id="rf_xray_details">
                        </div>
                        
                        <div class="mb-3">
                            <div class="checkbox-item">
                                <input type="checkbox" id="rf_ultrasound">
                                <label for="rf_ultrasound"><strong>ULTRASOUND</strong></label>
                            </div>
                            <input type="text" class="form-control mt-1" placeholder="Ultrasound details..." id="rf_ultrasound_details">
                        </div>
                        
                        <div class="mb-3">
                            <div class="checkbox-item">
                                <input type="checkbox" id="rf_ctscan">
                                <label for="rf_ctscan"><strong>CT SCAN</strong></label>
                            </div>
                            <input type="text" class="form-control mt-1" placeholder="CT Scan details..." id="rf_ctscan_details">
                        </div>
                        
                        <div class="mb-3">
                            <div class="checkbox-item">
                                <input type="checkbox" id="rf_mri">
                                <label for="rf_mri"><strong>MRI</strong></label>
                            </div>
                            <input type="text" class="form-control mt-1" placeholder="MRI details..." id="rf_mri_details">
                        </div>
                        
                        <div class="mb-3">
                            <div class="checkbox-item">
                                <input type="checkbox" id="rf_ecg">
                                <label for="rf_ecg"><strong>ECG</strong></label>
                            </div>
                            <input type="text" class="form-control mt-1" placeholder="ECG details..." id="rf_ecg_details">
                        </div>
                        
                        <div class="mb-3">
                            <label><strong>Working Diagnosis:</strong></label>
                            <textarea class="form-control" rows="3" id="rf_diagnosis" placeholder="Enter working diagnosis..."></textarea>
                        </div>
                        
                        <div style="margin-top: 40px;">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Requesting Physician:</strong></p>
                                    <div class="signature-area"></div>
                                    <p style="text-align: center;">Signature over Printed Name</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>License No.:</strong> ____________________</p>
                                    <p><strong>Date:</strong> <?php echo date('M d, Y'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- E.R. Record Sheet -->
        <div id="er-record" class="form-container" style="display: none;">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center no-print">
                    <h6 class="mb-0">E.R. Record Sheet</h6>
                    <button class="btn btn-dark btn-sm" onclick="printForm('er-record-content')">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
                <div class="card-body">
                    <div id="er-record-content" class="medical-form">
                        <div class="form-header">
                            <div class="clinic-logo-container">
                                <img src="img/logo.png" alt="Clinic Logo" class="clinic-logo-img">
                            </div>
                            <h4>MHAVIS MEDICAL CENTER</h4>
                            <p>Poblacion 3 Indang Cavite</p>
                            <p>(046) 415-1396</p>
                        </div>
                        
                        <h5 style="text-align: center; text-decoration: underline; margin: 20px 0;">E.R. RECORD SHEET</h5>
                        
                        <div style="border: 2px solid #000; padding: 15px; margin-bottom: 20px;">
                            <h6><strong>PATIENT'S DATA</strong></h6>
                            <div class="row mb-2">
                                <div class="col-md-2">
                                    <label style="margin-bottom: 5px;"><strong>Patient Type:</strong></label>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="display: flex; align-items: center;">
                                            <input type="radio" name="er_patient_type" id="er_new" value="new" style="margin-right: 5px;">
                                            <label for="er_new" style="margin: 0;">NEW</label>
                                        </div>
                                        <div style="display: flex; align-items: center;">
                                            <input type="radio" name="er_patient_type" id="er_old" value="old" style="margin-right: 5px;">
                                            <label for="er_old" style="margin: 0;">OLD</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label><strong>FIRST NAME:</strong></label>
                                    <span id="er_first_name" class="patient-name-field form-control" style="display: inline-block; border-bottom: 1px solid #000; padding: 5px; background: transparent;">
                                        <?php echo $patient_details ? htmlspecialchars($patient_details['first_name']) : '____________________'; ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <label><strong>LAST NAME:</strong></label>
                                    <span id="er_last_name" class="patient-name-field form-control" style="display: inline-block; border-bottom: 1px solid #000; padding: 5px; background: transparent;">
                                        <?php echo $patient_details ? htmlspecialchars($patient_details['last_name']) : '____________________'; ?>
                                    </span>
                                </div>
                                <div class="col-md-2">
                                    <label><strong>AGE:</strong></label>
                                    <input type="number" class="form-control" id="er_age" 
                                           value="<?php 
                                           if ($patient_details) {
                                               $birthDate = new DateTime($patient_details['date_of_birth']);
                                               $today = new DateTime();
                                               echo $today->diff($birthDate)->y;
                                           }
                                           ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <label><strong>CIVIL STATUS:</strong></label>
                                    <select class="form-control" id="er_civil_status">
                                        <option value="">Select</option>
                                        <option value="single">Single</option>
                                        <option value="married">Married</option>
                                        <option value="widowed">Widowed</option>
                                        <option value="separated">Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label><strong>GENDER:</strong></label>
                                    <select class="form-control" id="er_gender">
                                        <option value="">Select</option>
                                        <option value="male" <?php echo ($patient_details && $patient_details['sex'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($patient_details && $patient_details['sex'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label><strong>NATIONALITY:</strong></label>
                                    <input type="text" class="form-control" id="er_nationality" value="Filipino">
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-md-12">
                                    <label><strong>ADDRESS:</strong></label>
                                    <input type="text" class="form-control address-field" id="er_address" 
                                           value="<?php echo $patient_details ? htmlspecialchars($patient_details['address']) : ''; ?>"
                                           style="word-wrap: break-word; white-space: normal; overflow-wrap: break-word;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>OCCUPATION:</strong></label>
                                <input type="text" class="form-control" id="er_occupation">
                            </div>
                            <div class="col-md-6">
                                <label><strong>TELEPHONE NO.:</strong></label>
                                <input type="text" class="form-control" id="er_telephone" 
                                       value="<?php echo $patient_details ? htmlspecialchars(phoneToInputFormat($patient_details['phone'])) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label><strong>EMAIL ADDRESS:</strong></label>
                            <input type="email" class="form-control" id="er_email" 
                                   value="<?php echo $patient_details ? htmlspecialchars($patient_details['email']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label><strong>REFERRING HEALTH FACILITY:</strong></label>
                            <input type="text" class="form-control" id="er_referring_facility">
                        </div>
                        
                        <div class="mb-3">
                            <label><strong>REASON FOR REFERRAL:</strong></label>
                            <textarea class="form-control" rows="2" id="er_referral_reason"></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label><strong>CHIEF COMPLAINT:</strong></label>
                            <textarea class="form-control" rows="2" id="er_chief_complaint"></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label><strong>ASSESSMENT/DIAGNOSIS:</strong></label>
                            <textarea class="form-control" rows="3" id="er_assessment"></textarea>
                        </div>
                        
                        <h6 class="mt-4"><strong>AUTHORIZATION OF EMERGENCY TREATMENT</strong></h6>
                        <p>Authorization is hereby granted for appropriate emergency treatment and procedures by the physician and staff of Mhavis Medical Center.</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <label><strong>Patient's Signature:</strong></label>
                                <div class="signature-area"></div>
                                <p style="text-align: center;">Patient/Guardian Signature</p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Physician's Signature:</strong></label>
                                <div class="signature-area"></div>
                                <p style="text-align: center;">Attending Physician</p>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p><strong>Date:</strong> <?php echo date('M d, Y'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Time:</strong> <input type="time" id="er_time" style="border: none; border-bottom: 1px solid #000;"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showForm(formId) {
    // Hide all forms first
    const forms = document.querySelectorAll('.form-container');
    forms.forEach(form => {
        form.style.display = 'none';
    });
    
    // Show selected form
    const selectedForm = document.getElementById(formId);
    if (selectedForm) {
        selectedForm.style.display = 'block';
        
        // Auto-populate patient data if available
        populatePatientData(formId);
    }
}

function populatePatientData(formId) {
    // This function can be extended to populate patient data
    // For now, it just ensures current date/time is set
    const today = new Date();
    const currentDate = today.toISOString().split('T')[0];
    const currentTime = today.toTimeString().split(' ')[0].substring(0, 5);
    
    // Set current date for all date inputs in the active form
    const activeForm = document.getElementById(formId);
    if (activeForm) {
        const dateInputs = activeForm.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            if (!input.value && input.id !== 'mc_treatment_date' && input.id !== 'mc_admission_date' && input.id !== 'mc_discharge_date') {
                input.value = currentDate;
            }
        });
        
        // Set current time for time inputs
        const timeInputs = activeForm.querySelectorAll('input[type="time"]');
        timeInputs.forEach(input => {
            if (!input.value) {
                input.value = currentTime;
            }
        });
        
        // Ensure patient name fields remain non-editable
        const patientNameFields = activeForm.querySelectorAll('.patient-name-field');
        patientNameFields.forEach(field => {
            field.setAttribute('contenteditable', 'false');
            field.style.userSelect = 'none';
            field.style.webkitUserSelect = 'none';
            field.style.mozUserSelect = 'none';
            field.style.msUserSelect = 'none';
            field.style.pointerEvents = 'none';
            field.style.cursor = 'default';
        });
    }
}

function printForm(contentId) {
    const content = document.getElementById(contentId);
    if (!content) {
        showAlert('Form content not found!', 'Error', 'error');
        return;
    }
    
    // Get form title for the document
    let formTitle = 'Medical Form';
    switch(contentId) {
        case 'medical-certificate-content':
            formTitle = 'Medical Certificate';
            break;
        case 'laboratory-request-content':
            formTitle = 'Laboratory Request';
            break;
        case 'request-form-content':
            formTitle = 'General Request Form';
            break;
        case 'er-record-content':
            formTitle = 'E.R. Record Sheet';
            break;
    }
    
    // Capture all form values from the original content before cloning
    const formValues = {};
    
    // Capture input values (text, date, number, etc.)
    const inputs = content.querySelectorAll('input');
    inputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
            formValues[input.id] = input.checked;
        } else {
            formValues[input.id] = input.value;
        }
    });
    
    // Capture select values
    const selects = content.querySelectorAll('select');
    selects.forEach(select => {
        formValues[select.id] = select.value;
    });
    
    // Capture textarea values
    const textareas = content.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        formValues[textarea.id] = textarea.value;
    });
    
    // Clone the content to preserve original
    const contentClone = content.cloneNode(true);
    
    // Helper function to format date for display
    function formatDateForDisplay(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const day = String(date.getDate()).padStart(2, '0');
            const month = months[date.getMonth()];
            const year = date.getFullYear();
            return `${month} ${day}, ${year}`;
        } catch (e) {
            return dateString;
        }
    }
    
    // Apply captured values to cloned elements
    Object.keys(formValues).forEach(id => {
        const clonedElement = contentClone.querySelector('#' + id);
        if (clonedElement && formValues[id] !== null && formValues[id] !== undefined && formValues[id] !== '') {
            if (clonedElement.tagName === 'INPUT') {
                if (clonedElement.type === 'checkbox' || clonedElement.type === 'radio') {
                    clonedElement.checked = formValues[id];
                    // Set attribute for print CSS
                    if (formValues[id]) {
                        clonedElement.setAttribute('checked', 'checked');
                    }
                } else if (clonedElement.type === 'date') {
                    clonedElement.value = formValues[id];
                    // Also add a display value attribute for better formatting
                    clonedElement.setAttribute('data-display-value', formatDateForDisplay(formValues[id]));
                } else {
                    clonedElement.value = formValues[id];
                }
            } else if (clonedElement.tagName === 'SELECT') {
                clonedElement.value = formValues[id];
                // Update all options
                const options = clonedElement.querySelectorAll('option');
                options.forEach(opt => {
                    opt.selected = (opt.value === formValues[id]);
                });
            } else if (clonedElement.tagName === 'TEXTAREA') {
                clonedElement.value = formValues[id];
                clonedElement.textContent = formValues[id];
                clonedElement.innerHTML = formValues[id].replace(/\n/g, '<br>');
            }
        }
    });
    
    // Define proceedWithPrint function first
    function proceedWithPrint() {
        // Serialize formValues safely
        const formValuesJson = JSON.stringify(formValues);
        
        // Get HTML content safely
        const htmlContent = contentClone.innerHTML;
        
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        
        // Write document structure
        printWindow.document.write('<!DOCTYPE html><html><head><title>' + escapeHtml(formTitle) + '</title><style>');
        printWindow.document.write('* { box-sizing: border-box; }');
        printWindow.document.write('body { font-family: "Times New Roman", serif; margin: 0 auto; padding: 10px 15px; font-size: 10pt; line-height: 1.3; max-width: 100%; }');
        printWindow.document.write('.medical-form { border: 2px solid #000; margin: 0 auto; padding: 10px 15px; max-width: 100%; page-break-inside: avoid; }');
        printWindow.document.write('.text-center { text-align: center !important; }');
        printWindow.document.write('.form-header { text-align: center !important; margin-bottom: 8px !important; padding-bottom: 8px !important; border-bottom: 2px solid #000; }');
        printWindow.document.write('.form-header h4 { margin: 5px 0 !important; font-size: 16pt !important; }');
        printWindow.document.write('.form-header p { margin: 2px 0 !important; font-size: 10pt !important; }');
        printWindow.document.write('h5, h6 { text-align: center !important; margin: 8px 0 !important; font-size: 12pt !important; }');
        printWindow.document.write('.mb-4 { margin-bottom: 8px !important; }');
        printWindow.document.write('.mb-3 { margin-bottom: 8px !important; }');
        printWindow.document.write('.mb-2 { margin-bottom: 6px !important; }');
        printWindow.document.write('.mt-1 { margin-top: 4px !important; }');
        printWindow.document.write('.mt-3 { margin-top: 8px !important; }');
        printWindow.document.write('.mt-4 { margin-top: 8px !important; }');
        printWindow.document.write('.border-bottom { border-bottom: 1px solid #000; }');
        printWindow.document.write('.signature-area { border-bottom: 1px solid #000; height: 35px !important; margin: 5px 0 !important; }');
        printWindow.document.write('.checkbox-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 5px !important; margin: 8px 0 !important; }');
        printWindow.document.write('.checkbox-item { display: flex; align-items: center; margin-bottom: 4px !important; font-size: 9pt !important; }');
        printWindow.document.write('input, select, textarea { border: none; border-bottom: 1px solid #000; background: transparent; padding: 1px 3px !important; font-family: inherit; font-size: 10pt !important; }');
        printWindow.document.write('input[type="checkbox"], input[type="radio"] { border: 1px solid #000; margin-right: 5px; width: 15px; height: 15px; -webkit-appearance: none; -moz-appearance: none; appearance: none; position: relative; }');
        printWindow.document.write('input[type="checkbox"]:checked::after { content: "✓"; position: absolute; left: 2px; top: -2px; font-size: 14px; font-weight: bold; color: #000; }');
        printWindow.document.write('input[type="radio"]:checked::after { content: "●"; position: absolute; left: 3px; top: -1px; font-size: 10px; color: #000; }');
        printWindow.document.write('select { background: transparent; }');
        printWindow.document.write('select option:checked { background: transparent; }');
        printWindow.document.write('label { font-size: 10pt !important; margin-bottom: 2px !important; display: block; }');
        printWindow.document.write('.form-control { width: 100%; border: none; border-bottom: 1px solid #000; padding: 1px 3px !important; background: transparent; font-size: 10pt !important; }');
        printWindow.document.write('.row { display: flex; margin: 0 !important; flex-wrap: wrap; }');
        printWindow.document.write('.col-md-1 { flex: 0 0 8.333%; padding: 0 8px; }');
        printWindow.document.write('.col-md-2 { flex: 0 0 16.666%; padding: 0 8px; }');
        printWindow.document.write('.col-md-3 { flex: 0 0 25%; padding: 0 8px; }');
        printWindow.document.write('.col-md-4 { flex: 0 0 33.333%; padding: 0 8px; }');
        printWindow.document.write('.col-md-5 { flex: 0 0 41.666%; padding: 0 8px; }');
        printWindow.document.write('.col-md-6 { flex: 0 0 50%; padding: 0 8px; }');
        printWindow.document.write('.col-md-8 { flex: 0 0 66.666%; padding: 0 8px; }');
        printWindow.document.write('.col-md-9 { flex: 0 0 75%; padding: 0 8px; }');
        printWindow.document.write('.col-md-12 { flex: 0 0 100%; padding: 0 8px; }');
        printWindow.document.write('.clinic-logo-container { margin-bottom: 5px !important; text-align: center; }');
        printWindow.document.write('.clinic-logo-img { max-width: 60px !important; max-height: 60px !important; height: auto; width: auto; object-fit: contain; }');
        printWindow.document.write('.address-field { word-wrap: break-word !important; white-space: normal !important; overflow-wrap: break-word !important; word-break: break-word !important; overflow: visible !important; text-overflow: clip !important; max-width: 100% !important; display: block !important; width: 100% !important; }');
        printWindow.document.write('.patient-name-field { border-bottom: 1px solid #000; display: inline-block; padding: 1px 3px !important; font-size: 10pt !important; }');
        printWindow.document.write('textarea { font-size: 9pt !important; padding: 3px !important; line-height: 1.3 !important; }');
        printWindow.document.write('p { margin: 4px 0 !important; font-size: 10pt !important; }');
        printWindow.document.write('input[type="radio"] { margin-right: 5px !important; vertical-align: middle !important; }');
        printWindow.document.write('label[for^="er_new"], label[for^="er_old"] { margin: 0 !important; vertical-align: middle !important; display: inline !important; }');
        printWindow.document.write('.col-md-2 label { display: block !important; margin-bottom: 5px !important; }');
        printWindow.document.write('@page { margin: 0.3in !important; size: A4; }');
        printWindow.document.write('</style></head><body>');
        
        // Write HTML content
        printWindow.document.write(htmlContent);
        
        // Write script to set form values (split script tag to avoid closing current script)
        printWindow.document.write('<' + 'script>');
        printWindow.document.write('(function() {');
        printWindow.document.write('var formValues = ' + formValuesJson + ';');
        printWindow.document.write('Object.keys(formValues).forEach(function(id) {');
        printWindow.document.write('var element = document.getElementById(id);');
        printWindow.document.write('if (element && formValues[id] !== null && formValues[id] !== undefined && formValues[id] !== "") {');
        printWindow.document.write('if (element.tagName === "INPUT") {');
        printWindow.document.write('if (element.type === "checkbox" || element.type === "radio") {');
        printWindow.document.write('element.checked = formValues[id];');
        printWindow.document.write('if (formValues[id]) { element.setAttribute("checked", "checked"); }');
        printWindow.document.write('} else {');
        printWindow.document.write('element.value = formValues[id];');
        printWindow.document.write('if (element.type === "date" && formValues[id]) {');
        printWindow.document.write('try {');
        printWindow.document.write('var dateStr = formValues[id];');
        printWindow.document.write('var date = new Date(dateStr + "T00:00:00");');
        printWindow.document.write('if (!isNaN(date.getTime())) {');
        printWindow.document.write('var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];');
        printWindow.document.write('var day = String(date.getDate()).padStart(2, "0");');
        printWindow.document.write('var month = months[date.getMonth()];');
        printWindow.document.write('var year = date.getFullYear();');
        printWindow.document.write('var formattedDate = month + " " + day + ", " + year;');
        printWindow.document.write('var displaySpan = document.createElement("span");');
        printWindow.document.write('displaySpan.textContent = formattedDate;');
        printWindow.document.write('displaySpan.style.borderBottom = "1px solid #000";');
        printWindow.document.write('displaySpan.style.padding = "2px 5px";');
        printWindow.document.write('displaySpan.style.display = "inline-block";');
        printWindow.document.write('displaySpan.style.minWidth = "150px";');
        printWindow.document.write('element.parentNode.replaceChild(displaySpan, element);');
        printWindow.document.write('}');
        printWindow.document.write('} catch (e) { element.setAttribute("value", formValues[id]); }');
        printWindow.document.write('}');
        printWindow.document.write('}');
        printWindow.document.write('} else if (element.tagName === "SELECT") {');
        printWindow.document.write('element.value = formValues[id];');
        printWindow.document.write('var options = element.querySelectorAll("option");');
        printWindow.document.write('options.forEach(function(opt) { opt.selected = (opt.value === formValues[id]); });');
        printWindow.document.write('} else if (element.tagName === "TEXTAREA") {');
        printWindow.document.write('element.value = formValues[id];');
        printWindow.document.write('element.textContent = formValues[id];');
        printWindow.document.write('}');
        printWindow.document.write('}');
        printWindow.document.write('});');
        printWindow.document.write('})();');
        printWindow.document.write('<' + '/script>');
        printWindow.document.write('</body></html>');
        
        printWindow.document.close();
        
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    }
    
    // Convert logo images to base64 data URLs for reliable printing
    const logoImages = contentClone.querySelectorAll('.clinic-logo-img');
    const logoPromises = Array.from(logoImages).map(img => {
        return new Promise((resolve) => {
            const currentSrc = img.getAttribute('src');
            if (!currentSrc || currentSrc.startsWith('data:')) {
                resolve();
                return;
            }
            
            // Create a new image to load and convert
            const tempImg = new Image();
            tempImg.crossOrigin = 'anonymous';
            
            // Convert relative path to absolute if needed
            let imgSrc = currentSrc;
            if (!imgSrc.startsWith('http') && !imgSrc.startsWith('data:')) {
                const basePath = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                imgSrc = basePath + '/' + imgSrc;
            }
            
            tempImg.onload = function() {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = this.width;
                    canvas.height = this.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(this, 0, 0);
                    const dataURL = canvas.toDataURL('image/png');
                    img.src = dataURL;
                    resolve();
                } catch (e) {
                    // If canvas conversion fails, use absolute URL
                    img.src = imgSrc;
                    resolve();
                }
            };
            
            tempImg.onerror = function() {
                // If image fails to load, keep original src
                img.src = imgSrc;
                resolve();
            };
            
            tempImg.src = imgSrc;
        });
    });
    
    // Wait for all logos to be converted before printing, or proceed immediately if no logos
    if (logoPromises.length === 0) {
        proceedWithPrint();
    } else {
        Promise.all(logoPromises).then(() => {
            proceedWithPrint();
        }).catch(() => {
            // If promise fails, still proceed with print
            proceedWithPrint();
        });
    }
}

// Auto-populate forms when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Set current date and time
    const today = new Date();
    const currentDate = today.toISOString().split('T')[0];
    const currentTime = today.toTimeString().split(' ')[0].substring(0, 5);
    
    // Set default values for date inputs
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value && input.id !== 'mc_treatment_date' && input.id !== 'mc_admission_date' && input.id !== 'mc_discharge_date') {
            input.value = currentDate;
        }
    });
    
    // Set default values for time inputs
    const timeInputs = document.querySelectorAll('input[type="time"]');
    timeInputs.forEach(input => {
        if (!input.value) {
            input.value = currentTime;
        }
    });
    
    // Make all patient name fields non-editable
    const patientNameFields = document.querySelectorAll('.patient-name-field');
    patientNameFields.forEach(field => {
        field.setAttribute('contenteditable', 'false');
        field.style.userSelect = 'none';
        field.style.webkitUserSelect = 'none';
        field.style.mozUserSelect = 'none';
        field.style.msUserSelect = 'none';
        field.style.pointerEvents = 'none';
        field.style.cursor = 'default';
        
        // Prevent any editing attempts
        field.addEventListener('mousedown', function(e) {
            e.preventDefault();
            return false;
        });
        
        field.addEventListener('keydown', function(e) {
            e.preventDefault();
            return false;
        });
        
        field.addEventListener('paste', function(e) {
            e.preventDefault();
            return false;
        });
    });
    
    // Ensure address fields wrap properly
    const addressFields = document.querySelectorAll('.address-field');
    addressFields.forEach(field => {
        field.style.wordWrap = 'break-word';
        field.style.whiteSpace = 'normal';
        field.style.overflowWrap = 'break-word';
        field.style.wordBreak = 'break-word';
    });
    
    // Add change handlers for checkboxes to enable/disable related fields
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const relatedField = document.getElementById(this.id + '_details');
            if (relatedField) {
                relatedField.disabled = !this.checked;
                if (!this.checked) {
                    relatedField.value = '';
                }
            }
        });
    });
});

// Function to clear all forms
function clearForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else if (input.type === 'date') {
                input.value = new Date().toISOString().split('T')[0];
            } else if (input.type === 'time') {
                input.value = new Date().toTimeString().split(' ')[0].substring(0, 5);
            } else {
                input.value = '';
            }
        });
    }
}

// Add clear button functionality if needed
function addClearButton(formId) {
    const form = document.getElementById(formId);
    if (form) {
        const clearBtn = document.createElement('button');
        clearBtn.className = 'btn btn-secondary btn-sm no-print';
        clearBtn.innerHTML = '<i class="fas fa-eraser"></i> Clear Form';
        clearBtn.onclick = () => clearForm(formId);
        
        const cardHeader = form.querySelector('.card-header');
        if (cardHeader) {
            cardHeader.appendChild(clearBtn);
        }
    }
}
</script>