<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../includes/config.php';
require_once '../includes/settings.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $db = Database::getInstance()->getConnection();
    $settings = new SystemSettings();
    
    switch ($method) {
        case 'POST':
            if (!isset($input['action'])) {
                throw new Exception('Action not specified');
            }
            
            switch ($input['action']) {
                case 'mark_attendance':
                    $result = markAttendance($db, $settings, $input);
                    break;
                    
                case 'get_user_by_face':
                    $result = getUserByFaceDescriptor($db, $input);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'get_attendance':
                    $result = getUserAttendance($db, $_GET);
                    break;
                    
                case 'get_settings':
                    $result = getSystemSettings($settings);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function markAttendance($db, $settings, $input) {
    if (!isset($input['user_id']) || !isset($input['face_descriptor'])) {
        throw new Exception('Missing required parameters');
    }
    
    $userId = $input['user_id'];
    $faceDescriptor = $input['face_descriptor'];
    $confidenceScore = $input['confidence_score'] ?? 0.8;
    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');
    
    // Check if user already marked attendance today
    $stmt = $db->prepare("SELECT id, check_in_time FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $currentDate]);
    $existing = $stmt->fetch();
    
    if ($existing && !$existing['check_out_time']) {
        // Mark check out
        $stmt = $db->prepare("UPDATE attendance SET check_out_time = ? WHERE id = ?");
        $stmt->execute([$currentTime, $existing['id']]);
        
        // Log the action
        logAttendanceAction($db, $userId, 'check_out', $confidenceScore);
        
        return [
            'action' => 'check_out',
            'time' => $currentTime,
            'message' => 'Check out recorded successfully'
        ];
    } else {
        // Mark check in
        $status = $settings->getAttendanceStatus($currentTime);
        
        $stmt = $db->prepare("
            INSERT INTO attendance (user_id, date, check_in_time, status, confidence_score) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $currentDate, $currentTime, $status, $confidenceScore]);
        
        // Log the action
        logAttendanceAction($db, $userId, 'check_in', $confidenceScore);
        
        return [
            'action' => 'check_in',
            'time' => $currentTime,
            'status' => $status,
            'message' => 'Check in recorded successfully'
        ];
    }
}

function getUserByFaceDescriptor($db, $input) {
    if (!isset($input['face_descriptor'])) {
        throw new Exception('Face descriptor not provided');
    }
    
    // This is a simplified version - in a real implementation,
    // you would compare the face descriptor with stored encodings
    $stmt = $db->query("
        SELECT u.id, u.name, u.employee_id, fe.encoding 
        FROM users u 
        JOIN face_encodings fe ON u.id = fe.user_id 
        WHERE u.status = 'active'
    ");
    
    $users = $stmt->fetchAll();
    $inputDescriptor = $input['face_descriptor'];
    
    // Simple comparison logic (you should implement proper face matching)
    foreach ($users as $user) {
        $storedDescriptor = json_decode($user['encoding'], true);
        $similarity = calculateSimilarity($inputDescriptor, $storedDescriptor);
        
        if ($similarity > 0.6) { // Threshold for face match
            return [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'employee_id' => $user['employee_id'],
                'confidence' => $similarity
            ];
        }
    }
    
    throw new Exception('Face not recognized');
}

function calculateSimilarity($desc1, $desc2) {
    // Simplified similarity calculation
    // In a real implementation, use proper face recognition algorithms
    if (!is_array($desc1) || !is_array($desc2) || count($desc1) !== count($desc2)) {
        return 0;
    }
    
    $dotProduct = 0;
    $norm1 = 0;
    $norm2 = 0;
    
    for ($i = 0; $i < count($desc1); $i++) {
        $dotProduct += $desc1[$i] * $desc2[$i];
        $norm1 += $desc1[$i] * $desc1[$i];
        $norm2 += $desc2[$i] * $desc2[$i];
    }
    
    if ($norm1 == 0 || $norm2 == 0) {
        return 0;
    }
    
    return $dotProduct / (sqrt($norm1) * sqrt($norm2));
}

function getUserAttendance($db, $params) {
    $userId = $params['user_id'] ?? null;
    $startDate = $params['start_date'] ?? date('Y-m-01');
    $endDate = $params['end_date'] ?? date('Y-m-t');
    
    if (!$userId) {
        throw new Exception('User ID not provided');
    }
    
    $stmt = $db->prepare("
        SELECT date, check_in_time, check_out_time, status, confidence_score
        FROM attendance 
        WHERE user_id = ? AND date BETWEEN ? AND ?
        ORDER BY date DESC
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    
    return $stmt->fetchAll();
}

function getSystemSettings($settings) {
    return [
        'work_hours' => $settings->getWorkHours(),
        'late_threshold' => $settings->getLateThreshold(),
        'face_confidence_threshold' => $settings->getFaceConfidenceThreshold(),
        'company_name' => $settings->get('company_name', 'Company'),
        'timezone' => $settings->get('system_timezone', 'UTC')
    ];
}

function logAttendanceAction($db, $userId, $action, $confidenceScore = null) {
    $stmt = $db->prepare("
        INSERT INTO attendance_logs (user_id, action, confidence_score, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt->execute([$userId, $action, $confidenceScore, $ipAddress, $userAgent]);
}
?>