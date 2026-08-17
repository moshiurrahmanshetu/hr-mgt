<?php
$page_title = 'View Employee';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

require_once __DIR__ . '/../../templates/header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get employee data
try {
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar, u.status as user_status,
               d.name as department_name, des.title as designation_name, 
               creator.name as created_by_name
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        JOIN departments d ON e.department_id = d.id 
        JOIN designations des ON e.designation_id = des.id 
        LEFT JOIN users creator ON e.created_by = creator.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'Employee not found.');
    }
} catch (PDOException $e) {
    error_log("Employee fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'An error occurred while fetching the employee.');
}

$avatar_url = get_avatar_url($employee['user_avatar']);
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Employee Profile</h2>
        <p class="text-muted">View employee details</p>
    </div>
    <div class="col-auto">
        <a href="<?php echo BASE_URL; ?>/modules/employees/edit.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil me-1" viewBox="0 0 16 16">
                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
            </svg>
            Edit Employee
        </a>
        <a href="<?php echo BASE_URL; ?>/modules/employees/list.php" class="btn btn-outline-secondary ms-2">Back to List</a>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <img src="<?php echo $avatar_url; ?>" alt="Profile" class="rounded-circle mb-3" width="120" height="120">
                <h5 class="card-title"><?php echo htmlspecialchars($employee['user_name']); ?></h5>
                <p class="card-text text-muted"><?php echo htmlspecialchars($employee['employee_code']); ?></p>
                <span class="badge <?php echo $employee['employment_status'] === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary'; ?>">
                    <?php echo ucfirst($employee['employment_status']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Account Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Account Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Email Address</label>
                        <div><?php echo htmlspecialchars($employee['user_email']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Account Status</label>
                        <div>
                            <span class="badge <?php echo $employee['user_status'] === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary'; ?>">
                                <?php echo ucfirst($employee['user_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Job Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Job Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Employee Code</label>
                        <div class="fw-medium"><?php echo htmlspecialchars($employee['employee_code']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Department</label>
                        <div><?php echo htmlspecialchars($employee['department_name']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Designation</label>
                        <div><?php echo htmlspecialchars($employee['designation_name']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Joining Date</label>
                        <div><?php echo date('F d, Y', strtotime($employee['joining_date'])); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Employment Status</label>
                        <div>
                            <span class="badge <?php echo $employee['employment_status'] === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary'; ?>">
                                <?php echo ucfirst($employee['employment_status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Basic Salary</label>
                        <div class="fw-medium">$<?php echo number_format($employee['basic_salary'], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Personal Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Personal Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Phone Number</label>
                        <div><?php echo $employee['phone'] ? htmlspecialchars($employee['phone']) : 'Not provided'; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Gender</label>
                        <div><?php echo $employee['gender'] ? ucfirst($employee['gender']) : 'Not provided'; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Date of Birth</label>
                        <div><?php echo $employee['date_of_birth'] ? date('F d, Y', strtotime($employee['date_of_birth'])) : 'Not provided'; ?></div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="text-muted small">Address</label>
                        <div><?php echo $employee['address'] ? nl2br(htmlspecialchars($employee['address'])) : 'Not provided'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Created By</label>
                        <div><?php echo htmlspecialchars($employee['created_by_name'] ?? 'System'); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Created At</label>
                        <div><?php echo date('F d, Y g:i A', strtotime($employee['created_at'])); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Last Updated</label>
                        <div><?php echo date('F d, Y g:i A', strtotime($employee['updated_at'])); ?></div>
                    </div>
                    <?php if ($employee['deleted_at']): ?>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Deleted At</label>
                            <div class="text-danger"><?php echo date('F d, Y g:i A', strtotime($employee['deleted_at'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Placeholder sections for future modules -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Attendance Summary</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.914-.915a.5.5 0 0 1 0-.708l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    Attendance tracking module coming in a future update.
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Leave Summary</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.914-.915a.5.5 0 0 1 0-.708l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    Leave management module coming in a future update.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
