<?php
$page_title = 'Add Employee';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

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

// Initialize form variables
$name = '';
$email = '';
$password = '';
$department_id = '';
$designation_id = '';
$joining_date = date('Y-m-d');
$employment_status = 'active';
$basic_salary = '';
$phone = '';
$gender = '';
$date_of_birth = '';
$address = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        // Account Info
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Job Info
        $department_id = intval($_POST['department_id'] ?? 0);
        $designation_id = intval($_POST['designation_id'] ?? 0);
        $joining_date = $_POST['joining_date'] ?? '';
        $employment_status = $_POST['employment_status'] ?? 'active';
        $basic_salary = floatval($_POST['basic_salary'] ?? 0);
        
        // Personal Info
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
            // Check email uniqueness
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'An account with this email already exists.';
                }
            } catch (PDOException $e) {
                error_log("Email uniqueness check error: " . $e->getMessage());
                $errors[] = 'An error occurred while validating the email.';
            }
        }
        
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        
        if ($department_id <= 0) {
            $errors['department_id'] = 'Please select a department.';
        } else {
            // Validate department exists and is active
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
            // Validate designation exists, is active, and belongs to selected department
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
        } elseif (strtotime($joining_date) > strtotime('+1 month')) {
            $errors['joining_date'] = 'Joining date cannot be more than 1 month in the future.';
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
        $avatar_filename = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handle_avatar_upload($_FILES['avatar']);
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
                
                // Generate employee code
                $employee_code = generate_employee_code();
                if (!$employee_code) {
                    throw new Exception('Failed to generate employee code.');
                }
                
                // Insert user record
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_stmt = $pdo->prepare("
                    INSERT INTO users (role_id, name, email, password, avatar, status, created_at) 
                    VALUES (2, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)
                ");
                $user_stmt->execute([$name, $email, $hashed_password, $avatar_filename]);
                $user_id = $pdo->lastInsertId();
                
                // Insert employee record
                $employee_stmt = $pdo->prepare("
                    INSERT INTO employees (user_id, employee_code, department_id, designation_id, phone, gender, 
                                         date_of_birth, address, joining_date, employment_status, basic_salary, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $employee_stmt->execute([
                    $user_id, $employee_code, $department_id, $designation_id, $phone, $gender,
                    $date_of_birth ?: null, $address, $joining_date, $employment_status, $basic_salary, $_SESSION['user_id']
                ]);
                
                // Commit transaction
                $pdo->commit();
                
                // Log activity
                log_activity($_SESSION['user_id'], 'employee_create', "Created employee: $employee_code - $name");
                
                redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'success', "Employee $employee_code created successfully!");
                
            } catch (Exception $e) {
                // Rollback transaction on any error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                // Delete uploaded avatar if transaction failed
                if ($avatar_filename) {
                    $avatar_path = __DIR__ . '/../../uploads/avatars/' . $avatar_filename;
                    if (file_exists($avatar_path)) {
                        unlink($avatar_path);
                    }
                }
                
                error_log("Employee creation error: " . $e->getMessage());
                $errors[] = 'An error occurred while creating the employee. Please try again.';
            }
        }
    }
}


// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Add Employee</h2>
        <p class="text-muted">Create a new employee record</p>
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
                
                <?php if (empty($departments)): ?>
                    <div class="alert alert-warning">
                        No active departments available. Please create a department first.
                        <a href="<?php echo BASE_URL; ?>/modules/departments/create.php" class="alert-link">Create Department</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                        
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
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Temporary Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                           id="password" name="password" required minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateRandomPassword()">Generate</button>
                                </div>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                                <?php endif; ?>
                                <div class="form-text">Minimum 8 characters. Employee will be prompted to change on first login.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="avatar" class="form-label">Profile Photo</label>
                                <input type="file" class="form-control <?php echo isset($errors['avatar']) ? 'is-invalid' : ''; ?>" 
                                       id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
                                <?php if (isset($errors['avatar'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['avatar']); ?></div>
                                <?php endif; ?>
                                <div class="form-text">Optional. JPG, PNG, or WebP. Max 2MB.</div>
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
                            <button type="submit" class="btn btn-primary">Create Employee</button>
                            <a href="<?php echo BASE_URL; ?>/modules/employees/list.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
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
    document.getElementById('password').value = password;
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

// Load designations on page load if department is pre-selected
document.addEventListener('DOMContentLoaded', function() {
    const departmentId = document.getElementById('department_id').value;
    if (departmentId) {
        loadDesignations();
    }
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
