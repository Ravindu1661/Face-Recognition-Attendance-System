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
                        <!-- Error Messages -->
                        <div id="errorMessage" class="mb-3"></div>
                        
                        <!-- Loading Indicator -->
                        <div id="loading" class="mb-3" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading face recognition models...</p>
                        </div>

                        <!-- Camera Section -->
                        <div id="camera-section">
                            <video id="video" width="640" height="480" autoplay muted playsinline style="display: none;"></video>
                            <canvas id="canvas" width="640" height="480" style="display: none;"></canvas>
                        </div>

                        <!-- Camera Controls -->
                        <div class="mt-3">
                            <button id="startCamera" class="btn btn-primary">
                                <i class="fas fa-video"></i> Start Camera
                            </button>
                            <button id="captureBtn" class="btn btn-success" style="display: none;">
                                <i class="fas fa-camera"></i> Capture & Mark Attendance
                            </button>
                            <button id="stopCamera" class="btn btn-danger" style="display: none;">
                                <i class="fas fa-stop"></i> Stop Camera
                            </button>
                        </div>

                        <div id="result" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <!-- Camera functionality -->
    <script>
        // Global variables
        let videoStream = null;
        let video = null;
        
        // Wait for DOM to load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up camera...');
            
            // Get elements
            video = document.getElementById('video');
            const startBtn = document.getElementById('startCamera');
            const stopBtn = document.getElementById('stopCamera');
            const captureBtn = document.getElementById('captureBtn');
            
            // Check if elements exist
            if (!video) {
                console.error('Video element not found');
                return;
            }
            
            if (!startBtn) {
                console.error('Start button not found');
                return;
            }
            
            console.log('Elements found, adding event listeners...');
            
            // Start Camera Event
            startBtn.addEventListener('click', async function() {
                console.log('Start camera button clicked');
                
                try {
                    // Disable button and show loading
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
                    
                    // Clear any previous errors
                    document.getElementById('errorMessage').innerHTML = '';
                    
                    // Request camera access
                    console.log('Requesting camera access...');
                    
                    videoStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            width: { ideal: 640, max: 1280 },
                            height: { ideal: 480, max: 720 },
                            facingMode: 'user'
                        },
                        audio: false
                    });
                    
                    console.log('Camera access granted');
                    
                    // Set video source
                    video.srcObject = videoStream;
                    
                    // Wait for video to load
                    video.onloadedmetadata = function() {
                        console.log('Video metadata loaded');
                        video.play().then(() => {
                            console.log('Video playing');
                            
                            // Show video and update UI
                            video.style.display = 'block';
                            startBtn.style.display = 'none';
                            stopBtn.style.display = 'inline-block';
                            captureBtn.style.display = 'inline-block';
                            
                            // Show success message
                            showMessage('Camera started successfully!', 'success');
                            
                        }).catch(error => {
                            console.error('Video play error:', error);
                            showError('Failed to start video playback: ' + error.message);
                        });
                    };
                    
                    video.onerror = function(error) {
                        console.error('Video error:', error);
                        showError('Video error occurred');
                    };
                    
                } catch (error) {
                    console.error('Camera access error:', error);
                    handleCameraError(error);
                } finally {
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-video"></i> Start Camera';
                }
            });
            
            // Stop Camera Event
            if (stopBtn) {
                stopBtn.addEventListener('click', function() {
                    console.log('Stop camera button clicked');
                    stopCamera();
                });
            }
            
            // Capture Event
            if (captureBtn) {
                captureBtn.addEventListener('click', function() {
                    console.log('Capture button clicked');
                    captureImage();
                });
            }
            
            console.log('Event listeners added successfully');
        });
        
        // Stop camera function
        function stopCamera() {
            try {
                if (videoStream) {
                    videoStream.getTracks().forEach(track => {
                        track.stop();
                        console.log('Camera track stopped');
                    });
                    videoStream = null;
                }
                
                if (video) {
                    video.srcObject = null;
                    video.style.display = 'none';
                }
                
                // Update UI
                document.getElementById('startCamera').style.display = 'inline-block';
                document.getElementById('stopCamera').style.display = 'none';
                document.getElementById('captureBtn').style.display = 'none';
                
                showMessage('Camera stopped', 'info');
                
            } catch (error) {
                console.error('Error stopping camera:', error);
            }
        }
        
        // Capture image function
        function captureImage() {
            try {
                if (!video || !videoStream) {
                    throw new Error('Camera not active');
                }
                
                const canvas = document.getElementById('canvas');
                const ctx = canvas.getContext('2d');
                
                // Draw video frame to canvas
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // Get image data
                const imageData = canvas.toDataURL('image/jpeg', 0.8);
                
                console.log('Image captured');
                
                // Process attendance (you can add face recognition here)
                processAttendance(imageData);
                
            } catch (error) {
                console.error('Capture error:', error);
                showError('Failed to capture image: ' + error.message);
            }
        }
        
        // Process attendance function
        async function processAttendance(imageData) {
            try {
                // Show loading
                document.getElementById('loading').style.display = 'block';
                
                // Send to server (replace with your actual endpoint)
                const response = await fetch('process_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: <?= $_SESSION['user_id'] ?? 'null' ?>,
                        image_data: imageData,
                        timestamp: new Date().toISOString()
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage('Attendance marked successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    showError(result.message || 'Failed to mark attendance');
                }
                
            } catch (error) {
                console.error('Attendance processing error:', error);
                showError('Failed to process attendance: ' + error.message);
            } finally {
                document.getElementById('loading').style.display = 'none';
            }
        }
        
        // Error handling function
        function handleCameraError(error) {
            let errorMessage = 'Camera Error: ';
            let solutions = [];
            
            switch (error.name) {
                case 'NotAllowedError':
                    errorMessage += 'Permission denied';
                    solutions = [
                        'Click "Allow" when prompted for camera access',
                        'Check browser settings for camera permissions',
                        'Make sure no other app is using the camera'
                    ];
                    break;
                    
                case 'NotFoundError':
                    errorMessage += 'No camera found';
                    solutions = [
                        'Make sure a camera is connected',
                        'Check camera drivers',
                        'Try restarting your browser'
                    ];
                    break;
                    
                case 'NotReadableError':
                    errorMessage += 'Camera is busy';
                    solutions = [
                        'Close other apps using the camera',
                        'Close other browser tabs with camera access',
                        'Restart your browser'
                    ];
                    break;
                    
                default:
                    errorMessage += error.message || 'Unknown error';
                    solutions = [
                        'Try refreshing the page',
                        'Try a different browser',
                        'Check camera permissions'
                    ];
            }
            
            const solutionsList = solutions.map(s => `<li>${s}</li>`).join('');
            
            showError(`
                ${errorMessage}
                <br><strong>Solutions:</strong>
                <ul class="text-start mt-2">${solutionsList}</ul>
            `);
        }
        
        // Show error message
        function showError(message) {
            document.getElementById('errorMessage').innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        
        // Show success/info message
        function showMessage(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'warning' ? 'alert-warning' : 'alert-info';
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            document.getElementById('errorMessage').innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show">
                    <i class="fas ${icon}"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        
        // Browser compatibility check
        function checkBrowserSupport() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showError('Your browser does not support camera access. Please use Chrome, Firefox, Safari, or Edge.');
                return false;
            }
            return true;
        }
        
        // HTTPS check
        function checkHTTPS() {
            if (location.protocol !== 'https:' && 
                location.hostname !== 'localhost' && 
                location.hostname !== '127.0.0.1') {
                showError('Camera access requires HTTPS. Please use https:// or localhost');
                return false;
            }
            return true;
        }
        
        // Initial checks
        document.addEventListener('DOMContentLoaded', function() {
            if (!checkBrowserSupport() || !checkHTTPS()) {
                document.getElementById('startCamera').disabled = true;
            }
        });
        
    </script>
</body>
</html>