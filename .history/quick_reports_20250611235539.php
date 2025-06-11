<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Quick stats for different periods
$periods = [
    'today' => [
        'label' => 'Today',
        'start' => date('Y-m-d'),
        'end' => date('Y-m-d')
    ],
    'week' => [
        'label' => 'This Week',
        'start' => date('Y-m-d', strtotime('monday this week')),
        'end' => date('Y-m-d', strtotime('sunday this week'))
    ],
    'month' => [
        'label' => 'This Month',
        'start' => date('Y-m-01'),
        'end' => date('Y-m-t')
    ],
    'year' => [
        'label' => 'This Year',
        'start' => date('Y-01-01'),
        'end' => date('Y-12-31')
    ]
];

$stats = [];
foreach ($periods as $key => $period) {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'late' THEN