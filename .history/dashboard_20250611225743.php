<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$db = Database::getInstance()->getConnection();

// Get today's attendance
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$todayAttendance = $stmt->fetch();

// Get recent attendance
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentAttendance = $stmt->fetchAll();
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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Face Attendance System</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Welcome, <?= $_SESSION['name'] ?>!</span>
                <?php if (isAdmin()): ?>
                    <a class="nav-link" href="admin/dashboard.php">Admin Panel</a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Today's Status -->
            <div class="col-md-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Today's Status
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $todayAttendance ? 
                                        '<span class="text-success">Present</span>' : 
                                        '<span class="text-warning">Not Marked</span>' ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check In Time -->
            <div class="col-md-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Check In Time
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $todayAttendance ? $todayAttendance['check_in_time'] : '--:--' ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mark Attendance Button -->
            <div class="col-md-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body text-center">
                        <?php if (!$todayAttendance): ?>
                            <a href="attendance.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-camera"></i> Mark Attendance
                            </a>
                        <?php else: ?>
                            <div class="text-success">
                                <i class="fas fa-check-circle fa-2x"></i>
                                <p class="mt-2">Attendance Marked!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="row mt-4">
            <div class="col-12">
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