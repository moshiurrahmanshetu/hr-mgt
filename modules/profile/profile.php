<?php
$page_title = 'Profile';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../templates/header.php';

require_login();

$current_user = get_logged_in_user();
$avatar_url = get_avatar_url($current_user['avatar']);

// Initialize message variables
$profile_message = '';
$profile_message_type = '';
$password_message = '';
$password_message_type = '';
$avatar_message = '';
$avatar_message_type = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf_token()) {
        $profile_message = 'Invalid form submission. Please try again.';
        $profile_message_type = 'danger';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        
        if (empty($name)) {
            $profile_message = 'Name is required.';
            $profile_message_type = 'danger';
        } elseif (strlen($name) < 2) {
            $profile_message = 'Name must be at least 2 characters.';
            $profile_message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$name, $_SESSION['user_id']]);
                
                // Update session
                $_SESSION['name'] = $name;
                
                // Log activity
                log_activity($_SESSION['user_id'], 'profile_update', 'User updated profile information');
                
                // Refresh user data
                $current_user = get_logged_in_user();
                
                $profile_message = 'Profile updated successfully!';
                $profile_message_type = 'success';
            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
                $profile_message = 'An error occurred while updating your profile.';
                $profile_message_type = 'danger';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verify_csrf_token()) {
        $password_message = 'Invalid form submission. Please try again.';
        $password_message_type = 'danger';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $password_message = 'All password fields are required.';
            $password_message_type = 'danger';
        } elseif (!password_verify($current_password, $current_user['password'])) {
            $password_message = 'Current password is incorrect.';
            $password_message_type = 'danger';
        } elseif (strlen($new_password) < 8) {
            $password_message = 'New password must be at least 8 characters.';
            $password_message_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $password_message = 'New password and confirm password do not match.';
            $password_message_type = 'danger';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                // Log activity
                log_activity($_SESSION['user_id'], 'password_change', 'User changed password');
                
                // Refresh user data
                $current_user = get_logged_in_user();
                
                $password_message = 'Password changed successfully!';
                $password_message_type = 'success';
            } catch (PDOException $e) {
                error_log("Password change error: " . $e->getMessage());
                $password_message = 'An error occurred while changing your password.';
                $password_message_type = 'danger';
            }
        }
    }
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_avatar') {
    if (!verify_csrf_token()) {
        $avatar_message = 'Invalid form submission. Please try again.';
        $avatar_message_type = 'danger';
    } else {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $avatar_message = 'Please select a file to upload.';
            $avatar_message_type = 'danger';
        } else {
            $file = $_FILES['avatar'];
            
            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, ALLOWED_AVATAR_TYPES)) {
                $avatar_message = 'Invalid file type. Please upload a JPG, PNG, or WebP image.';
                $avatar_message_type = 'danger';
            } elseif ($file['size'] > MAX_AVATAR_SIZE) {
                $avatar_message = 'File size exceeds the maximum limit of 2MB.';
                $avatar_message_type = 'danger';
            } else {
                try {
                    // Delete old avatar if exists
                    if ($current_user['avatar']) {
                        $old_avatar_path = __DIR__ . '/../../uploads/avatars/' . $current_user['avatar'];
                        if (file_exists($old_avatar_path)) {
                            unlink($old_avatar_path);
                        }
                    }
                    
                    // Generate unique filename
                    $new_filename = generate_unique_filename($file['name']);
                    $upload_path = __DIR__ . '/../../uploads/avatars/' . $new_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Update database
                        $stmt = $pdo->prepare("UPDATE users SET avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$new_filename, $_SESSION['user_id']]);
                        
                        // Update session
                        $_SESSION['avatar'] = $new_filename;
                        
                        // Log activity
                        log_activity($_SESSION['user_id'], 'avatar_change', 'User changed profile avatar');
                        
                        // Refresh user data and avatar URL
                        $current_user = get_logged_in_user();
                        $avatar_url = get_avatar_url($current_user['avatar']);
                        
                        $avatar_message = 'Avatar updated successfully!';
                        $avatar_message_type = 'success';
                    } else {
                        $avatar_message = 'Failed to upload file. Please try again.';
                        $avatar_message_type = 'danger';
                    }
                } catch (PDOException $e) {
                    error_log("Avatar upload error: " . $e->getMessage());
                    $avatar_message = 'An error occurred while uploading your avatar.';
                    $avatar_message_type = 'danger';
                }
            }
        }
    }
}

// Handle avatar deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_avatar') {
    if (!verify_csrf_token()) {
        $avatar_message = 'Invalid form submission. Please try again.';
        $avatar_message_type = 'danger';
    } else {
        if ($current_user['avatar']) {
            try {
                // Delete file
                $avatar_path = __DIR__ . '/../../uploads/avatars/' . $current_user['avatar'];
                if (file_exists($avatar_path)) {
                    unlink($avatar_path);
                }
                
                // Update database
                $stmt = $pdo->prepare("UPDATE users SET avatar = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                // Update session
                $_SESSION['avatar'] = null;
                
                // Log activity
                log_activity($_SESSION['user_id'], 'avatar_delete', 'User deleted profile avatar');
                
                // Refresh user data and avatar URL
                $current_user = get_logged_in_user();
                $avatar_url = get_avatar_url($current_user['avatar']);
                
                $avatar_message = 'Avatar deleted successfully!';
                $avatar_message_type = 'success';
            } catch (PDOException $e) {
                error_log("Avatar deletion error: " . $e->getMessage());
                $avatar_message = 'An error occurred while deleting your avatar.';
                $avatar_message_type = 'danger';
            }
        } else {
            $avatar_message = 'No avatar to delete.';
            $avatar_message_type = 'warning';
        }
    }
}
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Profile</h2>
        <p class="text-muted">Manage your account settings and preferences</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="edit-profile-tab" data-bs-toggle="tab" data-bs-target="#edit-profile" type="button" role="tab">Edit Profile</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="change-password-tab" data-bs-toggle="tab" data-bs-target="#change-password" type="button" role="tab">Change Password</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="change-avatar-tab" data-bs-toggle="tab" data-bs-target="#change-avatar" type="button" role="tab">Change Avatar</button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="profileTabsContent">
                    <!-- Edit Profile Tab -->
                    <div class="tab-pane fade show active" id="edit-profile" role="tabpanel">
                        <?php if ($profile_message): ?>
                            <div class="alert alert-<?php echo $profile_message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($profile_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($current_user['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($current_user['email']); ?>" readonly>
                                <div class="form-text">Email cannot be changed. Contact administrator if needed.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" name="role" 
                                       value="<?php echo htmlspecialchars(ucfirst($current_user['role_name'])); ?>" readonly>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                    
                    <!-- Change Password Tab -->
                    <div class="tab-pane fade" id="change-password" role="tabpanel">
                        <?php if ($password_message): ?>
                            <div class="alert alert-<?php echo $password_message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($password_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                <div class="form-text">Password must be at least 8 characters.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                    
                    <!-- Change Avatar Tab -->
                    <div class="tab-pane fade" id="change-avatar" role="tabpanel">
                        <?php if ($avatar_message): ?>
                            <div class="alert alert-<?php echo $avatar_message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($avatar_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label class="form-label">Current Avatar</label>
                            <div class="d-flex align-items-center">
                                <img src="<?php echo $avatar_url; ?>" alt="Current Avatar" class="rounded-circle me-3" width="80" height="80">
                                <div>
                                    <p class="mb-1"><?php echo $current_user['avatar'] ? htmlspecialchars($current_user['avatar']) : 'Default avatar'; ?></p>
                                    <?php if ($current_user['avatar']): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_avatar">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete your avatar?')">Delete Avatar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                            <input type="hidden" name="action" value="change_avatar">
                            
                            <div class="mb-3">
                                <label for="avatar" class="form-label">Upload New Avatar</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">Allowed formats: JPG, PNG, WebP. Maximum size: 2MB.</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Upload Avatar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <img src="<?php echo $avatar_url; ?>" alt="Profile Avatar" class="rounded-circle mb-3" width="120" height="120">
                <h5 class="card-title"><?php echo htmlspecialchars($current_user['name']); ?></h5>
                <p class="card-text text-muted"><?php echo htmlspecialchars($current_user['email']); ?></p>
                <span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst($current_user['role_name'])); ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
