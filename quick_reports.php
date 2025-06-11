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
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE 
                WHEN time_in IS NOT NULL AND time_out IS NOT NULL 
                THEN TIME_TO_SEC(TIMEDIFF(time_out, time_in))/3600 
                ELSE 0 
            END) as total_hours
        FROM attendance 
        WHERE user_id = ? AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $period['start'], $period['end']]);
    $result = $stmt->fetch();
    
    $stats[$key] = array_merge($period, $result);
    $stats[$key]['attendance_rate'] = $result['total_days'] > 0 ? 
        round(($result['present_days'] / $result['total_days']) * 100, 1) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Reports - Face Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-clock"></i> Face Attendance System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Detailed Reports
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-tachometer-alt"></i> Quick Reports
            </h1>
            <a href="reports.php" class="btn btn-primary">
                <i class="fas fa-chart-line"></i> Detailed Reports
            </a>
        </div>

        <!-- Quick Stats Cards -->
        <div class="row">
            <?php foreach ($stats as $key => $stat): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-<?= $key === 'today' ? 'primary' : ($key === 'week' ? 'success' : ($key === 'month' ? 'info' : 'warning')) ?> shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-<?= $key === 'today' ? 'primary' : ($key === 'week' ? 'success' : ($key === 'month' ? 'info' : 'warning')) ?> text-uppercase mb-1">
                                    <?= $stat['label'] ?>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stat['attendance_rate'] ?>%
                                </div>
                                <div class="text-xs text-muted">
                                    <?= $stat['present_days'] ?>/<?= $stat['total_days'] ?> days present
                                </div>
                                <div class="text-xs text-muted">
                                    <?= number_format($stat['total_hours'], 1) ?>h total
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-<?= $key === 'today' ? 'calendar-day' : ($key === 'week' ? 'calendar-week' : ($key === 'month' ? 'calendar-alt' : 'calendar')) ?> fa-2x text-gray-300"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="reports.php?start_date=<?= $stat['start'] ?>&end_date=<?= $stat['end'] ?>" 
                               class="btn btn-sm btn-outline-<?= $key === 'today' ? 'primary' : ($key === 'week' ? 'success' : ($key === 'month' ? 'info' : 'warning')) ?> w-100">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-clock"></i> Recent Activity
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $db->prepare("
                            SELECT date, time_in, time_out, status 
                            FROM attendance 
                            WHERE user_id = ? 
                            ORDER BY date DESC 
                            LIMIT 7
                        ");
                        $stmt->execute([$userId]);
                        $recentActivity = $stmt->fetchAll();
                        ?>
                        
                        <div class="timeline">
                            <?php foreach ($recentActivity as $activity): ?>
                            <div class="timeline-item mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="timeline-marker bg-<?= $activity['status'] === 'present' ? 'success' : ($activity['status'] === 'late' ? 'warning' : 'danger') ?>"></div>
                                    <div class="timeline-content ms-3 flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= date('M j, Y', strtotime($activity['date'])) ?></strong>
                                                <span class="badge bg-<?= $activity['status'] === 'present' ? 'success' : ($activity['status'] === 'late' ? 'warning' : 'danger') ?> ms-2">
                                                    <?= ucfirst($activity['status']) ?>
                                                </span>
                                            </div>
                                            <div class="text-muted">
                                                <?= $activity['time_in'] ? date('h:i A', strtotime($activity['time_in'])) : '--:--' ?>
                                                <?php if ($activity['time_out']): ?>
                                                    - <?= date('h:i A', strtotime($activity['time_out'])) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-trophy"></i> Performance Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Calculate overall performance
                        $stmt = $db->prepare("
                            SELECT 
                                COUNT(*) as total_days,
                                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                                AVG(CASE 
                                    WHEN time_in IS NOT NULL AND time_out IS NOT NULL 
                                    THEN TIME_TO_SEC(TIMEDIFF(time_out, time_in))/3600 
                                    ELSE NULL 
                                END) as avg_hours
                            FROM attendance 
                            WHERE user_id = ? 
                            AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        ");
                        $stmt->execute([$userId]);
                        $performance = $stmt->fetch();
                        
                        $overallRate = $performance['total_days'] > 0 ? 
                            round(($performance['present_days'] / $performance['total_days']) * 100, 1) : 0;
                        ?>
                        
                        <div class="text-center mb-3">
                            <div class="h2 text-<?= $overallRate >= 90 ? 'success' : ($overallRate >= 75 ? 'warning' : 'danger') ?>">
                                <?= $overallRate ?>%
                            </div>
                            <div class="text-muted">30-Day Attendance Rate</div>
                        </div>
                        
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar bg-<?= $overallRate >= 90 ? 'success' : ($overallRate >= 75 ? 'warning' : 'danger') ?>" 
                                 style="width: <?= $overallRate ?>%"></div>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-right">
                                    <div class="h5 text-primary"><?= $performance['present_days'] ?></div>
                                    <small class="text-muted">Present Days</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="h5 text-info"><?= number_format($performance['avg_hours'] ?? 0, 1) ?>h</div>
                                <small class="text-muted">Avg Hours/Day</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <a href="reports.php" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-chart-line"></i> View Detailed Report
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php
                            $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE()");
                            $stmt->execute([$userId]);
                            $todayRecord = $stmt->fetch();
                            ?>
                            
                            <?php if (!$todayRecord): ?>
                                <a href="attendance.php" class="btn btn-success">
                                    <i class="fas fa-camera"></i> Mark Attendance
                                </a>
                            <?php elseif ($todayRecord && !$todayRecord['time_out']): ?>
                                <button class="btn btn-warning" onclick="checkOut()">
                                    <i class="fas fa-sign-out-alt"></i> Check Out
                                </button>
                            <?php else: ?>
                                <div class="alert alert-success text-center mb-0">
                                    <i class="fas fa-check-circle"></i><br>
                                    All done for today!
                                </div>
                            <?php endif; ?>
                            
                            <a href="reports.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>" 
                               class="btn btn-info">
                                <i class="fas fa-calendar-alt"></i> Monthly Report
                            </a>
                            
                            <a href="reports.php?start_date=<?= date('Y-m-d', strtotime('monday this week')) ?>&end_date=<?= date('Y-m-d', strtotime('sunday this week')) ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-calendar-week"></i> Weekly Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkOut() {
            if (confirm('Are you sure you want to check out now?')) {
                fetch('process_checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: <?= $userId ?>,
                        checkout_time: new Date().toISOString()
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Successfully checked out!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error processing check out');
                });
            }
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            location.reload();
        }, 5 * 60 * 1000);
    </script>

    <style>
        .timeline-marker {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .timeline-item:not(:last-child) .timeline-marker::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 12px;
            width: 2px;
            height: 40px;
            background-color: #e3e6f0;
        }
        
        .timeline-marker {
            position: relative;
        }
    </style>
</body>
</html>