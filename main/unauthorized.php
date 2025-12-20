<?php
define('MHAVIS_EXEC', true);
$page_title = "Unauthorized Access";
require_once __DIR__ . '/config/init.php';
include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                    <h2 class="mt-4">Unauthorized Access</h2>
                    <p class="text-muted">You do not have permission to access this page.</p>
                    <div class="mt-4">
                        <?php if (isAdmin()): ?>
                            <a href="admin_dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                        <?php elseif (isDoctor()): ?>
                            <a href="doctor_dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                        <?php elseif (isset($_SESSION['patient_user_id']) && !empty($_SESSION['patient_user_id'])): ?>
                            <a href="patient_dashboard.php" class="btn btn-primary">Go to Patient Dashboard</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 