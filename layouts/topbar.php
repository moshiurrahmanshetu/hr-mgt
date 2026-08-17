<?php
/**
 * Master Topbar Component
 * Developed by Senior PHP Software Architect
 * 
 * Provides global tools, dark/light theme switching, and user profile management.
 */

require_once __DIR__ . '/../helpers/url_helper.php';

$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['user_full_name'] ?? 'System Guest';
$role = $_SESSION['user_role'] ?? 'Employee';
?>

<header class="topbar">
    <!-- Left Hand: Hamburger Menu Toggle for Mobile Views -->
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebar-toggle" aria-label="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
        </button>
        
        <!-- Quick Title or Context -->
        <span class="text-muted d-none d-sm-inline-block" style="font-size: 0.85rem;">
            Enterprise Workspace
        </span>
    </div>

    <!-- Right Hand: Global Controls & Profile Context -->
    <div class="d-flex align-items-center gap-3">
        
        <!-- Dark/Light Theme Switcher -->
        <button class="btn btn-sm btn-link text-secondary p-1" id="theme-switcher-btn" title="Toggle Dark/Light Mode">
            <i class="bi bi-moon-stars-fill fs-5" id="theme-btn-icon"></i>
        </button>

        <!-- Divider line -->
        <div style="width: 1px; height: 24px; background-color: var(--border-color);"></div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2 p-0" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <!-- Mini Avatar Circle -->
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary border text-primary" style="width: 38px; height: 38px; font-weight: 600; font-size: 0.9rem; border-color: var(--border-color) !important;">
                    <?php echo sanitize(strtoupper($username[0] ?? 'U')); ?>
                </div>
                
                <div class="text-start d-none d-md-block">
                    <div class="fw-bold text-primary" style="font-size: 0.825rem; line-height: 1.2; color: var(--text-primary) !important;">
                        <?php echo sanitize($fullName); ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.725rem; line-height: 1;">
                        <?php echo sanitize($role); ?>
                    </div>
                </div>
                <i class="bi bi-chevron-down text-muted d-none d-md-inline-block" style="font-size: 0.75rem;"></i>
            </button>
            
            <ul class="dropdown-menu dropdown-menu-end shadow border p-2" aria-labelledby="userDropdown" style="background-color: var(--bg-secondary); border-color: var(--border-color); width: 220px;">
                <li class="px-3 py-2 border-bottom mb-2" style="border-color: var(--border-color) !important;">
                    <div class="text-muted small">Logged in as</div>
                    <div class="fw-bold text-truncate" style="font-size: 0.85rem;"><?php echo sanitize($_SESSION['user_email'] ?? 'admin@hrmsystem.com'); ?></div>
                </li>
                <li>
                    <a class="dropdown-item rounded py-2 d-flex align-items-center gap-2 text-primary" href="<?php echo base_url('index.php?route=dashboard'); ?>" style="color: var(--text-primary) !important;">
                        <i class="bi bi-person-fill text-muted"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li>
                    <hr class="dropdown-divider" style="border-color: var(--border-color);">
                </li>
                <li>
                    <!-- Form-based safe post method logout for csrf/session protection -->
                    <form action="<?php echo base_url('index.php?route=logout'); ?>" method="POST" class="m-0">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="dropdown-item rounded py-2 d-flex align-items-center gap-2 text-danger w-100">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Sign Out Securely</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
