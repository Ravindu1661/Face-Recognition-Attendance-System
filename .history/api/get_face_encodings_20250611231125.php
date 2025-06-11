<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("
        SELECT 
            u.id as user_id,
            u.name,
            u.employee_id,
            fe.encoding,
            fe.confidence_threshold
        FROM users u
        JOIN face_encodings fe ON u.id = fe.user_id
        WHERE u.status = 'active' AND fe.is_primary = 1
    ");
    
    $faces = [];
    while ($row = $stmt->fetch()) {
        $faces[] = [
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'employee_id' => $row['employee_id'],
            'encoding' => json_decode($row['encoding']),
            'confidence_threshold' => (float)$row['confidence_threshold']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'faces' => $faces,
        'count' => count($faces)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>