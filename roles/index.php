<?php
/**
 * Roles Directory (System/Organization Module)
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

$page_title = 'Role Management';
$db = Database::getConnection();

// --- FILTERS & PAGINATION CONFIGURATION ---
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- DYNAMIC QUERY BUILDING ---
$where_clauses = ["r.deleted_at IS NULL"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(r.name LIKE :search OR r.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    $where_clauses[] = "r.status = :status_filter";
    $params['status_filter'] = $status_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// Count Query (For Pagination)
$count_sql = "SELECT COUNT(*) FROM `roles` r WHERE $where_sql";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Query (Count associated users for information)
$fetch_sql = "SELECT r.*, COUNT(u.id) AS user_count
              FROM `roles` r
              LEFT JOIN `users` u ON r.id = u.role_id
              WHERE $where_sql
              GROUP BY r.id
              ORDER BY r.name ASC
              LIMIT $limit OFFSET $offset";

$fetch_stmt = $db->prepare($fetch_sql);
$fetch_stmt->execute($params);
$roles_list = $fetch_stmt->fetchAll();

// Render Header and Layout Components
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="main-content">
    <div class="content-body" data-aos="fade-up">
        <!-- Dashboard Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold tracking-tight mb-1" style="color: var(--text-primary);">Roles</h2>
                <p class="text-muted small mb-0">Establish, organize, and manage corporate roles, security clearance profiles, and access authorization levels.</p>
            </div>
            <div>
                <a href="<?php echo base_url('index.php?route=roles-create'); ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1.5 px-3 py-2 fw-semibold">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add New Role</span>
                </a>
            </div>
        </div>

        <!-- Session Alerts -->
        <?php echo flash_display(); ?>

        <!-- Search & Filters Panel -->
        <div class="custom-card mb-4 p-3" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
            <form action="<?php echo base_url('index.php'); ?>" method="GET" class="row g-2.5">
                <input type="hidden" name="route" value="roles">
                
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text border-end-0" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-muted);">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by role name, description..." value="<?php echo sanitize($search); ?>" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <select name="status" class="form-select form-select-sm" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="Inactive" <?php echo $status_filter === 'Inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3 flex-grow-1 fw-medium">
                        <i class="bi bi-filter me-1"></i> Apply Filters
                    </button>
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        <a href="<?php echo base_url('index.php?route=roles'); ?>" class="btn btn-outline-secondary btn-sm px-3 d-inline-flex align-items-center justify-content-center">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Roles Grid / Table -->
        <div class="custom-card p-0 overflow-hidden" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="color: var(--text-primary);">
                    <thead class="table-dark" style="--bs-table-bg: var(--bg-tertiary); --bs-table-border-color: var(--border-color); color: var(--text-secondary);">
                        <tr>
                            <th class="ps-4 py-3" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; width: 250px;">Role Name</th>
                            <th class="py-3" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Description</th>
                            <th class="py-3" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; width: 150px;">Assigned Users</th>
                            <th class="py-3" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; width: 120px;">Status</th>
                            <th class="pe-4 py-3 text-end" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="border-top: none;">
                        <?php if (empty($roles_list)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-shield-slash text-muted display-4 d-block mb-3"></i>
                                    <h5 class="fw-semibold text-secondary">No Roles Found</h5>
                                    <p class="text-muted small mb-0">No security roles match your search and filtering criteria.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roles_list as $role): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-shield-lock-fill text-danger" style="font-size: 1.1rem;"></i>
                                            <span class="fw-bold" style="font-size: 0.9rem; color: var(--text-primary);">
                                                <?php echo sanitize($role['name']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-muted small" style="max-width: 400px; text-overflow: ellipsis; overflow: hidden;">
                                            <?php echo $role['description'] ? sanitize($role['description']) : 'No description provided'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-primary-subtle text-primary px-2.5 py-1" style="font-size: 0.75rem; border: 1px solid rgba(59, 130, 246, 0.25);">
                                            <?php echo (int)$role['user_count']; ?> Users
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($role['status'] === 'Active'): ?>
                                            <span class="badge rounded-pill bg-success-subtle text-success px-2.5 py-1" style="font-size: 0.75rem; border: 1px solid rgba(16, 185, 129, 0.25);">Active</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-danger-subtle text-danger px-2.5 py-1" style="font-size: 0.75rem; border: 1px solid rgba(239, 68, 68, 0.25);">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-2">
                                            <a href="<?php echo base_url('index.php?route=roles-edit&id=' . $role['id']); ?>" class="btn btn-outline-secondary btn-xs p-1" title="Edit details">
                                                <i class="bi bi-pencil-square" style="font-size: 0.9rem; padding: 2px;"></i>
                                            </a>
                                            <!-- Restrict system essential roles like Admin and HR Manager from direct easy delete via index UI if they are critical -->
                                            <?php if (in_array($role['name'], ['Admin', 'HR Manager', 'Employee'], true)): ?>
                                                <button type="button" class="btn btn-outline-secondary btn-xs p-1 opacity-50" title="System Protected Role" disabled>
                                                    <i class="bi bi-slash-circle" style="font-size: 0.9rem; padding: 2px;"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-danger btn-xs p-1" title="Soft Delete" onclick="confirmDelete(<?php echo $role['id']; ?>, '<?php echo sanitize(addslashes($role['name'])); ?>')">
                                                    <i class="bi bi-trash-fill" style="font-size: 0.9rem; padding: 2px;"></i>
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

            <!-- Pagination Footer -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top" style="border-color: var(--border-color) !important; background-color: var(--bg-primary);">
                    <div class="text-muted small">
                        Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong> (Total: <strong><?php echo $total_rows; ?></strong> roles)
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo base_url('index.php?route=roles&page=' . ($page - 1) . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter)); ?>" style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo base_url('index.php?route=roles&page=' . $i . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter)); ?>" style="<?php echo $page == $i ? 'background-color: var(--accent-primary); border-color: var(--accent-primary); color: white;' : 'background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);'; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo base_url('index.php?route=roles&page=' . ($page + 1) . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter)); ?>" style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary);">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Soft Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="small text-muted mb-2">Are you sure you want to soft-delete/deactivate this role?</p>
                <div class="p-2.5 rounded border mb-3 text-center bg-primary-subtle" style="background-color: var(--bg-primary); border-color: var(--border-color) !important;">
                    <strong id="deleteTargetName" style="color: var(--text-primary);">Role Name</strong>
                </div>
                <p class="small text-muted mb-0"><i class="bi bi-shield-fill-exclamation text-warning me-1"></i>This keeps historical transactional logs intact, but marks status to Inactive and prevents assigning to users.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" action="<?php echo base_url('index.php?route=roles-delete'); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" id="deleteTargetId">
                    <button type="submit" class="btn btn-sm btn-danger px-3">Soft-Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteTargetId').value = id;
    document.getElementById('deleteTargetName').textContent = name;
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
