<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['image_data'])) {
        throw new Exception('No image data received');
    }
    
    $userId = $_SESSION['user_id'];
    $imageData = $input['image_data'];
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
    
    // Check if already marked today
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = CURDATE()");
    $stmt->execute([$userId]);
    

    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Attendance already marked for today']);
        exit;
    }
    
    // Save image (optional)
    $imagePath = null;
    if ($imageData) {
        // Remove data:image/jpeg;base64, prefix
        $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);
        $imageData = base64_decode($imageData);
        
        if ($imageData) {
            $uploadDir = 'uploads/attendance/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filename = 'attendance_' . $userId . '_' . date('Y-m-d_H-i-s') . '.jpg';
            $imagePath = $uploadDir . $filename;
            
            if (file_put_contents($imagePath, $imageData)) {
                // Image saved successfully
            } else {
                $imagePath = null;
            }
        }
    }
    
    // Insert attendance record
    $stmt = $db->prepare("
        INSERT INTO attendance (user_id, date, time_in, status, image_path, created_at) 
        VALUES (?, CURDATE(), CURTIME(), 'present', ?, NOW())
    ");
    
    $result = $stmt->execute([$userId, $imagePath]);
    
    if ($result) {
        // Log the attendance
        error_log("Attendance marked for user ID: $userId at " . date('Y-m-d H:i:s'));
        
        echo json_encode([
            'success' => true, 
            'message' => 'Attendance marked successfully',
            'data' => [
                'user_id' => $userId,
                'date' => date('Y-m-d'),
                'time' => date('H:i:s'),
                'image_saved' => !empty($imagePath)
            ]
        ]);
    } else {
        throw new Exception('Failed to save attendance record');
    }
    
} catch (Exception $e) {
    error_log("Attendance processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>