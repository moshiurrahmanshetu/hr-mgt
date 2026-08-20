<?php
$page_title = 'Leave Requests Management';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token()) {
        redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'danger', 'Invalid form submission.');
    }
    
    $action = $_POST['action'];
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $remarks = sanitize_input($_POST['remarks'] ?? '');
    
    if (!in_array($action, ['approve', 'reject'])) {
        redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'danger', 'Invalid action.');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get request details
        $req_stmt = $pdo->prepare("
            SELECT lr.*, e.user_id, lt.name as leave_type_name 
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            JOIN leave_types lt ON lr.leave_type_id = lt.id 
            WHERE lr.id = ?
        ");
        $req_stmt->execute([$request_id]);
        $request = $req_stmt->fetch();
        
        if (!$request) {
            redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'danger', 'Leave request not found.');
        }
        
        if ($request['status'] !== 'pending') {
            redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'danger', 'This request has already been processed.');
        }
        
        // Approve action
        if ($action === 'approve') {
            // Update request status
            $update_stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'approved', reviewed_by = ?, review_remarks = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND status = 'pending'
            ");
            $update_stmt->execute([$_SESSION['user_id'], $remarks ?: null, $request_id]);
            
            // Check if update succeeded (race condition check)
            if ($update_stmt->rowCount() === 0) {
                $pdo->rollBack();
                redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'danger', 'This request has already been processed by another admin.');
            }
            
            // Sync attendance records for leave period
            // APPROACH: Always set status='leave' and clear check_in/check_out since approved leave should override
            // This ensures approved leave takes precedence over any accidental check-ins
            $start = new DateTime($request['start_date']);
            $end = new DateTime($request['end_date']);
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
            
            foreach ($period as $date) {
                $date_str = $date->format('Y-m-d');
                
                // Check if attendance record exists
                $check_stmt = $pdo->prepare("
                    SELECT id FROM attendance 
                    WHERE employee_id = ? AND date = ?
                ");
                $check_stmt->execute([$request['employee_id'], $date_str]);
                $existing = $check_stmt->fetch();
                
                if ($existing) {
                    // Update existing record
                    $update_att_stmt = $pdo->prepare("
                        UPDATE attendance 
                        SET status = 'leave', check_in = NULL, check_out = NULL, marked_by = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $update_att_stmt->execute([$_SESSION['user_id'], $existing['id']]);
                } else {
                    // Insert new record
                    $insert_att_stmt = $pdo->prepare("
                        INSERT INTO attendance (employee_id, date, status, marked_by, created_at) 
                        VALUES (?, ?, 'leave', ?, CURRENT_TIMESTAMP)
                    ");
                    $insert_att_stmt->execute([$request['employee_id'], $date_str, $_SESSION['user_id']]);
                }
            }
            
            log_activity($_SESSION['user_id'], 'leave_approve', "Approved leave request #{$request_id} for {$request['leave_type_name']}");
            
            $pdo->commit();
            
            redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'success', 'Leave request approved successfully. Attendance records updated.');
        }
        
        // Reject action
        if ($action === 'reject') {
            if (empty($remarks)) {
                $pdo->rollBack();
                redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'danger', 'Remarks are required when rejecting a leave request.');
            }
            
            $update_stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'rejected', reviewed_by = ?, review_remarks = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND status = 'pending'
            ");
            $update_stmt->execute([$_SESSION['user_id'], $remarks, $request_id]);
            
            // Check if update succeeded (race condition check)
            if ($update_stmt->rowCount() === 0) {
                $pdo->rollBack();
                redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'danger', 'This request has already been processed by another admin.');
            }
            
            log_activity($_SESSION['user_id'], 'leave_reject', "Rejected leave request #{$request_id} for {$request['leave_type_name']}");
            
            $pdo->commit();
            
            redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'success', 'Leave request rejected successfully.');
        }
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Leave request action error: " . $e->getMessage());
        redirect_with_flash(BASE_URL . '/modules/leave/admin_requests.php', 'danger', 'An error occurred while processing the request.');
    }
}

// Filter and pagination
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$leave_type_filter = $_GET['leave_type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = '(u.name LIKE ? OR e.employee_code LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($department_filter)) {
    $where[] = 'e.department_id = ?';
    $params[] = $department_filter;
}

if (!empty($leave_type_filter)) {
    $where[] = 'lr.leave_type_id = ?';
    $params[] = $leave_type_filter;
}

if (!empty($status_filter)) {
    $where[] = 'lr.status = ?';
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where[] = 'lr.start_date >= ?';
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = 'lr.end_date <= ?';
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where);

// Get total count
try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        JOIN users u ON e.user_id = u.id 
        WHERE $where_clause
    ");
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
        SELECT lr.*, e.employee_code, u.name as user_name, d.name as department_name, lt.name as leave_type_name,
               CASE WHEN lr.reviewed_by IS NULL THEN '-' ELSE 
                  (SELECT name FROM users WHERE id = lr.reviewed_by) END as reviewed_by_name
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        JOIN users u ON e.user_id = u.id 
        JOIN departments d ON e.department_id = d.id 
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

// Get departments and leave types for filters
try {
    $dept_stmt = $pdo->prepare("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll();
    
    $type_stmt = $pdo->prepare("SELECT id, name FROM leave_types WHERE status = 'active' ORDER BY name ASC");
    $type_stmt->execute();
    $leave_types = $type_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Filters fetch error: " . $e->getMessage());
    $departments = [];
    $leave_types = [];
}

// Get pending count
try {
    $pending_stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
    $pending_count = $pending_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Pending count error: " . $e->getMessage());
    $pending_count = 0;
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Leave Requests Management</h2>
        <p class="text-muted">Review and manage employee leave requests</p>
    </div>
    <div class="col-auto">
        <?php if ($pending_count > 0): ?>
            <span class="badge bg-warning text-dark">
                <?php echo $pending_count; ?> Pending
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Search and Filter -->
        <form method="GET" action="" class="row g-3 mb-4">
            <div class="col-md-2">
                <input type="text" class="form-control" name="search" placeholder="Search name/code..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="leave_type">
                    <option value="">All Leave Types</option>
                    <?php foreach ($leave_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $leave_type_filter == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="To">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="<?php echo BASE_URL; ?>/modules/leave/admin_requests.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>

        <!-- Requests Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Code</th>
                        <th>Department</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">No leave requests found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['user_name']); ?></td>
                                <td class="fw-medium"><?php echo htmlspecialchars($req['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($req['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($req['leave_type_name']); ?></td>
                                <td>
                                    <?php echo date('M d', strtotime($req['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($req['end_date'])); ?>
                                </td>
                                <td><?php echo $req['total_days']; ?></td>
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
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>/modules/leave/view.php?id=<?php echo $req['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $req['id']; ?>">
                                                <i class="bi bi-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $req['id']; ?>">
                                                <i class="bi bi-x"></i>
                                            </button>
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
                <?php echo get_pagination($page, $total_pages, BASE_URL . '/modules/leave/admin_requests.php', ['search' => $search, 'department' => $department_filter, 'leave_type' => $leave_type_filter, 'status' => $status_filter, 'date_from' => $date_from, 'date_to' => $date_to]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approve Modals -->
<?php foreach ($requests as $req): ?>
    <?php if ($req['status'] === 'pending'): ?>
        <!-- Approve Modal -->
        <div class="modal fade" id="approveModal<?php echo $req['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Approve Leave Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                        <div class="modal-body">
                            <p>Are you sure you want to approve this leave request?</p>
                            <p class="text-muted">
                                <strong>Employee:</strong> <?php echo htmlspecialchars($req['user_name']); ?><br>
                                <strong>Leave Type:</strong> <?php echo htmlspecialchars($req['leave_type_name']); ?><br>
                                <strong>Dates:</strong> <?php echo date('M d, Y', strtotime($req['start_date'])); ?> to <?php echo date('M d, Y', strtotime($req['end_date'])); ?> (<?php echo $req['total_days']; ?> days)
                            </p>
                            <div class="mb-3">
                                <label for="remarks<?php echo $req['id']; ?>" class="form-label">Remarks (optional)</label>
                                <textarea class="form-control" id="remarks<?php echo $req['id']; ?>" name="remarks" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Approve</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Reject Modal -->
        <div class="modal fade" id="rejectModal<?php echo $req['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Leave Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                        <div class="modal-body">
                            <p>Are you sure you want to reject this leave request?</p>
                            <p class="text-muted">
                                <strong>Employee:</strong> <?php echo htmlspecialchars($req['user_name']); ?><br>
                                <strong>Leave Type:</strong> <?php echo htmlspecialchars($req['leave_type_name']); ?><br>
                                <strong>Dates:</strong> <?php echo date('M d, Y', strtotime($req['start_date'])); ?> to <?php echo date('M d, Y', strtotime($req['end_date'])); ?> (<?php echo $req['total_days']; ?> days)
                            </p>
                            <div class="mb-3">
                                <label for="reject_remarks<?php echo $req['id']; ?>" class="form-label">Remarks <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="reject_remarks<?php echo $req['id']; ?>" name="remarks" rows="3" required></textarea>
                                <div class="form-text">Please explain why this request is being rejected.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
