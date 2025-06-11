<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Check if already marked today
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$todayAttendance = $stmt->fetch();

if ($todayAttendance) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Face Recognition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Face Attendance System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Mark Your Attendance</h5>
                    </div>
                    <div class="card-body text-center">
                        <div id="loading" class="mb-3" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading face recognition models...</p>
                        </div>

                        <div id="camera-section">
                            <video id="video" width="640" height="480" autoplay muted></video>
                            <canvas id="canvas" width="640" height="480" style="display: none;"></canvas>
                        </div>

                        <div class="mt-3">
                            <button id="startCamera" class="btn btn-primary">Start Camera</button>
                            <button id="captureBtn" class="btn btn-success" style="display: none;">Capture & Mark Attendance</button>
                            <button id="stopCamera" class="btn btn-danger" style="display: none;">Stop Camera</button>
                        </div>

                        <div id="result" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Face API Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script src="assets/js/face-recognition.js"></script>
    <script>
        const userId = <?= $_SESSION['user_id'] ?>;
        const userName = "<?= $_SESSION['name'] ?>";
    </script>
</body>
</html>