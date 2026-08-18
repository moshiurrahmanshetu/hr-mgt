<?php
$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../templates/header.php';

require_role('admin');

// Get real statistics
try {
    // Total employees (excluding soft-deleted)
    $total_employees_stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL");
    $total_employees = $total_employees_stmt->fetchColumn();
    
    // Active employees (deleted_at IS NULL AND employment_status = 'active' AND user account is active)
    $active_employees_stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        WHERE e.deleted_at IS NULL 
        AND e.employment_status = 'active' 
        AND u.status = 'active'
    ");
    $active_employees = $active_employees_stmt->fetchColumn();
    
    // Total departments
    $departments_stmt = $pdo->query("SELECT COUNT(*) FROM departments WHERE status = 'active'");
    $departments = $departments_stmt->fetchColumn();
    
    // Today's attendance stats
    $today = date('Y-m-d');
    
    // Today's present (present + late)
    $today_present_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM attendance 
        WHERE date = ? AND status IN ('present', 'late')
    ");
    $today_present_stmt->execute([$today]);
    $today_present = $today_present_stmt->fetchColumn();
    
    // Today's absent (actual 'absent' status only - not counting no-record employees)
    $today_absent_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM attendance 
        WHERE date = ? AND status = 'absent'
    ");
    $today_absent_stmt->execute([$today]);
    $today_absent = $today_absent_stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $total_employees = 0;
    $active_employees = 0;
    $departments = 0;
    $today_present = 0;
    $today_absent = 0;
}
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Admin Dashboard</h2>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($current_user['name']); ?>!</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-primary bg-opacity-10 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                    <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value"><?php echo $total_employees; ?></h3>
                <p class="stat-card-label">Total Employees</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-success bg-opacity-10 text-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-person-check" viewBox="0 0 16 16">
                    <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H1s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                    <path fill-rule="evenodd" d="M15.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L12.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value"><?php echo $active_employees; ?></h3>
                <p class="stat-card-label">Active Employees</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-info bg-opacity-10 text-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-building" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M14.763.075A.5.5 0 0 1 15 .5v15a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .342-.474L6 7.64V4.5a.5.5 0 0 1 .276-.447l8-4a.5.5 0 0 1 .487.022zM6 8.694L1 10.36V15h5V8.694zM7 15h2v-1.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5V15h2V1.293l-7 3.5V15z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value"><?php echo $departments; ?></h3>
                <p class="stat-card-label">Departments</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-warning bg-opacity-10 text-warning">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-calendar-check" viewBox="0 0 16 16">
                    <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value"><?php echo $today_present; ?></h3>
                <p class="stat-card-label">Today's Present</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-danger bg-opacity-10 text-danger">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-calendar-x" viewBox="0 0 16 16">
                    <path d="M6.146 7.146a.5.5 0 0 1 .708 0L8 8.293l1.146-1.147a.5.5 0 0 1 .708.708L8.707 9l1.147 1.146a.5.5 0 0 1-.708.708L8 9.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 9 6.146 7.854a.5.5 0 0 1 0-.708z"/>
                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value"><?php echo $today_absent; ?></h3>
                <p class="stat-card-label">Today's Absent</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-secondary bg-opacity-10 text-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16">
                    <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.074.997zm2.004.45a7.003 7.003 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342l-.36.933zm1.37.71a7.01 7.01 0 0 0-.439-.27l.493-.87a8.025 8.025 0 0 1 .979.654l-.615.789a6.996 6.996 0 0 0-.418-.302zm1.834 1.79a6.99 6.99 0 0 0-.653-.796l.79-.616c.347.445.653.938.89 1.483l-.927.529zm.744 1.352a7.08 7.08 0 0 0-.214-.468l.893-.45a7.976 7.976 0 0 1 .45 1.082l-.95.313a7.023 7.023 0 0 0-.179-.477zm.03 1.484a6.977 6.977 0 0 0 .087-.51l.983.165c-.075.548-.187 1.08-.335 1.593l-.935-.262c.101-.384.17-.78.2-1.186zm.083 1.262a7.07 7.07 0 0 0-.008-.398l.996-.063a8.008 8.008 0 0 1 .046.655l-.998.072c-.014-.192-.026-.384-.034-.566zM16 8a8 8 0 1 1-16 0A8 8 0 0 1 16 8zM8 4.5a.5.5 0 0 0-1 0v3.363l-1.429 2.38a.5.5 0 1 0 .858.515L8 8.309V4.5z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value">0</h3>
                <p class="stat-card-label">Pending Leaves</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-primary bg-opacity-10 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-currency-dollar" viewBox="0 0 16 16">
                    <path d="M5.5 9.5A.5.5 0 0 1 6 9h4a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5z"/>
                    <path d="M3.5.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-.5.5H9V9.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 0 0-1H9a.5.5 0 0 1-.5-.5V3.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 0 0-1H9a.5.5 0 0 1-.5-.5V.5a.5.5 0 0 1 .5-.5h-3a.5.5 0 0 1-.5.5v2a.5.5 0 0 1-.5.5H3.5a.5.5 0 0 0 0 1H5a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-.5.5H3.5a.5.5 0 0 0 0 1H5a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-8z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value">$0</h3>
                <p class="stat-card-label">Payroll Summary</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-success bg-opacity-10 text-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-graph-up" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M0 0h1v15h15v1H0V0Zm10 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V4.9l-3.613 4.417a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61L13.445 4H10.5a.5.5 0 0 1-.5-.5Z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value">0%</h3>
                <p class="stat-card-label">Attendance Rate</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="alert alert-info d-flex align-items-center" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-info-circle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
            </svg>
            <div>
                <strong>Welcome to the HR Management System!</strong> This is the admin dashboard. The statistics above will be populated with real data in the next phases of development.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
