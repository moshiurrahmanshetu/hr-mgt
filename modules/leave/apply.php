<?php
$page_title = 'Apply for Leave';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('employee');

// Get employee data
try {
    $stmt = $pdo->prepare("
        SELECT e.*, d.name as department_name 
        FROM employees e 
        JOIN departments d ON e.department_id = d.id 
        WHERE e.user_id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        redirect_with_flash(BASE_URL . '/modules/dashboard/employee_dashboard.php', 'danger', 'Employee profile not found.');
    }
} catch (PDOException $e) {
    error_log("Employee fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/dashboard/employee_dashboard.php', 'danger', 'An error occurred.');
}

// Get active leave types
try {
    $leave_types_stmt = $pdo->prepare("SELECT * FROM leave_types WHERE status = 'active' ORDER BY name ASC");
    $leave_types_stmt->execute();
    $leave_types = $leave_types_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Leave types fetch error: " . $e->getMessage());
    $leave_types = [];
}

// Initialize form variables
$leave_type_id = '';
$start_date = '';
$end_date = '';
$reason = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $leave_type_id = $_POST['leave_type_id'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $reason = sanitize_input($_POST['reason'] ?? '');
        
        // Validation
        if (empty($leave_type_id)) {
            $errors['leave_type_id'] = 'Leave type is required.';
        }
        
        if (empty($start_date)) {
            $errors['start_date'] = 'Start date is required.';
        } elseif (!strtotime($start_date)) {
            $errors['start_date'] = 'Invalid start date.';
        } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
            $errors['start_date'] = 'Start date cannot be in the past.';
        }
        
        if (empty($end_date)) {
            $errors['end_date'] = 'End date is required.';
        } elseif (!strtotime($end_date)) {
            $errors['end_date'] = 'Invalid end date.';
        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $errors['end_date'] = 'End date must be on or after start date.';
        }
        
        if (empty($reason)) {
            $errors['reason'] = 'Reason is required.';
        } elseif (strlen($reason) < 10) {
            $errors['reason'] = 'Reason must be at least 10 characters.';
        }
        
        // Calculate total days (counting all calendar days inclusive)
        $total_days = 0;
        if (!empty($start_date) && !empty($end_date) && strtotime($start_date) <= strtotime($end_date)) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $total_days = $interval->days + 1; // +1 to include both start and end dates
        }
        
        // Check leave balance
        if (!empty($leave_type_id) && !isset($errors['leave_type_id'])) {
            $balance = calculate_leave_balance($employee['id'], $leave_type_id);
            if ($total_days > $balance['remaining']) {
                $errors['leave_type_id'] = "Insufficient leave balance. You have {$balance['remaining']} days remaining, but requested $total_days days.";
            }
        }
        
        // Check for overlapping dates
        if (!empty($start_date) && !empty($end_date) && empty($errors['start_date']) && empty($errors['end_date'])) {
            if (check_leave_overlap($employee['id'], $start_date, $end_date)) {
                $errors['start_date'] = 'You have overlapping leave requests for these dates.';
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO leave_requests 
                    (employee_id, leave_type_id, start_date, end_date, total_days, reason, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    $employee['id'],
                    $leave_type_id,
                    $start_date,
                    $end_date,
                    $total_days,
                    $reason
                ]);
                
                $pdo->commit();
                
                log_activity($_SESSION['user_id'], 'leave_apply', "Applied for leave: $start_date to $end_date ($total_days days)");
                
                redirect_with_flash(BASE_URL . '/modules/leave/my_requests.php', 'success', 'Leave request submitted successfully!');
                
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Leave application error: " . $e->getMessage());
                $errors[] = 'An error occurred while submitting your leave request.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Apply for Leave</h2>
        <p class="text-muted">Submit a new leave request</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
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
                        <label for="leave_type_id" class="form-label">Leave Type <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo isset($errors['leave_type_id']) ? 'is-invalid' : ''; ?>" 
                                id="leave_type_id" name="leave_type_id" required>
                            <option value="">Select leave type</option>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" 
                                        data-max-days="<?php echo $type['max_days_per_year']; ?>"
                                        <?php echo $leave_type_id == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['max_days_per_year']; ?> days/year)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['leave_type_id'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['leave_type_id']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control <?php echo isset($errors['start_date']) ? 'is-invalid' : ''; ?>" 
                                   id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                            <?php if (isset($errors['start_date'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['start_date']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control <?php echo isset($errors['end_date']) ? 'is-invalid' : ''; ?>" 
                                   id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                            <?php if (isset($errors['end_date'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['end_date']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Total Days</label>
                        <div class="form-control-plaintext fw-medium" id="total_days">
                            <?php echo $total_days > 0 ? $total_days : '0'; ?> days
                        </div>
                        <div class="form-text">Calculated automatically from date range (includes weekends)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control <?php echo isset($errors['reason']) ? 'is-invalid' : ''; ?>" 
                                  id="reason" name="reason" rows="4" required><?php echo htmlspecialchars($reason); ?></textarea>
                        <div class="form-text">Please provide a detailed reason for your leave request (minimum 10 characters)</div>
                        <?php if (isset($errors['reason'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['reason']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <a href="<?php echo BASE_URL; ?>/modules/leave/my_requests.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Leave Balances</h5>
                <?php foreach ($leave_types as $type): ?>
                    <?php $balance = calculate_leave_balance($employee['id'], $type['id']); ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted"><?php echo htmlspecialchars($type['name']); ?></span>
                            <span class="fw-medium"><?php echo $balance['remaining']; ?> / <?php echo $balance['max']; ?> days</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <?php $percentage = $balance['max'] > 0 ? ($balance['used'] / $balance['max']) * 100 : 0; ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const totalDays = document.getElementById('total_days');
    
    function calculateTotalDays() {
        if (startDate.value && endDate.value) {
            const start = new Date(startDate.value);
            const end = new Date(endDate.value);
            
            if (end >= start) {
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                totalDays.textContent = diffDays + ' days';
            } else {
                totalDays.textContent = '0 days';
            }
        } else {
            totalDays.textContent = '0 days';
        }
    }
    
    startDate.addEventListener('change', calculateTotalDays);
    endDate.addEventListener('change', calculateTotalDays);
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
