<?php
$page_title = 'Edit Attendance';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get attendance record
try {
    $stmt = $pdo->prepare("
        SELECT a.*, e.employee_code, u.name as user_name, d.name as department_name 
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.id 
        JOIN users u ON e.user_id = u.id 
        JOIN departments d ON e.department_id = d.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $attendance = $stmt->fetch();
    
    if (!$attendance) {
        redirect_with_flash(BASE_URL . '/modules/attendance/admin_list.php', 'danger', 'Attendance record not found.');
    }
} catch (PDOException $e) {
    error_log("Attendance fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/attendance/admin_list.php', 'danger', 'An error occurred.');
}

// Initialize form variables
$check_in = $attendance['check_in'] ? date('H:i', strtotime($attendance['check_in'])) : '';
$check_out = $attendance['check_out'] ? date('H:i', strtotime($attendance['check_out'])) : '';
$status = $attendance['status'];
$remarks = $attendance['remarks'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $check_in = $_POST['check_in'] ?? '';
        $check_out = $_POST['check_out'] ?? '';
        $status = $_POST['status'] ?? 'present';
        $remarks = sanitize_input($_POST['remarks'] ?? '');
        
        // Validation
        if (empty($status)) {
            $errors['status'] = 'Status is required.';
        }
        
        if (!empty($check_in) && !strtotime($check_in)) {
            $errors['check_in'] = 'Invalid check-in time.';
        }
        
        if (!empty($check_out) && !strtotime($check_out)) {
            $errors['check_out'] = 'Invalid check-out time.';
        }
        
        // Check-out must be after check-in if both present
        if (!empty($check_in) && !empty($check_out)) {
            if (strtotime($check_in) >= strtotime($check_out)) {
                $errors['check_out'] = 'Check-out time must be after check-in time.';
            }
        }
        
        // Remarks required when admin changes anything
        if (($check_in !== ($attendance['check_in'] ? date('H:i', strtotime($attendance['check_in'])) : '') ||
             $check_out !== ($attendance['check_out'] ? date('H:i', strtotime($attendance['check_out'])) : '') ||
             $status !== $attendance['status']) && empty($remarks)) {
            $errors['remarks'] = 'Remarks are required when modifying attendance details.';
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $update_stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET check_in = ?, check_out = ?, status = ?, remarks = ?, marked_by = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $update_stmt->execute([
                    $check_in ?: null,
                    $check_out ?: null,
                    $status,
                    $remarks ?: null,
                    $_SESSION['user_id'],
                    $id
                ]);
                
                $pdo->commit();
                
                log_activity($_SESSION['user_id'], 'attendance_edit', "Corrected attendance for {$attendance['employee_code']} on {$attendance['date']}");
                
                redirect_with_flash(BASE_URL . '/modules/attendance/admin_list.php', 'success', 'Attendance record updated successfully!');
                
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Attendance update error: " . $e->getMessage());
                $errors[] = 'An error occurred while updating the attendance record.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Edit Attendance</h2>
        <p class="text-muted">Correct attendance record for <?php echo htmlspecialchars($attendance['user_name']); ?> (<?php echo htmlspecialchars($attendance['employee_code']); ?>)</p>
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
                        <label class="text-muted small">Employee</label>
                        <div class="fw-medium"><?php echo htmlspecialchars($attendance['user_name']); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Employee Code</label>
                        <div class="fw-medium"><?php echo htmlspecialchars($attendance['employee_code']); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Department</label>
                        <div><?php echo htmlspecialchars($attendance['department_name']); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Date</label>
                        <div><?php echo date('F d, Y', strtotime($attendance['date'])); ?></div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="check_in" class="form-label">Check In Time</label>
                            <input type="time" class="form-control <?php echo isset($errors['check_in']) ? 'is-invalid' : ''; ?>" 
                                   id="check_in" name="check_in" value="<?php echo htmlspecialchars($check_in); ?>">
                            <?php if (isset($errors['check_in'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['check_in']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="check_out" class="form-label">Check Out Time</label>
                            <input type="time" class="form-control <?php echo isset($errors['check_out']) ? 'is-invalid' : ''; ?>" 
                                   id="check_out" name="check_out" value="<?php echo htmlspecialchars($check_out); ?>">
                            <?php if (isset($errors['check_out'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['check_out']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                id="status" name="status" required>
                            <option value="present" <?php echo $status === 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="late" <?php echo $status === 'late' ? 'selected' : ''; ?>>Late</option>
                            <option value="absent" <?php echo $status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="leave" <?php echo $status === 'leave' ? 'selected' : ''; ?>>Leave</option>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['status']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks <span class="text-danger">*</span></label>
                        <textarea class="form-control <?php echo isset($errors['remarks']) ? 'is-invalid' : ''; ?>" 
                                  id="remarks" name="remarks" rows="3" required><?php echo htmlspecialchars($remarks); ?></textarea>
                        <div class="form-text">Required when modifying attendance details. Explain why this correction was made.</div>
                        <?php if (isset($errors['remarks'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['remarks']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Record</button>
                        <a href="<?php echo BASE_URL; ?>/modules/attendance/admin_list.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
