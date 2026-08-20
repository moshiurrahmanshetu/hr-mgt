<?php
$page_title = 'My Leave Requests';
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

// Handle cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!verify_csrf_token()) {
        redirect_with_flash(BASE_URL . '/modules/leave/my_requests.php', 'danger', 'Invalid form submission.');
    }
    
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    
    try {
        // Verify request belongs to this employee and is pending
        $check_stmt = $pdo->prepare("
            SELECT lr.*, lt.name as leave_type_name 
            FROM leave_requests lr 
            JOIN leave_types lt ON lr.leave_type_id = lt.id 
            WHERE lr.id = ? AND lr.employee_id = ? AND lr.status = 'pending'
        ");
        $check_stmt->execute([$request_id, $employee['id']]);
        $request = $check_stmt->fetch();
        
        if (!$request) {
            redirect_with_flash(BASE_URL . '/modules/leave/my_requests.php', 'danger', 'Invalid request or request not in pending status.');
        }
        
        // Cancel the request
        $update_stmt = $pdo->prepare("
            UPDATE leave_requests 
            SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $update_stmt->execute([$request_id]);
        
        log_activity($_SESSION['user_id'], 'leave_cancel', "Cancelled leave request #{$request_id} ({$request['leave_type_name']})");
        
        redirect_with_flash(BASE_URL . '/modules/leave/my_requests.php', 'success', 'Leave request cancelled successfully.');
        
    } catch (PDOException $e) {
        error_log("Leave cancel error: " . $e->getMessage());
        redirect_with_flash(BASE_URL . '/modules/leave/my_requests.php', 'danger', 'An error occurred while cancelling the request.');
    }
}

// Get active leave types for balance summary
try {
    $leave_types_stmt = $pdo->prepare("SELECT * FROM leave_types WHERE status = 'active' ORDER BY name ASC");
    $leave_types_stmt->execute();
    $leave_types = $leave_types_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Leave types fetch error: " . $e->getMessage());
    $leave_types = [];
}

// Filter and pagination
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where = ['employee_id = ?'];
$params = [$employee['id']];

if (!empty($status_filter)) {
    $where[] = 'status = ?';
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where);

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
} catch (PDOException $e) {
    error_log("Leave requests count error: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// Get leave requests
try {
    $stmt = $pdo->prepare("
        SELECT lr.*, lt.name as leave_type_name 
        FROM leave_requests lr 
        JOIN leave_types lt ON lr.leave_type_id = lt.id 
        WHERE $where_clause 
        ORDER BY lr.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Leave requests fetch error: " . $e->getMessage());
    $requests = [];
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">My Leave Requests</h2>
        <p class="text-muted">View and manage your leave requests</p>
    </div>
    <div class="col-auto">
        <a href="<?php echo BASE_URL; ?>/modules/leave/apply.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>
            Apply for Leave
        </a>
    </div>
</div>

<!-- Leave Balance Summary -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Leave Balances (Current Year)</h5>
        <div class="row">
            <?php foreach ($leave_types as $type): ?>
                <?php $balance = calculate_leave_balance($employee['id'], $type['id']); ?>
                <div class="col-md-3 mb-3">
                    <div class="p-3 border rounded">
                        <div class="text-muted small"><?php echo htmlspecialchars($type['name']); ?></div>
                        <div class="fw-bold"><?php echo $balance['remaining']; ?> / <?php echo $balance['max']; ?> days</div>
                        <div class="progress mt-2" style="height: 8px;">
                            <?php $percentage = $balance['max'] > 0 ? ($balance['used'] / $balance['max']) * 100 : 0; ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Filter -->
        <form method="GET" action="" class="row g-3 mb-4">
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?php echo BASE_URL; ?>/modules/leave/my_requests.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>

        <!-- Requests Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Total Days</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">No leave requests found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td class="fw-medium"><?php echo htmlspecialchars($req['leave_type_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($req['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($req['end_date'])); ?></td>
                                <td><?php echo $req['total_days']; ?> days</td>
                                <td>
                                    <?php
                                    $badge_class = match($req['status']) {
                                        'pending' => 'bg-warning bg-opacity-10 text-warning',
                                        'approved' => 'bg-success bg-opacity-10 text-success',
                                        'rejected' => 'bg-danger bg-opacity-10 text-danger',
                                        'cancelled' => 'bg-secondary bg-opacity-10 text-secondary',
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($req['status']); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <?php if ($req['review_remarks']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($req['review_remarks'], 0, 30)); ?>...</small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>/modules/leave/view.php?id=<?php echo $req['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this leave request?')">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <?php echo get_pagination($page, $total_pages, BASE_URL . '/modules/leave/my_requests.php', ['status' => $status_filter]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
