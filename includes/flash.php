<?php
/**
 * Session Flash Notification System
 * Developed by Senior PHP Software Architect
 * 
 * Provides an easy, non-blocking notification mechanism to display system 
 * responses (e.g. "Save successful", "Invalid login") that survive redirects 
 * and self-clear on display.
 */

/**
 * Stores a notification in the session.
 * 
 * @param string $type The alert category: 'success', 'danger', 'warning', 'info'
 * @param string $message The alert content
 */
function flash_set(string $type, string $message): void {
    if ($type === 'error') $type = 'danger'; // map generic error to bootstrap danger
    $_SESSION['flash_notifications'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Displays and immediately clears all pending session flash messages.
 * Uses beautiful, responsive Bootstrap 5 Alert templates with corresponding SVG icons.
 * 
 * @return string HTML alerts list
 */
function flash_display(): string {
    $html = '';
    
    // Gather and map single historic parameters (compatibility with simple setups)
    if (!empty($_SESSION['flash_success'])) {
        $_SESSION['flash_notifications'][] = ['type' => 'success', 'message' => $_SESSION['flash_success']];
        unset($_SESSION['flash_success']);
    }
    if (!empty($_SESSION['flash_error'])) {
        $_SESSION['flash_notifications'][] = ['type' => 'danger', 'message' => $_SESSION['flash_error']];
        unset($_SESSION['flash_error']);
    }

    if (empty($_SESSION['flash_notifications'])) {
        return '';
    }

    $alerts = $_SESSION['flash_notifications'];
    unset($_SESSION['flash_notifications']); // Self-clear

    foreach ($alerts as $alert) {
        $type = sanitize($alert['type']);
        $msg = sanitize($alert['message']);
        
        // Match specific icons for specific statuses
        $icon = '';
        if ($type === 'success') {
            $icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Success:" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>';
        } elseif ($type === 'danger') {
            $icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Danger:" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>';
        } elseif ($type === 'warning') {
            $icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>';
        } else {
            $icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>';
        }

        $html .= sprintf(
            '<div class="alert alert-%s alert-dismissible fade show d-flex align-items-center" role="alert" id="alert-%s">
                %s
                <div>%s</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>',
            $type,
            uniqid(),
            $icon,
            $msg
        );
    }
    
    return $html;
}
