<?php
$page_title = 'Mark Absent';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

$date = $_GET['date'] ?? date('Y-m-d');
$message = '';
$message_type = '';

// Handle bulk mark absent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_absent') {
    if (!verify_csrf_token()) {
        $message = 'Invalid form submission.';
        $message_type = 'danger';
    } else {
        $target_date = $_POST['date'] ?? '';
        $employee_ids = $_POST['employee_ids'] ?? [];
        
        if (empty($target_date)) {
            $message = 'Please select a date.';
            $message_type = 'danger';
        } elseif (empty($employee_ids)) {
            $message = 'Please select at least one employee.';
            $message_type = 'danger';
        } elseif (strtotime($target_date) > strtotime(date('Y-m-d'))) {
            $message = 'Cannot mark absent for future dates.';
            $message_type = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                
                $count = 0;
                foreach ($employee_ids as $employee_id) {
                    // Check if attendance record already exists
                    $check_stmt = $pdo->prepare("
                        SELECT id FROM attendance 
                        WHERE employee_id = ? AND date = ?
                    ");
                    $check_stmt->execute([$employee_id, $target_date]);
                    
                    if (!$check_stmt->fetch()) {
                        // Insert absent record
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO attendance (employee_id, date, status, marked_by, created_at) 
                            VALUES (?, ?, 'absent', ?, CURRENT_TIMESTAMP)
                        ");
                        $insert_stmt->execute([$employee_id, $target_date, $_SESSION['user_id']]);
                        $count++;
                    }
                }
                
                $pdo->commit();
                
                log_activity($_SESSION['user_id'], 'bulk_mark_absent', "Marked $count employees absent on $target_date");
                
                redirect_with_flash(BASE_URL . '/modules/attendance/mark_absent.php?date=' . $target_date, 'success', "Successfully marked $count employees as absent.");
                
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Bulk mark absent error: " . $e->getMessage());
                $message = 'An error occurred while marking employees absent.';
                $message_type = 'danger';
            }
        }
    }
}

// Get employees without attendance for selected date
try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.employee_code, u.name as user_name, d.name as department_name 
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        JOIN departments d ON e.department_id = d.id 
        WHERE e.deleted_at IS NULL 
        AND u.status = 'active'
        AND e.id NOT IN (
            SELECT employee_id FROM attendance WHERE date = ?
        )
        ORDER BY d.name, u.name
    ");
    $stmt->execute([$date]);
    $available_employees = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Available employees fetch error: " . $e->getMessage());
    $available_employees = [];
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Mark Absent</h2>
        <p class="text-muted">Bulk mark employees as absent for a specific date</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
            <input type="hidden" name="action" value="mark_absent">
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Update List</button>
                </div>
            </div>
            
            <?php if (empty($available_employees)): ?>
                <div class="alert alert-info">
                    All employees already have attendance records for <?php echo date('F d, Y', strtotime($date)); ?>.
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    Found <?php echo count($available_employees); ?> employee(s) without attendance for <?php echo date('F d, Y', strtotime($date)); ?>.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                                </th>
                                <th>Employee Code</th>
                                <th>Name</th>
                                <th>Department</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_employees as $emp): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="employee-checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>">
                                    </td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($emp['employee_code']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['department_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to mark selected employees as absent?')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-x me-1" viewBox="0 0 16 16">
                            <path d="M6.146 7.146a.5.5 0 0 1 .708 0L8 8.293l1.146-1.147a.5.5 0 0 1 .708.708L8.707 9l1.147 1.146a.5.5 0 0 1-.708.708L8 9.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 9 6.146 7.854a.5.5 0 0 1 0-.708z"/>
                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                        </svg>
                        Mark Selected as Absent
                    </button>
                    <a href="<?php echo BASE_URL; ?>/modules/attendance/admin_list.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
