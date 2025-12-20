<?php
define('MHAVIS_EXEC', true);
$page_title = "Manage Doctor Departments";
$active_page = "doctors";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();

$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$success = '';
$error = '';

// Get doctor information
$doctor = null;
if ($doctor_id > 0) {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, specialization, email FROM users WHERE id = ? AND role = 'Doctor'");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $doctor = $result->fetch_assoc();
    } else {
        $error = "Doctor not found.";
        $doctor_id = 0;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor_id > 0) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_department') {
        $department_id = (int)$_POST['department_id'];
        $specialization = sanitize($_POST['specialization'] ?? '');
        $prc_number = sanitize($_POST['prc_number'] ?? '');
        $license_type = sanitize($_POST['license_type'] ?? '');
        $prc_id_document = null;
        
        // Check if department already assigned
        $stmt = $conn->prepare("SELECT id FROM doctor_departments WHERE doctor_id = ? AND department_id = ?");
        $stmt->bind_param("ii", $doctor_id, $department_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Doctor is already assigned to this department.";
        } else {
            // Handle PRC ID document upload
            if (isset($_FILES['prc_id_document']) && $_FILES['prc_id_document']['error'] === 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                $maxSize = 10 * 1024 * 1024; // 10MB
                
                if (in_array($_FILES['prc_id_document']['type'], $allowedTypes) && $_FILES['prc_id_document']['size'] <= $maxSize) {
                    $extension = pathinfo($_FILES['prc_id_document']['name'], PATHINFO_EXTENSION);
                    $filename = 'uploads/prc_dept_' . time() . '_' . uniqid() . '.' . $extension;
                    
                    if (!is_dir('uploads')) {
                        mkdir('uploads', 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['prc_id_document']['tmp_name'], $filename)) {
                        $prc_id_document = $filename;
                    }
                }
            }
            
            // Insert into doctor_departments
            $stmt = $conn->prepare("INSERT INTO doctor_departments (doctor_id, department_id, specialization, prc_number, license_type, prc_id_document) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $doctor_id, $department_id, $specialization, $prc_number, $license_type, $prc_id_document);
            
            if ($stmt->execute()) {
                $success = "Department assigned successfully.";
            } else {
                $error = "Error assigning department: " . $stmt->error;
            }
        }
    } elseif ($action === 'update_department') {
        $dept_assignment_id = (int)$_POST['dept_assignment_id'];
        $specialization = sanitize($_POST['specialization'] ?? '');
        $prc_number = sanitize($_POST['prc_number'] ?? '');
        $license_type = sanitize($_POST['license_type'] ?? '');
        
        // Get existing PRC document
        $stmt = $conn->prepare("SELECT prc_id_document FROM doctor_departments WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $dept_assignment_id, $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $prc_id_document = $existing['prc_id_document'] ?? null;
        
        // Handle PRC ID document upload
        if (isset($_FILES['prc_id_document']) && $_FILES['prc_id_document']['error'] === 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            if (in_array($_FILES['prc_id_document']['type'], $allowedTypes) && $_FILES['prc_id_document']['size'] <= $maxSize) {
                $extension = pathinfo($_FILES['prc_id_document']['name'], PATHINFO_EXTENSION);
                $filename = 'uploads/prc_dept_' . time() . '_' . uniqid() . '.' . $extension;
                
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['prc_id_document']['tmp_name'], $filename)) {
                    // Delete old file if exists
                    if ($prc_id_document && file_exists($prc_id_document)) {
                        unlink($prc_id_document);
                    }
                    $prc_id_document = $filename;
                }
            }
        }
        
        // Update assignment
        $stmt = $conn->prepare("UPDATE doctor_departments SET specialization = ?, prc_number = ?, license_type = ?, prc_id_document = ? WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ssssii", $specialization, $prc_number, $license_type, $prc_id_document, $dept_assignment_id, $doctor_id);
        
        if ($stmt->execute()) {
            $success = "Department information updated successfully.";
        } else {
            $error = "Error updating department: " . $stmt->error;
        }
    } elseif ($action === 'remove_department') {
        $dept_assignment_id = (int)$_POST['dept_assignment_id'];
        
        // Get PRC document path before deletion
        $stmt = $conn->prepare("SELECT prc_id_document FROM doctor_departments WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $dept_assignment_id, $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $assignment = $result->fetch_assoc();
            $prc_doc = $assignment['prc_id_document'];
            
            // Delete the assignment
            $stmt = $conn->prepare("DELETE FROM doctor_departments WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("ii", $dept_assignment_id, $doctor_id);
            
            if ($stmt->execute()) {
                // Delete associated file
                if ($prc_doc && file_exists($prc_doc)) {
                    unlink($prc_doc);
                }
                $success = "Department removed successfully.";
            } else {
                $error = "Error removing department: " . $stmt->error;
            }
        }
    }
}

// Get all departments
$departments_result = $conn->query("SELECT id, name, description FROM departments ORDER BY name");

// Get doctor's current department assignments
$doctor_departments = [];
if ($doctor_id > 0) {
    $stmt = $conn->prepare("SELECT dd.*, d.name as department_name, d.description as department_description 
                            FROM doctor_departments dd 
                            INNER JOIN departments d ON dd.department_id = d.id 
                            WHERE dd.doctor_id = ? 
                            ORDER BY d.name");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $doctor_departments[] = $row;
    }
}

// Get all doctors for the dropdown
$doctors_result = $conn->query("SELECT id, first_name, last_name, specialization FROM users WHERE role = 'Doctor' ORDER BY first_name, last_name");

include 'includes/header.php';
?>

<div class="container-fluid">
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-user-md me-2"></i>Manage Doctor Departments</h4>
                <a href="doctors.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Doctors
                </a>
            </div>
        </div>
    </div>

    <!-- Doctor Selection -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Select Doctor</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Select a Doctor --</option>
                                <?php while ($doc = $doctors_result->fetch_assoc()): ?>
                                    <option value="<?php echo $doc['id']; ?>" <?php echo ($doctor_id == $doc['id']) ? 'selected' : ''; ?>>
                                        Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                                        <?php if ($doc['specialization']): ?>
                                            - <?php echo htmlspecialchars($doc['specialization']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Load Doctor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($doctor_id > 0 && $doctor): ?>
        <!-- Doctor Information -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-md"></i> 
                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php if ($doctor['specialization']): ?>
                                    <p><strong>Primary Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Department Assignments -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Current Department Assignments</h5>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                            <i class="fas fa-plus"></i> Add Department
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($doctor_departments)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-hospital fa-3x mb-3"></i>
                                <p>No departments assigned yet.</p>
                                <p class="small">Click "Add Department" to assign this doctor to a department.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Specialization</th>
                                            <th>PRC Number</th>
                                            <th>License Type</th>
                                            <th>PRC Document</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($doctor_departments as $dept): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                                    <?php if ($dept['department_description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($dept['department_description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($dept['specialization'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($dept['prc_number'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($dept['license_type'] ?: '-'); ?></td>
                                                <td>
                                                    <?php if ($dept['prc_id_document']): ?>
                                                        <?php 
                                                        $fileExt = strtolower(pathinfo($dept['prc_id_document'], PATHINFO_EXTENSION));
                                                        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])): 
                                                        ?>
                                                            <img src="<?php echo htmlspecialchars($dept['prc_id_document']); ?>" 
                                                                 alt="PRC Document" 
                                                                 class="img-thumbnail" 
                                                                 style="max-width: 50px; max-height: 50px; cursor: pointer;"
                                                                 onclick="viewDocument('<?php echo htmlspecialchars($dept['prc_id_document']); ?>')">
                                                        <?php else: ?>
                                                            <a href="<?php echo htmlspecialchars($dept['prc_id_document']); ?>" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-file-pdf"></i> View PDF
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-warning" 
                                                            onclick="editDepartment(<?php echo htmlspecialchars(json_encode($dept)); ?>)"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this department assignment?');">
                                                        <input type="hidden" name="action" value="remove_department">
                                                        <input type="hidden" name="dept_assignment_id" value="<?php echo $dept['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Remove">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Please select a doctor to manage their department assignments.
        </div>
    <?php endif; ?>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDepartmentModalLabel">Add Department Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_department">
                    
                    <div class="mb-3">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-select" required>
                            <option value="">-- Select Department --</option>
                            <?php 
                            $departments_result->data_seek(0); // Reset pointer
                            while ($dept = $departments_result->fetch_assoc()): 
                                // Check if already assigned
                                $already_assigned = false;
                                foreach ($doctor_departments as $dd) {
                                    if ($dd['department_id'] == $dept['id']) {
                                        $already_assigned = true;
                                        break;
                                    }
                                }
                                if (!$already_assigned):
                            ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                    <?php if ($dept['description']): ?>
                                        - <?php echo htmlspecialchars($dept['description']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" class="form-control" 
                               placeholder="e.g., Cardiology, Internal Medicine">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PRC Number</label>
                        <input type="text" name="prc_number" class="form-control" 
                               placeholder="e.g., 0123456">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">License Type</label>
                        <select name="license_type" class="form-select">
                            <option value="">-- Select License Type --</option>
                            <option value="MD">Doctor of Medicine (MD)</option>
                            <option value="DMD">Doctor of Dental Medicine (DMD)</option>
                            <option value="DDS">Doctor of Dental Surgery (DDS)</option>
                            <option value="DVM">Doctor of Veterinary Medicine (DVM)</option>
                            <option value="RN">Registered Nurse (RN)</option>
                            <option value="RPh">Registered Pharmacist (RPh)</option>
                            <option value="RPT">Registered Physical Therapist (RPT)</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PRC ID / Government ID Document</label>
                        <input type="file" name="prc_id_document" class="form-control" accept="image/*,.pdf">
                        <small class="text-muted">Max file size: 10MB. Allowed formats: JPG, PNG, GIF, PDF</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDepartmentModalLabel">Edit Department Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_department">
                    <input type="hidden" name="dept_assignment_id" id="edit_dept_assignment_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" id="edit_department_name" class="form-control" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" id="edit_specialization" class="form-control" 
                               placeholder="e.g., Cardiology, Internal Medicine">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PRC Number</label>
                        <input type="text" name="prc_number" id="edit_prc_number" class="form-control" 
                               placeholder="e.g., 0123456">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">License Type</label>
                        <select name="license_type" id="edit_license_type" class="form-select">
                            <option value="">-- Select License Type --</option>
                            <option value="MD">Doctor of Medicine (MD)</option>
                            <option value="DMD">Doctor of Dental Medicine (DMD)</option>
                            <option value="DDS">Doctor of Dental Surgery (DDS)</option>
                            <option value="DVM">Doctor of Veterinary Medicine (DVM)</option>
                            <option value="RN">Registered Nurse (RN)</option>
                            <option value="RPh">Registered Pharmacist (RPh)</option>
                            <option value="RPT">Registered Physical Therapist (RPT)</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Current PRC ID Document</label>
                        <div id="edit_current_document" class="mb-2"></div>
                        <input type="file" name="prc_id_document" class="form-control" accept="image/*,.pdf">
                        <small class="text-muted">Leave blank to keep current document. Max file size: 10MB. Allowed formats: JPG, PNG, GIF, PDF</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Information</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document View Modal -->
<div class="modal fade" id="documentViewModal" tabindex="-1" aria-labelledby="documentViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentViewModalLabel">PRC ID Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center" id="documentViewContent">
                <img src="" alt="Document" class="img-fluid" id="documentImageView" style="display: none;">
                <iframe src="" id="documentPdfView" style="display: none; width: 100%; height: 600px;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
function editDepartment(dept) {
    document.getElementById('edit_dept_assignment_id').value = dept.id;
    document.getElementById('edit_department_name').value = dept.department_name;
    document.getElementById('edit_specialization').value = dept.specialization || '';
    document.getElementById('edit_prc_number').value = dept.prc_number || '';
    document.getElementById('edit_license_type').value = dept.license_type || '';
    
    // Show current document
    const currentDocDiv = document.getElementById('edit_current_document');
    if (dept.prc_id_document) {
        const fileExt = dept.prc_id_document.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
            currentDocDiv.innerHTML = `<img src="${dept.prc_id_document}" alt="Current Document" class="img-thumbnail" style="max-width: 200px;">`;
        } else {
            currentDocDiv.innerHTML = `<a href="${dept.prc_id_document}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf"></i> View Current Document</a>`;
        }
    } else {
        currentDocDiv.innerHTML = '<span class="text-muted">No document uploaded</span>';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
    modal.show();
}

function viewDocument(docPath) {
    const fileExt = docPath.split('.').pop().toLowerCase();
    const imageView = document.getElementById('documentImageView');
    const pdfView = document.getElementById('documentPdfView');
    
    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
        imageView.src = docPath;
        imageView.style.display = 'block';
        pdfView.style.display = 'none';
    } else {
        pdfView.src = docPath;
        pdfView.style.display = 'block';
        imageView.style.display = 'none';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('documentViewModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>

