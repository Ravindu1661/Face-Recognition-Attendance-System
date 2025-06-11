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
    
    if ($stmt->fetch