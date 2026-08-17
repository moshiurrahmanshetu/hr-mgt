<?php
/**
 * Core PHP HRM Front Controller / Router
 * Developed by Senior PHP Software Architect
 * 
 * Intercepts all incoming client requests, manages router dispatches, processes 
 * POST form submissions (logins/logouts), checks CSRF tokens, and renders 
 * correct views with appropriate layout headers and footers.
 * 
 * Works cleanly inside subfolders (e.g. localhost/XAMPP) without breaking paths!
 */

// 1. Boot up global configurations and establish secure sessions
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/url_helper.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/includes/flash.php';

// 2. Parse request route parameter (defaulting to 'dashboard')
$route = $_GET['route'] ?? 'dashboard';

// 3. Central Dispatch Route Switcher
switch ($route) {
    
    // VIEW: User login portal
    case 'login':
        AuthMiddleware::guestOnly();
        require_once __DIR__ . '/views/login.php';
        break;

    // VIEW: Forgot password request
    case 'forgot-password':
        AuthMiddleware::guestOnly();
        require_once __DIR__ . '/views/forgot-password.php';
        break;

    // ACTION: Handle forgot password (UI only as requested)
    case 'forgot_password_submit':
        AuthMiddleware::guestOnly();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash_set('error', 'Invalid request method. Reset requests must be submitted via secure POST.');
            redirect('index.php?route=forgot-password');
        }

        // CSRF Token Security Verification
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            flash_set('error', 'Security Violation: CSRF Token expired or invalid. Please try again.');
            redirect('index.php?route=forgot-password');
        }

        // Since it's UI only, display successful simulation message
        $username = trim($_POST['username'] ?? '');
        if (empty($username)) {
            flash_set('error', 'Please enter a valid username or email address.');
            redirect('index.php?route=forgot-password');
        }

        // Generate a random simulated token
        $simToken = 'HRM-RST-' . strtoupper(bin2hex(random_bytes(4)));
        flash_set('success', 'Security Protocol: If the account exists, a secure password reset link has been dispatched. [SIMULATION] Use token: ' . $simToken . ' or click <a href="index.php?route=reset-password&token=' . urlencode($simToken) . '" class="alert-link">Reset Link</a>');
        redirect('index.php?route=forgot-password');
        break;

    // VIEW: Reset password form
    case 'reset-password':
        AuthMiddleware::guestOnly();
        require_once __DIR__ . '/views/reset-password.php';
        break;

    // ACTION: Handle reset password submit (UI only as requested)
    case 'reset_password_submit':
        AuthMiddleware::guestOnly();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash_set('error', 'Invalid request method. Submission must be via secure POST.');
            redirect('index.php?route=reset-password');
        }

        // CSRF Token Security Verification
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            flash_set('error', 'Security Violation: CSRF Token expired or invalid.');
            redirect('index.php?route=reset-password');
        }

        $resetToken = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($resetToken)) {
            flash_set('error', 'Reset authorization token is required.');
            redirect('index.php?route=reset-password');
        }

        if (strlen($password) < 8) {
            flash_set('error', 'Password strength criteria failed. Password must be at least 8 characters long.');
            redirect('index.php?route=reset-password&token=' . urlencode($resetToken));
        }

        if ($password !== $confirmPassword) {
            flash_set('error', 'Confirmation error: The password entries do not match.');
            redirect('index.php?route=reset-password&token=' . urlencode($resetToken));
        }

        flash_set('success', 'Simulated Password Reset Successful: Your new credentials have been committed. You can now log in.');
        redirect('index.php?route=login');
        break;

    // ACTION: Handle user login submit (POST only)
    case 'login_submit':
        AuthMiddleware::guestOnly();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash_set('error', 'Invalid request method. Sign in requests must be submitted via secure POST.');
            redirect('index.php?route=login');
        }

        // CSRF Token Security Verification
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            flash_set('error', 'Security Violation: CSRF Token expired or invalid. Please refresh and try again.');
            redirect('index.php?route=login');
        }

        // Extract and sanitize login credentials
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

        $authController = new AuthController();
        if ($authController->handleLogin($username, $password, $remember)) {
            redirect('index.php?route=dashboard');
        } else {
            redirect('index.php?route=login');
        }
        break;

    // ACTION: Handle user logout (POST only for security)
    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?route=dashboard');
        }

        // Validate logout CSRF to prevent malicious logouts
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            flash_set('error', 'Security warning: Failed logout CSRF validation.');
            redirect('index.php?route=dashboard');
        }

        $authController = new AuthController();
        $authController->handleLogout();
        break;

    // VIEW: Main corporate dashboard
    case 'dashboard':
        AuthMiddleware::requireLogin();
        require_once __DIR__ . '/views/dashboard.php';
        break;

    // VIEW: Employee Management Directory (Sprint 03)
    case 'employees':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/employees/index.php';
        break;

    // VIEW: Create Employee biographical form
    case 'employees-create':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/employees/create.php';
        break;

    // VIEW: Employee Biographical Profile Detail view
    case 'employees-show':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/employees/show.php';
        break;

    // VIEW: Modify Employee profile form
    case 'employees-edit':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/employees/edit.php';
        break;

    // --- ORGANIZATION MODULE: BRANCHES ---
    case 'branches':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/branches/index.php';
        break;
    case 'branches-create':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/branches/create.php';
        break;
    case 'branches-store':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/branches/store.php';
        break;
    case 'branches-edit':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/branches/edit.php';
        break;
    case 'branches-update':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/branches/update.php';
        break;
    case 'branches-delete':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/branches/delete.php';
        break;

    // --- ORGANIZATION MODULE: DEPARTMENTS ---
    case 'departments':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/departments/index.php';
        break;
    case 'departments-create':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/departments/create.php';
        break;
    case 'departments-store':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/departments/store.php';
        break;
    case 'departments-edit':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/departments/edit.php';
        break;
    case 'departments-update':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/departments/update.php';
        break;
    case 'departments-delete':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/departments/delete.php';
        break;

    // --- ORGANIZATION MODULE: DESIGNATIONS ---
    case 'designations':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/designations/index.php';
        break;
    case 'designations-create':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/designations/create.php';
        break;
    case 'designations-store':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/designations/store.php';
        break;
    case 'designations-edit':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/designations/edit.php';
        break;
    case 'designations-update':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/designations/update.php';
        break;
    case 'designations-delete':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/designations/delete.php';
        break;

    // --- SYSTEM MODULE: ROLES ---
    case 'roles':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/roles/index.php';
        break;
    case 'roles-create':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/roles/create.php';
        break;
    case 'roles-store':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/roles/store.php';
        break;
    case 'roles-edit':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/roles/edit.php';
        break;
    case 'roles-update':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/roles/update.php';
        break;
    case 'roles-delete':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/roles/delete.php';
        break;

    // VIEW: Database schema blueprinter (Admins/HR Managers only)
    case 'schema':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/views/schema.php';
        break;

    // VIEW: Interactive SQL query terminal (Admins/HR Managers only)
    case 'sql_console':
        AuthMiddleware::requireRoles(['Admin', 'HR Manager']);
        require_once __DIR__ . '/views/sql_console.php';
        break;

    // 404: Unrecognized route fallback
    default:
        // Set standard 404 HTTP status
        header("HTTP/1.0 404 Not Found");
        
        // Render simple inline 404 visual card
        $page_title = 'Page Not Found';
        require_once __DIR__ . '/layouts/header.php';
        ?>
        <div class="container d-flex align-items-center justify-content-center min-vh-100">
            <div class="text-center p-5 rounded border shadow-lg" style="background-color: var(--bg-secondary); border-color: var(--border-color) !important; max-width: 460px;">
                <i class="bi bi-exclamation-octagon text-danger display-1 mb-3"></i>
                <h3 class="fw-bold">404 - Page Not Found</h3>
                <p class="text-muted small mt-2">The route or service requested is either restricted, doesn't exist, or has moved to another section.</p>
                <a href="<?php echo base_url('index.php?route=dashboard'); ?>" class="btn btn-primary btn-sm mt-3 px-4 py-2">
                    <i class="bi bi-house-door-fill me-1"></i> Return to Safety
                </a>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/layouts/footer.php';
        break;
}
