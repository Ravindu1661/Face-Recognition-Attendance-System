class FaceRecognitionSystem {
    constructor() {
        this.video = null;
        this.canvas = null;
        this.isModelLoaded = false;
        this.isRecognizing = false;
        this.knownFaces = [];
        this.confidenceThreshold = 0.6;
    }

    async initialize() {
        try {
            // Load face-api.js models
            await this.loadModels();
            
            // Setup video stream
            await this.setupVideo();
            
            console.log('Face recognition system initialized successfully');
            return true;
        } catch (error) {
            console.error('Failed to initialize face recognition:', error);
            throw error;
        }
    }

    async loadModels() {
        const modelUrl = '/assets/models';
        
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl),
            faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl),
            faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl),
            faceapi.nets.faceExpressionNet.loadFromUri(modelUrl)
        ]);
        
        this.isModelLoaded = true;
        console.log('Face recognition models loaded');
    }

    async setupVideo() {
        this.video = document.getElementById('video');
        
        if (!this.video) {
            throw new Error('Video element not found');
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                }
            });
            
            this.video.srcObject = stream;
            
            return new Promise((resolve) => {
                this.video.onloadedmetadata = () => {
                    resolve();
                };
            });
        } catch (error) {
            throw new Error('Failed to access camera: ' + error.message);
        }
    }

    async loadKnownFaces() {
        try {
            const response = await fetch('/api/get_face_encodings.php');
            const data = await response.json();
            
            if (data.success) {
                this.knownFaces = data.faces.map(face => ({
                    id: face.user_id,
                    name: face.name,
                    employeeId: face.employee_id,
                    descriptor: new Float32Array(face.encoding)
                }));
                
                console.log(`Loaded ${this.knownFaces.length} known faces`);
            }
        } catch (error) {
            console.error('Failed to load known faces:', error);
        }
    }

    async detectFaces() {
        if (!this.isModelLoaded || !this.video) {
            return [];
        }

        const detections = await faceapi
            .detectAllFaces(this.video, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks()
            .withFaceDescriptors();

        return detections;
    }

    async recognizeFace() {
        if (this.isRecognizing) {
            return null;
        }

        this.isRecognizing = true;

        try {
            const detections = await this.detectFaces();
            
            if (detections.length === 0) {
                throw new Error('No face detected');
            }

            if (detections.length > 1) {
                throw new Error('Multiple faces detected. Please ensure only one person is in frame.');
            }

            const detection = detections[0];
            const faceDescriptor = detection.descriptor;

            // Find best match among known faces
            let bestMatch = null;
            let bestDistance = Infinity;

            for (const knownFace of this.knownFaces) {
                const distance = this.calculateDistance(faceDescriptor, knownFace.descriptor);
                
                if (distance < bestDistance) {
                    bestDistance = distance;
                    bestMatch = knownFace;
                }
            }

            const confidence = Math.max(0, 1 - bestDistance);

            if (confidence >= this.confidenceThreshold && bestMatch) {
                return {
                    userId: bestMatch.id,
                    name: bestMatch.name,
                    employeeId: bestMatch.employeeId,
                    confidence: confidence,
                    faceDescriptor: Array.from(faceDescriptor)
                };
            } else {
                throw new Error('Face not recognized or confidence too low');
            }

        } catch (error) {
            throw error;
        } finally {
            this.isRecognizing = false;
        }
    }

    calculateDistance(descriptor1, descriptor2) {
        // Calculate Euclidean distance
        let sum = 0;
        for (let i = 0; i < descriptor1.length; i++) {
            sum += Math.pow(descriptor1[i] - descriptor2[i], 2);
        }
        return Math.sqrt(sum);
    }

    async captureAndEncodeFace() {
        const detections = await this.detectFaces();
        
        if (detections.length !== 1) {
            throw new Error('Please ensure exactly one face is visible');
        }

        const detection = detections[0];
        return {
            descriptor: Array.from(detection.descriptor),
            landmarks: detection.landmarks.positions.map(p => [p.x, p.y]),
            box: detection.detection.box
        };
    }

    drawDetections(detections) {
        if (!this.canvas) {
            this.canvas = document.getElementById('overlay');
            if (!this.canvas) return;
        }

        const ctx = this.canvas.getContext('2d');
        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        detections.forEach(detection => {
            const box = detection.detection.box;
            
            // Draw bounding box
            ctx.strokeStyle = '#00ff00';
            ctx.lineWidth = 2;
            ctx.strokeRect(box.x, box.y, box.width, box.height);
            
            // Draw landmarks
            if (detection.landmarks) {
                ctx.fillStyle = '#ff0000';
                detection.landmarks.positions.forEach(point => {
                    ctx.fillRect(point.x - 1, point.y - 1, 2, 2);
                });
            }
        });
    }

    async startRealTimeDetection(callback) {
        const detect = async () => {
            if (this.video && this.video.readyState === 4) {
                const detections = await this.detectFaces();
                this.drawDetections(detections);
                
                if (callback) {
                    callback(detections);
                }
            }
            
            requestAnimationFrame(detect);
        };
        
        detect();
    }

    stopVideo() {
        if (this.video && this.video.srcObject) {
            const tracks = this.video.srcObject.getTracks();
            tracks.forEach(track => track.stop());
            this.video.srcObject = null;
        }
    }

    setConfidenceThreshold(threshold) {
        this.confidenceThreshold = Math.max(0, Math.min(1, threshold));
    }
}

// Utility functions
function showLoading(message = 'Processing...') {
    const loadingDiv = document.getElementById('loading');
    if (loadingDiv) {
        loadingDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="loading-spinner me-2"></div>
                <span>${message}</span>
            </div>
        `;
        loadingDiv.style.display = 'block';
    }
}

function hideLoading() {
    const loadingDiv = document.getElementById('loading');
    if (loadingDiv) {
        loadingDiv.style.display = 'none';
    }
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}

// Export for use in other scripts
window.FaceRecognitionSystem = FaceRecognitionSystem;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.showAlert = showAlert;