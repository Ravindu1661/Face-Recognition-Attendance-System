<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id']) || !isset($input['face_descriptor'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Check if attendance already marked today
    $stmt = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = CURDATE()");
    $stmt->execute([$input['user_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Attendance already marked today']);
        exit;
    }

    // Get current time and determine status
    $currentTime = date('H:i:s');
    $lateThreshold = '09:15:00'; // Can be fetched from settings table
    $status = ($currentTime > $lateThreshold) ? 'late' : 'present';

    // Insert attendance record
    $stmt = $db->prepare("
        INSERT INTO attendance (user_id, date, check_in_time, status, confidence_score, ip_address) 
        VALUES (?, CURDATE(), ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $input['user_id'],
        $currentTime,
        $status,
        $input['confidence'] ?? 0,
        $_SERVER['REMOTE_ADDR']
    ]);

    if ($result) {
        // Log the attendance
        $stmt = $db->prepare("
            INSERT INTO attendance_logs (user_id, action, confidence_score, ip_address, user_agent) 
            VALUES (?, 'check_in', ?, ?, ?)
        ");
        $stmt->execute([
            $input['user_id'],
            $input['confidence'] ?? 0,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        echo json_encode([
            'success' => true, 
            'message' => 'Attendance marked successfully',
            'status' => $status,
            'time' => $currentTime
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save attendance']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>