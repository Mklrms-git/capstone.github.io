<?php
define('MHAVIS_EXEC', true);
$page_title = "Fee Management";
$active_page = "fees";
require_once __DIR__ . '/config/init.php';
requireAdmin();

$conn = getDBConnection();

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        
        $stmt = $conn->prepare("INSERT INTO fee_categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        
        if ($stmt->execute()) {
            $success = "Category added successfully";
        } else {
            $error = "Error adding category";
        }
    }

    if (isset($_POST['delete_category'])) {
        $categoryId = (int)$_POST['delete_category_id'];

        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First, get all fee IDs in this category for analytics cleanup
            $stmt = $conn->prepare("SELECT id FROM fees WHERE category_id = ?");
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            $result = $stmt->get_result();
            $feeIds = [];
            while ($row = $result->fetch_assoc()) {
                $feeIds[] = $row['id'];
            }
            
            // Delete transaction items related to these fees (if any fees exist)
            if (!empty($feeIds)) {
                $placeholders = str_repeat('?,', count($feeIds) - 1) . '?';
                $stmt = $conn->prepare("DELETE FROM transaction_items WHERE fee_id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($feeIds)), ...$feeIds);
                $stmt->execute();
                
                // Delete transactions that reference these fees
                $stmt = $conn->prepare("DELETE FROM transactions WHERE fee_id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($feeIds)), ...$feeIds);
                $stmt->execute();
            }
            
            // Then delete all fees in this category
            $stmt = $conn->prepare("DELETE FROM fees WHERE category_id = ?");
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            
            // Finally delete the category
            $stmt = $conn->prepare("DELETE FROM fee_categories WHERE id = ?");
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            
            // Commit the transaction
            $conn->commit();
            $success = "Category and all associated fees and transactions deleted successfully.";
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $error = "Failed to delete category: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['add_fee'])) {
        $categoryId = (int)$_POST['category_id'];
        $name = sanitize($_POST['name']);
        $opd_amount = (float)$_POST['opd_amount'];
        $er_amount = (float)$_POST['er_amount'];
        $inward_amount = (float)$_POST['inward_amount'];
        $description = sanitize($_POST['description'] ?? '');
        
        $stmt = $conn->prepare("INSERT INTO fees (category_id, name, opd_amount, er_amount, inward_amount, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isddds", $categoryId, $name, $opd_amount, $er_amount, $inward_amount, $description);
        
        if ($stmt->execute()) {
            $success = "Fee added successfully";
        } else {
            $error = "Error adding fee";
        }
    }
    
    if (isset($_POST['update_fee'])) {
        $feeId = (int)$_POST['fee_id'];
        $opd_amount = (float)$_POST['opd_amount'];
        $er_amount = (float)$_POST['er_amount'];
        $inward_amount = (float)$_POST['inward_amount'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE fees SET opd_amount = ?, er_amount = ?, inward_amount = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("dddii", $opd_amount, $er_amount, $inward_amount, $isActive, $feeId);
        
        if ($stmt->execute()) {
            $success = "Fee updated successfully";
        } else {
            $error = "Error updating fee";
        }
    }
    
    if (isset($_POST['delete_fee'])) {
        $feeId = (int)$_POST['delete_fee_id'];
        
        // Get fee name for success message
        $stmt = $conn->prepare("SELECT name FROM fees WHERE id = ?");
        $stmt->bind_param("i", $feeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $feeName = $result->fetch_assoc()['name'] ?? 'Fee';
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete transaction items related to this fee
            $stmt = $conn->prepare("DELETE FROM transaction_items WHERE fee_id = ?");
            $stmt->bind_param("i", $feeId);
            $stmt->execute();
            
            // Delete transactions that reference this fee
            $stmt = $conn->prepare("DELETE FROM transactions WHERE fee_id = ?");
            $stmt->bind_param("i", $feeId);
            $stmt->execute();
            
            // Finally delete the fee
            $stmt = $conn->prepare("DELETE FROM fees WHERE id = ?");
            $stmt->bind_param("i", $feeId);
            $stmt->execute();
            
            // Commit the transaction
            $conn->commit();
            $success = "Fee '{$feeName}' and all associated transactions deleted successfully.";
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $error = "Failed to delete fee: " . $e->getMessage();
        }
    }
}

// Get categories with fee count only
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM fees f WHERE f.category_id = c.id) as fee_count
          FROM fee_categories c
          ORDER BY c.name";
$categories = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get all fees from all categories for "All Services" tab
$allFeesQuery = "SELECT f.*, c.name as category_name, c.id as category_id
                 FROM fees f
                 JOIN fee_categories c ON f.category_id = c.id
                 ORDER BY f.name";
$allFees = $conn->query($allFeesQuery)->fetch_all(MYSQLI_ASSOC);
$totalFeesCount = count($allFees);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3 flex-wrap flex-grow-1">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus"></i> New Category
            </button>
            <?php if (!empty($categories)): ?>
            <div class="category-search-wrapper flex-grow-1" style="max-width: 400px; min-width: 250px; position: relative;">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" 
                           class="form-control border-start-0" 
                           id="categorySearchInput" 
                           placeholder="Search services/fees across all categories..." 
                           autocomplete="off">
                    <button class="btn btn-outline-secondary border-start-0" 
                            type="button" 
                            id="clearSearchBtn" 
                            style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="searchAutocomplete" class="search-autocomplete-dropdown" style="display: none;"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <h5>No Fee Categories Yet</h5>
                <p class="text-muted">Start by creating your first fee category to organize your services.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i> Create First Category
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- Tab Navigation Container -->
        <div class="tabs-container-wrapper mb-4">
            <div class="tabs-container" id="categoryTabsContainer">
                <ul class="nav nav-tabs" id="categoryTabs" role="tablist">
                    <!-- All Services Tab -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" 
                                id="all-services-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#all-services" 
                                type="button" 
                                role="tab"
                                aria-controls="all-services"
                                aria-selected="true">
                            <span class="tab-text">All Services</span>
                            <span class="badge bg-primary ms-2"><?php echo $totalFeesCount; ?></span>
                        </button>
                    </li>
                    <?php foreach ($categories as $category): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" 
                                    id="category-<?php echo $category['id']; ?>-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#category-<?php echo $category['id']; ?>" 
                                    type="button" 
                                    role="tab"
                                    aria-controls="category-<?php echo $category['id']; ?>"
                                    aria-selected="false">
                                <span class="tab-text"><?php echo htmlspecialchars($category['name']); ?></span>
                                <span class="badge bg-primary ms-2"><?php echo $category['fee_count']; ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="categoryTabsContent">
            <!-- All Services Tab Content -->
            <div class="tab-pane fade show active" 
                 id="all-services" 
                 role="tabpanel"
                 aria-labelledby="all-services-tab">
                
                <!-- All Services Header -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div class="category-header-info">
                                <h5>All Services</h5>
                                <small class="text-muted category-description">View all services and fees from all categories</small>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="category-sort-wrapper">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0">
                                            <i class="fas fa-sort text-muted"></i>
                                        </span>
                                        <select class="form-select border-start-0 category-sort-select" 
                                                data-category-id="all-services"
                                                title="Sort all services">
                                            <option value="name-asc">Name (A-Z)</option>
                                            <option value="price-asc">Price: Low to High</option>
                                            <option value="price-desc">Price: High to Low</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($allFees)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No services available yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fee Name</th>
                                            <th>Category</th>
                                            <th>OPD</th>
                                            <th>ER</th>
                                            <th>WARD</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fee-table-body" data-category-id="all-services">
                                        <?php foreach ($allFees as $fee): 
                                            // Calculate minimum and maximum price for sorting
                                            $prices = [
                                                floatval($fee['opd_amount']),
                                                floatval($fee['er_amount']),
                                                floatval($fee['inward_amount'])
                                            ];
                                            $minPrice = min($prices);
                                            $maxPrice = max($prices);
                                        ?>
                                            <tr class="fee-row <?php echo !$fee['is_active'] ? 'table-secondary' : ''; ?>"
                                                data-fee-name="<?php echo htmlspecialchars($fee['name']); ?>"
                                                data-fee-description="<?php echo htmlspecialchars($fee['description'] ?? ''); ?>"
                                                data-category-name="<?php echo htmlspecialchars($fee['category_name']); ?>"
                                                data-min-price="<?php echo $minPrice; ?>"
                                                data-max-price="<?php echo $maxPrice; ?>">
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($fee['name']); ?></strong>
                                                        <?php if ($fee['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($fee['description']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($fee['category_name']); ?></span>
                                                </td>
                                                <td><span class="badge bg-light text-dark"><?php echo formatCurrency($fee['opd_amount']); ?></span></td>
                                                <td><span class="badge bg-light text-dark"><?php echo formatCurrency($fee['er_amount']); ?></span></td>
                                                <td><span class="badge bg-light text-dark"><?php echo formatCurrency($fee['inward_amount']); ?></span></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $fee['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $fee['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-warning edit-fee" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editFeeModal"
                                                            data-fee-id="<?php echo $fee['id']; ?>"
                                                            data-fee-name="<?php echo htmlspecialchars($fee['name']); ?>"
                                                            data-category-name="<?php echo htmlspecialchars($fee['category_name']); ?>"
                                                            data-opd-amount="<?php echo $fee['opd_amount']; ?>"
                                                            data-er-amount="<?php echo $fee['er_amount']; ?>"
                                                            data-inward-amount="<?php echo $fee['inward_amount']; ?>"
                                                            data-fee-active="<?php echo $fee['is_active']; ?>"
                                                            title="Edit Fee">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
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
            
            <?php foreach ($categories as $category): ?>
                <div class="tab-pane fade" 
                     id="category-<?php echo $category['id']; ?>" 
                     role="tabpanel"
                     aria-labelledby="category-<?php echo $category['id']; ?>-tab">
                    
                    <!-- Category Header -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div class="category-header-info">
                                    <h5><?php echo htmlspecialchars($category['name']); ?></h5>
                                    <?php if ($category['description']): ?>
                                        <small class="text-muted category-description"><?php echo htmlspecialchars($category['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="category-sort-wrapper">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0">
                                                <i class="fas fa-sort text-muted"></i>
                                            </span>
                                            <select class="form-select border-start-0 category-sort-select" 
                                                    data-category-id="<?php echo $category['id']; ?>"
                                                    title="Sort this category">
                                                <option value="name-asc">Name (A-Z)</option>
                                                <option value="price-asc">Price: Low to High</option>
                                                <option value="price-desc">Price: High to Low</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="btn-group category-actions">
                                        <button type="button" class="btn btn-success add-fee-btn" 
                                                data-category-id="<?php echo $category['id']; ?>" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#addFeeModal"
                                                title="Add Fee">
                                            <i class="fas fa-plus me-1"></i> Add Fee
                                        </button>
                                        <button type="button" class="btn btn-danger delete-category-btn" 
                                                data-category-id="<?php echo $category['id']; ?>" 
                                                data-category-name="<?php echo htmlspecialchars($category['name']); ?>" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteCategoryModal"
                                                title="Delete Category">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $feeQuery = "SELECT f.*
                                        FROM fees f 
                                        WHERE f.category_id = ? 
                                        ORDER BY f.name";
                            $stmt = $conn->prepare($feeQuery);
                            $stmt->bind_param("i", $category['id']);
                            $stmt->execute();
                            $fees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            ?>
                            
                            <?php if (empty($fees)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No fees in this category yet.</p>
                                    <button class="btn btn-outline-primary add-fee-btn" 
                                            data-category-id="<?php echo $category['id']; ?>" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#addFeeModal">
                                        <i class="fas fa-plus me-1"></i> Add First Fee
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fee Name</th>
                                                <th>OPD</th>
                                                <th>ER</th>
                                                <th>WARD</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fee-table-body" data-category-id="<?php echo $category['id']; ?>">
                                            <?php foreach ($fees as $fee): 
                                                // Calculate minimum and maximum price for sorting
                                                $prices = [
                                                    floatval($fee['opd_amount']),
                                                    floatval($fee['er_amount']),
                                                    floatval($fee['inward_amount'])
                                                ];
                                                $minPrice = min($prices);
                                                $maxPrice = max($prices);
                                            ?>
                                                <tr class="fee-row <?php echo !$fee['is_active'] ? 'table-secondary' : ''; ?>"
                                                    data-fee-name="<?php echo htmlspecialchars($fee['name']); ?>"
                                                    data-fee-description="<?php echo htmlspecialchars($fee['description'] ?? ''); ?>"
                                                    data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                    data-min-price="<?php echo $minPrice; ?>"
                                                    data-max-price="<?php echo $maxPrice; ?>">
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($fee['name']); ?></strong>
                                                            <?php if ($fee['description']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($fee['description']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark"><?php echo formatCurrency($fee['opd_amount']); ?></span></td>
                                                    <td><span class="badge bg-light text-dark"><?php echo formatCurrency($fee['er_amount']); ?></span></td>
                                                    <td><span class="badge bg-light text-dark"><?php echo formatCurrency($fee['inward_amount']); ?></span></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $fee['is_active'] ? 'success' : 'danger'; ?>">
                                                            <?php echo $fee['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-warning edit-fee" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editFeeModal"
                                                                data-fee-id="<?php echo $fee['id']; ?>"
                                                                data-fee-name="<?php echo htmlspecialchars($fee['name']); ?>"
                                                                data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                                data-opd-amount="<?php echo $fee['opd_amount']; ?>"
                                                                data-er-amount="<?php echo $fee['er_amount']; ?>"
                                                                data-inward-amount="<?php echo $fee['inward_amount']; ?>"
                                                                data-fee-active="<?php echo $fee['is_active']; ?>"
                                                                title="Edit Fee">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
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
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Laboratory Tests, X-Ray Services">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief description of this category"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Fee Modal -->
<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required id="addFeeCategorySelect">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fee Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Complete Blood Count, Chest X-Ray">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department Pricing</label>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label small">OPD Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="opd_amount" class="form-control" step="0.01" min="0" required placeholder="0.00">
                                </div>
                                <small class="text-muted">Out Patient Department</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">ER Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="er_amount" class="form-control" step="0.01" min="0" required placeholder="0.00">
                                </div>
                                <small class="text-muted">Emergency Room</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Ward Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="inward_amount" class="form-control" step="0.01" min="0" required placeholder="0.00">
                                </div>
                                <small class="text-muted">In-Patient/Ward</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fee" class="btn btn-primary">Add Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Fee Modal -->
<div class="modal fade" id="editFeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="fee_id" id="editFeeId">
                    <div class="mb-3">
                        <label class="form-label">Fee Name</label>
                        <input type="text" id="editFeeName" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department Pricing</label>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label small">OPD Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="opd_amount" id="editOpdAmount" class="form-control" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">ER Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="er_amount" id="editErAmount" class="form-control" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Ward Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="inward_amount" id="editInwardAmount" class="form-control" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" id="editFeeActive" 
                                   class="form-check-input" value="1">
                            <label class="form-check-label">Active Status</label>
                        </div>
                        <small class="text-muted">Inactive fees cannot be selected for new transactions</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger delete-fee-btn" style="margin-right: auto;">
                        <i class="fas fa-trash me-1"></i> Delete Fee
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_fee" class="btn btn-primary">Update Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning!</strong> This action cannot be undone.
                    </div>
                    <p>Are you sure you want to delete the category <strong id="categoryToDelete"></strong> and all its associated fees and transaction records?</p>
                    <input type="hidden" name="delete_category_id" id="deleteCategoryId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_category" class="btn btn-danger">Delete Category</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Fee Confirmation Modal (2nd Confirmation) -->
<div class="modal fade" id="deleteFeeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <form method="POST" id="deleteFeeForm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Fee Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning! This action cannot be undone.</strong>
                    </div>
                    <p class="mb-3">You are about to delete the fee:</p>
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <h6 class="card-title mb-1" id="feeToDeleteName"></h6>
                            <small class="text-muted">Category: <span id="feeToDeleteCategory"></span></small>
                        </div>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>
                            <strong>This will also delete:</strong>
                            <ul class="mb-0 mt-2">
                                <li>All transaction records associated with this fee</li>
                                <li>All transaction items referencing this fee</li>
                            </ul>
                        </small>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="confirmDeleteCheckbox" required>
                        <label class="form-check-label" for="confirmDeleteCheckbox">
                            I understand this action is permanent and cannot be undone
                        </label>
                    </div>
                    <input type="hidden" name="delete_fee_id" id="deleteFeeId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_fee" class="btn btn-danger" id="confirmDeleteFeeBtn" disabled>
                        <i class="fas fa-trash me-1"></i> Yes, Delete Fee
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fee/Service search functionality - searches across all categories
    const categorySearchInput = document.getElementById('categorySearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const allFeeRows = document.querySelectorAll('.fee-row');
    const autocompleteDropdown = document.getElementById('searchAutocomplete');
    
    // Build list of all fees for autocomplete
    const allFees = Array.from(allFeeRows).map(row => ({
        name: row.dataset.feeName || '',
        description: row.dataset.feeDescription || '',
        categoryName: row.dataset.categoryName || '',
        row: row
    }));
    
    function showAutocompleteSuggestions(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        
        if (term === '') {
            autocompleteDropdown.style.display = 'none';
            return;
        }
        
        // Find matching fees (limit to top 10 for performance)
        const matches = allFees
            .filter(fee => {
                const name = fee.name.toLowerCase();
                const description = fee.description.toLowerCase();
                const category = fee.categoryName.toLowerCase();
                return name.includes(term) || description.includes(term) || category.includes(term);
            })
            .slice(0, 10);
        
        if (matches.length === 0) {
            autocompleteDropdown.style.display = 'none';
            return;
        }
        
        // Build autocomplete HTML
        let html = '<div class="autocomplete-list">';
        matches.forEach(fee => {
            const highlightedName = highlightMatch(fee.name, term);
            const categoryBadge = `<span class="badge bg-secondary ms-2">${fee.categoryName}</span>`;
            html += `
                <div class="autocomplete-item" 
                     data-fee-name="${escapeHtml(fee.name)}" 
                     tabindex="0"
                     role="option">
                    <div class="autocomplete-item-name">${highlightedName} ${categoryBadge}</div>
                    ${fee.description ? `<div class="autocomplete-item-desc">${highlightMatch(fee.description.substring(0, 60), term)}${fee.description.length > 60 ? '...' : ''}</div>` : ''}
                </div>
            `;
        });
        html += '</div>';
        
        autocompleteDropdown.innerHTML = html;
        autocompleteDropdown.style.display = 'block';
        autocompleteDropdown.setAttribute('role', 'listbox');
        
        // Add click handlers and keyboard handlers to autocomplete items
        autocompleteDropdown.querySelectorAll('.autocomplete-item').forEach(item => {
            const selectItem = () => {
                const feeName = item.dataset.feeName;
                categorySearchInput.value = feeName;
                searchFees(feeName);
                autocompleteDropdown.style.display = 'none';
                categorySearchInput.focus();
            };
            
            item.addEventListener('click', selectItem);
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    selectItem();
                }
            });
        });
    }
    
    function highlightMatch(text, term) {
        if (!term) return escapeHtml(text);
        const regex = new RegExp(`(${escapeRegex(term)})`, 'gi');
        return escapeHtml(text).replace(regex, '<mark>$1</mark>');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function searchFees(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        let hasVisibleFees = false;
        let categoriesWithResults = new Set();
        
        // Search through all fee rows
        allFeeRows.forEach((row) => {
            const feeName = (row.dataset.feeName || '').toLowerCase();
            const feeDescription = (row.dataset.feeDescription || '').toLowerCase();
            const categoryName = (row.dataset.categoryName || '').toLowerCase();
            
            // Check if search term matches fee name, description, or category
            const matches = term === '' || 
                          feeName.includes(term) || 
                          feeDescription.includes(term) || 
                          categoryName.includes(term);
            
            if (matches) {
                row.style.display = '';
                hasVisibleFees = true;
                // Track which categories have visible fees
                const tbody = row.closest('.fee-table-body');
                const categoryId = tbody?.dataset.categoryId;
                if (categoryId) {
                    categoriesWithResults.add(categoryId);
                }
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide clear button
        if (clearSearchBtn) {
            if (term !== '') {
                clearSearchBtn.style.display = '';
            } else {
                clearSearchBtn.style.display = 'none';
            }
        }
        
        // Show message if no results
        if (term !== '' && !hasVisibleFees) {
            showNoResultsMessage();
        } else {
            hideNoResultsMessage();
        }
        
        // Highlight categories with results (optional visual enhancement)
        if (term !== '') {
            document.querySelectorAll('#categoryTabs .nav-item').forEach((tabItem) => {
                const tabButton = tabItem.querySelector('.nav-link');
                const tabTarget = tabButton.getAttribute('data-bs-target');
                if (tabTarget) {
                    let categoryId = tabTarget.replace('#category-', '');
                    // Handle "all-services" tab
                    if (tabTarget === '#all-services') {
                        categoryId = 'all-services';
                    }
                    const tabPane = document.querySelector(tabTarget);
                    
                    // For "all-services" tab, check if there are any visible rows
                    if (categoryId === 'all-services') {
                        if (tabPane) {
                            const tbody = tabPane.querySelector('.fee-table-body');
                            const visibleRows = tbody ? Array.from(tbody.querySelectorAll('.fee-row')).filter(r => r.style.display !== 'none') : [];
                            if (visibleRows.length > 0) {
                                tabItem.classList.add('has-results');
                            } else {
                                tabItem.classList.remove('has-results');
                            }
                        }
                    } else if (categoriesWithResults.has(categoryId)) {
                        tabItem.classList.add('has-results');
                    } else {
                        tabItem.classList.remove('has-results');
                    }
                }
            });
        } else {
            // Remove all result highlights when search is cleared
            document.querySelectorAll('#categoryTabs .nav-item').forEach((tabItem) => {
                tabItem.classList.remove('has-results');
            });
        }
    }
    
    function showNoResultsMessage() {
        let noResultsMsg = document.getElementById('noFeeResults');
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.id = 'noFeeResults';
            noResultsMsg.className = 'alert alert-info mt-3 mb-0';
            noResultsMsg.innerHTML = '<i class="fas fa-search me-2"></i>No services/fees found matching your search.';
            const tabsWrapper = document.querySelector('.tabs-container-wrapper');
            tabsWrapper.parentNode.insertBefore(noResultsMsg, tabsWrapper.nextSibling);
        }
        noResultsMsg.style.display = '';
    }
    
    function hideNoResultsMessage() {
        const noResultsMsg = document.getElementById('noFeeResults');
        if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
    }
    
    // Search input event listener
    if (categorySearchInput) {
        categorySearchInput.addEventListener('input', function(e) {
            const value = e.target.value;
            showAutocompleteSuggestions(value);
            searchFees(value);
        });
        
        categorySearchInput.addEventListener('focus', function(e) {
            if (e.target.value.trim() !== '') {
                showAutocompleteSuggestions(e.target.value);
            }
        });
        
        // Clear search button
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                categorySearchInput.value = '';
                searchFees('');
                autocompleteDropdown.style.display = 'none';
                categorySearchInput.focus();
            });
        }
        
        // Handle keyboard navigation in autocomplete
        categorySearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                categorySearchInput.value = '';
                searchFees('');
                autocompleteDropdown.style.display = 'none';
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                const firstItem = autocompleteDropdown.querySelector('.autocomplete-item');
                if (firstItem) {
                    firstItem.focus();
                    firstItem.classList.add('autocomplete-item-hover');
                }
            } else if (e.key === 'Enter') {
                const hoveredItem = autocompleteDropdown.querySelector('.autocomplete-item-hover');
                if (hoveredItem) {
                    e.preventDefault();
                    hoveredItem.click();
                }
            }
        });
        
        // Keyboard navigation within autocomplete
        if (autocompleteDropdown) {
            autocompleteDropdown.addEventListener('keydown', function(e) {
                const items = Array.from(this.querySelectorAll('.autocomplete-item'));
                const currentIndex = items.findIndex(item => item.classList.contains('autocomplete-item-hover'));
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    items.forEach(item => item.classList.remove('autocomplete-item-hover'));
                    const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
                    items[nextIndex].classList.add('autocomplete-item-hover');
                    items[nextIndex].focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    items.forEach(item => item.classList.remove('autocomplete-item-hover'));
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                    items[prevIndex].classList.add('autocomplete-item-hover');
                    items[prevIndex].focus();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const hoveredItem = items[currentIndex];
                    if (hoveredItem) {
                        hoveredItem.click();
                    }
                } else if (e.key === 'Escape') {
                    this.style.display = 'none';
                    categorySearchInput.focus();
                }
            });
        }
    }
    
    // Hide autocomplete when clicking outside
    document.addEventListener('click', function(e) {
        if (autocompleteDropdown && !categorySearchInput.contains(e.target) && !autocompleteDropdown.contains(e.target)) {
            autocompleteDropdown.style.display = 'none';
        }
    });
    
    // Store current fee data globally for delete modal
    let currentFeeData = {};
    
    // Edit fee modal population
    document.querySelectorAll('.edit-fee').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('editFeeId').value = button.dataset.feeId;
            document.getElementById('editFeeName').value = button.dataset.feeName;
            document.getElementById('editOpdAmount').value = button.dataset.opdAmount;
            document.getElementById('editErAmount').value = button.dataset.erAmount;
            document.getElementById('editInwardAmount').value = button.dataset.inwardAmount;
            document.getElementById('editFeeActive').checked = button.dataset.feeActive === '1';
            
            // Store fee data for delete modal
            currentFeeData = {
                feeId: button.dataset.feeId,
                feeName: button.dataset.feeName,
                categoryName: button.dataset.categoryName || 'Unknown Category'
            };
        });
    });
    
    // Delete fee button handler - opens confirmation modal
    const deleteFeeModal = document.getElementById('deleteFeeModal');
    const confirmDeleteCheckbox = document.getElementById('confirmDeleteCheckbox');
    const confirmDeleteFeeBtn = document.getElementById('confirmDeleteFeeBtn');
    
    // Handle delete button click from edit modal
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-fee-btn')) {
            e.preventDefault();
            const editModal = document.getElementById('editFeeModal');
            
            // Populate delete confirmation modal with current fee data
            if (currentFeeData.feeId) {
                document.getElementById('deleteFeeId').value = currentFeeData.feeId;
                document.getElementById('feeToDeleteName').textContent = currentFeeData.feeName || 'Unknown Fee';
                document.getElementById('feeToDeleteCategory').textContent = currentFeeData.categoryName || 'Unknown Category';
                
                // Reset checkbox and disable delete button
                confirmDeleteCheckbox.checked = false;
                confirmDeleteFeeBtn.disabled = true;
                
                // Close edit modal and open delete modal
                if (editModal) {
                    const editModalInstance = bootstrap.Modal.getInstance(editModal);
                    if (editModalInstance) {
                        editModalInstance.hide();
                    }
                }
                
                // Wait for edit modal to close, then open delete modal
                setTimeout(() => {
                    const deleteModalInstance = new bootstrap.Modal(deleteFeeModal);
                    deleteModalInstance.show();
                }, 300);
            }
        }
    });
    
    // Enable/disable delete button based on checkbox
    if (confirmDeleteCheckbox && confirmDeleteFeeBtn) {
        confirmDeleteCheckbox.addEventListener('change', function() {
            confirmDeleteFeeBtn.disabled = !this.checked;
        });
    }
    
    // Reset delete modal when closed
    if (deleteFeeModal) {
        deleteFeeModal.addEventListener('hidden.bs.modal', function() {
            confirmDeleteCheckbox.checked = false;
            confirmDeleteFeeBtn.disabled = true;
            document.getElementById('deleteFeeForm').reset();
        });
    }

    // Delete category modal population
    document.querySelectorAll('.delete-category-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('deleteCategoryId').value = button.dataset.categoryId;
            document.getElementById('categoryToDelete').innerText = button.dataset.categoryName;
        });
    });

    // Add fee modal - set category
    document.querySelectorAll('.add-fee-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('addFeeCategorySelect').value = button.dataset.categoryId;
        });
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Sorting functionality
    function sortFeesTable(tbody, sortType) {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            if (sortType === 'name-asc') {
                const nameA = (a.dataset.feeName || '').toLowerCase();
                const nameB = (b.dataset.feeName || '').toLowerCase();
                return nameA.localeCompare(nameB);
            } else if (sortType === 'price-asc') {
                const priceA = parseFloat(a.dataset.minPrice || 0);
                const priceB = parseFloat(b.dataset.minPrice || 0);
                return priceA - priceB;
            } else if (sortType === 'price-desc') {
                const priceA = parseFloat(a.dataset.maxPrice || 0);
                const priceB = parseFloat(b.dataset.maxPrice || 0);
                return priceB - priceA;
            }
            return 0;
        });
        
        // Clear tbody and append sorted rows
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
    }

    // Per-category sort
    document.querySelectorAll('.category-sort-select').forEach(select => {
        select.addEventListener('change', function() {
            const sortType = this.value;
            const categoryId = this.dataset.categoryId;
            const tbody = document.querySelector(`.fee-table-body[data-category-id="${categoryId}"]`);
            
            if (tbody) {
                sortFeesTable(tbody, sortType);
            }
        });
    });
});
</script>

<style>
/* Tab Navigation Container Styling */
.tabs-container-wrapper {
    position: relative;
    background-color: #fff;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 20px;
}

.tabs-container {
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
}

.tabs-container::-webkit-scrollbar {
    height: 8px;
}

.tabs-container::-webkit-scrollbar-track {
    background: #f7fafc;
}

.tabs-container::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

.tabs-container::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* Tab Navigation Styling */
#categoryTabs {
    border-bottom: none;
    display: flex;
    flex-wrap: nowrap;
    gap: 0;
    min-width: fit-content;
    margin-bottom: 0;
}

#categoryTabs .nav-item {
    flex-shrink: 0;
    margin-right: 4px;
}

#categoryTabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 16px 24px;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    text-align: center;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-width: fit-content;
    background-color: transparent;
    border-radius: 0;
}

#categoryTabs .nav-link .tab-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}

#categoryTabs .nav-link:hover {
    color: #495057;
    border-bottom-color: #dee2e6;
    background-color: #f8f9fa;
}

#categoryTabs .nav-link.active {
    color: #0d6efd;
    background-color: transparent;
    border-bottom-color: #0d6efd;
    font-weight: 600;
}

#categoryTabs .nav-link .badge {
    font-size: 0.75rem;
    padding: 4px 8px;
    font-weight: 600;
    flex-shrink: 0;
    white-space: nowrap;
}

/* Tab Content Styling */
.tab-content {
    padding-top: 0;
}

.tab-pane {
    min-height: 300px;
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Card Styling */
.card {
    transition: box-shadow 0.2s ease;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-radius: 8px;
    overflow: hidden;
}

.card-header {
    border-bottom: 2px solid #dee2e6;
    padding: 20px 24px;
    background-color: #f8f9fa;
}

.card-header h5 {
    margin: 0 0 4px 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
}

.category-header-info {
    flex: 1;
    min-width: 200px;
}

.category-description {
    display: block;
    margin-top: 4px;
    font-size: 0.875rem;
    line-height: 1.4;
}

.category-actions {
    flex-shrink: 0;
}

.card-body {
    padding: 0;
}

/* Table Styling */
.table-responsive {
    border-radius: 0;
    border: none;
}

.table {
    margin-bottom: 0;
    width: 100%;
}

.table thead th {
    font-weight: 600;
    padding: 16px 20px;
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #495057;
    white-space: nowrap;
}

.table tbody td {
    padding: 16px 20px;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.95rem;
}

.table tbody tr {
    transition: background-color 0.2s ease;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.table tbody tr:last-child td {
    border-bottom: none;
}

.table-secondary {
    opacity: 0.7;
}

.table tbody td strong {
    font-weight: 600;
    color: #212529;
    display: block;
    margin-bottom: 2px;
}

.table tbody td small {
    display: block;
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 4px;
    line-height: 1.4;
    word-wrap: break-word;
}

.table tbody td:first-child {
    min-width: 250px;
    max-width: 400px;
}

.table tbody td {
    white-space: normal;
}

/* Badge Styling */
.badge {
    font-size: 0.85rem;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 500;
}

/* Button Group Styling */
.btn-group {
    gap: 8px;
}

.btn-group .btn {
    border-radius: 6px;
    padding: 8px 16px;
    font-weight: 500;
}

/* Empty State Styling */
.text-center.py-5 {
    padding: 60px 20px !important;
}

.text-center.py-5 i {
    margin-bottom: 16px;
    opacity: 0.5;
}

.text-center.py-5 p {
    margin-bottom: 20px;
    font-size: 1rem;
}

/* Container Spacing */
.container-fluid {
    padding: 20px 30px;
}

/* Action Buttons Area */
.d-flex.justify-content-between.align-items-center.mb-4 {
    margin-bottom: 24px !important;
    padding: 0 4px;
}

/* Category Search Styling */
.category-search-wrapper {
    position: relative;
}

.category-search-wrapper .input-group {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 6px;
    overflow: hidden;
}

.category-search-wrapper .input-group-text {
    border-right: none;
    padding: 10px 14px;
}

.category-search-wrapper .form-control {
    border-left: none;
    padding: 10px 14px;
    font-size: 0.95rem;
}

.category-search-wrapper .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.category-search-wrapper .form-control::placeholder {
    color: #adb5bd;
}

.category-search-wrapper #clearSearchBtn {
    border-left: 1px solid #ced4da;
    padding: 10px 12px;
    color: #6c757d;
    transition: all 0.2s ease;
}

.category-search-wrapper #clearSearchBtn:hover {
    background-color: #f8f9fa;
    color: #495057;
}

/* Autocomplete Dropdown Styling */
.search-autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 6px 6px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    margin-top: -1px;
}

.autocomplete-list {
    padding: 0;
    margin: 0;
}

.autocomplete-item {
    padding: 12px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
    display: block;
    text-decoration: none;
    color: inherit;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.autocomplete-item:hover,
.autocomplete-item.autocomplete-item-hover,
.autocomplete-item:focus {
    background-color: #f8f9fa;
    outline: 2px solid #0d6efd;
    outline-offset: -2px;
}

.autocomplete-item-name {
    font-weight: 500;
    color: #212529;
    font-size: 0.95rem;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.autocomplete-item-name mark {
    background-color: #fff3cd;
    color: #856404;
    padding: 0 2px;
    font-weight: 600;
}

.autocomplete-item-desc {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 4px;
    line-height: 1.4;
}

.autocomplete-item-desc mark {
    background-color: #fff3cd;
    color: #856404;
    padding: 0 2px;
    font-weight: 600;
}

.autocomplete-item .badge {
    font-size: 0.75rem;
    padding: 3px 8px;
}

.search-autocomplete-dropdown::-webkit-scrollbar {
    width: 8px;
}

.search-autocomplete-dropdown::-webkit-scrollbar-track {
    background: #f7fafc;
}

.search-autocomplete-dropdown::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

.search-autocomplete-dropdown::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* Category Sort Styling */
.category-sort-wrapper {
    position: relative;
    min-width: 180px;
}

.category-sort-wrapper .input-group {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 6px;
    overflow: hidden;
}

.category-sort-wrapper .input-group-text {
    border-right: none;
    padding: 8px 12px;
    font-size: 0.9rem;
}

.category-sort-wrapper .form-select {
    border-left: none;
    padding: 8px 12px;
    font-size: 0.9rem;
    cursor: pointer;
}

.category-sort-wrapper .form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

#noFeeResults {
    border-left: 4px solid #0dcaf0;
    background-color: #e7f3f5;
    color: #055160;
}

/* Highlight categories with search results */
#categoryTabs .nav-item.has-results .nav-link {
    border-bottom-color: #0dcaf0;
    position: relative;
}

#categoryTabs .nav-item.has-results .nav-link::after {
    content: '';
    position: absolute;
    top: 8px;
    right: 8px;
    width: 8px;
    height: 8px;
    background-color: #0dcaf0;
    border-radius: 50%;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .container-fluid {
        padding: 20px 15px;
    }
    
    .category-search-wrapper {
        max-width: 100% !important;
        width: 100%;
    }
    
    .category-sort-wrapper {
        min-width: 160px;
    }
    
    #categoryTabs .nav-link {
        padding: 14px 20px;
        font-size: 0.9rem;
    }
    
    #categoryTabs .nav-link .tab-text {
        max-width: 150px;
    }
}

@media (max-width: 768px) {
    .d-flex.justify-content-between.align-items-center.mb-4 {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .d-flex.justify-content-between.align-items-center.mb-4 > div {
        width: 100%;
    }
    
    .category-search-wrapper {
        max-width: 100% !important;
        min-width: 100% !important;
    }
    
    .category-sort-wrapper {
        width: 100%;
        min-width: 100%;
    }
    
    .card-header .d-flex {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .card-header .d-flex > div {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .card-header .d-flex > div:last-child {
        margin-bottom: 0;
    }
    
    #categoryTabs .nav-link {
        padding: 12px 16px;
        font-size: 0.85rem;
    }
    
    #categoryTabs .nav-link .tab-text {
        max-width: 120px;
    }
    
    .card-header {
        padding: 16px 20px;
    }
    
    .card-header h5 {
        font-size: 1.1rem;
    }
    
    .table thead th,
    .table tbody td {
        padding: 12px 15px;
        font-size: 0.9rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>