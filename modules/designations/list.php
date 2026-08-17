<?php
$page_title = 'Designations';
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
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = '(d.title LIKE ? OR d.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($department_filter)) {
    $where[] = 'd.department_id = ?';
    $params[] = $department_filter;
}

if (!empty($status_filter)) {
    $where[] = 'd.status = ?';
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where);

// Get total count
try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM designations d 
        WHERE $where_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
} catch (PDOException $e) {
    error_log("Designation count error: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// Get designations
try {
    $stmt = $pdo->prepare("
        SELECT d.*, dept.name as department_name, u.name as created_by_name 
        FROM designations d 
        JOIN departments dept ON d.department_id = dept.id 
        LEFT JOIN users u ON d.created_by = u.id 
        WHERE $where_clause 
        ORDER BY d.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $designations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Designation list error: " . $e->getMessage());
    $designations = [];
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
        <h2 class="fw-bold">Designations</h2>
        <p class="text-muted">Manage job designations within departments</p>
    </div>
    <div class="col-auto">
        <a href="<?php echo BASE_URL; ?>/modules/designations/create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/>
            </svg>
            Add Designation
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
                <input type="text" class="form-control" name="search" placeholder="Search designations..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
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
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?php echo BASE_URL; ?>/modules/designations/list.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>

        <!-- Designations Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Department</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($designations)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">No designations found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($designations as $designation): ?>
                            <tr>
                                <td class="fw-medium"><?php echo htmlspecialchars($designation['title']); ?></td>
                                <td><?php echo htmlspecialchars($designation['department_name']); ?></td>
                                <td><?php echo htmlspecialchars(truncate_text($designation['description'] ?? '', 50)); ?></td>
                                <td><?php echo get_status_badge($designation['status']); ?></td>
                                <td><?php echo htmlspecialchars($designation['created_by_name'] ?? 'System'); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>/modules/designations/edit.php?id=<?php echo $designation['id']; ?>" class="btn btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                            </svg>
                                        </a>
                                        <form method="POST" action="<?php echo BASE_URL; ?>/modules/designations/delete.php" class="d-inline">
                                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?php echo $designation['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $designation['status']; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $designation['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                                    onclick="return confirm('<?php echo $designation['status'] === 'active' ? 'Are you sure you want to deactivate this designation?' : 'Are you sure you want to reactivate this designation?'; ?>')">
                                                <?php if ($designation['status'] === 'active'): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-dash-lg" viewBox="0 0 16 16">
                                                        <path fill-rule="evenodd" d="M2 8a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 8Z"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
                                                        <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.29.29.29.76 0 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.216 8.115a.754.754 0 0 1 0-1.048.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </button>
                                        </form>
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
                <?php echo get_pagination($page, $total_pages, BASE_URL . '/modules/designations/list.php', ['search' => $search, 'department' => $department_filter, 'status' => $status_filter]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
