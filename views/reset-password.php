<?php
/**
 * Reset Password Portal View
 * Developed by Senior PHP Software Architect
 * 
 * Beautiful, secure interface for completing password resets.
 * Allows inputting reset token, new password, and confirm password.
 */

require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../includes/flash.php';

// Safe route assertion - redirect if session exists already
if (isset($_SESSION['user_id'])) {
    redirect('index.php?route=dashboard');
}

$page_title = 'Set New Password';
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($page_title); ?> | HRM Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root, [data-theme="dark"] {
            --bg-primary: #0b0f19;
            --bg-secondary: #121824;
            --border-color: #212d45;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --accent-primary: #3b82f6;
            --accent-primary-hover: #2563eb;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.6);
        }

        [data-theme="light"] {
            --bg-primary: #f3f4f6;
            --bg-secondary: #ffffff;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --accent-primary: #2563eb;
            --accent-primary-hover: #1d4ed8;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
            transition: all 0.3s ease;
        }

        .form-control {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            border-radius: 0.375rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            background-color: var(--bg-primary);
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            color: var(--text-primary);
        }

        .btn-primary {
            background-color: var(--accent-primary);
            border-color: var(--accent-primary);
            padding: 0.75rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--accent-primary-hover);
            border-color: var(--accent-primary-hover);
        }

        .input-group-text {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .theme-switcher {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }
    </style>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>
    
    <!-- Floating Theme Switcher Button -->
    <div class="theme-switcher">
        <button class="btn btn-sm btn-outline-secondary" id="theme-btn" title="Toggle Theme">
            <i class="bi bi-moon-stars-fill" id="theme-icon"></i>
        </button>
    </div>

    <!-- Login Box -->
    <div class="login-card">
        <!-- Logo -->
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary rounded-circle mb-3" style="width: 56px; height: 56px; border: 1px solid var(--border-color);">
                <i class="bi bi-key-fill text-success" style="font-size: 1.75rem;"></i>
            </div>
            <h4 class="fw-bold tracking-tight mb-1">Set New Password</h4>
            <p class="text-muted small">Establish a new robust security password for your HRM account access.</p>
        </div>

        <!-- Render Any Active Flash/Session Warnings -->
        <?php echo flash_display(); ?>

        <!-- Form Submission -->
        <form action="<?php echo base_url('index.php?route=reset_password_submit'); ?>" method="POST" autocomplete="off">
            <!-- CSRF Token protection field -->
            <?php echo csrf_field(); ?>

            <!-- Reset Token -->
            <div class="mb-3">
                <label for="token" class="form-label text-secondary small fw-medium">Reset Authorization Token</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-ticket-perforated"></i></span>
                    <input type="text" class="form-control" id="token" name="token" placeholder="Enter security reset token" value="<?php echo sanitize($token); ?>" required autofocus>
                </div>
            </div>

            <!-- New Password -->
            <div class="mb-3">
                <label for="password" class="form-label text-secondary small fw-medium">New Secure Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••••••" required>
                    <button class="btn btn-outline-secondary border" type="button" id="toggle-password" style="border-color: var(--border-color) !important;">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div id="password-feedback" class="form-text small text-muted mt-1" style="font-size: 0.75rem;">
                    Minimum 8 characters with numbers & symbols.
                </div>
            </div>

            <!-- Confirm New Password -->
            <div class="mb-4">
                <label for="confirm_password" class="form-label text-secondary small fw-medium">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-check-circle"></i></span>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="••••••••••••" required>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2 mb-3">
                <span>Commit Password Change</span>
                <i class="bi bi-check-all"></i>
            </button>

            <!-- Return to Login Link -->
            <div class="text-center">
                <a href="<?php echo base_url('index.php?route=login'); ?>" class="text-decoration-none small text-secondary hover:text-white transition">
                    <i class="bi bi-chevron-left me-1"></i> Return to Sign In
                </a>
            </div>
        </form>
    </div>

    <!-- Theme management scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password Toggle Visibility
            const togglePassword = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('password');
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    const eyeIcon = this.querySelector('i');
                    eyeIcon.classList.toggle('bi-eye');
                    eyeIcon.classList.toggle('bi-eye-slash');
                });
            }

            // Simple Theme Switcher inside Page
            const themeBtn = document.getElementById('theme-btn');
            const themeIcon = document.getElementById('theme-icon');
            
            function updateTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                if (theme === 'light') {
                    themeIcon.className = 'bi bi-sun-fill text-warning';
                } else {
                    themeIcon.className = 'bi bi-moon-stars-fill text-secondary';
                }
            }

            const initialTheme = localStorage.getItem('theme') || 'dark';
            updateTheme(initialTheme);

            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    updateTheme(newTheme);
                });
            }
        });
    </script>
</body>
</html>
