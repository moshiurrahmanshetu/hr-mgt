<?php
$page_title = 'Leave Types';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = 'name LIKE ?';
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where[] = 'status = ?';
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where);

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_types WHERE $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
} catch (PDOException $e) {
    error_log("Leave types count error: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// Get leave types
try {
    $stmt = $pdo->prepare("
        SELECT lt.*, 
               (SELECT COUNT(*) FROM leave_requests lr WHERE lr.leave_type_id = lt.id) as request_count
        FROM leave_types lt 
        WHERE $where_clause 
        ORDER BY lt.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $leave_types = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Leave types list error: " . $e->getMessage());
    $leave_types = [];
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Leave Types</h2>
        <p class="text-muted">Manage leave type categories</p>
    </div>
    <div class="col-auto">
        <a href="<?php echo BASE_URL; ?>/modules/leave-types/create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 4a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-1 0V4.5a.5.5 0 0 1 .5-.5zm2 7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0v-1a.5.5 0 0 1 .5-.5zM8 5.5a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0v-1a.5.5 0 0 1 .5-.5zm0 3a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0v-1a.5.5 0 0 1 .5-.5zm2 7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0v-1a.5.5 0 0 1 .5-.5zM14 4.5V14a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 0 .5.5h2a.5.5 0 0 0 .5.5v-2a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5.5v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5.5v-2a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5v-2z"/>
            </svg>
            Add Leave Type
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Search and Filter -->
        <form method="GET" action="" class="row g-3 mb-4">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <div class="col-md-3">
                <a href="<?php echo BASE_URL; ?>/modules/leave-types/list.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>

        <!-- Leave Types Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Max Days/Year</th>
                        <th>Status</th>
                        <th>Requests</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leave_types)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">No leave types found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leave_types as $type): ?>
                            <tr>
                                <td class="fw-medium"><?php echo htmlspecialchars($type['name']); ?></td>
                                <td><?php echo $type['max_days_per_year']; ?> days</td>
                                <td>
                                    <?php 
                                    $badge_class = $type['status'] === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($type['status']); ?></span>
                                </td>
                                <td><?php echo $type['request_count']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>/modules/leave-types/edit.php?id=<?php echo $type['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="<?php echo BASE_URL; ?>/modules/leave-types/delete.php" class="d-inline">
                                            <input type="hidden"
                                                name="<?php echo CSRF_TOKEN_NAME; ?>"
                                                value="<?php echo get_csrf_token(); ?>">

                                            <input type="hidden"
                                                name="id"
                                                value="<?php echo (int)$type['id']; ?>">

                                            <input type="hidden"
                                                name="action"
                                                value="<?php echo $type['status'] === 'active' ? 'deactivate' : 'reactivate'; ?>">

                                            <?php if ($type['status'] === 'active'): ?>

                                                <button type="submit"
                                                        class="btn btn-outline-danger"
                                                        title="Deactivate">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>

                                            <?php else: ?>

                                                <button type="submit"
                                                        class="btn btn-outline-success"
                                                        title="Reactivate">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>

                                            <?php endif; ?>
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
                <?php echo get_pagination($page, $total_pages, BASE_URL . '/modules/leave-types/list.php', ['search' => $search, 'status' => $status_filter]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
