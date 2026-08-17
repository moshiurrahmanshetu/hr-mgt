<?php
$page_title = 'Employees';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

require_once __DIR__ . '/../../templates/header.php';

// Initialize variables
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
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

if (!empty($status_filter)) {
    $where[] = 'e.employment_status = ?';
    $params[] = $status_filter;
}

if (!$show_deleted) {
    $where[] = 'e.deleted_at IS NULL';
}

$where_clause = implode(' AND ', $where);

// Get total count
try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        WHERE $where_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
} catch (PDOException $e) {
    error_log("Employee count error: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// Get employees
try {
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar, 
               u.status as user_status, d.name as department_name, des.title as designation_name 
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        JOIN departments d ON e.department_id = d.id 
        JOIN designations des ON e.designation_id = des.id 
        WHERE $where_clause 
        ORDER BY e.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Employee list error: " . $e->getMessage());
    $employees = [];
}

// Get active departments for filter dropdown
try {
    $dept_stmt = $pdo->prepare("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Departments fetch error: " . $e->getMessage());
    $departments = [];
}

// Flash message
$flash = get_flash_message();
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Employees</h2>
        <p class="text-muted">Manage employee records</p>
    </div>
    <div class="col-auto">
        <a href="<?php echo BASE_URL; ?>/modules/employees/create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/>
            </svg>
            Add Employee
        </a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <!-- Search and Filter -->
        <form method="GET" action="" class="row g-3 mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search); ?>">
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
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="terminated" <?php echo $status_filter === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="show_deleted" value="1" id="show_deleted" <?php echo $show_deleted ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="show_deleted">Show Deleted</label>
                </div>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?php echo BASE_URL; ?>/modules/employees/list.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>

        <!-- Employees Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Status</th>
                        <th>Joining Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">No employees found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo get_avatar_url($employee['user_avatar']); ?>" 
                                         alt="Avatar" class="rounded-circle" width="32" height="32">
                                </td>
                                <td class="fw-medium"><?php echo htmlspecialchars($employee['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($employee['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['designation_name']); ?></td>
                                <td>
                                    <?php 
                                    $status = $employee['deleted_at'] ? 'deleted' : $employee['employment_status'];
                                    $badge_class = $status === 'active' ? 'bg-success bg-opacity-10 text-success' : 
                                                  ($status === 'deleted' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-secondary bg-opacity-10 text-secondary');
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($employee['joining_date'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>/modules/employees/view.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.68-1.955 1.955C11.879 6.668 10.12 5.5 8 5.5c-2.12 0-3.879 1.168-5.168 2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                            </svg>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/modules/employees/edit.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                            </svg>
                                        </a>
                                        <?php if ($employee['deleted_at']): ?>
                                            <form method="POST" action="<?php echo BASE_URL; ?>/modules/employees/delete.php" class="d-inline">
                                                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                                                <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                                                <input type="hidden" name="action" value="reactivate">
                                                <button type="submit" class="btn btn-outline-success" title="Reactivate">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16">
                                                        <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                                        <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?php echo BASE_URL; ?>/modules/employees/delete.php" class="d-inline">
                                                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                                                <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this employee? This will deactivate their account.')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-.5-.5V6a.5.5 0 0 1 1 0v6a.5.5 0 0 0-.5.5z"/>
                                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                                    </svg>
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
                <?php echo get_pagination($page, $total_pages, BASE_URL . '/modules/employees/list.php', ['search' => $search, 'department' => $department_filter, 'status' => $status_filter, 'show_deleted' => $show_deleted ? '1' : '']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
