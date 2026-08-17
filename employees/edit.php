<?php
/**
 * Edit Employee Biographical Profile (Employee Management Module)
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

$page_title = 'Modify Employee Profile';
$db = Database::getConnection();

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    flash_set('error', 'Modification Lookup Error: Missing or invalid Employee ID parameter.');
    redirect('employees/index.php');
}

// Fetch main employee biographical record
$stmt_emp = $db->prepare("SELECT * FROM `employees` WHERE `id` = :id LIMIT 1");
$stmt_emp->execute(['id' => $id]);
$employee = $stmt_emp->fetch();

if (!$employee) {
    flash_set('error', 'Modification Lookup Error: Employee record not found.');
    redirect('employees/index.php');
}

// Unpack Serialized Bank & Address Data
$address_data = [
    'home_address' => '',
    'bank_name' => '',
    'bank_account_name' => '',
    'bank_account_number' => '',
    'bank_routing_number' => ''
];

if (!empty($employee['address'])) {
    $decoded = json_decode($employee['address'], true);
    if (is_array($decoded)) {
        $address_data = array_merge($address_data, $decoded);
    } else {
        $address_data['home_address'] = $employee['address']; // fallback if raw text
    }
}

// Fetch Salary structure
$stmt_sal = $db->prepare("SELECT * FROM `salary_structures` WHERE `employee_id` = :id LIMIT 1");
$stmt_sal->execute(['id' => $id]);
$salary = $stmt_sal->fetch();

// Fetch active branches, departments, designations, and roles for dropdown selection
$branches = $db->query("SELECT * FROM `branches` WHERE `deleted_at` IS NULL AND `status` = 'Active' ORDER BY `name` ASC")->fetchAll();
$departments = $db->query("SELECT * FROM `departments` WHERE `deleted_at` IS NULL AND `status` = 'Active' ORDER BY `name` ASC")->fetchAll();
$designations = $db->query("SELECT d.*, dept.name AS dept_name FROM `designations` d LEFT JOIN `departments` dept ON d.department_id = dept.id WHERE d.deleted_at IS NULL AND d.status = 'Active' AND dept.deleted_at IS NULL AND dept.status = 'Active' ORDER BY dept.name ASC, d.title ASC")->fetchAll();
$roles = $db->query("SELECT * FROM `roles` WHERE `deleted_at` IS NULL AND `status` = 'Active' ORDER BY `name` ASC")->fetchAll();
$managers = $db->prepare("SELECT `id`, `first_name`, `last_name`, `employee_code` FROM `employees` WHERE `employment_status` != 'Terminated' AND `id` != ? ORDER BY `first_name` ASC, `last_name` ASC");
$managers->execute([$id]);
$managers = $managers->fetchAll();

// Fetch active documents to display filenames
$stmt_docs = $db->prepare("SELECT `document_type`, `file_path` FROM `employee_documents` WHERE `employee_id` = ?");
$stmt_docs->execute([$id]);
$documents = $stmt_docs->fetchAll();
$docs_by_type = [];
foreach ($documents as $doc) {
    $docs_by_type[$doc['document_type']] = $doc;
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="main-content">
    <div class="content-body" data-aos="fade-up">
        <!-- Back Link & Header -->
        <div class="mb-4">
            <a href="<?php echo base_url('employees/show.php?id=' . $employee['id']); ?>" class="btn btn-sm btn-outline-secondary mb-3 d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Employee Details</span>
            </a>
            <h2 class="fw-bold tracking-tight mb-1" style="color: var(--text-primary);">Modify Employee Profile</h2>
            <p class="text-muted small mb-0 font-mono">Employee ID Reference: <?php echo sanitize($employee['employee_code']); ?></p>
        </div>

        <!-- Session Alerts -->
        <?php if ($flash_error = flash_get('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 small py-2.5 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?php echo sanitize($flash_error); ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Registration Pipeline -->
        <form action="<?php echo base_url('employees/update.php'); ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">

            <div class="row g-4">
                <!-- Left Hand Columns: Multi-Section Information Form -->
                <div class="col-12 col-lg-8 space-y-4">
                    
                    <!-- SECTION 1: Personal Information -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-person-fill me-2"></i>Personal Information</h4>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($employee['first_name']); ?>" required>
                                <div class="invalid-feedback">First name is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($employee['last_name']); ?>" required>
                                <div class="invalid-feedback">Last name is required.</div>
                            </div>
                             <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                    <option value="Male" <?php echo $employee['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $employee['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $employee['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    <option value="Prefer Not to Say" <?php echo $employee['gender'] === 'Prefer Not to Say' ? 'selected' : ''; ?>>Prefer Not to Say</option>
                                </select>
                                <div class="invalid-feedback">Gender selection is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $employee['date_of_birth']; ?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Blood Group</label>
                                <select name="blood_group" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo ($employee['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($employee['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($employee['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($employee['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo ($employee['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($employee['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo ($employee['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($employee['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Marital Status</label>
                                <select name="marital_status" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="">Select Marital Status</option>
                                    <option value="Single" <?php echo ($employee['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo ($employee['marital_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?php echo ($employee['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo ($employee['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="Prefer Not to Say" <?php echo ($employee['marital_status'] ?? '') === 'Prefer Not to Say' ? 'selected' : ''; ?>>Prefer Not to Say</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Contact Information -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-telephone-fill me-2"></i>Contact Information</h4>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Official Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($employee['email']); ?>" required>
                                <div class="invalid-feedback">Please enter a valid unique corporate email.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Phone Number</label>
                                <input type="text" name="phone" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($employee['phone']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Home Address</label>
                                <textarea name="address" class="form-control" rows="2" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);"><?php echo sanitize($address_data['home_address']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: Employment Information -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-briefcase-fill me-2"></i>Employment Details</h4>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Branch Site <span class="text-danger">*</span></label>
                                <select name="branch_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach ($branches as $br): ?>
                                        <option value="<?php echo $br['id']; ?>" <?php echo $employee['branch_id'] == $br['id'] ? 'selected' : ''; ?>><?php echo sanitize($br['name']) . ' (' . sanitize($br['code']) . ')'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Branch selection is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Department <span class="text-danger">*</span></label>
                                <select name="department_id" id="department_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo $employee['department_id'] == $dept['id'] ? 'selected' : ''; ?>><?php echo sanitize($dept['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Department selection is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Designation <span class="text-danger">*</span></label>
                                <select name="designation_id" id="designation_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                    <option value="">Select Designation</option>
                                    <?php foreach ($designations as $desg): ?>
                                        <option value="<?php echo $desg['id']; ?>" data-dept="<?php echo $desg['department_id']; ?>" <?php echo $employee['designation_id'] == $desg['id'] ? 'selected' : ''; ?>><?php echo sanitize($desg['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Designation selection is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Employee ID Code <span class="text-danger">*</span></label>
                                <input type="text" name="employee_code" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($employee['employee_code']); ?>" required>
                                <div class="invalid-feedback">Unique Employee Code is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Date of Joining <span class="text-danger">*</span></label>
                                <input type="date" name="hire_date" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $employee['hire_date']; ?>" required>
                                <div class="invalid-feedback">Joining date is required.</div>
                            </div>
                             <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Employment Status <span class="text-danger">*</span></label>
                                <select name="employment_status" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                    <option value="Full-Time" <?php echo $employee['employment_status'] === 'Full-Time' ? 'selected' : ''; ?>>Full-Time</option>
                                    <option value="Part-Time" <?php echo $employee['employment_status'] === 'Part-Time' ? 'selected' : ''; ?>>Part-Time</option>
                                    <option value="Contract" <?php echo $employee['employment_status'] === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                    <option value="Intern" <?php echo $employee['employment_status'] === 'Intern' ? 'selected' : ''; ?>>Intern</option>
                                    <option value="Terminated" <?php echo $employee['employment_status'] === 'Terminated' ? 'selected' : ''; ?>>Terminated (Soft-Deleted)</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">System Role <span class="text-danger">*</span></label>
                                <select name="role_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r['id']; ?>" <?php echo $employee['role_id'] == $r['id'] ? 'selected' : ''; ?>><?php echo sanitize($r['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">System Role selection is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Reporting Manager</label>
                                <select name="reporting_manager_id" class="form-select" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="">Select Reporting Manager (Active Employees)</option>
                                    <?php foreach ($managers as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" <?php echo $employee['reporting_manager_id'] == $m['id'] ? 'selected' : ''; ?>><?php echo sanitize($m['first_name'] . ' ' . $m['last_name']) . ' (' . sanitize($m['employee_code']) . ')'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: Salary & Compensation -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-wallet2 me-2"></i>Salary Information</h4>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label text-muted small fw-semibold">Basic Salary ($/mo) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="basic_salary" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $salary ? $salary['basic_salary'] : '0.00'; ?>" required>
                                <div class="invalid-feedback">Basic salary amount is required.</div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label text-muted small fw-semibold">House Rent Allowance</label>
                                <input type="number" step="0.01" name="house_rent_allowance" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $salary ? $salary['house_rent_allowance'] : '0.00'; ?>">
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label text-muted small fw-semibold">Medical Allowance</label>
                                <input type="number" step="0.01" name="medical_allowance" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $salary ? $salary['medical_allowance'] : '0.00'; ?>">
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label text-muted small fw-semibold">Conveyance Allowance</label>
                                <input type="number" step="0.01" name="conveyance_allowance" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $salary ? $salary['conveyance_allowance'] : '0.00'; ?>">
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label text-muted small fw-semibold">Other Allowances</label>
                                <input type="number" step="0.01" name="other_allowances" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $salary ? $salary['other_allowances'] : '0.00'; ?>">
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label text-muted small fw-semibold">Provident Fund Deduction</label>
                                <input type="number" step="0.01" name="provident_fund" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $salary ? $salary['provident_fund'] : '0.00'; ?>">
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label text-muted small fw-semibold">Professional Tax</label>
                                <input type="number" step="0.01" name="professional_tax" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $salary ? $salary['professional_tax'] : '0.00'; ?>">
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label text-muted small fw-semibold">Other Deductions</label>
                                <input type="number" step="0.01" name="other_deductions" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo $salary ? $salary['other_deductions'] : '0.00'; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 5: Bank Account Information -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-bank me-2"></i>Bank Transfer Details</h4>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($address_data['bank_name']); ?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Account Holder Name</label>
                                <input type="text" name="bank_account_name" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($address_data['bank_account_name']); ?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Account Number</label>
                                <input type="text" name="bank_account_number" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($address_data['bank_account_number']); ?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Routing Transit Code (ABA)</label>
                                <input type="text" name="bank_routing_number" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($address_data['bank_routing_number']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 6: Emergency Contacts -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-heart-pulse-fill me-2"></i>Emergency Contact Details</h4>
                        <div class="row g-3">
                             <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Contact Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="emergency_contact_name" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($employee['emergency_contact_name']); ?>" required>
                                <div class="invalid-feedback">Emergency contact name is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Contact Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="emergency_contact_phone" class="form-control font-mono" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($employee['emergency_contact_phone']); ?>" required>
                                <div class="invalid-feedback">Emergency contact phone number is required.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-muted small fw-semibold">Relationship</label>
                                <input type="text" name="emergency_contact_relationship" class="form-control" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" value="<?php echo sanitize($employee['emergency_contact_relationship'] ?? ''); ?>" placeholder="e.g. Spouse, Parent, Sibling">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Hand Column: File Uploads & Save Commands -->
                <div class="col-12 col-lg-4 space-y-4">
                    
                    <!-- UPLOAD COMPONENT: Profile Photo -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-image me-2"></i>Profile Photo</h4>
                        
                        <?php if (isset($docs_by_type['Profile Photo'])): ?>
                            <div class="mb-3 text-center">
                                <img src="<?php echo base_url($docs_by_type['Profile Photo']['file_path']); ?>" alt="Current Avatar" class="rounded-circle object-fit-cover border border-secondary shadow-sm mb-2" style="width: 80px; height: 80px;" referrerPolicy="no-referrer">
                                <div class="text-muted small">Current Photo Active</div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Upload New Photo File (Replaces Current)</label>
                            <input type="file" name="profile_photo" class="form-control form-control-sm" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" accept="image/png, image/jpeg, image/jpg">
                            <p class="text-muted text-[10px] mt-1.5 font-mono">Format: JPG, PNG. Size: Max 2MB.</p>
                        </div>
                    </div>

                    <!-- UPLOAD COMPONENT: Documents Chest -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-file-earmark-pdf-fill me-2"></i>Official Documents</h4>
                        
                        <!-- National ID -->
                        <div class="mb-3.5 pb-3 border-bottom" style="border-color: var(--border-color) !important;">
                            <label class="form-label text-muted small fw-semibold">National ID File</label>
                            <?php if (isset($docs_by_type['National ID'])): ?>
                                <div class="small mb-2 font-mono text-success"><i class="bi bi-check-circle-fill me-1.5"></i>Already uploaded: <a href="<?php echo base_url($docs_by_type['National ID']['file_path']); ?>" target="_blank" class="text-decoration-underline text-success">View File</a></div>
                            <?php endif; ?>
                            <input type="file" name="national_id" class="form-control form-control-sm" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" accept="application/pdf, image/png, image/jpeg">
                        </div>

                        <!-- Passport -->
                        <div class="mb-3.5 pb-3 border-bottom" style="border-color: var(--border-color) !important;">
                            <label class="form-label text-muted small fw-semibold">Passport File</label>
                            <?php if (isset($docs_by_type['Passport'])): ?>
                                <div class="small mb-2 font-mono text-success"><i class="bi bi-check-circle-fill me-1.5"></i>Already uploaded: <a href="<?php echo base_url($docs_by_type['Passport']['file_path']); ?>" target="_blank" class="text-decoration-underline text-success">View File</a></div>
                            <?php endif; ?>
                            <input type="file" name="passport" class="form-control form-control-sm" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" accept="application/pdf, image/png, image/jpeg">
                        </div>
                        
                        <!-- Resume CV -->
                        <div class="mb-3.5 pb-3 border-bottom" style="border-color: var(--border-color) !important;">
                            <label class="form-label text-muted small fw-semibold">Resume / CV File</label>
                            <?php if (isset($docs_by_type['Resume'])): ?>
                                <div class="small mb-2 font-mono text-success"><i class="bi bi-check-circle-fill me-1.5"></i>Already uploaded: <a href="<?php echo base_url($docs_by_type['Resume']['file_path']); ?>" target="_blank" class="text-decoration-underline text-success">View File</a></div>
                            <?php endif; ?>
                            <input type="file" name="resume" class="form-control form-control-sm" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" accept="application/pdf, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        </div>

                        <!-- Academic Certificates -->
                        <div class="mb-3.5 pb-3 border-bottom" style="border-color: var(--border-color) !important;">
                            <label class="form-label text-muted small fw-semibold">Academic Certificates File</label>
                            <?php if (isset($docs_by_type['Certificates'])): ?>
                                <div class="small mb-2 font-mono text-success"><i class="bi bi-check-circle-fill me-1.5"></i>Already uploaded: <a href="<?php echo base_url($docs_by_type['Certificates']['file_path']); ?>" target="_blank" class="text-decoration-underline text-success">View File</a></div>
                            <?php endif; ?>
                            <input type="file" name="certificates" class="form-control form-control-sm" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" accept="application/pdf, image/png, image/jpeg">
                        </div>

                        <!-- Other Docs -->
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Other Documents</label>
                            <?php if (isset($docs_by_type['Other Document'])): ?>
                                <div class="small mb-2 font-mono text-success"><i class="bi bi-check-circle-fill me-1.5"></i>Already uploaded: <a href="<?php echo base_url($docs_by_type['Other Document']['file_path']); ?>" target="_blank" class="text-decoration-underline text-success">View File</a></div>
                            <?php endif; ?>
                            <input type="file" name="other_docs" class="form-control form-control-sm" style="background-color: var(--bg-primary); border-color: var(--border-color); color: var(--text-primary);" accept="application/pdf, image/png, image/jpeg">
                        </div>

                        <p class="text-muted text-[10px] mt-1.5 font-mono">Accepts: PDF, DOC, DOCX, PNG, JPG. Size: Max 5MB per document.</p>
                    </div>

                    <!-- SUBMIT & RESET COMMANDS -->
                    <div class="custom-card">
                        <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-shield-fill-check me-2"></i>Actions</h4>
                        <button type="submit" class="btn btn-warning w-100 py-2.5 mb-2 d-flex align-items-center justify-content-center gap-2 font-semibold">
                            <i class="bi bi-person-check-fill"></i>
                            <span>Update Employee Profile</span>
                        </button>
                        <a href="<?php echo base_url('employees/show.php?id=' . $employee['id']); ?>" class="btn btn-outline-secondary w-100 py-2.5 d-flex align-items-center justify-content-center gap-2 text-muted">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel Changes</span>
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

    // Dynamic Designation Filter based on Selected Department
    const deptSelect = document.getElementById('department_id');
    const desgSelect = document.getElementById('designation_id');
    const originalOptions = Array.from(desgSelect.options);

    function filterDesignations() {
        if (!deptSelect || !desgSelect) return;
        const selectedDeptId = deptSelect.value;
        const currentSelectedVal = desgSelect.value;
        
        // Clear current options
        desgSelect.innerHTML = '';
        
        // Re-add placeholder
        desgSelect.appendChild(originalOptions[0]);
        
        // Filter and append
        originalOptions.forEach(opt => {
            if (opt.value && opt.getAttribute('data-dept') === selectedDeptId) {
                desgSelect.appendChild(opt);
            }
        });
        
        // Re-select original designation if it matches the department
        let found = false;
        Array.from(desgSelect.options).forEach(opt => {
            if (opt.value === currentSelectedVal) {
                desgSelect.value = currentSelectedVal;
                found = true;
            }
        });
        
        if (!found) {
            desgSelect.selectedIndex = 0;
        }
    }

    if (deptSelect && desgSelect) {
        deptSelect.addEventListener('change', filterDesignations);
        // Execute on load to pre-filter properly
        filterDesignations();
    }
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
