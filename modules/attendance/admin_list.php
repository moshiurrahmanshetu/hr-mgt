<?php
$page_title = 'Attendance Management';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

// Initialize filter variables
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
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

if (!empty($status_filter)) {
    $where[] = 'a.status = ?';
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where[] = 'a.date >= ?';
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = 'a.date <= ?';
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where);

// Get total count
try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.id 
        JOIN users u ON e.user_id = u.id 
        WHERE $where_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
} catch (PDOException $e) {
    error_log("Attendance count error: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// Get attendance records
try {
    $stmt = $pdo->prepare("
        SELECT a.*, e.employee_code, u.name as user_name, d.name as department_name,
               CASE WHEN a.marked_by IS NULL THEN 'Self' ELSE 
                  (SELECT name FROM users WHERE id = a.marked_by) END as marked_by_name
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.id 
        JOIN users u ON e.user_id = u.id 
        JOIN departments d ON e.department_id = d.id 
        WHERE $where_clause 
        ORDER BY a.date DESC, a.check_in DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Attendance list error: " . $e->getMessage());
    $attendance_records = [];
}

// Get departments for filter dropdown
try {
    $dept_stmt = $pdo->prepare("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Departments fetch error: " . $e->getMessage());
    $departments = [];
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Attendance Management</h2>
        <p class="text-muted">View and manage employee attendance</p>
    </div>
    <div class="col-auto">
        <a href="<?php echo BASE_URL; ?>/modules/attendance/mark_absent.php" class="btn btn-warning">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-x me-1" viewBox="0 0 16 16">
                <path d="M6.146 7.146a.5.5 0 0 1 .708 0L8 8.293l1.146-1.147a.5.5 0 0 1 .708.708L8.707 9l1.147 1.146a.5.5 0 0 1-.708.708L8 9.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 9 6.146 7.854a.5.5 0 0 1 0-.708z"/>
                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
            </svg>
            Mark Absent
        </a>
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
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="present" <?php echo $status_filter === 'present' ? 'selected' : ''; ?>>Present</option>
                    <option value="late" <?php echo $status_filter === 'late' ? 'selected' : ''; ?>>Late</option>
                    <option value="absent" <?php echo $status_filter === 'absent' ? 'selected' : ''; ?>>Absent</option>
                    <option value="leave" <?php echo $status_filter === 'leave' ? 'selected' : ''; ?>>Leave</option>
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
                <a href="<?php echo BASE_URL; ?>/modules/attendance/admin_list.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>

        <!-- Attendance Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Code</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                        <th>Marked By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance_records)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">No attendance records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['user_name']); ?></td>
                                <td class="fw-medium"><?php echo htmlspecialchars($record['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($record['department_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                                <td><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                <td>
                                    <?php
                                    $badge_class = match($record['status']) {
                                        'present' => 'bg-success bg-opacity-10 text-success',
                                        'late' => 'bg-warning bg-opacity-10 text-warning',
                                        'absent' => 'bg-danger bg-opacity-10 text-danger',
                                        'leave' => 'bg-info bg-opacity-10 text-info',
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($record['status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($record['marked_by_name']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/modules/attendance/edit.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                            <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                        </svg>
                                    </a>
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
                <?php echo get_pagination($page, $total_pages, BASE_URL . '/modules/attendance/admin_list.php', ['search' => $search, 'department' => $department_filter, 'status' => $status_filter, 'date_from' => $date_from, 'date_to' => $date_to]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
