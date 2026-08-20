<div class="bg-dark" id="sidebar-wrapper">
    <div class="sidebar-heading px-3 py-3">
        <div class="d-flex align-items-center">
            <div class="sidebar-logo me-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-building" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M14.763.075A.5.5 0 0 1 15 .5v15a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .342-.474L6 7.64V4.5a.5.5 0 0 1 .276-.447l8-4a.5.5 0 0 1 .487.022zM6 8.694L1 10.36V15h5V8.694zM7 15h2v-1.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5V15h2V1.293l-7 3.5V15z"/>
                </svg>
            </div>
            <span class="sidebar-brand">HR System</span>
        </div>
    </div>
    
    <div class="list-group list-group-flush">
        <a href="<?php echo BASE_URL; ?>/modules/dashboard/<?php echo $_SESSION['role']; ?>_dashboard.php" 
           class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], $_SESSION['role'] . '_dashboard.php') !== false ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2 me-2"></i>
            Dashboard
        </a>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>/modules/employees/list.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'employees') !== false ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>
                Employees
            </a>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>/modules/departments/list.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'departments') !== false ? 'active' : ''; ?>">
                <i class="bi bi-building me-2"></i>
                Departments
            </a>
            
            <a href="<?php echo BASE_URL; ?>/modules/designations/list.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'designations') !== false ? 'active' : ''; ?>">
                <i class="bi bi-briefcase me-2"></i>
                Designations
            </a>
            
            <a href="<?php echo BASE_URL; ?>/modules/leave-types/list.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'leave-types') !== false ? 'active' : ''; ?>">
                <i class="bi bi-collection me-2"></i>
                Leave Types
            </a>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>/modules/attendance/admin_list.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'attendance') !== false ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check me-2"></i>
                Attendance
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/modules/attendance/my_attendance.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'attendance') !== false ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check me-2"></i>
                Attendance
            </a>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>/modules/leave/admin_requests.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'leave') !== false ? 'active' : ''; ?>">
                <i class="bi bi-calendar-x me-2"></i>
                Leave Requests
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/modules/leave/my_requests.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'leave') !== false ? 'active' : ''; ?>">
                <i class="bi bi-calendar-x me-2"></i>
                Leave Requests
            </a>
            <a href="<?php echo BASE_URL; ?>/modules/leave/apply.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'apply.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-plus-lg me-2"></i>
                Apply for Leave
            </a>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="list-group-item list-group-item-action disabled bg-secondary text-white small">
                <i class="bi bi-gear me-2"></i>
                Administration
            </div>
            <a href="<?php echo BASE_URL; ?>/modules/users/list.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : ''; ?>">
                <i class="bi bi-person-gear me-2"></i>
                Users
            </a>
            <a href="<?php echo BASE_URL; ?>/modules/roles/list.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'roles') !== false ? 'active' : ''; ?>">
                <i class="bi bi-shield-lock me-2"></i>
                Roles
            </a>
            <a href="<?php echo BASE_URL; ?>/modules/permissions/list.php" 
               class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'permissions') !== false ? 'active' : ''; ?>">
                <i class="bi bi-key me-2"></i>
                Permissions
            </a>
        <?php endif; ?>
        
        <a href="#" class="list-group-item list-group-item-action disabled" title="Coming soon">
            <i class="bi bi-currency-dollar me-2"></i>
            Payroll
        </a>
        
        <a href="<?php echo BASE_URL; ?>/modules/profile/profile.php" 
           class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'profile.php') !== false ? 'active' : ''; ?>">
            <i class="bi bi-person me-2"></i>
            Profile
        </a>
        
        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php" class="list-group-item list-group-item-action text-danger">
            <i class="bi bi-box-arrow-right me-2"></i>
            Logout
        </a>
    </div>
</div>
