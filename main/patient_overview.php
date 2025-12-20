<?php
// Patient Overview Tab
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}
?>


<div class="row">
    <!-- Personal Information Card -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless" style="table-layout: auto; width: 100%;">
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Full Name:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><?php 
                            $fullName = $patient_details['first_name'];
                            if (!empty($patient_details['middle_name'])) {
                                $fullName .= ' ' . $patient_details['middle_name'];
                            }
                            $fullName .= ' ' . $patient_details['last_name'];
                            echo htmlspecialchars($fullName);
                        ?></td>
                    </tr>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Date of Birth:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><?php echo date('F j, Y', strtotime($patient_details['date_of_birth'])); ?></td>
                    </tr>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Age:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;">
                            <?php
                            $birthDate = new DateTime($patient_details['date_of_birth']);
                            $today = new DateTime();
                            $age = $today->diff($birthDate)->y;
                            echo $age . ' years old';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Sex:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><?php echo htmlspecialchars($patient_details['sex']); ?></td>
                    </tr>
                    <?php if (!empty($patient_details['blood_type'])): ?>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Blood Type:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><strong class="text-danger"><?php echo htmlspecialchars($patient_details['blood_type']); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Phone:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><?php echo htmlspecialchars(formatPhoneNumber($patient_details['phone'])); ?></td>
                    </tr>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Email:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><?php echo htmlspecialchars($patient_details['email']); ?></td>
                    </tr>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem; vertical-align: top;"><strong>Address:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><?php echo htmlspecialchars($patient_details['address'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Patient Number:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><?php echo htmlspecialchars($patient_details['patient_number']); ?></td>
                    </tr>
                    <tr>
                        <td style="width: 30%; min-width: 150px; padding-right: 1rem;"><strong>Date Registered:</strong></td>
                        <td style="width: 70%; word-wrap: break-word;"><?php echo date('F j, Y', strtotime($patient_details['created_at'] ?? '')); ?></td>
                    </tr>
                    <?php if ($patient_details['is_senior_citizen'] || $patient_details['is_pwd']): ?>
                    <tr>
                        <td><strong>Special Status:</strong></td>
                        <td>
                            <?php if ($patient_details['is_senior_citizen']): ?>
                                <span class="badge bg-warning text-dark me-1">
                                    <i class="fas fa-user-clock"></i> Senior Citizen
                                    <?php if (!empty($patient_details['senior_citizen_id'])): ?>
                                        (ID: <?php echo htmlspecialchars($patient_details['senior_citizen_id']); ?>) 
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($patient_details['is_pwd']): ?>
                                <span class="badge bg-info text-white">
                                    <i class="fas fa-wheelchair"></i> PWD
                                    <?php if (!empty($patient_details['pwd_id'])): ?>
                                        (ID: <?php echo htmlspecialchars($patient_details['pwd_id']); ?>)
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Emergency Contact Card -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-phone me-2"></i>Emergency Contact</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($patient_details['emergency_contact_name']) || !empty($patient_details['emergency_contact_phone'])): ?>
                <table class="table table-borderless">
                    <?php if (!empty($patient_details['emergency_contact_name'])): ?>
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo htmlspecialchars($patient_details['emergency_contact_name']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($patient_details['emergency_contact_phone'])): ?>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo htmlspecialchars(formatPhoneNumber($patient_details['emergency_contact_phone'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($patient_details['relationship'])): ?>
                    <tr>
                        <td><strong>Relationship:</strong></td>
                        <td><?php echo htmlspecialchars($patient_details['relationship']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php else: ?>
                <p class="text-muted mb-0">No emergency contact information on file.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chief Complaint Card -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Chief Complaint</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($patient_details['chief_complaint'])): ?>
                <p class="mb-0"><?php echo htmlspecialchars($patient_details['chief_complaint']); ?></p>
                <?php else: ?>
                <p class="text-muted mb-0">No chief complaint recorded.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>