<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_role('admin');

header('Content-Type: application/json');

$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;

if ($department_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid department ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title 
        FROM designations 
        WHERE department_id = ? AND status = 'active' 
        ORDER BY title ASC
    ");
    $stmt->execute([$department_id]);
    $designations = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'designations' => $designations]);
} catch (PDOException $e) {
    error_log("Designations fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch designations']);
}
