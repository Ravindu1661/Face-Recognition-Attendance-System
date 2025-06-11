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




                                <h4 class="text-danger"><?= $monthlyStats['absent_days'] ?? 0 ?></h4>
                                <small class="text-muted">Absent</small>
                            </div>
                        </div>
                        
                        <?php if ($monthlyStats['total_days'] > 0): ?>
                        <div class="mt-3">
                            <?php 
                            $attendanceRate = round(($monthlyStats['present_days'] / $monthlyStats['total_days']) * 100, 1);
                            $progressClass = $attendanceRate >= 90 ? 'success' : ($attendanceRate >= 75 ? 'warning' : 'danger');
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Attendance Rate</span>
                                <span class="font-weight-bold text-<?= $progressClass ?>"><?= $attendanceRate ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?= $progressClass ?>" 
                                     style="width: <?= $attendanceRate ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>


            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-info-circle"></i> Quick Info
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Employee ID:</strong> 
                            <span class="badge bg-secondary"><?= htmlspecialchars($_SESSION['employee_id'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Department:</strong> 
                            <span class="text-muted"><?= htmlspecialchars($_SESSION['department'] ?? 'General') ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Work Schedule:</strong> 
                            <span class="text-muted">9:00 AM - 5:00 PM</span>
                        </div>
                        <div class="mb-3">
                            <strong>Today's Date:</strong> 
                            <span class="text-muted"><?= date('F j, Y') ?></span>
                        </div>
                        <div>
                            <strong>Current Time:</strong> 
                            <span class="text-primary" id="currentTime2"><?= date('h:i:s A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-history"></i> Recent Attendance
                        </h6>
                        <a href="reports.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt"></i> View All
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">


                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>




                                        <th><i class="fas fa-calendar"></i> Date</th>
                                        <th><i class="fas fa-sign-in-alt"></i> Check In</th>
                                        <th><i class="fas fa-sign-out-alt"></i> Check Out</th>
                                        <th><i class="fas fa-clock"></i> Hours</th>
                                        <th><i class="fas fa-info-circle"></i> Status</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php if (empty($recentAttendance)): ?>
                                    <tr>








                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2"></i>
                                            <br>No attendance records found
                                        </td>
                                    </tr>

                                    <?php else: ?>
                                        <?php foreach ($recentAttendance as $record): ?>
                                        <tr>
                                            <td>
                                                <strong><?= date('M d, Y', strtotime($record['date'])) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= date('l', strtotime($record['date'])) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($record['time_in']): ?>
                                                    <span class="text-success">
                                                        <i class="fas fa-clock"></i>
                                                        <?= date('h:i A', strtotime($record['time_in'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-minus"></i> --:--
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['time_out']): ?>
                                                    <span class="text-info">
                                                        <i class="fas fa-clock"></i>
                                                        <?= date('h:i A', strtotime($record['time_out'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-minus"></i> --:--
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($record['time_in'] && $record['time_out']) {
                                                    $timeIn = new DateTime($record['time_in']);
                                                    $timeOut = new DateTime($record['time_out']);
                                                    $interval = $timeIn->diff($timeOut);
                                                    echo '<span class="text-primary">';
                                                    echo '<i class="fas fa-hourglass-half"></i> ';
                                                    echo $interval->format('%H:%I');
                                                    echo '</span>';
                                                } else {
                                                    echo '<span class="text-muted"><i class="fas fa-minus"></i> --:--</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = $record['status'] === 'present' ? 'success' : 
                                                              ($record['status'] === 'late' ? 'warning' : 'danger');
                                                $statusIcon = $record['status'] === 'present' ? 'check-circle' : 
                                                             ($record['status'] === 'late' ? 'clock' : 'times-circle');
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <i class="fas fa-<?= $statusIcon ?>"></i>
                                                    <?= ucfirst($record['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Check Out Modal -->
    <div class="modal fade" id="checkOutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-sign-out-alt"></i> Check Out Confirmation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to check out now?</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Current Time:</strong> <span id="checkOutTime"><?= date('h:i:s A') ?></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmCheckOut">
                        <i class="fas fa-sign-out-alt"></i> Confirm Check Out
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update current time every second
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            document.getElementById('currentTime').textContent = timeString;
            document.getElementById('currentTime2').textContent = timeString;
            document.getElementById('checkOutTime').textContent = timeString;
        }
        
        // Update time immediately and then every second
        updateTime();
        setInterval(updateTime, 1000);
        
        // Check out functionality
        document.addEventListener('DOMContentLoaded', function() {
            const checkOutBtn = document.getElementById('checkOutBtn');
            const checkOutModal = new bootstrap.Modal(document.getElementById('checkOutModal'));
            const confirmCheckOutBtn = document.getElementById('confirmCheckOut');
            
            if (checkOutBtn) {
                checkOutBtn.addEventListener('click', function() {
                    checkOutModal.show();
                });
            }
            
            if (confirmCheckOutBtn) {
                confirmCheckOutBtn.addEventListener('click', async function() {
                    try {
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                        
                        const response = await fetch('process_checkout.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                user_id: <?= $_SESSION['user_id'] ?>,
                                checkout_time: new Date().toISOString()
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Show success message
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-success alert-dismissible fade show';
                            alertDiv.innerHTML = `
                                <i class="fas fa-check-circle"></i> ${result.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `;
                            
                            document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
                            
                            // Close modal and reload page after 2 seconds
                            checkOutModal.hide();
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            throw new Error(result.message || 'Failed to check out');
                        }
                        
                    } catch (error) {
                        console.error('Check out error:', error);
                        alert('Error: ' + error.message);
                    } finally {
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-sign-out-alt"></i> Confirm Check Out';
                    }
                });
            }
        });
        
        // Auto-refresh page every 5 minutes to keep data current
        setTimeout(() => {
            location.reload();
        }, 5 * 60 * 1000);
    </script>
</body>
</html>