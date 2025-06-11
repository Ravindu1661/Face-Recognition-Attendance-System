<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');
    
    $db = Database::getInstance()->getConnection();
    
    // Check if user has checked in today
    $stmt = $db->prepare("SELECT id, time_in, time_out FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $currentDate]);
    $attendance = $stmt->fetch();
    
    if (!$attendance) {
        throw new Exception('No check-in record found for today');
    }
    
    if ($attendance['time_out']) {
        throw new Exception('Already checked out today at ' . date('h:i A', strtotime($attendance['time_out'])));
    }
    
    // Update with check out time
    $stmt = $db->prepare("UPDATE attendance SET time_out = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$currentTime, $attendance['id']]);
    
    if ($result) {
        // Calculate working hours
        $timeIn = new DateTime($attendance['time_in']);
        $timeOut = new DateTime($currentTime);
        $interval = $timeIn->diff($timeOut);
        $workingHours = $interval->format('%H:%I');
        
        // Log the checkout
        error_log("Check out: User ID $userId at $currentTime on $currentDate");
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully checked out!',
            'data' => [
                'checkout_time' => $currentTime,
                'working_hours' => $workingHours,
                'formatted_time' => date('h:i A', strtotime($currentTime))
            ]
        ]);
    } else {
        throw new Exception('Failed to update check out time');
    }
    
} catch (Exception $e) {
    error_log("Check out error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>