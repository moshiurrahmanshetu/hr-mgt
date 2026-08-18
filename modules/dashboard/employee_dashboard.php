<?php
$page_title = 'Employee Dashboard';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../templates/header.php';

require_role('employee');

// Get employee data for the logged-in user
$employee_data = null;
try {
    $stmt = $pdo->prepare("
        SELECT e.*, d.name as department_name, des.title as designation_name 
        FROM employees e 
        JOIN departments d ON e.department_id = d.id 
        JOIN designations des ON e.designation_id = des.id 
        WHERE e.user_id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $employee_data = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Employee data fetch error: " . $e->getMessage());
}

// Get today's attendance status
$today_attendance = null;
if ($employee_data) {
    try {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? AND date = ?
        ");
        $stmt->execute([$employee_data['id'], $today]);
        $today_attendance = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Today's attendance fetch error: " . $e->getMessage());
    }
}
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Employee Dashboard</h2>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($current_user['name']); ?>!</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-primary bg-opacity-10 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value"><?php echo htmlspecialchars($current_user['name']); ?></h3>
                <p class="stat-card-label">Profile Summary</p>
                <?php if ($employee_data): ?>
                    <small class="text-muted">
                        <?php echo htmlspecialchars($employee_data['employee_code']); ?><br>
                        <?php echo htmlspecialchars($employee_data['department_name']); ?><br>
                        <?php echo htmlspecialchars($employee_data['designation_name']); ?><br>
                        Joined: <?php echo date('M d, Y', strtotime($employee_data['joining_date'])); ?>
                    </small>
                <?php else: ?>
                    <small class="text-muted">Profile details not available</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-success bg-opacity-10 text-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-calendar-check" viewBox="0 0 16 16">
                    <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <?php if (!$today_attendance): ?>
                    <h3 class="stat-card-value">Not Checked In</h3>
                <?php elseif ($today_attendance['check_out']): ?>
                    <h3 class="stat-card-value">Checked Out</h3>
                    <small class="text-muted"><?php echo date('h:i A', strtotime($today_attendance['check_out'])); ?></small>
                <?php else: ?>
                    <h3 class="stat-card-value"><?php echo ucfirst($today_attendance['status']); ?></h3>
                    <small class="text-muted"><?php echo date('h:i A', strtotime($today_attendance['check_in'])); ?></small>
                <?php endif; ?>
                <p class="stat-card-label">Today's Attendance</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-info bg-opacity-10 text-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-calendar-range" viewBox="0 0 16 16">
                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                    <path d="M3.5 6a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1H4v3.5a.5.5 0 0 1-1 0V6zm6 0a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1H10v3.5a.5.5 0 0 1-1 0V6z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value">0 days</h3>
                <p class="stat-card-label">Leave Balance</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon bg-warning bg-opacity-10 text-warning">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16">
                    <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.074.997zm2.004.45a7.003 7.003 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342l-.36.933zm1.37.71a7.01 7.01 0 0 0-.439-.27l.493-.87a8.025 8.025 0 0 1 .979.654l-.615.789a6.996 6.996 0 0 0-.418-.302zm1.834 1.79a6.99 6.99 0 0 0-.653-.796l.79-.616c.347.445.653.938.89 1.483l-.927.529zm.744 1.352a7.08 7.08 0 0 0-.214-.468l.893-.45a7.976 7.976 0 0 1 .45 1.082l-.95.313a7.023 7.023 0 0 0-.179-.477zm.03 1.484a6.977 6.977 0 0 0 .087-.51l.983.165c-.075.548-.187 1.08-.335 1.593l-.935-.262c.101-.384.17-.78.2-1.186zm.083 1.262a7.07 7.07 0 0 0-.008-.398l.996-.063a8.008 8.008 0 0 1 .046.655l-.998.072c-.014-.192-.026-.384-.034-.566zM16 8a8 8 0 1 1-16 0A8 8 0 0 1 16 8zM8 4.5a.5.5 0 0 0-1 0v3.363l-1.429 2.38a.5.5 0 1 0 .858.515L8 8.309V4.5z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value">0</h3>
                <p class="stat-card-label">Pending Requests</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-card-icon bg-primary bg-opacity-10 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-currency-dollar" viewBox="0 0 16 16">
                    <path d="M5.5 9.5A.5.5 0 0 1 6 9h4a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5z"/>
                    <path d="M3.5.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-.5.5H9V9.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 0 0-1H9a.5.5 0 0 1-.5-.5V3.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 0 0-1H9a.5.5 0 0 1-.5-.5V.5a.5.5 0 0 1 .5-.5h-3a.5.5 0 0 1-.5.5v2a.5.5 0 0 1-.5.5H3.5a.5.5 0 0 0 0 1H5a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-.5.5H3.5a.5.5 0 0 0 0 1H5a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-8z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value">No payslip available</h3>
                <p class="stat-card-label">Latest Payslip Info</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-card-icon bg-success bg-opacity-10 text-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-building" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M14.763.075A.5.5 0 0 1 15 .5v15a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .342-.474L6 7.64V4.5a.5.5 0 0 1 .276-.447l8-4a.5.5 0 0 1 .487.022zM6 8.694L1 10.36V15h5V8.694zM7 15h2v-1.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5V15h2V1.293l-7 3.5V15z"/>
                </svg>
            </div>
            <div class="stat-card-content">
                <h3 class="stat-card-value"><?php echo $employee_data ? htmlspecialchars($employee_data['department_name']) : 'Not Assigned'; ?></h3>
                <p class="stat-card-label">Department</p>
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
                <strong>Welcome to the HR Management System!</strong> Your profile summary is now available. Attendance, leave, and payroll features will be added in future phases.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
