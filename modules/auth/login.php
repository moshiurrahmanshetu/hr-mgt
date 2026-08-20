<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    $dashboard_url = BASE_URL . '/modules/dashboard/' . $_SESSION['role'] . '_dashboard.php';
    header('Location: ' . $dashboard_url);
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verify_csrf_token()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate inputs
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!is_valid_email($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check brute force protection
            if (!check_login_attempts($email)) {
                $lockout_time = get_lockout_time($email);
                $minutes = ceil($lockout_time / 60);
                $error = "Too many failed attempts. Please try again in {$minutes} minutes.";
            } else {
                try {
                    // Fetch user by email
                    $stmt = $pdo->prepare("
                        SELECT u.*, r.name as role_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.id 
                        WHERE u.email = ?
                    ");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Check if account is active
                        if ($user['status'] !== 'active') {
                            $error = 'Your account is inactive. Please contact the administrator.';
                        } else {
                            // Successful login
                            session_regenerate_id(true); // Prevent session fixation
                            
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['name'] = $user['name'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $user['role_name'];
                            $_SESSION['avatar'] = $user['avatar'];
                            
                            // Load permissions into session cache
                            load_user_permissions($user['id'], $user['role_id']);
                            
                            // Update last_login timestamp
                            $update_stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                            $update_stmt->execute([$user['id']]);
                            
                            // Clear login attempts
                            clear_login_attempts($email);
                            
                            // Log the login activity
                            log_activity($user['id'], 'login', 'User logged in successfully');
                            
                            // Redirect to appropriate dashboard
                            $dashboard_url = BASE_URL . '/modules/dashboard/' . $user['role_name'] . '_dashboard.php';
                            header('Location: ' . $dashboard_url);
                            exit;
                        }
                    } else {
                        // Invalid credentials
                        record_failed_attempt($email);
                        $error = 'Invalid email or password.';
                    }
                } catch (PDOException $e) {
                    error_log("Login error: " . $e->getMessage());
                    $error = 'An error occurred. Please try again.';
                }
            }
        }
    }
}

// Check for logout success message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HR Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">HR Management System</h1>
                <p class="login-subtitle">Sign in to your account</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 btn-lg">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p class="text-muted small">Default admin: admin@hrsystem.com / Admin@123</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
