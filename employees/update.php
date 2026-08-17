<?php
/**
 * Update Employee Controller (Employee Management Module)
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error', 'Invalid request method. Record update must be sent via POST.');
    redirect('employees/index.php');
}

$db = Database::getConnection();

// 1. CSRF Token Verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed. Please try again.');
    redirect('employees/index.php');
}

// 2. Validate and retrieve ID
$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$POST['id'] : 0;
if ($id <= 0) {
    flash_set('error', 'Update Error: Missing or invalid Employee ID reference.');
    redirect('employees/index.php');
}

// Check employee exists
$stmt_check = $db->prepare("SELECT * FROM `employees` WHERE `id` = ?");
$stmt_check->execute([$id]);
$employee = $stmt_check->fetch();
if (!$employee) {
    flash_set('error', 'Update Error: The requested employee record does not exist.');
    redirect('employees/index.php');
}

// 3. Extract and Sanitize Inputs
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$gender = trim($_POST['gender'] ?? 'Prefer Not to Say');
$date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
$blood_group = !empty($_POST['blood_group']) ? trim($_POST['blood_group']) : null;
$marital_status = !empty($_POST['marital_status']) ? trim($_POST['marital_status']) : null;

$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

$branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
$department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$designation_id = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
$role_id = !empty($_POST['role_id']) ? (int)$_POST['role_id'] : null;
$reporting_manager_id = !empty($_POST['reporting_manager_id']) ? (int)$_POST['reporting_manager_id'] : null;
$employee_code = trim($_POST['employee_code'] ?? '');
$hire_date = trim($_POST['hire_date'] ?? '');
$employment_status = trim($_POST['employment_status'] ?? 'Full-Time');

$basic_salary = (float)($_POST['basic_salary'] ?? 0.00);
$house_rent_allowance = (float)($_POST['house_rent_allowance'] ?? 0.00);
$medical_allowance = (float)($_POST['medical_allowance'] ?? 0.00);
$conveyance_allowance = (float)($_POST['conveyance_allowance'] ?? 0.00);
$other_allowances = (float)($_POST['other_allowances'] ?? 0.00);
$provident_fund = (float)($_POST['provident_fund'] ?? 0.00);
$professional_tax = (float)($_POST['professional_tax'] ?? 0.00);
$other_deductions = (float)($_POST['other_deductions'] ?? 0.00);

$bank_name = trim($_POST['bank_name'] ?? '');
$bank_account_name = trim($_POST['bank_account_name'] ?? '');
$bank_account_number = trim($_POST['bank_account_number'] ?? '');
$bank_routing_number = trim($_POST['bank_routing_number'] ?? '');

$emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
$emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
$emergency_contact_relationship = trim($_POST['emergency_contact_relationship'] ?? '');

// Calculate total net/base salary to save on main employee profile
$total_salary = ($basic_salary + $house_rent_allowance + $medical_allowance + $conveyance_allowance + $other_allowances) - ($provident_fund + $professional_tax + $other_deductions);

// 4. Robust Validation Checks
$errors = [];

if (empty($first_name)) $errors[] = "First name is a required field.";
if (empty($last_name)) $errors[] = "Last name is a required field.";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid corporate email address is required.";
if (empty($employee_code)) $errors[] = "Employee unique code is required.";
if (empty($hire_date)) $errors[] = "Joining hire date is required.";
if (empty($branch_id)) $errors[] = "Branch selection is required.";
if (empty($department_id)) $errors[] = "Department selection is required.";
if (empty($designation_id)) $errors[] = "Designation selection is required.";
if (empty($role_id)) $errors[] = "System Role selection is required.";
if ($basic_salary <= 0) $errors[] = "Basic salary must be a positive decimal number.";
if (empty($emergency_contact_name)) $errors[] = "Emergency contact name is required.";
if (empty($emergency_contact_phone)) $errors[] = "Emergency contact phone is required.";

// Verify uniqueness of Email excluding self
$stmt = $db->prepare("SELECT COUNT(*) FROM `employees` WHERE `email` = ? AND `id` != ?");
$stmt->execute([$email, $id]);
if ((int)$stmt->fetchColumn() > 0) {
    $errors[] = "The corporate email address '$email' is already assigned to another personnel profile.";
}

// Verify uniqueness of Phone excluding self
if (!empty($phone)) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM `employees` WHERE `phone` = ? AND `id` != ?");
    $stmt->execute([$phone, $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors[] = "The phone number '$phone' is already assigned to another personnel profile.";
    }
}

// Verify uniqueness of Employee Code excluding self
$stmt = $db->prepare("SELECT COUNT(*) FROM `employees` WHERE `employee_code` = ? AND `id` != ?");
$stmt->execute([$employee_code, $id]);
if ((int)$stmt->fetchColumn() > 0) {
    $errors[] = "The Employee Code '$employee_code' is already assigned to another personnel profile.";
}

// Redirect back if validation fails
if (!empty($errors)) {
    flash_set('error', implode('<br>', $errors));
    redirect('employees/edit.php?id=' . $id);
}

// 5. Secure File Upload Handler
$upload_base = __DIR__ . '/../uploads';
if (!is_dir($upload_base)) mkdir($upload_base, 0755, true);
if (!is_dir($upload_base . '/photos')) mkdir($upload_base . '/photos', 0755, true);
if (!is_dir($upload_base . '/documents')) mkdir($upload_base . '/documents', 0755, true);

$uploaded_files = []; // Mapping: document_type => relative_file_path

$allowed_photo_types = ['image/jpeg', 'image/jpg', 'image/png'];
$allowed_doc_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

$process_upload = function($file_field, $is_photo, $max_size, $allowed_mime_types, $subfolder) use (&$uploaded_files, &$errors) {
    if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_field];
        
        // Size validation
        if ($file['size'] > $max_size) {
            $errors[] = "File size of " . $file_field . " exceeds permissible limit (" . ($max_size / 1024 / 1024) . "MB).";
            return false;
        }

        // MIME validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime_types, true)) {
            $errors[] = "File type of " . $file_field . " is not supported. Upload a valid image or PDF.";
            return false;
        }

        // Secure file name generation (Anti-Path-Traversal / Filename Obfuscation)
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = bin2hex(random_bytes(16)) . '.' . $ext;
        $relative_path = 'uploads/' . $subfolder . '/' . $new_name;
        $destination = __DIR__ . '/../' . $relative_path;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $uploaded_files[$file_field] = $relative_path;
            return true;
        } else {
            $errors[] = "Failed to finalize upload of " . $file_field . ". Permission errors.";
            return false;
        }
    }
    return true;
};

// Execute Upload validations
$process_upload('profile_photo', true, 2097152, $allowed_photo_types, 'photos');
$process_upload('national_id', false, 5242880, $allowed_doc_types, 'documents');
$process_upload('passport', false, 5242880, $allowed_doc_types, 'documents');
$process_upload('resume', false, 5242880, $allowed_doc_types, 'documents');
$process_upload('certificates', false, 5242880, $allowed_doc_types, 'documents');
$process_upload('other_docs', false, 5242880, $allowed_doc_types, 'documents');

if (!empty($errors)) {
    // Purge any successfully uploaded files to keep server clean on failure
    foreach ($uploaded_files as $relative_path) {
        @unlink(__DIR__ . '/../' . $relative_path);
    }
    flash_set('error', implode('<br>', $errors));
    redirect('employees/edit.php?id=' . $id);
}

// 6. SQL Transaction Implementation
try {
    $db->beginTransaction();

    // Serialize Bank Account & Home Address inside TEXT column 'address' to bypass database design constraints
    $combined_address = json_encode([
        'home_address' => $address,
        'bank_name' => $bank_name,
        'bank_account_name' => $bank_account_name,
        'bank_account_number' => $bank_account_number,
        'bank_routing_number' => $bank_routing_number
    ], JSON_UNESCAPED_SLASHES);

    // Update Employee Biographical profile details
    $sql_emp = "UPDATE `employees` SET
                    `branch_id` = :branch_id,
                    `department_id` = :department_id,
                    `designation_id` = :designation_id,
                    `role_id` = :role_id,
                    `reporting_manager_id` = :reporting_manager_id,
                    `employee_code` = :employee_code,
                    `first_name` = :first_name,
                    `last_name` = :last_name,
                    `email` = :email,
                    `phone` = :phone,
                    `hire_date` = :hire_date,
                    `employment_status` = :employment_status,
                    `salary` = :salary,
                    `gender` = :gender,
                    `date_of_birth` = :date_of_birth,
                    `blood_group` = :blood_group,
                    `marital_status` = :marital_status,
                    `address` = :address,
                    `emergency_contact_name` = :emergency_contact_name,
                    `emergency_contact_phone` = :emergency_contact_phone,
                    `emergency_contact_relationship` = :emergency_contact_relationship,
                    `updated_at` = CURRENT_TIMESTAMP
                WHERE `id` = :id";

    $stmt_emp = $db->prepare($sql_emp);
    $stmt_emp->execute([
        'branch_id' => $branch_id,
        'department_id' => $department_id,
        'designation_id' => $designation_id,
        'role_id' => $role_id,
        'reporting_manager_id' => $reporting_manager_id,
        'employee_code' => $employee_code,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'hire_date' => $hire_date,
        'employment_status' => $employment_status,
        'salary' => $total_salary,
        'gender' => $gender,
        'date_of_birth' => $date_of_birth,
        'blood_group' => $blood_group,
        'marital_status' => $marital_status,
        'address' => $combined_address,
        'emergency_contact_name' => $emergency_contact_name,
        'emergency_contact_phone' => $emergency_contact_phone,
        'emergency_contact_relationship' => $emergency_contact_relationship,
        'id' => $id
    ]);

    // Update Salary Compensation Structure
    // Check if salary record already exists
    $stmt_sal_check = $db->prepare("SELECT COUNT(*) FROM `salary_structures` WHERE `employee_id` = ?");
    $stmt_sal_check->execute([$id]);
    $salary_exists = (int)$stmt_sal_check->fetchColumn() > 0;

    if ($salary_exists) {
        $sql_sal = "UPDATE `salary_structures` SET
                        `basic_salary` = :basic_salary,
                        `house_rent_allowance` = :house_rent,
                        `medical_allowance` = :medical,
                        `conveyance_allowance` = :conveyance,
                        `other_allowances` = :other_allow,
                        `provident_fund` = :provident,
                        `professional_tax` = :tax,
                        `other_deductions` = :deductions,
                        `updated_at` = CURRENT_TIMESTAMP
                    WHERE `employee_id` = :employee_id";
    } else {
        $sql_sal = "INSERT INTO `salary_structures` (
                        `employee_id`, `basic_salary`, `house_rent_allowance`, `medical_allowance`,
                        `conveyance_allowance`, `other_allowances`, `provident_fund`,
                        `professional_tax`, `other_deductions`
                    ) VALUES (
                        :employee_id, :basic_salary, :house_rent, :medical,
                        :conveyance, :other_allow, :provident,
                        :tax, :deductions
                    )";
    }

    $stmt_sal = $db->prepare($sql_sal);
    $stmt_sal->execute([
        'employee_id' => $id,
        'basic_salary' => $basic_salary,
        'house_rent' => $house_rent_allowance,
        'medical' => $medical_allowance,
        'conveyance' => $conveyance_allowance,
        'other_allow' => $other_allowances,
        'provident' => $provident_fund,
        'tax' => $professional_tax,
        'deductions' => $other_deductions
    ]);

    // Document Replacement helper function (Deletes stale files on replacement)
    $update_document_type = function($doc_type, $doc_name, $file_field) use ($db, $id, $uploaded_files) {
        if (isset($uploaded_files[$file_field])) {
            // Find old path and delete it
            $st = $db->prepare("SELECT `file_path` FROM `employee_documents` WHERE `employee_id` = ? AND `document_type` = ? LIMIT 1");
            $st->execute([$id, $doc_type]);
            $old_path = $st->fetchColumn();
            if ($old_path) {
                @unlink(__DIR__ . '/../' . $old_path);
            }

            // Remove from DB to avoid composite key conflicts
            $st = $db->prepare("DELETE FROM `employee_documents` WHERE `employee_id` = ? AND `document_type` = ?");
            $st->execute([$id, $doc_type]);

            // Insert fresh document path
            $st = $db->prepare("INSERT INTO `employee_documents` (`employee_id`, `document_name`, `document_type`, `file_path`, `status`) VALUES (?, ?, ?, ?, 'Verified')");
            $st->execute([$id, $doc_name, $doc_type, $uploaded_files[$file_field]]);
        }
    };

    $update_document_type('Profile Photo', 'Profile Avatar', 'profile_photo');
    $update_document_type('National ID', 'National ID File', 'national_id');
    $update_document_type('Passport', 'Passport File', 'passport');
    $update_document_type('Resume', 'CV Resume File', 'resume');
    $update_document_type('Certificates', 'Academic Certificates File', 'certificates');
    $update_document_type('Other Document', 'Other Document Attachment', 'other_docs');

    // Automatically Create Activity Log Entry (Required)
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $activity_payload = json_encode([
        'employee_code' => $employee_code,
        'full_name' => $first_name . ' ' . $last_name,
        'department_id' => $department_id,
        'designation_id' => $designation_id,
        'net_base_salary' => $total_salary
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Employee Updated',
        'employees',
        $id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();

    flash_set('success', "Personnel profile '$first_name $last_name' has been updated and synchronized successfully.");
    redirect('employees/show.php?id=' . $id);

} catch (Exception $e) {
    $db->rollBack();
    
    // Purge newly uploaded files since transaction failed
    foreach ($uploaded_files as $relative_path) {
        @unlink(__DIR__ . '/../' . $relative_path);
    }

    flash_set('error', 'Critical Transaction Error: Failed to commit modifications. ' . $e->getMessage());
    redirect('employees/edit.php?id=' . $id);
}
