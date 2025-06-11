<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$db = Database::getInstance()->getConnection();

// Get today's attendance with proper column names
$stmt = $db->prepare("
    SELECT 
        id,
        date,
        time_in,
        time_out,
        status,
        created_at,
        updated_at
    FROM attendance 
    WHERE user_id = ? AND date = CURDATE()
");
$stmt->execute([$_SESSION['user_id']]);
$todayAttendance = $stmt->fetch();

// Get recent attendance (last 7 days)
$stmt = $db->prepare("
    SELECT 
        date,
        time_in,
        time_out,
        status,
        created_at
    FROM attendance 
    WHERE user_id = ? 
    ORDER BY date DESC, created_at DESC 
    LIMIT 7
");
$stmt->execute([$_SESSION['user_id']]);
$recentAttendance = $stmt->fetchAll();

// Calculate working hours for today
$workingHours = null;
if ($todayAttendance && $todayAttendance['time_in'] && $todayAttendance['time_out']) {
    $timeIn = new DateTime($todayAttendance['time_in']);
    $timeOut = new DateTime($todayAttendance['time_out']);
    $interval = $timeIn->diff($timeOut);
    $workingHours = $interval->format('%H:%I');
}

// Get this month's attendance summary
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
    FROM attendance 
    WHERE user_id = ? 
    AND MONTH(date) = MONTH(CURDATE()) 
    AND YEAR(date) = YEAR(CURDATE())
");
$stmt->execute([$_SESSION['user_id']]);
$monthlyStats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Face Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .border-left-primary { border-left-color: #4e73df !important; }
        .border-left-success { border-left-color: #1cc88a !important; }
        .border-left-info { border-left-color: #36b9cc !important; }
        .border-left-warning { border-left-color: #f6c23e !important; }
        .text-xs { font-size: 0.7rem; }
        .font-weight-bold { font-weight: 700; }
        .text-gray-800 { color: #5a5c69 !important; }
        .text-gray-300 { color: #dddfeb !important; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-clock"></i> Face Attendance System
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!
                </span>
                <?php if (isAdmin()): ?>
                    <a class="nav-link" href="admin/dashboard.php">
                        <i class="fas fa-cog"></i> Admin Panel
                    </a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </h1>
            <div class="text-muted">
                <i class="fas fa-calendar"></i> <?= date('l, F j, Y') ?>
                <span class="ms-2">
                    <i class="fas fa-clock"></i> <span id="currentTime"><?= date('H:i:s') ?></span>
                </span>
            </div>
        </div>

        <!-- Today's Status Cards -->
        <div class="row mb-4">
            <!-- Today's Status -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Today's Status
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php if ($todayAttendance): ?>
                                        <?php 
                                        $statusClass = $todayAttendance['status'] === 'present' ? 'success' : 
                                                      ($todayAttendance['status'] === 'late' ? 'warning' : 'danger');
                                        $statusIcon = $todayAttendance['status'] === 'present' ? 'check-circle' : 
                                                     ($todayAttendance['status'] === 'late' ? 'clock' : 'times-circle');
                                        ?>
                                        <span class="text-<?= $statusClass ?>">
                                            <i class="fas fa-<?= $statusIcon ?>"></i> 
                                            <?= ucfirst($todayAttendance['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-warning">
                                            <i class="fas fa-question-circle"></i> Not Marked
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check In Time -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Check In Time
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php if ($todayAttendance && $todayAttendance['time_in']): ?>
                                        <i class="fas fa-clock text-success"></i>
                                        <?= date('h:i A', strtotime($todayAttendance['time_in'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-minus"></i> --:--
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-sign-in-alt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check Out Time -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Check Out Time
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php if ($todayAttendance && $todayAttendance['time_out']): ?>
                                        <i class="fas fa-clock text-info"></i>
                                        <?= date('h:i A', strtotime($todayAttendance['time_out'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-minus"></i> --:--
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-sign-out-alt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Working Hours -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Working Hours
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php if ($workingHours): ?>
                                        <i class="fas fa-hourglass-half text-warning"></i>
                                        <?= $workingHours ?>
                                    <?php elseif ($todayAttendance && $todayAttendance['time_in'] && !$todayAttendance['time_out']): ?>
                                        <span class="text-success">
                                            <i class="fas fa-play"></i> In Progress
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-minus"></i> --:--
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-business-time fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body text-center py-4">
                        <?php if (!$todayAttendance): ?>
                            <!-- Check In Button -->
                            <a href="attendance.php" class="btn btn-success btn-lg me-3">
                                <i class="fas fa-camera"></i> Mark Check In
                            </a>
                        <?php elseif ($todayAttendance && !$todayAttendance['time_out']): ?>
                            <!-- Check Out Button -->
                            <button id="checkOutBtn" class="btn btn-danger btn-lg me-3">
                                <i class="fas fa-sign-out-alt"></i> Mark Check Out
                            </button>
                        <?php else: ?>
                            <!-- Already Completed -->
                            <div class="text-success">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h5>Attendance Complete for Today!</h5>
                                <p class="text-muted">
                                    Checked in at <?= date('h:i A', strtotime($todayAttendance['time_in'])) ?> • 
                                    Checked out at <?= date('h:i A', strtotime($todayAttendance['time_out'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- View Reports Button -->
                        <a href="reports.php" class="btn btn-info btn-lg">
                            <i class="fas fa-chart-bar"></i> View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Statistics -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie"></i> This Month's Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="border-right">
                                    <h4 class="text-primary"><?= $monthlyStats['total_days'] ?? 0 ?></h4>
                                    <small class="text-muted">Total Days</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-right">
                                    <h4 class="text-success"><?= $monthlyStats['present_days'] ?? 0 ?></h4>
                                    <small class="text-muted">Present</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-right">
                                    <h4 class="text-warning"><?= $monthlyStats['late_days'] ?? 0 ?></h4>
                                    <small class="text-muted">Late</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-right">
                                    <h4 class="text-danger"><?= $monthlyStats['absent_days'] ?? 0 ?></h4>
                                    <small class="text-muted">Absent</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Attendance</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttendance as $record): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($record['date'])) ?></td>
                                        <td><?= $record['check_in_time'] ?: '--:--' ?></td>
                                        <td><?= $record['check_out_time'] ?: '--:--' ?></td>
                                        <td>
                                            <span class="badge bg-<?= $record['status'] === 'present' ? 'success' : 
                                                ($record['status'] === 'late' ? 'warning' : 'danger') ?>">
                                                <?= ucfirst($record['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>