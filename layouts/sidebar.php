<?php
/**
 * Master Sidebar Component
 * Developed by Senior PHP Software Architect
 * 
 * Renders consistent navigation with role-based link filtering.
 */

require_once __DIR__ . '/../helpers/url_helper.php';

// Safe check for current route to apply .active styles
$current_route = $_GET['route'] ?? 'dashboard';
$user_role = $_SESSION['user_role'] ?? 'Employee';
?>

<aside class="sidebar" id="sidebar">
    <!-- Brand Logo and Header -->
    <a href="<?php echo base_url('index.php?route=dashboard'); ?>" class="sidebar-brand">
        <i class="bi bi-briefcase-fill text-primary" style="font-size: 1.5rem;"></i>
        <span>HRM <span class="text-primary">Portal</span></span>
    </a>

    <!-- Sidebar Menu Options -->
    <nav class="sidebar-menu">
        <div class="text-muted text-uppercase fw-bold mb-3" style="font-size: 0.65rem; letter-spacing: 0.05em; padding-left: 1rem;">
            Core Services
        </div>
        
        <a href="<?php echo base_url('index.php?route=dashboard'); ?>" class="sidebar-link <?php echo $current_route === 'dashboard' ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <!-- Conditional Menu rendering based on User Roles (Admin / HR Access) -->
        <?php if (in_array($user_role, ['Admin', 'HR Manager'], true)): ?>
            <div class="text-muted text-uppercase fw-bold mt-4 mb-3" style="font-size: 0.65rem; letter-spacing: 0.05em; padding-left: 1rem;">
                System Administration
            </div>
            
            <a href="<?php echo base_url('index.php?route=schema'); ?>" class="sidebar-link <?php echo $current_route === 'schema' ? 'active' : ''; ?>">
                <i class="bi bi-database-fill-gear"></i>
                <span>Database Schema</span>
            </a>
            
            <a href="<?php echo base_url('index.php?route=sql_console'); ?>" class="sidebar-link <?php echo $current_route === 'sql_console' ? 'active' : ''; ?>">
                <i class="bi bi-terminal-fill"></i>
                <span>SQL Console</span>
            </a>
        <?php endif; ?>

        <!-- Employee Quick Links -->
        <div class="text-muted text-uppercase fw-bold mt-4 mb-3" style="font-size: 0.65rem; letter-spacing: 0.05em; padding-left: 1rem;">
            Self Service
        </div>
        
        <!-- Employee Directory Module -->
        <?php if (in_array($user_role, ['Admin', 'HR Manager'], true)): ?>
            <a href="<?php echo base_url('index.php?route=employees'); ?>" class="sidebar-link <?php echo ($current_route === 'employees' || strpos($current_route, 'employees') === 0) ? 'active' : ''; ?>">
                <i class="bi bi-people-fill text-primary"></i>
                <span>Employee Directory</span>
            </a>
        <?php else: ?>
            <a href="#" class="sidebar-link disabled text-muted opacity-50" title="Restricted to Admin and HR Manager">
                <i class="bi bi-people-fill"></i>
                <span>Directory <span class="badge bg-secondary float-end" style="font-size: 0.55rem; padding: 0.15rem 0.3rem;">Secure</span></span>
            </a>
        <?php endif; ?>

        <!-- Organization Module Links -->
        <?php if (in_array($user_role, ['Admin', 'HR Manager'], true)): ?>
            <div class="text-muted text-uppercase fw-bold mt-4 mb-3" style="font-size: 0.65rem; letter-spacing: 0.05em; padding-left: 1rem;">
                Organization
            </div>
            <a href="<?php echo base_url('index.php?route=branches'); ?>" class="sidebar-link <?php echo ($current_route === 'branches' || strpos($current_route, 'branches') === 0) ? 'active' : ''; ?>">
                <i class="bi bi-geo-alt-fill text-info"></i>
                <span>Branches</span>
            </a>
            <a href="<?php echo base_url('index.php?route=departments'); ?>" class="sidebar-link <?php echo ($current_route === 'departments' || strpos($current_route, 'departments') === 0) ? 'active' : ''; ?>">
                <i class="bi bi-building-fill text-warning"></i>
                <span>Departments</span>
            </a>
            <a href="<?php echo base_url('index.php?route=designations'); ?>" class="sidebar-link <?php echo ($current_route === 'designations' || strpos($current_route, 'designations') === 0) ? 'active' : ''; ?>">
                <i class="bi bi-person-workspace text-success"></i>
                <span>Designations</span>
            </a>
            <a href="<?php echo base_url('index.php?route=roles'); ?>" class="sidebar-link <?php echo ($current_route === 'roles' || strpos($current_route, 'roles') === 0) ? 'active' : ''; ?>">
                <i class="bi bi-shield-lock-fill text-danger"></i>
                <span>Roles</span>
            </a>
        <?php endif; ?>

        <a href="#" class="sidebar-link disabled text-muted opacity-50" title="Locked - Coming in Sprint 02">
            <i class="bi bi-calendar-check-fill"></i>
            <span>Attendance</span>
        </a>

        <a href="#" class="sidebar-link disabled text-muted opacity-50" title="Locked - Coming in Sprint 02">
            <i class="bi bi-clock-history"></i>
            <span>Leave Requests</span>
        </a>

        <a href="#" class="sidebar-link disabled text-muted opacity-50" title="Locked - Coming in Sprint 03">
            <i class="bi bi-wallet2"></i>
            <span>Payroll Hub</span>
        </a>
    </nav>

    <!-- Footer of Sidebar -->
    <div class="sidebar-footer">
        <div>Sprint 01 Foundation Build</div>
        <div>v1.0.0-PROD</div>
    </div>
</aside>
