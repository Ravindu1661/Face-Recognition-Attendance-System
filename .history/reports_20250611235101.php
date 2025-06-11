<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Get filter parameters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get user's attendance records for the selected period
$stmt = $db->prepare("
    SELECT 
        date,
        time_in,
        time_out,
        status,
        created_at,
        CASE 
            WHEN time_in IS NOT NULL AND time_out IS NOT NULL 
            THEN TIMEDIFF(time_out, time_in)
            ELSE NULL 
        END as working_hours
    FROM attendance 
    WHERE user_id = ? 
    AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$stmt->execute([$userId, $startDate, $endDate]);
$attendanceRecords = $stmt->fetchAll();

// Calculate statistics
$totalDays = count($attendanceRecords);
$presentDays = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'present'));
$lateDays = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'late'));
$absentDays = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'absent'));

// Calculate total working hours
$totalWorkingMinutes = 0;
foreach ($attendanceRecords as $record) {
    if ($record['working_hours']) {
        $time = explode(':', $record['working_hours']);
        $totalWorkingMinutes += ($time[0] * 60) + $time[1];
    }
}
$totalWorkingHours = floor($totalWorkingMinutes / 60);
$totalWorkingMins = $totalWorkingMinutes % 60;

// Get monthly statistics for chart
$monthlyStats = [];
for ($i = 11; $i >= 0; $i--) {
    $monthDate = date('Y-m', strtotime("-$i months"));
    $monthYear = explode('-', $monthDate);
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM attendance 
        WHERE user_id = ? 
        AND YEAR(date) = ? 
        AND MONTH(date) = ?
    ");
    $stmt->execute([$userId, $monthYear[0], $monthYear[1]]);
    $stats = $stmt->fetch();
    
    $monthlyStats[] = [
        'month' => date('M Y', strtotime($monthDate . '-01')),
        'total' => $stats['total'],
        'present' => $stats['present'],
        'late' => $stats['late'],
        'rate' => $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100, 1) : 0
    ];
}

// Get working days in selected period (excluding weekends)
$workingDays = 0;
$currentDate = new DateTime($startDate);
$endDateTime = new DateTime($endDate);

while ($currentDate <= $endDateTime) {
    $dayOfWeek = $currentDate->format('N');
    if ($dayOfWeek < 6) { // Monday = 1, Sunday = 7
        $workingDays++;
    }
    $currentDate->add(new DateInterval('P1D'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Face Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .border-left-warning { border-left-color: #f6c23e !important; }
        .border-left-danger { border-left-color: #e74a3b !important; }
        .border-left-info { border-left-color: #36b9cc !important; }
        .text-xs { font-size: 0.7rem; }
        .font-weight-bold { font-weight: 700; }
        .text-gray-800 { color: #5a5c69 !important; }
        .text-gray-300 { color: #dddfeb !important; }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.075);
        }
        @media print {
            .no-print { display: none !important; }
            .card { border: 1px solid #dee2e6 !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-clock"></i> Face Attendance System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
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
        <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-chart-bar"></i> Attendance Reports
            </h1>
            <div>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button onclick="exportToCSV()" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card shadow mb-4 no-print">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-filter"></i> Filter Reports
                </h6>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?= $startDate ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?= $endDate ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select class="form-control" name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= sprintf('%02d', $m) ?>" <?= $m == $month ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select class="form-control" name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Days
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalDays ?></div>
                                <div class="text-xs text-muted">Out of <?= $workingDays ?> working days</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Present Days
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $presentDays ?></div>
                                <div class="text-xs text-muted">
                                    <?= $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0 ?>% attendance rate
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Late Days
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $lateDays ?></div>
                                <div class="text-xs text-muted">
                                    <?= $totalDays > 0 ? round(($lateDays / $totalDays) * 100, 1) : 0 ?>% of total days
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Working Hours
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $totalWorkingHours ?>h <?= $totalWorkingMins ?>m
                                </div>
                                <div class="text-xs text-muted">
                                    Avg: <?= $totalDays > 0 ? round($totalWorkingMinutes / $totalDays / 60, 1) : 0 ?>h per day
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

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-line"></i> Monthly Attendance Trend
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie"></i> Attendance Distribution
                        </h6>
                    </div>