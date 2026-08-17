<?php
/**
 * Edit Branch Form (Organization Module)
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
    flash_set('error', 'Operation Error: Missing or invalid Branch ID parameter.');
    redirect('index.php?route=branches');
}

$db = Database::getConnection();

// Fetch Branch details
$stmt = $db->prepare("SELECT * FROM `branches` WHERE `id` = ? AND `deleted_at` IS NULL");
$stmt->execute([$id]);
$branch = $stmt->fetch();

if (!$branch) {
    flash_set('error', 'Operation Error: Branch record not found or has been deleted.');
    redirect('index.php?route=branches');
}

// Fetch active employees to assign as manager
$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM `employees` WHERE `employment_status` != 'Terminated' ORDER BY `first_name` ASC")->fetchAll();

$page_title = 'Edit Corporate Branch';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="main-content">
    <div class="content-body" data-aos="fade-up">
        <!-- Back Link & Header -->
        <div class="mb-4">
            <a href="<?php echo base_url('index.php?route=branches'); ?>" class="btn btn-sm btn-outline-secondary mb-3 d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Branches</span>
            </a>
            <h2 class="fw-bold tracking-tight mb-1" style="color: var(--text-primary);">Edit Branch Details</h2>
            <p class="text-muted small mb-0">Modify physical address, operational status, and update the assigned Branch Manager.</p>
        </div>

        <!-- Session Alerts -->
        <?php if ($flash_error = flash_get('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 small py-2.5 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?php echo sanitize($flash_error); ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Update Pipeline -->
        <form action="<?php echo base_url('index.php?route=branches-update'); ?>" method="POST" class="needs-validation" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$branch['id']; ?>">

            <div class="row g-4">
                <!-- Left Hand Columns: Primary Details -->
                <div class="col-12 col-lg-8">
                    <div class="custom-card mb-4" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;">
                            <i class="bi bi-building me-2"></i>Primary Details
                        </h4>
                        
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Branch Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo sanitize($branch['name']); ?>" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                <div class="invalid-feedback">Branch name is required.</div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Branch Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" value="<?php echo sanitize($branch['code']); ?>" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                <div class="invalid-feedback">Branch code is required.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Physical Address</label>
                                <textarea name="address" rows="3" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);"><?php echo sanitize($branch['address']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="custom-card" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;">
                            <i class="bi bi-telephone-fill me-2"></i>Contact & Management
                        </h4>
                        
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo sanitize($branch['phone']); ?>" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo sanitize($branch['email']); ?>" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Branch Manager</label>
                                <select name="manager_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="">Select Branch Manager (None Assigned)</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" <?php echo (int)$branch['manager_id'] === (int)$emp['id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Hand Column: Side Settings Panel -->
                <div class="col-12 col-lg-4">
                    <div class="custom-card mb-4" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;">
                            <i class="bi bi-sliders me-2"></i>Configurations
                        </h4>

                        <div class="mb-4">
                            <label class="form-label text-muted small fw-semibold">Branch Operational Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                <option value="Active" <?php echo $branch['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $branch['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-sm py-2 fw-semibold">
                                <i class="bi bi-check-lg me-1"></i> Update Branch
                            </button>
                            <a href="<?php echo base_url('index.php?route=branches'); ?>" class="btn btn-outline-secondary btn-sm py-2 fw-semibold">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Enable standard Bootstrap client-side validation
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
