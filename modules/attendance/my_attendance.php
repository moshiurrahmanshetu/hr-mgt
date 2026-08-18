<?php
$page_title = 'My Attendance';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_login();

// Get employee data for logged-in user
try {
    $stmt = $pdo->prepare("
        SELECT e.id as employee_id, e.employee_code, u.name as user_name 
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        WHERE e.user_id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        redirect_with_flash(BASE_URL . '/modules/dashboard/employee_dashboard.php', 'warning', 'Employee record not found.');
    }
} catch (PDOException $e) {
    error_log("Employee fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/dashboard/employee_dashboard.php', 'danger', 'An error occurred.');
}

$today = date('Y-m-d');
$message = '';
$message_type = '';

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_in') {
    if (!verify_csrf_token()) {
        $message = 'Invalid form submission.';
        $message_type = 'danger';
    } else {
        try {
            // Check if attendance record already exists for today
            $check_stmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE employee_id = ? AND date = ?
            ");
            $check_stmt->execute([$employee['employee_id'], $today]);
            
            if ($check_stmt->fetch()) {
                $message = 'You have already checked in today.';
                $message_type = 'warning';
            } else {
                // Determine status based on office start time
                $current_time = date('H:i:s');
                $status = $current_time > OFFICE_START_TIME ? 'late' : 'present';
                
                // Insert attendance record
                $insert_stmt = $pdo->prepare("
                    INSERT INTO attendance (employee_id, date, check_in, status, created_at) 
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $insert_stmt->execute([$employee['employee_id'], $today, $current_time, $status]);
                
                log_activity($_SESSION['user_id'], 'check_in', "Checked in at $current_time, status: $status");
                
                redirect_with_flash(BASE_URL . '/modules/attendance/my_attendance.php', 'success', "Checked in successfully! Status: " . ucfirst($status));
            }
        } catch (PDOException $e) {
            // Check for unique constraint violation (race condition)
            if ($e->getCode() == 23000) {
                $message = 'You have already checked in today.';
                $message_type = 'warning';
            } else {
                error_log("Check-in error: " . $e->getMessage());
                $message = 'An error occurred while checking in.';
                $message_type = 'danger';
            }
        }
    }
}

// Handle check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_out') {
    if (!verify_csrf_token()) {
        $message = 'Invalid form submission.';
        $message_type = 'danger';
    } else {
        try {
            $current_time = date('H:i:s');
            
            // Update today's attendance record
            $update_stmt = $pdo->prepare("
                UPDATE attendance 
                SET check_out = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE employee_id = ? AND date = ?
            ");
            $update_stmt->execute([$current_time, $employee['employee_id'], $today]);
            
            if ($update_stmt->rowCount() > 0) {
                log_activity($_SESSION['user_id'], 'check_out', "Checked out at $current_time");
                redirect_with_flash(BASE_URL . '/modules/attendance/my_attendance.php', 'success', 'Checked out successfully!');
            } else {
                $message = 'No check-in record found for today.';
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            error_log("Check-out error: " . $e->getMessage());
            $message = 'An error occurred while checking out.';
            $message_type = 'danger';
        }
    }
}

// Get today's attendance status
$today_attendance = null;
try {
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->execute([$employee['employee_id'], $today]);
    $today_attendance = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Today's attendance fetch error: " . $e->getMessage());
}

// Get attendance history with month filter
$month_filter = $_GET['month'] ?? date('Y-m');
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

try {
    $where = ['employee_id = ?'];
    $params = [$employee['employee_id']];
    
    if (!empty($month_filter)) {
        $where[] = "DATE_FORMAT(date, '%Y-%m') = ?";
        $params[] = $month_filter;
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    // Get attendance records
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE $where_clause 
        ORDER BY date DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $attendance_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Attendance history fetch error: " . $e->getMessage());
    $attendance_history = [];
    $total_records = 0;
    $total_pages = 1;
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">My Attendance</h2>
        <p class="text-muted">Track your daily attendance</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Today's Status Card -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Today's Status - <?php echo date('F d, Y'); ?></h5>
        
        <?php if (!$today_attendance): ?>
            <div class="d-flex align-items-center">
                <div class="alert alert-info mb-0 me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-info-circle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    Not checked in yet
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="action" value="check_in">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history me-1" viewBox="0 0 16 16">
                            <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.074.997zm2.004.45a7.003 7.003 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342l-.36.933zm1.37.71a7.01 7.01 0 0 0-.439-.27l.493-.87a8.025 8.025 0 0 1 .979.654l-.615.789a6.996 6.996 0 0 0-.418-.302zm1.834 1.79a6.99 6.99 0 0 0-.653-.796l.79-.616c.347.445.653.938.89 1.483l-.927.529zm.744 1.352a7.08 7.08 0 0 0-.214-.468l.893-.45a7.976 7.976 0 0 1 .45 1.082l-.95.313a7.023 7.023 0 0 0-.179-.477zm.03 1.484a6.977 6.977 0 0 0 .087-.51l.983.165c-.075.548-.187 1.08-.335 1.593l-.935-.262c.101-.384.17-.78.2-1.186zm.083 1.262a7.07 7.07 0 0 0-.008-.398l.996-.063a8.008 8.008 0 0 1 .046.655l-.998.072c-.014-.192-.026-.384-.034-.566zM16 8a8 8 0 1 1-16 0A8 8 0 0 1 16 8zM8 4.5a.5.5 0 0 0-1 0v3.363l-1.429 2.38a.5.5 0 1 0 .858.515L8 8.309V4.5z"/>
                        </svg>
                        Check In
                    </button>
                </form>
            </div>
        <?php elseif ($today_attendance['check_out']): ?>
            <div class="d-flex align-items-center">
                <div class="alert alert-success mb-0 me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                    </svg>
                    Checked out at <?php echo date('h:i A', strtotime($today_attendance['check_out'])); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="d-flex align-items-center">
                <div class="alert alert-primary mb-0 me-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-clock-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                    </svg>
                    Checked in at <?php echo date('h:i A', strtotime($today_attendance['check_in'])); ?>
                    <span class="badge <?php echo $today_attendance['status'] === 'late' ? 'bg-warning bg-opacity-10 text-warning' : 'bg-success bg-opacity-10 text-success'; ?> ms-2">
                        <?php echo ucfirst($today_attendance['status']); ?>
                    </span>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="action" value="check_out">
                    <button type="submit" class="btn btn-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right me-1" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                        </svg>
                        Check Out
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Attendance History -->
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="card-title mb-0">Attendance History</h5>
            <form method="GET" action="" class="d-flex gap-2">
                <input type="month" class="form-control" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
                <a href="<?php echo BASE_URL; ?>/modules/attendance/my_attendance.php" class="btn btn-outline-secondary">Clear</a>
            </form>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance_history)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">No attendance records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendance_history as $record): ?>
                            <tr>
                                <td><?php echo date('F d, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                                <td><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                <td>
                                    <?php
                                    $badge_class = match($record['status']) {
                                        'present' => 'bg-success bg-opacity-10 text-success',
                                        'late' => 'bg-warning bg-opacity-10 text-warning',
                                        'absent' => 'bg-danger bg-opacity-10 text-danger',
                                        'leave' => 'bg-info bg-opacity-10 text-info',
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($record['status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <?php echo get_pagination($page, $total_pages, BASE_URL . '/modules/attendance/my_attendance.php', ['month' => $month_filter]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
