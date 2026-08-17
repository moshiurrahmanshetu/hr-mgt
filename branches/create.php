<?php
/**
 * Create Branch Form (Organization Module)
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

$page_title = 'Create Corporate Branch';
$db = Database::getConnection();

// Fetch active employees to assign as manager
$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM `employees` WHERE `employment_status` != 'Terminated' ORDER BY `first_name` ASC")->fetchAll();

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
            <h2 class="fw-bold tracking-tight mb-1" style="color: var(--text-primary);">Create New Branch</h2>
            <p class="text-muted small mb-0">Define an operational physical office, localized details, and assign an active Branch Manager.</p>
        </div>

        <!-- Session Alerts -->
        <?php if ($flash_error = flash_get('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 small py-2.5 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?php echo sanitize($flash_error); ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Creation Pipeline -->
        <form action="<?php echo base_url('index.php?route=branches-store'); ?>" method="POST" class="needs-validation" novalidate>
            <?php echo csrf_field(); ?>

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
                                <input type="text" name="name" class="form-control" placeholder="e.g. Headquarters, Europe Office" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                <div class="invalid-feedback">Branch name is required.</div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Branch Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" placeholder="e.g. HQ-01, EU-02" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                <div class="invalid-feedback">Branch code is required.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Physical Address</label>
                                <textarea name="address" rows="3" class="form-control" placeholder="e.g. 100 Silicon Blvd, Suite 400" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);"></textarea>
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
                                <input type="text" name="phone" class="form-control" placeholder="e.g. +1-555-0199" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="e.g. branch@enterprise.com" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Branch Manager</label>
                                <select name="manager_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="">Select Branch Manager (None Assigned)</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>">
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
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-sm py-2 fw-semibold">
                                <i class="bi bi-save me-1"></i> Register Branch
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
