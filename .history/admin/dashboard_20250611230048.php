<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

$db = Database::getInstance()->getConnection();

// Get statistics
$stats = [];

// Total users
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$stats['total_users'] = $stmt->fetch()['total'];

// Today's attendance
$stmt = $db->query("SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE()");
$stats['today_attendance'] = $stmt->fetch()['total'];

// This month's average attendance
$stmt = $db->query("
    SELECT AVG(daily_count) as avg_attendance FROM (
        SELECT COUNT(*) as daily_count 
        FROM attendance 
        WHERE MONTH(date) = MONTH(CURDATE()) 
        AND YEAR(date) = YEAR(CURDATE())
        GROUP BY date
    ) as daily_stats
");
$result = $stmt->fetch();
$stats['avg_attendance'] = round($result['avg_attendance'] ?? 0, 1);

// Recent attendance
$stmt = $db->query("
    SELECT u.name, u.employee_id, a.date, a.check_in_time, a.status 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.date DESC, a.check_in_time DESC 
    LIMIT 10
");
$recentAttendance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Face Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-white">Admin Panel</div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="attendance.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-calendar-check"></i> View Attendance
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="../dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-arrow-left"></i> Back to User Panel
                </a>
                <a href="../logout.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="menu-toggle">Toggle Menu</button>
                    <div class="navbar-nav ms-auto">
                        <span class="navbar-text">Welcome, <?= $_SESSION['name'] ?>!</span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <h1 class="mt-4">Admin Dashboard</h1>
                
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Users
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $stats['total_users'] ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Today's Attendance
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $stats['today_attendance'] ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Average Daily Attendance
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $stats['avg_attendance'] ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Attendance Rate
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $stats['total_users'] > 0 ? round(($stats['today_attendance'] / $stats['total_users']) * 100, 1) : 0 ?>%
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Attendance</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Date</th>
                                        <th>Check In Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttendance as $record): ?>
                                    <tr>
                                        <td><?= $record['employee_id'] ?></td>
                                        <td><?= $record['name'] ?></td>
                                        <td><?= date('M d, Y', strtotime($record['date'])) ?></td>
                                        <td><?= $record['check_in_time'] ?></td>
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
    <script>
        document.getElementById("menu-toggle").addEventListener("click", function(e) {
            e.preventDefault();
            document.getElementById("wrapper").classList.toggle("toggled");
        });
    </script>
</body>
</html>