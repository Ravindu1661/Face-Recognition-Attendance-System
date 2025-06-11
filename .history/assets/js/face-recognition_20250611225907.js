let video = document.getElementById('video');
let canvas = document.getElementById('canvas');
let startBtn = document.getElementById('startCamera');
let captureBtn = document.getElementById('captureBtn');
let stopBtn = document.getElementById('stopCamera');
let loading = document.getElementById('loading');
let result = document.getElementById('result');

let stream = null;
let modelsLoaded = false;

// Load face-api models
async function loadModels() {
    loading.style.display = 'block';
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('assets/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('assets/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('assets/models'),
            faceapi.nets.faceExpressionNet.loadFromUri('assets/models')
        ]);
        modelsLoaded = true;
        loading.style.display = 'none';
        showMessage('Models loaded successfully!', 'success');
    } catch (error) {
        loading.style.display = 'none';
        showMessage('Error loading models: ' + error.message, 'danger');
    }
}

// Start camera
async function startCamera() {
    if (!modelsLoaded) {
        await loadModels();
    }
    
    try {
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { width: 640, height: 480 } 
        });
        video.srcObject = stream;
        
        startBtn.style.display = 'none';
        captureBtn.style.display = 'inline-block';
        stopBtn.style.display = 'inline-block';
        
        showMessage('Camera started. Position your face in the frame.', 'info');
    } catch (error) {
        showMessage('Error accessing camera: ' + error.message, 'danger');
    }
}

// Stop camera
function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        video.srcObject = null;
        stream = null;
    }
    
    startBtn.style.display = 'inline-block';
    captureBtn.style.display = 'none';
    stopBtn.style.display = 'none';
}

// Capture and process face
async function captureAndProcess() {
    if (!modelsLoaded) {
        showMessage('Models not loaded yet!', 'warning');
        return;
    }

    captureBtn.disabled = true;
    showMessage('Processing face...', 'info');

    try {
        // Detect face
        const detections = await faceapi
            .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks()
            .withFaceDescriptors();

        if (detections.length === 0) {
            showMessage('No face detected. Please try again.', 'warning');
            captureBtn.disabled = false;
            return;
        }

        if (detections.length > 1) {
            showMessage('Multiple faces detected. Please ensure only one person is in frame.', 'warning');
            captureBtn.disabled = false;
            return;
        }

        // Get face descriptor
        const faceDescriptor = detections[0].descriptor;
        
        // Send to server for verification
        const response = await fetch('includes/process_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                face_descriptor: Array.from(faceDescriptor),
                confidence: detections[0].detection.score
            })
        });

        const data = await response.json();
        
        if (data.success) {
            showMessage('Attendance marked successfully!', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        } else {
            showMessage(data.message || 'Failed to mark attendance', 'danger');
            captureBtn.disabled = false;
        }

    } catch (error) {
        showMessage('Error processing face: ' + error.message, 'danger');
        captureBtn.disabled = false;
    }
}

// Show message
function showMessage(message, type) {
    result.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
}

// Event listeners
startBtn.addEventListener('click', startCamera);
stopBtn.addEventListener('click', stopCamera);
captureBtn.addEventListener('click', captureAndProcess);

// Load models on page load
window.addEventListener('load', loadModels);