<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $date = $_GET['date'] ?? '';
    $userId = $_SESSION['user_id'];
    
    if (!$date) {
        throw new Exception('Date parameter is required');
    }
    
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT 
            id,
            user_id,
            date,
            time_in,
            time_out,
            status,
            image_path,
            notes,
            created_at,
            updated_at,
            CASE 
                WHEN time_in IS NOT NULL AND time_out IS NOT NULL 
                THEN TIMEDIFF(time_out, time_in)
                ELSE NULL 
            END as working_hours
        FROM attendance 
        WHERE user_id = ? AND date = ?
    ");
    
    $stmt->execute([$userId, $date]);
    $record = $stmt->fetch();
    
    if (!$record) {
        throw new Exception('No attendance record found for the specified date');
    }
    
    echo json_encode([
        'success' => true,
        'record' => $record
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>