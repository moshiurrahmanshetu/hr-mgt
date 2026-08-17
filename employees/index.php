<?php
/**
 * Employee Directory (Employee Management Module)
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

$page_title = 'Employee Directory';
$db = Database::getConnection();

// --- FAIL-SAFE AUTO SEEDING FOR EMPTY DATABASE ---
// Ensures the user has immediately available, rich data to play with!
$branch_check = $db->query("SELECT COUNT(*) FROM `branches`")->fetchColumn();
if ($branch_check == 0) {
    // Seed Branches
    $db->exec("INSERT INTO `branches` (`name`, `code`, `address`, `phone`, `email`) VALUES 
        ('Headquarters', 'HQ-01', '100 Silicon Blvd, Suite 400', '+1-555-0199', 'hq@enterprisehrm.com'),
        ('Europe Office', 'EU-02', '45 London Bridge St, London', '+44-20-7946', 'london@enterprisehrm.com')");
}

$dept_check = $db->query("SELECT COUNT(*) FROM `departments`")->fetchColumn();
if ($dept_check == 0) {
    $hq_id = $db->query("SELECT `id` FROM `branches` WHERE `code` = 'HQ-01'")->fetchColumn();
    $eu_id = $db->query("SELECT `id` FROM `branches` WHERE `code` = 'EU-02'")->fetchColumn();
    
    // Seed Departments
    $stmt = $db->prepare("INSERT INTO `departments` (`branch_id`, `name`, `code`, `description`) VALUES (?, ?, ?, ?)");
    $stmt->execute([$hq_id, 'Engineering', 'ENG', 'Core engineering, development, and research.']);
    $stmt->execute([$hq_id, 'Human Resources', 'HR', 'Personnel management, recruitment, and benefits.']);
    $stmt->execute([$hq_id, 'Finance & Accounting', 'FIN', 'Corporate billing, payroll, and financial tracking.']);
    $stmt->execute([$eu_id, 'Marketing & Sales', 'MKT', 'Lead generation, branding, and sales pipelines.']);
}

$desg_check = $db->query("SELECT COUNT(*) FROM `designations`")->fetchColumn();
if ($desg_check == 0) {
    $eng_id = $db->query("SELECT `id` FROM `departments` WHERE `code` = 'ENG'")->fetchColumn();
    $hr_id = $db->query("SELECT `id` FROM `departments` WHERE `code` = 'HR'")->fetchColumn();
    $fin_id = $db->query("SELECT `id` FROM `departments` WHERE `code` = 'FIN'")->fetchColumn();
    
    // Seed Designations
    $stmt = $db->prepare("INSERT INTO `designations` (`department_id`, `title`, `description`) VALUES (?, ?, ?)");
    $stmt->execute([$eng_id, 'Senior Software Engineer', 'Leads team development and architecture.']);
    $stmt->execute([$eng_id, 'Software Developer', 'Implements features and resolves technical issues.']);
    $stmt->execute([$hr_id, 'HR Specialist', 'Handles active recruiting and leaves tracking.']);
    $stmt->execute([$fin_id, 'Financial Controller', 'Supervises budgets, reporting, and payroll runs.']);
}

$emp_check = $db->query("SELECT COUNT(*) FROM `employees`")->fetchColumn();
if ($emp_check == 0) {
    $hq_id = $db->query("SELECT `id` FROM `branches` WHERE `code` = 'HQ-01'")->fetchColumn();
    $eng_id = $db->query("SELECT `id` FROM `departments` WHERE `code` = 'ENG'")->fetchColumn();
    $desg_id = $db->query("SELECT `id` FROM `designations` WHERE `title` = 'Senior Software Engineer'")->fetchColumn();
    
    // Seed Default Employees
    $stmt = $db->prepare("INSERT INTO `employees` (`branch_id`, `department_id`, `designation_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `employment_status`, `salary`, `gender`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$hq_id, $eng_id, $desg_id, 'EMP-10001', 'Mike', 'Ross', 'mike.ross@enterprisehrm.com', '+1-555-0101', '2025-01-15', 'Full-Time', 98000.00, 'Male']);
    $stmt->execute([$hq_id, $eng_id, $desg_id, 'EMP-10002', 'Sarah', 'Jenkins', 'sarah.jenkins@enterprisehrm.com', '+1-555-0102', '2024-06-01', 'Full-Time', 85000.00, 'Female']);
}

// --- FILTERS & PAGINATION CONFIGURATION ---
$search = trim($_GET['search'] ?? '');
$branch_filter = trim($_GET['branch_id'] ?? '');
$dept_filter = trim($_GET['department_id'] ?? '');
$desg_filter = trim($_GET['designation_id'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$sort_by = trim($_GET['sort_by'] ?? 'code');

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch filter list for dropdowns
$branches = $db->query("SELECT * FROM `branches` WHERE `deleted_at` IS NULL ORDER BY `name` ASC")->fetchAll();
$departments = $db->query("SELECT * FROM `departments` WHERE `deleted_at` IS NULL ORDER BY `name` ASC")->fetchAll();
$designations = $db->query("SELECT * FROM `designations` WHERE `deleted_at` IS NULL ORDER BY `title` ASC")->fetchAll();

// --- DYNAMIC QUERY BUILDING ---
$where_clauses = ["e.employment_status != 'Terminated'"]; // Soft delete exclusion by default unless status Terminated is selected
if ($status_filter === 'Terminated') {
    $where_clauses = ["e.employment_status = 'Terminated'"];
} elseif (!empty($status_filter)) {
    $where_clauses[] = "e.employment_status = :status_filter";
}

$params = [];

if (!empty($search)) {
    $where_clauses[] = "(e.first_name LIKE :search OR e.last_name LIKE :search OR e.employee_code LIKE :search OR e.email LIKE :search OR e.phone LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($branch_filter)) {
    $where_clauses[] = "e.branch_id = :branch_filter";
    $params['branch_filter'] = $branch_filter;
}

if (!empty($dept_filter)) {
    $where_clauses[] = "e.department_id = :dept_filter";
    $params['dept_filter'] = $dept_filter;
}

if (!empty($desg_filter)) {
    $where_clauses[] = "e.designation_id = :desg_filter";
    $params['desg_filter'] = $desg_filter;
}

if (!empty($status_filter) && $status_filter !== 'Terminated') {
    $params['status_filter'] = $status_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// Count Query (For Pagination)
$count_sql = "SELECT COUNT(*) FROM `employees` e WHERE $where_sql";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Sort Order SQL Map
$sort_sql_map = [
    'name_asc'     => 'e.first_name ASC, e.last_name ASC',
    'name_desc'    => 'e.first_name DESC, e.last_name DESC',
    'joining_asc'  => 'e.hire_date ASC',
    'joining_desc' => 'e.hire_date DESC',
    'code'         => 'e.employee_code ASC'
];
$order_by = $sort_sql_map[$sort_by] ?? 'e.employee_code ASC';

// Fetch Query
$fetch_sql = "SELECT e.*, d.name AS department_name, ds.title AS designation_title, b.name AS branch_name
              FROM `employees` e
              LEFT JOIN `departments` d ON e.department_id = d.id
              LEFT JOIN `designations` ds ON e.designation_id = ds.id
              LEFT JOIN `branches` b ON e.branch_id = b.id
              WHERE $where_sql
              ORDER BY $order_by
              LIMIT $limit OFFSET $offset";

$fetch_stmt = $db->prepare($fetch_sql);
$fetch_stmt->execute($params);
$employees_list = $fetch_stmt->fetchAll();

// Fetch Profile Photos mapping helper
$photo_stmt = $db->prepare("SELECT `file_path` FROM `employee_documents` WHERE `employee_id` = ? AND `document_type` = 'Profile Photo' LIMIT 1");

// Render Header and Layout Components
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="main-content">
    <div class="content-body" data-aos="fade-up">
        <!-- Dashboard Header -->
        <div class="d-flex flex-col flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold tracking-tight mb-1" style="color: var(--text-primary);">Employee Management</h2>
                <p class="text-muted small mb-0">Organize, register, and manage corporate employee biographical files, salaries, and documents.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2 px-3 py-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download"></i>
                        <span>Export</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <li><a class="dropdown-item" href="#" onclick="alert('Export to Excel initiated (UI only)'); return false;" style="color: var(--text-primary);"><i class="bi bi-file-earmark-excel text-success me-2"></i>Export to Excel</a></li>
                        <li><a class="dropdown-item" href="#" onclick="alert('Export to CSV initiated (UI only)'); return false;" style="color: var(--text-primary);"><i class="bi bi-file-earmark-spreadsheet text-info me-2"></i>Export to CSV</a></li>
                        <li><a class="dropdown-item" href="#" onclick="alert('Export to PDF initiated (UI only)'); return false;" style="color: var(--text-primary);"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Export to PDF</a></li>
                    </ul>
                </div>
                <a href="<?php echo base_url('employees/create.php'); ?>" class="btn btn-primary d-flex align-items-center gap-2 px-3 py-2">
                    <i class="bi bi-person-plus-fill"></i>
                    <span>Register Employee</span>
                </a>
            </div>
        </div>

        <!-- System Alerts / Notifications -->
        <?php if ($flash_error = flash_get('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 small py-2.5 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?php echo sanitize($flash_error); ?></div>
            </div>
        <?php endif; ?>
        <?php if ($flash_success = flash_get('success')): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 small py-2.5 mb-4" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <div><?php echo sanitize($flash_success); ?></div>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Card -->
        <div class="custom-card mb-4">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="route" value="employees"> <!-- Support routing context if requested -->
                
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-semibold">Search Personnel</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0" style="border-color: var(--border-color);"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" placeholder="Name, ID, Email, Phone..." value="<?php echo sanitize($search); ?>">
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label text-muted small fw-semibold">Branch</label>
                    <select name="branch_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?php echo $br['id']; ?>" <?php echo $branch_filter == $br['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($br['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label text-muted small fw-semibold">Department</label>
                    <select name="department_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $dept_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label text-muted small fw-semibold">Designation</label>
                    <select name="designation_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="">All Designations</option>
                        <?php foreach ($designations as $desg): ?>
                            <option value="<?php echo $desg['id']; ?>" <?php echo $desg_filter == $desg['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($desg['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-1.5" style="flex: 1 1 12%;">
                    <label class="form-label text-muted small fw-semibold">Status</label>
                    <select name="status" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="">Active (All)</option>
                        <option value="Full-Time" <?php echo $status_filter === 'Full-Time' ? 'selected' : ''; ?>>Full-Time</option>
                        <option value="Part-Time" <?php echo $status_filter === 'Part-Time' ? 'selected' : ''; ?>>Part-Time</option>
                        <option value="Contract" <?php echo $status_filter === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="Intern" <?php echo $status_filter === 'Intern' ? 'selected' : ''; ?>>Intern</option>
                        <option value="Terminated" <?php echo $status_filter === 'Terminated' ? 'selected' : ''; ?>>Terminated (Soft-Deleted)</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-1.5" style="flex: 1 1 12%;">
                    <label class="form-label text-muted small fw-semibold">Sort By</label>
                    <select name="sort_by" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="code" <?php echo $sort_by === 'code' ? 'selected' : ''; ?>>Employee ID</option>
                        <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="joining_asc" <?php echo $sort_by === 'joining_asc' ? 'selected' : ''; ?>>Joining (Oldest)</option>
                        <option value="joining_desc" <?php echo $sort_by === 'joining_desc' ? 'selected' : ''; ?>>Joining (Newest)</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex align-items-end gap-1.5" style="flex-grow: 1;">
                    <button type="submit" class="btn btn-primary w-100 py-2">Apply Filters</button>
                    <a href="<?php echo base_url('employees/index.php'); ?>" class="btn btn-outline-secondary py-2" title="Clear Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>

        <!-- Employees Table Card -->
        <div class="custom-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 text-nowrap" style="color: var(--text-primary); --bs-table-bg: transparent; --bs-table-border-color: var(--border-color);">
                    <thead style="background-color: var(--bg-tertiary); color: var(--text-secondary);">
                        <tr>
                            <th class="ps-4 py-3 text-uppercase font-mono text-muted" style="font-size: 0.725rem;">Employee Details</th>
                            <th class="py-3 text-uppercase font-mono text-muted" style="font-size: 0.725rem;">Employee ID</th>
                            <th class="py-3 text-uppercase font-mono text-muted" style="font-size: 0.725rem;">Contact Information</th>
                            <th class="py-3 text-uppercase font-mono text-muted" style="font-size: 0.725rem;">Position</th>
                            <th class="py-3 text-uppercase font-mono text-muted" style="font-size: 0.725rem;">Hire Date</th>
                            <th class="py-3 text-uppercase font-mono text-muted" style="font-size: 0.725rem;">Status</th>
                            <th class="pe-4 py-3 text-end text-uppercase font-mono text-muted" style="font-size: 0.725rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees_list)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-people-fill fs-2 mb-2 d-block"></i>
                                    <span>No corporate employee files match the search criteria.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees_list as $emp): 
                                // Fetch Profile Photo path
                                $photo_stmt->execute([$emp['id']]);
                                $photo_path = $photo_stmt->fetchColumn();
                                $photo_url = $photo_path ? base_url($photo_path) : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=120&h=120&q=80';
                            ?>
                                <tr class="hover-row">
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <!-- Profile Photo -->
                                            <img src="<?php echo $photo_url; ?>" alt="Employee Photo" class="rounded-circle object-fit-cover border border-secondary shadow-sm" style="width: 44px; height: 44px;" referrerPolicy="no-referrer">
                                            <div>
                                                <div class="fw-bold mb-0" style="font-size: 0.875rem;"><?php echo sanitize($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                                                <span class="text-muted" style="font-size: 0.75rem;"><?php echo sanitize($emp['gender']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 font-mono text-primary fw-semibold" style="font-size: 0.8rem;"><?php echo sanitize($emp['employee_code']); ?></td>
                                    <td class="py-3">
                                        <div style="font-size: 0.8rem;">
                                            <div class="mb-0"><i class="bi bi-envelope-fill text-muted me-1.5"></i><?php echo sanitize($emp['email']); ?></div>
                                            <div class="text-muted"><i class="bi bi-telephone-fill text-muted me-1.5"></i><?php echo sanitize($emp['phone'] ?: 'N/A'); ?></div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div style="font-size: 0.8rem;">
                                            <div class="fw-semibold text-primary mb-0"><?php echo sanitize($emp['designation_title'] ?: 'N/A'); ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-building text-muted me-1"></i><?php echo sanitize($emp['department_name'] ?: 'N/A'); ?></div>
                                        </div>
                                    </td>
                                    <td class="py-3" style="font-size: 0.8rem;"><?php echo format_date($emp['hire_date']); ?></td>
                                    <td class="py-3">
                                        <?php 
                                        $badge_class = 'badge-primary';
                                        if ($emp['employment_status'] === 'Full-Time') $badge_class = 'badge-success';
                                        elseif ($emp['employment_status'] === 'Part-Time') $badge_class = 'badge-primary';
                                        elseif ($emp['employment_status'] === 'Contract') $badge_class = 'badge-warning';
                                        elseif ($emp['employment_status'] === 'Intern') $badge_class = 'badge-primary text-secondary';
                                        elseif ($emp['employment_status'] === 'Terminated') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="custom-badge <?php echo $badge_class; ?>"><?php echo sanitize($emp['employment_status']); ?></span>
                                    </td>
                                    <td class="pe-4 py-3 text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary py-1 px-2.5" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border p-1" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                                <li>
                                                    <a class="dropdown-item rounded py-1.5 d-flex align-items-center gap-2 text-primary" href="<?php echo base_url('employees/show.php?id=' . $emp['id']); ?>">
                                                        <i class="bi bi-eye-fill text-muted"></i>
                                                        <span>View Details</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item rounded py-1.5 d-flex align-items-center gap-2 text-warning" href="<?php echo base_url('employees/edit.php?id=' . $emp['id']); ?>">
                                                        <i class="bi bi-pencil-fill text-muted"></i>
                                                        <span>Edit Details</span>
                                                    </a>
                                                </li>
                                                <?php if ($emp['employment_status'] !== 'Terminated'): ?>
                                                    <li><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                                                    <li>
                                                        <form action="<?php echo base_url('employees/delete.php'); ?>" method="POST" onsubmit="return confirm('Are you sure you want to terminate/soft-delete this employee record? This action preserves the log for audit tracking.');" class="m-0">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
                                                            <button type="submit" class="dropdown-item rounded py-1.5 d-flex align-items-center gap-2 text-danger">
                                                                <i class="bi bi-person-x-fill"></i>
                                                                <span>Terminate (Soft-Delete)</span>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Server-Side Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 p-4 border-top" style="border-color: var(--border-color) !important;">
                    <div class="text-muted small">
                        Showing <span class="fw-semibold"><?php echo min($total_rows, $offset + 1); ?></span> to <span class="fw-semibold"><?php echo min($total_rows, $offset + $limit); ?></span> of <span class="fw-semibold"><?php echo $total_rows; ?></span> entries
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Prev Link -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&department_id=<?php echo urlencode($dept_filter); ?>&designation_id=<?php echo urlencode($desg_filter); ?>&status=<?php echo urlencode($status_filter); ?>&page=<?php echo $page - 1; ?>" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?search=<?php echo urlencode($search); ?>&department_id=<?php echo urlencode($dept_filter); ?>&designation_id=<?php echo urlencode($desg_filter); ?>&status=<?php echo urlencode($status_filter); ?>&page=<?php echo $i; ?>" style="<?php echo $page == $i ? 'background-color: var(--accent-primary); border-color: var(--accent-primary); color: #fff;' : 'background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);'; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <!-- Next Link -->
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&department_id=<?php echo urlencode($dept_filter); ?>&designation_id=<?php echo urlencode($desg_filter); ?>&status=<?php echo urlencode($status_filter); ?>&page=<?php echo $page + 1; ?>" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
