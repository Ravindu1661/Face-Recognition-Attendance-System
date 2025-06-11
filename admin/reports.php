<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

$db = Database::getInstance()->getConnection();

// Get monthly attendance summary
$stmt = $db->query("
    SELECT 
        MONTH(a.date) as month,
        YEAR(a.date) as year,
        COUNT(DISTINCT a.user_id) as unique_users,
        COUNT(a.id) as total_attendance,
        AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate
    FROM attendance a
    WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(a.date), MONTH(a.date)
    ORDER BY year DESC, month DESC
");
$monthly_stats = $stmt->fetchAll();

// Get top performers (highest attendance rate)
$stmt = $db->query("
    SELECT 
        u.name, 
        u.employee_id,
        COUNT(a.id) as total_days,
        SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) as present_days,
        ROUND((SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as attendance_rate
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id 
    WHERE u.status = 'active' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    GROUP BY u.id, u.name, u.employee_id
    HAVING total_days > 0
    ORDER BY attendance_rate DESC
    LIMIT 10
");
$top_performers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-white">Admin Panel</div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="attendance.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-calendar-check"></i> View Attendance
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action bg-dark text-white active">
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
                <h1 class="mb-4">Reports & Analytics</h1>

                <!-- Monthly Attendance Chart -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Monthly Attendance Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $today_stats = $db->query("
                                    SELECT 
                                        COUNT(*) as total_present,
                                        (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users
                                    FROM attendance 
                                    WHERE date = CURDATE()
                                ")->fetch();
                                ?>
                                <div class="mb-3">
                                    <h5 class="text-primary">Today's Attendance</h5>
                                    <h3><?= $today_stats['total_present'] ?> / <?= $today_stats['total_users'] ?></h3>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?= ($today_stats['total_present'] / $today_stats['total_users']) * 100 ?>%"></div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <h5 class="text-success">This Month</h5>
                                    <?php
                                    $month_stats = $db->query("
                                        SELECT AVG(daily_attendance) as avg_attendance FROM (
                                            SELECT COUNT(*) as daily_attendance 
                                            FROM attendance 
                                            WHERE MONTH(date) = MONTH(CURDATE()) 
                                            AND YEAR(date) = YEAR(CURDATE())
                                            GROUP BY date
                                        ) as daily_stats
                                    ")->fetch();
                                    ?>
                                    <h4><?= round($month_stats['avg_attendance'] ?? 0, 1) ?></h4>
                                    <small class="text-muted">Average daily attendance</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performers -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Top Performers (Last 30 Days)</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Total Days</th>
                                        <th>Present Days</th>
                                        <th>Attendance Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_performers as $index => $performer): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index === 0): ?>
                                                <i class="fas fa-trophy text-warning"></i> <?= $index + 1 ?>
                                            <?php elseif ($index === 1): ?>
                                                <i class="fas fa-medal text-secondary"></i> <?= $index + 1 ?>
                                            <?php elseif ($index === 2): ?>
                                                <i class="fas fa-award text-warning"></i> <?= $index + 1 ?>
                                            <?php else: ?>
                                                <?= $index + 1 ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $performer['employee_id'] ?></td>
                                        <td><?= $performer['name'] ?></td>
                                        <td><?= $performer['total_days'] ?></td>
                                        <td><?= $performer['present_days'] ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><?= $performer['attendance_rate'] ?>%</span>
                                                <div class="progress flex-grow-1" style="height: 10px;">
                                                    <div class="progress-bar bg-<?= $performer['attendance_rate'] >= 90 ? 'success' : 
                                                        ($performer['attendance_rate'] >= 75 ? 'warning' : 'danger') ?>" 
                                                         style="width: <?= $performer['attendance_rate'] ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Export Reports</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <button class="btn btn-success w-100" onclick="exportMonthlyReport()">
                                    <i class="fas fa-file-excel"></i> Monthly Report
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-info w-100" onclick="exportUserReport()">
                                    <i class="fas fa-users"></i> User Summary
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-warning w-100" onclick="exportDetailedReport()">
                                    <i class="fas fa-chart-line"></i> Detailed Analytics
                                </button>
                            </div>
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

        // Monthly Chart
        const monthlyData = <?= json_encode($monthly_stats) ?>;
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                                   'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    return months[item.month - 1] + ' ' + item.year;
                }),
                datasets: [{
                    label: 'Attendance Rate (%)',
                    data: monthlyData.map(item => item.attendance_rate),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // Export functions
        function exportMonthlyReport() {
            window.open('export.php?type=monthly', '_blank');
        }

        function exportUserReport() {
            window.open('export.php?type=users', '_blank');
        }

        function exportDetailedReport() {
            window.open('export.php?type=detailed', '_blank');
        }
    </script>
</body>
</html>