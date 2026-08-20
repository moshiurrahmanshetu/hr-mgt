<?php
$page_title = 'Leave Request Details';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get leave request
try {
    $stmt = $pdo->prepare("
        SELECT lr.*, e.employee_code, u.name as user_name, u.email as user_email, 
               d.name as department_name, des.title as designation_name, 
               lt.name as leave_type_name, lt.max_days_per_year,
               CASE WHEN lr.reviewed_by IS NULL THEN NULL ELSE 
                  (SELECT name FROM users WHERE id = lr.reviewed_by) END as reviewed_by_name
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        JOIN users u ON e.user_id = u.id 
        JOIN departments d ON e.department_id = d.id 
        JOIN designations des ON e.designation_id = des.id 
        JOIN leave_types lt ON lr.leave_type_id = lt.id 
        WHERE lr.id = ?
    ");
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        redirect_with_flash(BASE_URL . '/modules/dashboard/' . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'employee_dashboard.php'), 'danger', 'Leave request not found.');
    }
} catch (PDOException $e) {
    error_log("Leave request fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/dashboard/' . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'employee_dashboard.php'), 'danger', 'An error occurred.');
}

// Access control: Only allow employee's own requests OR admin access
$is_admin = $_SESSION['role'] === 'admin';
$is_owner = false;

if (!$is_admin) {
    // Employee: Check if this is their own request
    try {
        $employee_stmt = $pdo->prepare("
            SELECT e.id FROM employees e 
            WHERE e.user_id = ? AND e.id = ?
        ");
        $employee_stmt->execute([$_SESSION['user_id'], $request['employee_id']]);
        if ($employee_stmt->fetch()) {
            $is_owner = true;
        }
    } catch (PDOException $e) {
        error_log("Employee ownership check error: " . $e->getMessage());
    }
    
    if (!$is_owner) {
        // Return 403 for unauthorized access
        http_response_code(403);
        require_once __DIR__ . '/../../templates/header.php';
        ?>
        <div class="row">
            <div class="col">
                <div class="alert alert-danger">
                    <h4 class="alert-heading">Access Denied</h4>
                    <p>You do not have permission to view this leave request.</p>
                    <hr>
                    <a href="<?php echo BASE_URL; ?>/modules/leave/my_requests.php" class="btn btn-primary">Back to My Requests</a>
                </div>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/../../templates/footer.php';
        exit;
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Leave Request Details</h2>
        <p class="text-muted">View complete leave request information</p>
    </div>
    <div class="col-auto">
        <?php if ($is_admin): ?>
            <a href="<?php echo BASE_URL; ?>/modules/leave/admin_requests.php" class="btn btn-outline-secondary">Back to All Requests</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/modules/leave/my_requests.php" class="btn btn-outline-secondary">Back to My Requests</a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-4">Request Information</h5>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="text-muted small">Employee</label>
                        <div class="fw-medium"><?php echo htmlspecialchars($request['user_name']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Employee Code</label>
                        <div class="fw-medium"><?php echo htmlspecialchars($request['employee_code']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Email</label>
                        <div><?php echo htmlspecialchars($request['user_email']); ?></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Department</label>
                        <div><?php echo htmlspecialchars($request['department_name']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Designation</label>
                        <div><?php echo htmlspecialchars($request['designation_name']); ?></div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Leave Type</label>
                        <div class="fw-medium"><?php echo htmlspecialchars($request['leave_type_name']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Status</label>
                        <?php
                        $badge_class = match($request['status']) {
                            'pending' => 'bg-warning bg-opacity-10 text-warning',
                            'approved' => 'bg-success bg-opacity-10 text-success',
                            'rejected' => 'bg-danger bg-opacity-10 text-danger',
                            'cancelled' => 'bg-secondary bg-opacity-10 text-secondary',
                        };
                        ?>
                        <span class="badge <?php echo $badge_class; ?> fs-6"><?php echo ucfirst($request['status']); ?></span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="text-muted small">Start Date</label>
                        <div><?php echo date('F d, Y', strtotime($request['start_date'])); ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">End Date</label>
                        <div><?php echo date('F d, Y', strtotime($request['end_date'])); ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Total Days</label>
                        <div class="fw-medium"><?php echo $request['total_days']; ?> days</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="text-muted small">Reason</label>
                    <div class="p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($request['reason'])); ?></div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Applied On</label>
                        <div><?php echo date('F d, Y g:i A', strtotime($request['created_at'])); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Last Updated</label>
                        <div><?php echo date('F d, Y g:i A', strtotime($request['updated_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($request['status'] !== 'pending'): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Review Information</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Reviewed By</label>
                            <div class="fw-medium"><?php echo $request['reviewed_by_name'] ?: 'N/A'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Reviewed On</label>
                            <div><?php echo $request['reviewed_at'] ? date('F d, Y g:i A', strtotime($request['reviewed_at'])) : 'N/A'; ?></div>
                        </div>
                    </div>
                    
                    <?php if ($request['review_remarks']): ?>
                        <div class="mb-3">
                            <label class="text-muted small">Review Remarks</label>
                            <div class="p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($request['review_remarks'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Leave Balance Info</h5>
                <?php $balance = calculate_leave_balance($request['employee_id'], $request['leave_type_id']); ?>
                <div class="mb-3">
                    <div class="text-muted small"><?php echo htmlspecialchars($request['leave_type_name']); ?> (Current Year)</div>
                    <div class="fw-bold fs-5"><?php echo $balance['remaining']; ?> / <?php echo $balance['max']; ?> days</div>
                    <div class="progress mt-2" style="height: 10px;">
                        <?php $percentage = $balance['max'] > 0 ? ($balance['used'] / $balance['max']) * 100 : 0; ?>
                        <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div class="small text-muted mt-1">
                        Used: <?php echo $balance['used']; ?> days
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($is_admin && $request['status'] === 'pending'): ?>
            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Actions</h5>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                            <i class="bi bi-check me-1"></i>
                            Approve Request
                        </button>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x me-1"></i>
                            Reject Request
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$is_admin && $request['status'] === 'pending'): ?>
            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Actions</h5>
                    <form method="POST" action="<?php echo BASE_URL; ?>/modules/leave/my_requests.php">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Are you sure you want to cancel this leave request?')">
                            <i class="bi bi-x me-1"></i>
                            Cancel Request
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($is_admin && $request['status'] === 'pending'): ?>
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Leave Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo BASE_URL; ?>/modules/leave/admin_requests.php">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <div class="modal-body">
                        <p>Are you sure you want to approve this leave request?</p>
                        <p class="text-muted">
                            <strong>Employee:</strong> <?php echo htmlspecialchars($request['user_name']); ?><br>
                            <strong>Leave Type:</strong> <?php echo htmlspecialchars($request['leave_type_name']); ?><br>
                            <strong>Dates:</strong> <?php echo date('M d, Y', strtotime($request['start_date'])); ?> to <?php echo date('M d, Y', strtotime($request['end_date'])); ?> (<?php echo $request['total_days']; ?> days)
                        </p>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks (optional)</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
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
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Leave Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo BASE_URL; ?>/modules/leave/admin_requests.php">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <div class="modal-body">
                        <p>Are you sure you want to reject this leave request?</p>
                        <p class="text-muted">
                            <strong>Employee:</strong> <?php echo htmlspecialchars($request['user_name']); ?><br>
                            <strong>Leave Type:</strong> <?php echo htmlspecialchars($request['leave_type_name']); ?><br>
                            <strong>Dates:</strong> <?php echo date('M d, Y', strtotime($request['start_date'])); ?> to <?php echo date('M d, Y', strtotime($request['end_date'])); ?> (<?php echo $request['total_days']; ?> days)
                        </p>
                        <div class="mb-3">
                            <label for="reject_remarks" class="form-label">Remarks <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reject_remarks" name="remarks" rows="3" required></textarea>
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

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
