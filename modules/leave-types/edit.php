<?php
$page_title = 'Edit Leave Type';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get leave type
try {
    $stmt = $pdo->prepare("SELECT * FROM leave_types WHERE id = ?");
    $stmt->execute([$id]);
    $leave_type = $stmt->fetch();
    
    if (!$leave_type) {
        redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'Leave type not found.');
    }
} catch (PDOException $e) {
    error_log("Leave type fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'An error occurred.');
}

// Initialize form variables
$name = $leave_type['name'];
$max_days = $leave_type['max_days_per_year'];
$status = $leave_type['status'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $max_days = $_POST['max_days'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        if (empty($name)) {
            $errors['name'] = 'Leave type name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Leave type name must be at least 2 characters.';
        }
        
        if (empty($max_days)) {
            $errors['max_days'] = 'Maximum days per year is required.';
        } elseif (!is_numeric($max_days) || intval($max_days) < 0) {
            $errors['max_days'] = 'Maximum days must be a non-negative integer.';
        }
        
        // Check for duplicate name (excluding current record)
        if (empty($errors['name'])) {
            try {
                $check_stmt = $pdo->prepare("SELECT id FROM leave_types WHERE name = ? AND id != ?");
                $check_stmt->execute([$name, $id]);
                if ($check_stmt->fetch()) {
                    $errors['name'] = 'A leave type with this name already exists.';
                }
            } catch (PDOException $e) {
                error_log("Leave type duplicate check error: " . $e->getMessage());
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE leave_types 
                    SET name = ?, max_days_per_year = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$name, intval($max_days), $status, $id]);
                
                log_activity($_SESSION['user_id'], 'leave_type_update', "Updated leave type: $name");
                
                redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'success', 'Leave type updated successfully!');
                
            } catch (PDOException $e) {
                error_log("Leave type update error: " . $e->getMessage());
                $errors[] = 'An error occurred while updating the leave type.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Edit Leave Type</h2>
        <p class="text-muted">Modify leave type settings</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $field => $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Leave Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                               id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        <div class="form-text">e.g., Casual Leave, Sick Leave, Annual Leave</div>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_days" class="form-label">Maximum Days Per Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control <?php echo isset($errors['max_days']) ? 'is-invalid' : ''; ?>" 
                               id="max_days" name="max_days" value="<?php echo htmlspecialchars($max_days); ?>" min="0" required>
                        <div class="form-text">Number of days employees can take for this leave type per year</div>
                        <?php if (isset($errors['max_days'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['max_days']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Leave Type</button>
                        <a href="<?php echo BASE_URL; ?>/modules/leave-types/list.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
