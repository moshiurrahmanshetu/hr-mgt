<?php
/**
 * Show Employee File Detail (Employee Management Module)
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

$page_title = 'Employee Profile';
$db = Database::getConnection();

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    flash_set('error', 'Biographical lookup failed: Missing or invalid Employee ID parameter.');
    redirect('employees/index.php');
}

// 1. Fetch main employee record with left joins on master tables
$sql_emp = "SELECT e.*, d.name AS department_name, ds.title AS designation_title, b.name AS branch_name, r.name AS role_name, mgr.first_name AS mgr_first, mgr.last_name AS mgr_last, mgr.employee_code AS mgr_code
            FROM `employees` e
            LEFT JOIN `departments` d ON e.department_id = d.id
            LEFT JOIN `designations` ds ON e.designation_id = ds.id
            LEFT JOIN `branches` b ON e.branch_id = b.id
            LEFT JOIN `roles` r ON e.role_id = r.id
            LEFT JOIN `employees` mgr ON e.reporting_manager_id = mgr.id
            WHERE e.id = :id LIMIT 1";

$stmt_emp = $db->prepare($sql_emp);
$stmt_emp->execute(['id' => $id]);
$employee = $stmt_emp->fetch();

if (!$employee) {
    flash_set('error', 'Biographical lookup failed: The requested employee file does not exist.');
    redirect('employees/index.php');
}

// 2. Unpack Serialized Bank & Address Data
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

// 3. Fetch Salary structure
$stmt_sal = $db->prepare("SELECT * FROM `salary_structures` WHERE `employee_id` = :id LIMIT 1");
$stmt_sal->execute(['id' => $id]);
$salary = $stmt_sal->fetch();

// 4. Fetch all uploaded documents
$stmt_docs = $db->prepare("SELECT * FROM `employee_documents` WHERE `employee_id` = :id ORDER BY `created_at` DESC");
$stmt_docs->execute(['id' => $id]);
$documents = $stmt_docs->fetchAll();

// Map documents by type for direct access
$docs_by_type = [];
foreach ($documents as $doc) {
    $docs_by_type[$doc['document_type']] = $doc;
}

// 5. Fetch Attendance Summary Statistics
$stmt_att = $db->prepare("SELECT `status`, COUNT(*) as count FROM `attendance` WHERE `employee_id` = :id GROUP BY `status`");
$stmt_att->execute(['id' => $id]);
$att_res = $stmt_att->fetchAll();

$att_stats = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0, 'On Leave' => 0];
$total_attendance_records = 0;
foreach ($att_res as $r) {
    if (array_key_exists($r['status'], $att_stats)) {
        $att_stats[$r['status']] = (int)$r['count'];
    }
    $total_attendance_records += (int)$r['count'];
}

// Calculate Present Ratio
$present_count = $att_stats['Present'] + $att_stats['Late'] + $att_stats['Half Day'];
$present_rate = $total_attendance_records > 0 ? round(($present_count / $total_attendance_records) * 100, 1) : 100.0;

// 6. Fetch Leaves Request Summary Statistics
$stmt_leaves = $db->prepare("SELECT `status`, COUNT(*) as count FROM `leave_requests` WHERE `employee_id` = :id GROUP BY `status`");
$stmt_leaves->execute(['id' => $id]);
$leave_res = $stmt_leaves->fetchAll();

$leave_stats = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach ($leave_res as $r) {
    if (array_key_exists($r['status'], $leave_stats)) {
        $leave_stats[$r['status']] = (int)$r['count'];
    }
}

// Resolve Profile Avatar Image
$profile_photo_url = isset($docs_by_type['Profile Photo']) ? base_url($docs_by_type['Profile Photo']['file_path']) : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=180&h=180&q=80';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="main-content">
    <div class="content-body" data-aos="fade-up">
        <!-- Back and Edit Actions Header -->
        <div class="d-flex flex-col flex-sm-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
            <div>
                <a href="<?php echo base_url('employees/index.php'); ?>" class="btn btn-sm btn-outline-secondary mb-2 d-inline-flex align-items-center gap-1.5">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Directory</span>
                </a>
                <h2 class="fw-bold tracking-tight mb-1" style="color: var(--text-primary);"><?php echo sanitize($employee['first_name'] . ' ' . $employee['last_name']); ?></h2>
                <p class="text-muted small mb-0 font-mono">Employee Code: <?php echo sanitize($employee['employee_code']); ?></p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo base_url('employees/edit.php?id=' . $employee['id']); ?>" class="btn btn-warning d-flex align-items-center gap-1.5 py-2 px-3">
                    <i class="bi bi-pencil-fill"></i>
                    <span>Edit Profile</span>
                </a>
            </div>
        </div>

        <!-- Banner Card -->
        <div class="custom-card mb-4 p-4 overflow-hidden position-relative" style="background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%); border-left: 5px solid var(--accent-primary);">
            <div class="d-flex flex-column flex-md-row align-items-center gap-4 position-relative z-1">
                <!-- Avatar image -->
                <img src="<?php echo $profile_photo_url; ?>" alt="Employee Avatar" class="rounded-circle object-fit-cover border border-4 shadow-sm" style="width: 110px; height: 110px; border-color: var(--border-color) !important;" referrerPolicy="no-referrer">
                
                <div class="text-center text-md-start flex-grow-1">
                    <h3 class="fw-bold mb-1" style="color: var(--text-primary);"><?php echo sanitize($employee['first_name'] . ' ' . $employee['last_name']); ?></h3>
                    <p class="mb-2 text-primary fw-semibold" style="font-size: 0.95rem;">
                        <?php echo sanitize($employee['designation_title'] ?: 'Designation Pending'); ?> 
                        <span class="text-muted font-mono px-1">|</span> 
                        <span class="text-muted"><?php echo sanitize($employee['department_name'] ?: 'Department Pending'); ?></span>
                    </p>
                    <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-2.5">
                        <span class="badge bg-primary px-2.5 py-1.5 small"><i class="bi bi-building me-1.5"></i><?php echo sanitize($employee['branch_name'] ?: 'HQ Site'); ?></span>
                        <span class="badge bg-secondary px-2.5 py-1.5 small"><i class="bi bi-calendar2-check-fill me-1.5"></i>Hired: <?php echo format_date($employee['hire_date']); ?></span>
                        <?php
                        $badge_class = 'bg-success';
                        if ($employee['employment_status'] === 'Contract') $badge_class = 'bg-warning text-dark';
                        elseif ($employee['employment_status'] === 'Intern') $badge_class = 'bg-info text-dark';
                        elseif ($employee['employment_status'] === 'Terminated') $badge_class = 'bg-danger';
                        ?>
                        <span class="badge <?php echo $badge_class; ?> px-2.5 py-1.5 small"><?php echo sanitize($employee['employment_status']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Hand Sidebar: General & Contact Info, Emergency Contacts, Salaries, Banks -->
            <div class="col-12 col-lg-7 space-y-4">
                
                <!-- Personal & Biographical Card -->
                <div class="custom-card">
                    <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-person-badge-fill me-2"></i>Biographical Information</h4>
                    <div class="row g-3">
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Gender</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><?php echo sanitize($employee['gender']); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Date of Birth</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><?php echo $employee['date_of_birth'] ? format_date($employee['date_of_birth']) : 'Not specified'; ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Blood Group</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><?php echo sanitize($employee['blood_group'] ?: 'Not specified'); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Marital Status</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><?php echo sanitize($employee['marital_status'] ?: 'Not specified'); ?></span>
                        </div>
                        <div class="col-12">
                            <hr class="my-2" style="border-color: var(--border-color);">
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Corporate Email</span>
                            <span class="fw-semibold text-primary font-mono" style="color: var(--text-primary) !important;"><i class="bi bi-envelope text-muted me-1.5"></i><?php echo sanitize($employee['email']); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Mobile Contact</span>
                            <span class="fw-semibold text-primary font-mono" style="color: var(--text-primary) !important;"><i class="bi bi-telephone text-muted me-1.5"></i><?php echo sanitize($employee['phone'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block small mb-0.5">Residential Home Address</span>
                            <span class="fw-semibold text-primary d-block" style="color: var(--text-primary) !important;"><i class="bi bi-geo-alt text-muted me-1.5"></i><?php echo sanitize($address_data['home_address'] ?: 'Not specified'); ?></span>
                        </div>
                        <div class="col-12">
                            <hr class="my-2" style="border-color: var(--border-color);">
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">System Role</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><i class="bi bi-shield-lock text-muted me-1.5"></i><?php echo sanitize($employee['role_name'] ?: 'No Role Assigned'); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Reporting Manager</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;">
                                <i class="bi bi-person-workspace text-muted me-1.5"></i>
                                <?php echo $employee['reporting_manager_id'] ? sanitize($employee['mgr_first'] . ' ' . $employee['mgr_last']) . ' (' . sanitize($employee['mgr_code']) . ')' : 'No reporting manager assigned'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact Card -->
                <div class="custom-card">
                    <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-heart-pulse-fill me-2"></i>Emergency Contact</h4>
                    <div class="row g-3">
                        <div class="col-4">
                            <span class="text-muted d-block small mb-0.5">Contact Name</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><i class="bi bi-person text-muted me-1.5"></i><?php echo sanitize($employee['emergency_contact_name'] ?: 'None registered'); ?></span>
                        </div>
                        <div class="col-4">
                            <span class="text-muted d-block small mb-0.5">Contact Phone</span>
                            <span class="fw-semibold text-primary font-mono" style="color: var(--text-primary) !important;"><i class="bi bi-phone text-muted me-1.5"></i><?php echo sanitize($employee['emergency_contact_phone'] ?: 'None registered'); ?></span>
                        </div>
                        <div class="col-4">
                            <span class="text-muted d-block small mb-0.5">Relationship</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><i class="bi bi-heart text-muted me-1.5"></i><?php echo sanitize($employee['emergency_contact_relationship'] ?: 'Not specified'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Salary Structure & Payroll Settings -->
                <div class="custom-card">
                    <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-wallet2 me-2"></i>Compensation Structure</h4>
                    <?php if (!$salary): ?>
                        <div class="text-muted small py-2"><i class="bi bi-exclamation-circle text-warning me-2"></i>No registered salary structure found. Default base salary: <?php echo format_money($employee['salary']); ?></div>
                    <?php else: ?>
                        <div class="row g-3 small">
                            <div class="col-12 mb-2">
                                <div class="p-3 rounded border d-flex justify-content-between align-items-center" style="background-color: var(--bg-primary); border-color: var(--border-color) !important;">
                                    <span class="text-muted fw-semibold">Net Calculated Take-Home Salary</span>
                                    <span class="fs-5 fw-bold text-success font-mono"><?php echo format_money($employee['salary']); ?> <span style="font-size: 0.725rem; font-weight: normal;" class="text-muted">/ mo</span></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between border-bottom pb-1.5" style="border-color: var(--border-color) !important;">
                                    <span class="text-muted">Basic Salary</span>
                                    <span class="fw-semibold font-mono text-primary" style="color: var(--text-primary) !important;"><?php echo format_money($salary['basic_salary']); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between border-bottom pb-1.5" style="border-color: var(--border-color) !important;">
                                    <span class="text-muted text-danger">Provident Fund</span>
                                    <span class="fw-semibold font-mono text-danger"><?php echo format_money($salary['provident_fund']); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between border-bottom pb-1.5" style="border-color: var(--border-color) !important;">
                                    <span class="text-muted">House Rent Allowance</span>
                                    <span class="fw-semibold font-mono text-primary" style="color: var(--text-primary) !important;"><?php echo format_money($salary['house_rent_allowance']); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between border-bottom pb-1.5" style="border-color: var(--border-color) !important;">
                                    <span class="text-muted text-danger">Professional Tax</span>
                                    <span class="fw-semibold font-mono text-danger"><?php echo format_money($salary['professional_tax']); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between border-bottom pb-1.5" style="border-color: var(--border-color) !important;">
                                    <span class="text-muted">Medical Allowance</span>
                                    <span class="fw-semibold font-mono text-primary" style="color: var(--text-primary) !important;"><?php echo format_money($salary['medical_allowance']); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between border-bottom pb-1.5" style="border-color: var(--border-color) !important;">
                                    <span class="text-muted text-danger">Other Deductions</span>
                                    <span class="fw-semibold font-mono text-danger"><?php echo format_money($salary['other_deductions']); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between border-bottom pb-1.5" style="border-color: var(--border-color) !important;">
                                    <span class="text-muted">Conveyance Allowance</span>
                                    <span class="fw-semibold font-mono text-primary" style="color: var(--text-primary) !important;"><?php echo format_money($salary['conveyance_allowance']); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between border-bottom pb-1.5" style="border-color: var(--border-color) !important;">
                                    <span class="text-muted">Other Allowances</span>
                                    <span class="fw-semibold font-mono text-primary" style="color: var(--text-primary) !important;"><?php echo format_money($salary['other_allowances']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Bank Transfer Account Card -->
                <div class="custom-card">
                    <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-bank me-2"></i>Bank Transfer Registry</h4>
                    <div class="row g-3">
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Bank Institution</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><?php echo sanitize($address_data['bank_name'] ?: 'None registered'); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Account Name</span>
                            <span class="fw-semibold text-primary" style="color: var(--text-primary) !important;"><?php echo sanitize($address_data['bank_account_name'] ?: 'None registered'); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Account Number</span>
                            <span class="fw-semibold text-primary font-mono" style="color: var(--text-primary) !important;"><?php echo sanitize($address_data['bank_account_number'] ?: 'None registered'); ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small mb-0.5">Routing Transit (ABA)</span>
                            <span class="fw-semibold text-primary font-mono" style="color: var(--text-primary) !important;"><?php echo sanitize($address_data['bank_routing_number'] ?: 'None registered'); ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Hand Column: Verification Documents & Live Attendance Metrics -->
            <div class="col-12 col-lg-5 space-y-4">
                
                <!-- Dynamic Metrics Board -->
                <div class="custom-card">
                    <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-graph-up me-2"></i>Performance & Presence</h4>
                    
                    <!-- Attendance Speedometer -->
                    <div class="p-3 rounded mb-3" style="background-color: var(--bg-primary);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted fw-semibold">Attendance Attendance Rate</span>
                            <span class="small fw-bold font-mono text-success"><?php echo $present_rate; ?>%</span>
                        </div>
                        <div class="progress" style="height: 10px; background-color: var(--bg-tertiary);">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $present_rate; ?>%" aria-valuenow="<?php echo $present_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2.5 text-muted" style="font-size: 0.725rem;">
                            <span>Present: <?php echo $att_stats['Present'] + $att_stats['Late'] + $att_stats['Half Day']; ?> days</span>
                            <span>Absent: <?php echo $att_stats['Absent']; ?> days</span>
                            <span>Leaves: <?php echo $att_stats['On Leave']; ?> days</span>
                        </div>
                    </div>

                    <!-- Leaves Request Scoreboard -->
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 border.rounded" style="background-color: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px;">
                                <div class="fs-5 fw-bold text-success font-mono"><?php echo $leave_stats['Approved']; ?></div>
                                <span class="text-muted text-[10px] d-block text-uppercase fw-semibold">Approved Leaves</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border.rounded" style="background-color: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px;">
                                <div class="fs-5 fw-bold text-warning font-mono"><?php echo $leave_stats['Pending']; ?></div>
                                <span class="text-muted text-[10px] d-block text-uppercase fw-semibold">Pending Leaves</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border.rounded" style="background-color: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px;">
                                <div class="fs-5 fw-bold text-danger font-mono"><?php echo $leave_stats['Rejected']; ?></div>
                                <span class="text-muted text-[10px] d-block text-uppercase fw-semibold">Rejected Leaves</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Chest Card -->
                <div class="custom-card">
                    <h4 class="card-title text-primary border-bottom pb-2 mb-3" style="font-size: 1rem;"><i class="bi bi-file-earmark-lock-fill me-2"></i>Official Verification Documents</h4>
                    <div class="space-y-3">
                        
                        <!-- 1. National ID Document -->
                        <div class="p-3 rounded border d-flex justify-content-between align-items-center" style="background-color: var(--bg-primary); border-color: var(--border-color) !important;">
                            <div class="d-flex align-items-center gap-2.5">
                                <i class="bi bi-card-image fs-4 text-primary"></i>
                                <div>
                                    <div class="fw-semibold small mb-0.5" style="color: var(--text-primary);">National ID</div>
                                    <span class="text-muted font-mono" style="font-size: 0.65rem;">Requirement: Mandatory</span>
                                </div>
                            </div>
                            <div>
                                <?php if (isset($docs_by_type['National ID'])): ?>
                                    <a href="<?php echo base_url($docs_by_type['National ID']['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 py-1 px-2.5">
                                        <i class="bi bi-download"></i>
                                        <span>Download</span>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-dark px-2 py-1.5 font-mono" style="font-size: 0.65rem;">Unattached</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 2. Passport Document -->
                        <div class="p-3 rounded border d-flex justify-content-between align-items-center" style="background-color: var(--bg-primary); border-color: var(--border-color) !important;">
                            <div class="d-flex align-items-center gap-2.5">
                                <i class="bi bi-journal-bookmark-fill fs-4 text-success"></i>
                                <div>
                                    <div class="fw-semibold small mb-0.5" style="color: var(--text-primary);">Passport Document</div>
                                    <span class="text-muted font-mono" style="font-size: 0.65rem;">Requirement: Optional</span>
                                </div>
                            </div>
                            <div>
                                <?php if (isset($docs_by_type['Passport'])): ?>
                                    <a href="<?php echo base_url($docs_by_type['Passport']['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 py-1 px-2.5">
                                        <i class="bi bi-download"></i>
                                        <span>Download</span>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-dark px-2 py-1.5 font-mono" style="font-size: 0.65rem;">Unattached</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 3. Resume CV Document -->
                        <div class="p-3 rounded border d-flex justify-content-between align-items-center" style="background-color: var(--bg-primary); border-color: var(--border-color) !important;">
                            <div class="d-flex align-items-center gap-2.5">
                                <i class="bi bi-file-pdf-fill fs-4 text-warning"></i>
                                <div>
                                    <div class="fw-semibold small mb-0.5" style="color: var(--text-primary);">Curriculum Vitae (CV)</div>
                                    <span class="text-muted font-mono" style="font-size: 0.65rem;">Requirement: Highly Recommended</span>
                                </div>
                            </div>
                            <div>
                                <?php if (isset($docs_by_type['Resume'])): ?>
                                    <a href="<?php echo base_url($docs_by_type['Resume']['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 py-1 px-2.5">
                                        <i class="bi bi-download"></i>
                                        <span>Download</span>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-dark px-2 py-1.5 font-mono" style="font-size: 0.65rem;">Unattached</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 4. Academic Certificates Document -->
                        <div class="p-3 rounded border d-flex justify-content-between align-items-center" style="background-color: var(--bg-primary); border-color: var(--border-color) !important;">
                            <div class="d-flex align-items-center gap-2.5">
                                <i class="bi bi-award fs-4 text-danger"></i>
                                <div>
                                    <div class="fw-semibold small mb-0.5" style="color: var(--text-primary);">Academic Certificates</div>
                                    <span class="text-muted font-mono" style="font-size: 0.65rem;">Requirement: Optional</span>
                                </div>
                            </div>
                            <div>
                                <?php if (isset($docs_by_type['Certificates'])): ?>
                                    <a href="<?php echo base_url($docs_by_type['Certificates']['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 py-1 px-2.5">
                                        <i class="bi bi-download"></i>
                                        <span>Download</span>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-dark px-2 py-1.5 font-mono" style="font-size: 0.65rem;">Unattached</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 5. Other Document Attachment -->
                        <div class="p-3 rounded border d-flex justify-content-between align-items-center" style="background-color: var(--bg-primary); border-color: var(--border-color) !important;">
                            <div class="d-flex align-items-center gap-2.5">
                                <i class="bi bi-paperclip fs-4 text-info"></i>
                                <div>
                                    <div class="fw-semibold small mb-0.5" style="color: var(--text-primary);">Other Document Annexes</div>
                                    <span class="text-muted font-mono" style="font-size: 0.65rem;">Requirement: Optional</span>
                                </div>
                            </div>
                            <div>
                                <?php if (isset($docs_by_type['Other Document'])): ?>
                                    <a href="<?php echo base_url($docs_by_type['Other Document']['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 py-1 px-2.5">
                                        <i class="bi bi-download"></i>
                                        <span>Download</span>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-dark px-2 py-1.5 font-mono" style="font-size: 0.65rem;">Unattached</span>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
