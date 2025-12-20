<?php
define('MHAVIS_EXEC', true);
$page_title = "My Appointments";
$active_page = "appointments";
require_once __DIR__ . '/config/init.php';
requireDoctor();

// Get counts from database
$conn = getDBConnection();
$doctorId = $_SESSION['user_id'];

// Get filter values
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Base query
$baseQuery = "SELECT a.*, 
                     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                     p.phone,
                     p.email,
                     p.emergency_contact_phone
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              WHERE a.doctor_id = ?";

$params = [$doctorId];
$types = "i";

// Add filters
if ($status) {
    $baseQuery .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($dateRange !== 'all') {
    switch ($dateRange) {
        case 'today':
            $date = date('Y-m-d');
            $baseQuery .= " AND a.appointment_date = ?";
            $params[] = $date;
            $types .= "s";
            break;
        case 'week':
            $weekStart = date('Y-m-d', strtotime('this week monday'));
            $weekEnd = date('Y-m-d', strtotime('this week sunday'));
            $baseQuery .= " AND a.appointment_date BETWEEN ? AND ?";
            $params[] = $weekStart;
            $params[] = $weekEnd;
            $types .= "ss";
            break;
        case 'month':
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            $baseQuery .= " AND a.appointment_date BETWEEN ? AND ?";
            $params[] = $monthStart;
            $params[] = $monthEnd;
            $types .= "ss";
            break;
    }
}

if ($searchTerm) {
    $baseQuery .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR p.phone LIKE ?)";
    $searchPattern = "%$searchTerm%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= "ss";
}

// Get appointment status counts for chart - use exact ENUM values
$statusCounts = [];
$statuses = ['scheduled', 'ongoing', 'settled', 'cancelled'];
foreach ($statuses as $s) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = ?");
    $stmt->bind_param("is", $doctorId, $s);
    $stmt->execute();
    $statusCounts[$s] = $stmt->get_result()->fetch_assoc()['count'];
}

// Get daily appointment counts for the last 7 days
$dailyData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = ?");
    $stmt->bind_param("is", $doctorId, $date);
    $stmt->execute();
    $dailyData[date('M j', strtotime($date))] = $stmt->get_result()->fetch_assoc()['count'];
}

// Prepare chart data
$statusLabels = json_encode(array_keys($statusCounts));
$statusData = json_encode(array_values($statusCounts));
$dailyLabels = json_encode(array_keys($dailyData));
$dailyData = json_encode(array_values($dailyData));

// Get appointments
$baseQuery .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$stmt = $conn->prepare($baseQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result();

include 'includes/header.php';
?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="ongoing" <?php echo $status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="settled" <?php echo $status === 'settled' ? 'selected' : ''; ?>>Settled</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date Range</label>
                <select name="date_range" class="form-select">
                    <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>This Month</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search patient name or contact" value="<?php echo htmlspecialchars($searchTerm); ?>">
            </div>
            
        </form>
    </div>
</div>

<!-- Charts -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Appointments by Status</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Daily Appointments (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Appointments Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Appointments List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="appointmentsTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $appointments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M j, Y h:i A', strtotime($row['appointment_date'] . ' ' . $row['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars(formatPhoneNumber($row['phone'])); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($row['email'] ?? ''); ?></small>
                            </td>
                            <td>
                                <?php
                                // Status badge class - consistent mapping across all files
                                $statusLower = strtolower(trim($row['status'] ?? ''));
                                $statusClass = match($statusLower) {
                                    'scheduled' => 'primary',
                                    'ongoing' => 'warning',
                                    'settled' => 'success',
                                    'cancelled', 'canceled' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $row['status']; ?></span>
                            </td>
                            <td>
                                <a href="view_patient.php?id=<?php echo $row['patient_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-user"></i>
                                </a>
                                <a href="medical_record.php?appointment_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-file-medical"></i>
                                </a>
                                <button class="btn btn-sm btn-success update-status" data-id="<?php echo $row['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                    <i class="fas fa-check"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Appointment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm">
                    <input type="hidden" id="appointmentId" name="appointmentId">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="settled">Settled</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="saveStatus">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<SCRIPTS
<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#appointmentsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25
        });
    });

    // Status Chart
    var statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: $statusLabels,
            datasets: [{
                data: $statusData,
                backgroundColor: ['#0d6efd', '#198754', '#dc3545', '#ffc107']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });

    // Daily Chart
    var dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: $dailyLabels,
            datasets: [{
                label: 'Number of Appointments',
                data: $dailyData,
                borderColor: '#0c1a6a',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Update Status Modal
    $('.update-status').click(function() {
        $('#appointmentId').val($(this).data('id'));
    });

    $('#saveStatus').click(function() {
        var formData = {
            appointment_id: $('#appointmentId').val(),
            status: $('#status').val(),
            notes: $('#notes').val()
        };

        $.ajax({
            url: 'edit_appointment.php',
            type: 'POST',
            data: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    showAlert('Error updating status: ' + (response.message || response.error || 'Unknown error'), 'Error', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showAlert('Error updating status', 'Error', 'error');
            }
        });
    });
</script>
SCRIPTS;

include 'includes/footer.php';
?> 