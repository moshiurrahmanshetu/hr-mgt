<?php
$page_title = 'Edit Employee';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get employee data
try {
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar,
               d.name as department_name, des.title as designation_name
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        JOIN departments d ON e.department_id = d.id 
        JOIN designations des ON e.designation_id = des.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'Employee not found.');
    }
} catch (PDOException $e) {
    error_log("Employee fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'An error occurred while fetching the employee.');
}

// Get active departments and designations
try {
    $dept_stmt = $pdo->prepare("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll();
    
    $des_stmt = $pdo->prepare("SELECT id, title, department_id FROM designations WHERE status = 'active' ORDER BY title ASC");
    $des_stmt->execute();
    $designations = $des_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Departments/Designations fetch error: " . $e->getMessage());
    $departments = [];
    $designations = [];
}

// Initialize form variables with current values
$name = $employee['user_name'];
$email = $employee['user_email'];
$department_id = $employee['department_id'];
$designation_id = $employee['designation_id'];
$joining_date = $employee['joining_date'];
$employment_status = $employee['employment_status'];
$basic_salary = $employee['basic_salary'];
$phone = $employee['phone'] ?? '';
$gender = $employee['gender'] ?? '';
$date_of_birth = $employee['date_of_birth'] ?? '';
$address = $employee['address'] ?? '';
$errors = [];

// Handle main form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employee') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        // Form fields
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $department_id = intval($_POST['department_id'] ?? 0);
        $designation_id = intval($_POST['designation_id'] ?? 0);
        $joining_date = $_POST['joining_date'] ?? '';
        $employment_status = $_POST['employment_status'] ?? 'active';
        $basic_salary = floatval($_POST['basic_salary'] ?? 0);
        $phone = sanitize_input($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $address = sanitize_input($_POST['address'] ?? '');
        
        // Validation
        if (empty($name)) {
            $errors['name'] = 'Name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        }
        
        if (empty($email)) {
            $errors['email'] = 'Email is required.';
        } elseif (!is_valid_email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            // Check email uniqueness (excluding current user)
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $employee['user_id']]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'An account with this email already exists.';
                }
            } catch (PDOException $e) {
                error_log("Email uniqueness check error: " . $e->getMessage());
                $errors[] = 'An error occurred while validating the email.';
            }
        }
        
        if ($department_id <= 0) {
            $errors['department_id'] = 'Please select a department.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND status = 'active'");
                $stmt->execute([$department_id]);
                if (!$stmt->fetch()) {
                    $errors['department_id'] = 'Selected department is invalid or inactive.';
                }
            } catch (PDOException $e) {
                error_log("Department validation error: " . $e->getMessage());
                $errors[] = 'An error occurred while validating the department.';
            }
        }
        
        if ($designation_id <= 0) {
            $errors['designation_id'] = 'Please select a designation.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM designations WHERE id = ? AND department_id = ? AND status = 'active'");
                $stmt->execute([$designation_id, $department_id]);
                if (!$stmt->fetch()) {
                    $errors['designation_id'] = 'Selected designation is invalid or does not belong to the selected department.';
                }
            } catch (PDOException $e) {
                error_log("Designation validation error: " . $e->getMessage());
                $errors[] = 'An error occurred while validating the designation.';
            }
        }
        
        if (empty($joining_date)) {
            $errors['joining_date'] = 'Joining date is required.';
        } elseif (!strtotime($joining_date)) {
            $errors['joining_date'] = 'Please enter a valid date.';
        }
        
        if ($basic_salary < 0) {
            $errors['basic_salary'] = 'Basic salary must be a non-negative number.';
        }
        
        if (!empty($phone) && !preg_match('/^[0-9+\-\s()]+$/', $phone)) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }
        
        if (!empty($date_of_birth) && !strtotime($date_of_birth)) {
            $errors['date_of_birth'] = 'Please enter a valid date of birth.';
        }
        
        // Handle avatar upload if provided
        $avatar_filename = $employee['user_avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handle_avatar_upload($_FILES['avatar'], $employee['user_avatar']);
            if ($upload_result['success']) {
                $avatar_filename = $upload_result['filename'];
            } else {
                $errors['avatar'] = $upload_result['error'];
            }
        }
        
        if (empty($errors)) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Update user record
                $user_stmt = $pdo->prepare("
                    UPDATE users SET name = ?, email = ?, avatar = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $user_stmt->execute([$name, $email, $avatar_filename, $employee['user_id']]);
                
                // Update employee record
                $employee_stmt = $pdo->prepare("
                    UPDATE employees SET department_id = ?, designation_id = ?, phone = ?, gender = ?, 
                                         date_of_birth = ?, address = ?, joining_date = ?, employment_status = ?, 
                                         basic_salary = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $employee_stmt->execute([
                    $department_id, $designation_id, $phone, $gender,
                    $date_of_birth ?: null, $address, $joining_date, $employment_status, $basic_salary, $id
                ]);
                
                // Commit transaction
                $pdo->commit();
                
                // Update session if editing own profile
                if ($employee['user_id'] == $_SESSION['user_id']) {
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    $_SESSION['avatar'] = $avatar_filename;
                }
                
                // Log activity
                log_activity($_SESSION['user_id'], 'employee_update', "Updated employee: {$employee['employee_code']} - $name");
                
                redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'success', "Employee updated successfully!");
                
            } catch (Exception $e) {
                // Rollback transaction on any error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                error_log("Employee update error: " . $e->getMessage());
                $errors[] = 'An error occurred while updating the employee. Please try again.';
            }
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($new_password)) {
            $errors['new_password'] = 'New password is required.';
        } elseif (strlen($new_password) < 8) {
            $errors['new_password'] = 'Password must be at least 8 characters.';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$hashed_password, $employee['user_id']]);
                
                log_activity($_SESSION['user_id'], 'password_reset', "Reset password for employee: {$employee['employee_code']}");
                
                redirect_with_flash(BASE_URL . '/modules/employees/edit.php?id=' . $id, 'success', 'Password reset successfully!');
            } catch (PDOException $e) {
                error_log("Password reset error: " . $e->getMessage());
                $errors[] = 'An error occurred while resetting the password.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Edit Employee</h2>
        <p class="text-muted">Update employee information</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $field => $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Main Edit Form -->
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_employee">
                    
                    <!-- Account Info -->
                    <h5 class="mb-3">Account Information</h5>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                   id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="avatar" class="form-label">Profile Photo</label>
                            <input type="file" class="form-control <?php echo isset($errors['avatar']) ? 'is-invalid' : ''; ?>" 
                                   id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
                            <?php if (isset($errors['avatar'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['avatar']); ?></div>
                            <?php endif; ?>
                            <div class="form-text">Leave empty to keep current photo. JPG, PNG, or WebP. Max 2MB.</div>
                        </div>
                    </div>
                    
                    <!-- Job Info -->
                    <h5 class="mb-3">Job Information</h5>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>" 
                                    id="department_id" name="department_id" required onchange="loadDesignations()">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $department_id == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['department_id'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['department_id']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="designation_id" class="form-label">Designation <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['designation_id']) ? 'is-invalid' : ''; ?>" 
                                    id="designation_id" name="designation_id" required>
                                <option value="">Select Designation</option>
                                <?php foreach ($designations as $des): ?>
                                    <option value="<?php echo $des['id']; ?>" data-department="<?php echo $des['department_id']; ?>" 
                                            <?php echo $designation_id == $des['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($des['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['designation_id'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['designation_id']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="joining_date" class="form-label">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control <?php echo isset($errors['joining_date']) ? 'is-invalid' : ''; ?>" 
                                   id="joining_date" name="joining_date" value="<?php echo htmlspecialchars($joining_date); ?>" required>
                            <?php if (isset($errors['joining_date'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['joining_date']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="employment_status" class="form-label">Employment Status</label>
                            <select class="form-select" id="employment_status" name="employment_status">
                                <option value="active" <?php echo $employment_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $employment_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="terminated" <?php echo $employment_status === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="basic_salary" class="form-label">Basic Salary <span class="text-danger">*</span></label>
                            <input type="number" class="form-control <?php echo isset($errors['basic_salary']) ? 'is-invalid' : ''; ?>" 
                                   id="basic_salary" name="basic_salary" value="<?php echo htmlspecialchars($basic_salary); ?>" required min="0" step="0.01">
                            <?php if (isset($errors['basic_salary'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['basic_salary']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Personal Info -->
                    <h5 class="mb-3">Personal Information</h5>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                   id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                            <?php if (isset($errors['phone'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control <?php echo isset($errors['date_of_birth']) ? 'is-invalid' : ''; ?>" 
                                   id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($date_of_birth); ?>">
                            <?php if (isset($errors['date_of_birth'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['date_of_birth']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Employee</button>
                        <a href="<?php echo BASE_URL; ?>/modules/employees/list.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <!-- Password Reset Section -->
                <h5 class="mb-3">Password Reset</h5>
                <form method="POST" action="">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                       id="new_password" name="new_password" required minlength="8">
                                <button class="btn btn-outline-secondary" type="button" onclick="generateRandomPassword()">Generate</button>
                            </div>
                            <?php if (isset($errors['new_password'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['new_password']); ?></div>
                            <?php endif; ?>
                            <div class="form-text">Minimum 8 characters. Employee will need to change this on next login.</div>
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-warning">Reset Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function generateRandomPassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('new_password').value = password;
}

function loadDesignations() {
    const departmentId = document.getElementById('department_id').value;
    const designationSelect = document.getElementById('designation_id');
    
    // Clear current options
    designationSelect.innerHTML = '<option value="">Select Designation</option>';
    
    if (!departmentId) {
        return;
    }
    
    // Fetch designations for selected department
    fetch('<?php echo BASE_URL; ?>/modules/employees/get_designations.php?department_id=' + departmentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.designations.forEach(des => {
                    const option = document.createElement('option');
                    option.value = des.id;
                    option.textContent = des.title;
                    designationSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading designations:', error));
}

// Load designations on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDesignations();
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
