<?php
/**
 * Edit Role Form (System/Organization Module)
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash_set('error', 'Operation Error: Missing or invalid Role ID parameter.');
    redirect('index.php?route=roles');
}

$db = Database::getConnection();

// Fetch Role details
$stmt = $db->prepare("SELECT * FROM `roles` WHERE `id` = ? AND `deleted_at` IS NULL");
$stmt->execute([$id]);
$role = $stmt->fetch();

if (!$role) {
    flash_set('error', 'Operation Error: Role record not found or has been deleted.');
    redirect('index.php?route=roles');
}

$page_title = 'Edit System Role';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="main-content">
    <div class="content-body" data-aos="fade-up">
        <!-- Back Link & Header -->
        <div class="mb-4">
            <a href="<?php echo base_url('index.php?route=roles'); ?>" class="btn btn-sm btn-outline-secondary mb-3 d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Roles Directory</span>
            </a>
            <h2 class="fw-bold tracking-tight mb-1" style="color: var(--text-primary);">Modify Role Details</h2>
            <p class="text-muted small mb-0">Update the security authorization profile details, active status, and descriptions.</p>
        </div>

        <!-- Session Alerts -->
        <?php if ($flash_error = flash_get('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 small py-2.5 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?php echo sanitize($flash_error); ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Update Pipeline -->
        <form action="<?php echo base_url('index.php?route=roles-update'); ?>" method="POST" class="needs-validation" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$role['id']; ?>">

            <div class="row g-4">
                <!-- Left Hand Column: Role Specifications -->
                <div class="col-12 col-lg-8">
                    <div class="custom-card mb-4" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;">
                            <i class="bi bi-shield-check me-2"></i>Role Specifications
                        </h4>
                        
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Role Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo sanitize($role['name']); ?>" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required <?php echo in_array($role['name'], ['Admin', 'HR Manager', 'Employee'], true) ? 'readonly' : ''; ?>>
                                <div class="invalid-feedback">A unique role name is required.</div>
                                <?php if (in_array($role['name'], ['Admin', 'HR Manager', 'Employee'], true)): ?>
                                    <p class="text-muted text-[10px] mt-1 font-mono">System-protected role name cannot be modified.</p>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Role Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                    <option value="Active" <?php echo $role['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $role['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <div class="invalid-feedback">Status selection is required.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Role Description</label>
                                <textarea name="description" rows="4" class="form-control" placeholder="Describe the permissions, responsibilities, and specific platform modules accessible under this security clearance band..." style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);"><?php echo sanitize($role['description']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Hand Column: Side Panel & Submit Actions -->
                <div class="col-12 col-lg-4">
                    <div class="custom-card mb-4" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;">
                            <i class="bi bi-info-circle me-2"></i>System Context
                        </h4>
                        <p class="small text-muted mb-0">
                            Roles serve as the core security identity in the platform. Assigning a user to a specific active role automatically delegates corresponding access privileges. Essential system roles (Admin, HR Manager) are system protected.
                        </p>
                    </div>

                    <div class="custom-card" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;">
                            <i class="bi bi-shield-fill-check me-2"></i>Actions
                        </h4>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2.5 mb-2 d-flex align-items-center justify-content-center gap-2 font-semibold">
                            <i class="bi bi-check-lg"></i>
                            <span>Update System Role</span>
                        </button>
                        
                        <a href="<?php echo base_url('index.php?route=roles'); ?>" class="btn btn-outline-secondary w-100 py-2.5 d-flex align-items-center justify-content-center gap-2 text-muted">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel Modification</span>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap validation script
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
