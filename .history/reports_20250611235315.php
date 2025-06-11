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
                    <div class="card-body">
                        <canvas id="pieChart" height="200"></canvas>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="border-right">
                                        <div class="text-success font-weight-bold"><?= $presentDays ?></div>
                                        <small class="text-muted">Present</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-right">
                                        <div class="text-warning font-weight-bold"><?= $lateDays ?></div>
                                        <small class="text-muted">Late</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-danger font-weight-bold"><?= $absentDays ?></div>
                                    <small class="text-muted">Absent</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Attendance Table -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-table"></i> Detailed Attendance Records
                        </h6>
                        <div class="no-print">
                            <small class="text-muted">
                                Showing <?= count($attendanceRecords) ?> records from 
                                <?= date('M j, Y', strtotime($startDate)) ?> to 
                                <?= date('M j, Y', strtotime($endDate)) ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="attendanceTable">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fas fa-calendar"></i> Date</th>
                                        <th><i class="fas fa-calendar-day"></i> Day</th>
                                        <th><i class="fas fa-sign-in-alt"></i> Check In</th>
                                        <th><i class="fas fa-sign-out-alt"></i> Check Out</th>
                                        <th><i class="fas fa-clock"></i> Working Hours</th>
                                        <th><i class="fas fa-info-circle"></i> Status</th>
                                        <th class="no-print"><i class="fas fa-cog"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendanceRecords)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <br>
                                            <h5>No Records Found</h5>
                                            <p>No attendance records found for the selected period.</p>
                                            <a href="attendance.php" class="btn btn-primary">
                                                <i class="fas fa-camera"></i> Mark Attendance
                                            </a>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendanceRecords as $index => $record): ?>
                                        <tr class="<?= $record['date'] === date('Y-m-d') ? 'table-info' : '' ?>">
                                            <td>
                                                <strong><?= date('M d, Y', strtotime($record['date'])) ?></strong>
                                                <?php if ($record['date'] === date('Y-m-d')): ?>
                                                    <span class="badge bg-info ms-1">Today</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $dayOfWeek = date('l', strtotime($record['date']));
                                                $isWeekend = in_array($dayOfWeek, ['Saturday', 'Sunday']);
                                                ?>
                                                <span class="<?= $isWeekend ? 'text-danger' : 'text-muted' ?>">
                                                    <?= $dayOfWeek ?>
                                                </span>
                                                <?php if ($isWeekend): ?>
                                                    <small class="text-danger d-block">Weekend</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['time_in']): ?>
                                                    <?php 
                                                    $checkInTime = strtotime($record['time_in']);
                                                    $lateThreshold = strtotime('09:00:00');
                                                    $isLate = $checkInTime > $lateThreshold;
                                                    ?>
                                                    <span class="<?= $isLate ? 'text-warning' : 'text-success' ?>">
                                                        <i class="fas fa-clock"></i>
                                                        <?= date('h:i A', $checkInTime) ?>
                                                    </span>
                                                    <?php if ($isLate): ?>
                                                        <small class="text-warning d-block">
                                                            <i class="fas fa-exclamation-triangle"></i> Late
                                                        </small>
                                                    <?php endif; ?>
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
                                                <?php elseif ($record['time_in'] && $record['date'] === date('Y-m-d')): ?>
                                                    <span class="text-primary">
                                                        <i class="fas fa-play"></i> In Progress
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-minus"></i> --:--
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['working_hours']): ?>
                                                    <?php 
                                                    $hours = explode(':', $record['working_hours']);
                                                    $totalHours = $hours[0] + ($hours[1] / 60);
                                                    $colorClass = $totalHours >= 8 ? 'text-success' : 
                                                                 ($totalHours >= 6 ? 'text-warning' : 'text-danger');
                                                    ?>
                                                    <span class="<?= $colorClass ?>">
                                                        <i class="fas fa-hourglass-half"></i>
                                                        <?= $hours[0] ?>h <?= $hours[1] ?>m
                                                    </span>
                                                    <?php if ($totalHours < 8): ?>
                                                        <small class="text-muted d-block">
                                                            Short: <?= number_format(8 - $totalHours, 1) ?>h
                                                        </small>
                                                    <?php endif; ?>
                                                <?php elseif ($record['time_in'] && !$record['time_out'] && $record['date'] === date('Y-m-d')): ?>
                                                    <?php 
                                                    $currentTime = new DateTime();
                                                    $checkInTime = new DateTime($record['time_in']);
                                                    $diff = $currentTime->diff($checkInTime);
                                                    ?>
                                                    <span class="text-primary">
                                                        <i class="fas fa-play"></i>
                                                        <?= $diff->h ?>h <?= $diff->i ?>m
                                                    </span>
                                                    <small class="text-muted d-block">Running</small>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-minus"></i> --:--
                                                    </span>
                                                <?php endif; ?>
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
                                            <td class="no-print">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info btn-sm" 
                                                            onclick="viewDetails('<?= $record['date'] ?>')"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if (isAdmin()): ?>
                                                    <button class="btn btn-outline-warning btn-sm" 
                                                            onclick="editRecord('<?= $record['date'] ?>')"
                                                            title="Edit Record">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
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

        <!-- Summary Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-bar"></i> Period Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="font-weight-bold">Attendance Overview</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-calendar text-primary"></i> 
                                        <strong>Period:</strong> <?= date('M j, Y', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?>
                                    </li>
                                    <li><i class="fas fa-briefcase text-info"></i> 
                                        <strong>Working Days:</strong> <?= $workingDays ?> days
                                    </li>
                                    <li><i class="fas fa-check-circle text-success"></i> 
                                        <strong>Attendance Rate:</strong> <?= $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0 ?>%
                                    </li>
                                    <li><i class="fas fa-clock text-warning"></i> 
                                        <strong>Punctuality Rate:</strong> <?= $totalDays > 0 ? round((($presentDays) / $totalDays) * 100, 1) : 0 ?>%
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="font-weight-bold">Performance Metrics</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-business-time text-primary"></i> 
                                        <strong>Total Hours:</strong> <?= $totalWorkingHours ?>h <?= $totalWorkingMins ?>m
                                    </li>
                                    <li><i class="fas fa-chart-line text-success"></i> 
                                        <strong>Average Daily Hours:</strong> <?= $totalDays > 0 ? round($totalWorkingMinutes / $totalDays / 60, 1) : 0 ?>h
                                    </li>
                                    <li><i class="fas fa-exclamation-triangle text-warning"></i> 
                                        <strong>Late Arrivals:</strong> <?= $lateDays ?> times
                                    </li>
                                    <li><i class="fas fa-star text-info"></i> 
                                        <strong>Perfect Days:</strong> <?= $presentDays - $lateDays ?> days
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle"></i> Attendance Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart.js configurations
        const monthlyData = <?= json_encode($monthlyStats) ?>;
        
        // Monthly Trend Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Present Days',
                    data: monthlyData.map(item => item.present),
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    tension