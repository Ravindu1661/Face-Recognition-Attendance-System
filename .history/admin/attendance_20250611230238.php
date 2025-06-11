<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

$db = Database::getInstance()->getConnection();

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$user_filter = $_GET['user'] ?? '';

// Build query
$query = "
    SELECT u.employee_id, u.name, a.date, a.check_in_time, a.check_out_time, 
           a.status, a.confidence_score
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE u.status = 'active'
";

$params = [$date_filter];

if ($user_filter) {
    $query .= " AND u.id = ?";
    $params[] = $user_filter;
}

$query .= " ORDER BY u.name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

// Get all users for filter dropdown
$stmt = $db->query("SELECT id, name, employee_id FROM users WHERE status = 'active' ORDER BY name");
$all_users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attendance - Admin Panel</title>
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
                <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="attendance.php" class="list-group-item list-group-item-action bg-dark text-white active">
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
                <h1 class="mb-4">View Attendance</h1>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?= $date_filter ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">User</label>
                                <select class="form-control" name="user">
                                    <option value="">All Users</option>
                                    <?php foreach ($all_users as $user): ?>
                                        <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                            <?= $user['name'] ?> (<?= $user['employee_id'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <a href="attendance.php" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Attendance Table -->
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Attendance for <?= date('M d, Y', strtotime($date_filter)) ?>
                        </h6>
                        <button class="btn btn-success btn-sm" onclick="exportToCSV()">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                        <th>Confidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?= $record['employee_id'] ?></td>
                                        <td><?= $record['name'] ?></td>
                                        <td><?= $record['check_in_time'] ?: '<span class="text-muted">--:--</span>' ?></td>
                                        <td><?= $record['check_out_time'] ?: '<span class="text-muted">--:--</span>' ?></td>
                                        <td>
                                            <?php if ($record['status']): ?>
                                                <span class="badge bg-<?= $record['status'] === 'present' ? 'success' : 
                                                    ($record['status'] === 'late' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($record['status']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Absent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $record['confidence_score'] ? 
                                                round($record['confidence_score'] * 100, 1) . '%' : 
                                                '<span class="text-muted">--</span>' ?>
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

