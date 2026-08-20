<?php
// Common helper functions

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Set flash message
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Redirect with flash message
 */
function redirect_with_flash($url, $type, $message) {
    set_flash_message($type, $message);
    header('Location: ' . $url);
    exit;
}

/**
 * Get avatar URL or default
 */
function get_avatar_url($avatar_filename) {
    if ($avatar_filename && file_exists(__DIR__ . '/../uploads/avatars/' . $avatar_filename)) {
        return BASE_URL . '/uploads/avatars/' . $avatar_filename;
    }
    // Return a simple SVG data URI as default avatar
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIgZmlsbD0iI2UyZThmMCIvPjxjaXJjbGUgY3g9IjUwIiBjeT0iNDAiIHI9IjIwIiBmaWxsPSIjNjQ3NDhiIi8+PHBhdGggZD0iTTUwIDcwYzE1IDAgMzAgMTAgMzAgMjB2MTBIMjB2LTEwYzAtMTAgMTUtMjAgMzAtMjB6IiBmaWxsPSIjNjQ3NDhiIi8+PC9zdmc+';
}

/**
 * Generate unique filename for upload
 */
function generate_unique_filename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    return uniqid('avatar_', true) . '.' . $extension;
}

/**
 * Check login attempts (brute force protection)
 */
function check_login_attempts($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        return true;
    }
    
    $attempts = $_SESSION['login_attempts'][$email];
    
    // Reset if lockout time has passed
    if (time() - $attempts['first_attempt'] > LOGIN_LOCKOUT_TIME) {
        unset($_SESSION['login_attempts'][$email]);
        return true;
    }
    
    // Block if max attempts reached
    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    
    return true;
}

/**
 * Record failed login attempt
 */
function record_failed_attempt($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        $_SESSION['login_attempts'][$email] = [
            'count' => 1,
            'first_attempt' => time()
        ];
    } else {
        $_SESSION['login_attempts'][$email]['count']++;
    }
}

/**
 * Clear login attempts on successful login
 */
function clear_login_attempts($email) {
    if (isset($_SESSION['login_attempts'][$email])) {
        unset($_SESSION['login_attempts'][$email]);
    }
}

/**
 * Get remaining lockout time in seconds
 */
function get_lockout_time($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        return 0;
    }
    
    $attempts = $_SESSION['login_attempts'][$email];
    if ($attempts['count'] < MAX_LOGIN_ATTEMPTS) {
        return 0;
    }
    
    $elapsed = time() - $attempts['first_attempt'];
    $remaining = LOGIN_LOCKOUT_TIME - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Generate pagination HTML
 */
function get_pagination($current_page, $total_pages, $base_url, $query_params = []) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $params = array_merge($query_params, ['page' => $current_page - 1]);
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($params) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $params = array_merge($query_params, ['page' => 1]);
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($params) . '">1</a></li>';
        if ($start_page > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $params = array_merge($query_params, ['page' => $i]);
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($params) . '">' . $i . '</a></li>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $params = array_merge($query_params, ['page' => $total_pages]);
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($params) . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $params = array_merge($query_params, ['page' => $current_page + 1]);
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?' . http_build_query($params) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Get status badge HTML
 */
function get_status_badge($status) {
    $badge_class = $status === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary';
    return '<span class="badge ' . $badge_class . '">' . ucfirst($status) . '</span>';
}

/**
 * Truncate text to specified length
 */
function truncate_text($text, $length = 50, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Toggle status in database
 */
function toggle_status($table, $id, $current_status) {
    global $pdo;
    
    $new_status = $current_status === 'active' ? 'inactive' : 'active';
    
    try {
        $stmt = $pdo->prepare("UPDATE $table SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        return $new_status;
    } catch (PDOException $e) {
        error_log("Status toggle error: " . $e->getMessage());
        return false;
    }
}

/**
 * Handle avatar upload (reusable function)
 * Returns new filename on success, error message on failure
 */
function handle_avatar_upload($file, $old_avatar = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Please select a file to upload.'];
    }
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_AVATAR_TYPES)) {
        return ['success' => false, 'error' => 'Invalid file type. Please upload a JPG, PNG, or WebP image.'];
    }
    
    if ($file['size'] > MAX_AVATAR_SIZE) {
        return ['success' => false, 'error' => 'File size exceeds the maximum limit of 2MB.'];
    }
    
    try {
        // Delete old avatar if exists
        if ($old_avatar) {
            $old_avatar_path = __DIR__ . '/../uploads/avatars/' . $old_avatar;
            if (file_exists($old_avatar_path)) {
                unlink($old_avatar_path);
            }
        }
        
        // Generate unique filename
        $new_filename = generate_unique_filename($file['name']);
        $upload_path = __DIR__ . '/../uploads/avatars/' . $new_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            return ['success' => true, 'filename' => $new_filename];
        } else {
            return ['success' => false, 'error' => 'Failed to upload file. Please try again.'];
        }
    } catch (Exception $e) {
        error_log("Avatar upload error: " . $e->getMessage());
        return ['success' => false, 'error' => 'An error occurred while uploading your avatar.'];
    }
}

/**
 * Generate employee code (EMP-000001 format)
 * Uses a lock approach to prevent race conditions
 */
function generate_employee_code() {
    global $pdo;
    
    try {
        // Lock the table to prevent race conditions
        $pdo->exec("LOCK TABLES employees WRITE");
        
        // Get the maximum existing employee code
        $stmt = $pdo->query("SELECT MAX(employee_code) as max_code FROM employees WHERE deleted_at IS NULL");
        $result = $stmt->fetch();
        
        $max_code = $result['max_code'] ?? 'EMP-000000';
        $max_number = intval(substr($max_code, -6));
        $new_number = $max_number + 1;
        
        // Format as EMP-000001
        $new_code = 'EMP-' . str_pad($new_number, 6, '0', STR_PAD_LEFT);
        
        // Unlock the table
        $pdo->exec("UNLOCK TABLES");
        
        return $new_code;
    } catch (PDOException $e) {
        // Make sure to unlock even on error
        try {
            $pdo->exec("UNLOCK TABLES");
        } catch (PDOException $unlock_error) {
            error_log("Unlock error: " . $unlock_error->getMessage());
        }
        
        error_log("Employee code generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate leave balance for an employee and leave type
 * Returns array with 'used' and 'remaining' days for the current calendar year
 */
function calculate_leave_balance($employee_id, $leave_type_id) {
    global $pdo;
    
    try {
        // Get max days per year for this leave type
        $stmt = $pdo->prepare("SELECT max_days_per_year FROM leave_types WHERE id = ?");
        $stmt->execute([$leave_type_id]);
        $max_days = $stmt->fetchColumn();
        
        if (!$max_days) {
            return ['used' => 0, 'remaining' => 0];
        }
        
        // Calculate used days for this year (approved requests only)
        $current_year = date('Y');
        $stmt = $pdo->prepare("
            SELECT SUM(total_days) as used_days 
            FROM leave_requests 
            WHERE employee_id = ? 
            AND leave_type_id = ? 
            AND status = 'approved' 
            AND YEAR(start_date) = ?
        ");
        $stmt->execute([$employee_id, $leave_type_id, $current_year]);
        $used_days = $stmt->fetchColumn() ?: 0;
        
        $remaining = max(0, $max_days - $used_days);
        
        return ['used' => $used_days, 'remaining' => $remaining, 'max' => $max_days];
    } catch (PDOException $e) {
        error_log("Leave balance calculation error: " . $e->getMessage());
        return ['used' => 0, 'remaining' => 0, 'max' => 0];
    }
}

/**
 * Check for overlapping leave requests for an employee
 * Returns true if overlapping dates exist, false otherwise
 */
function check_leave_overlap($employee_id, $start_date, $end_date, $exclude_request_id = null) {
    global $pdo;
    
    try {
        $where = "employee_id = ? AND status IN ('pending', 'approved') AND (
            (start_date <= ? AND end_date >= ?) OR 
            (start_date <= ? AND end_date >= ?)
        )";
        $params = [$employee_id, $end_date, $start_date, $start_date, $end_date];
        
        if ($exclude_request_id) {
            $where .= " AND id != ?";
            $params[] = $exclude_request_id;
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE $where");
        $stmt->execute($params);
        $count = $stmt->fetchColumn();
        
        return $count > 0;
    } catch (PDOException $e) {
        error_log("Leave overlap check error: " . $e->getMessage());
        return false; // Fail open to avoid blocking on errors
    }
}
