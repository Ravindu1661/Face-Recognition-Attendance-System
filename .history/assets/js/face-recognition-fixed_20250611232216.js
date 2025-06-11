class FaceRecognitionFixed {
    constructor() {
        this.video = null;
        this.canvas = null;
        this.isModelLoaded = false;
        this.isRecognizing = false;
        this.knownFaces = [];
        this.confidenceThreshold = 0.6;
        this.modelPath = './assets/models';
    }

    async initialize() {
        try {
            console.log('Initializing face recognition system...');
            
            // Load polyfills first
            await this.loadPolyfills();
            
            // Load models
            await this.loadModels();
            
            // Setup video
            await this.setupVideo();
            
            console.log('Face recognition system initialized successfully');
            return true;
        } catch (error) {
            console.error('Failed to initialize face recognition:', error);
            this.handleError(error);
            throw error;
        }
    }

    async loadPolyfills() {
        // Ensure all required Math functions exist
        const requiredMathFunctions = [
            'trunc', 'sign', 'log2', 'log10', 
            'sinh', 'cosh', 'tanh', 'asinh', 
            'acosh', 'atanh', 'hypot'
        ];
        
        const missingFunctions = requiredMathFunctions.filter(fn => !Math[fn]);
        
        if (missingFunctions.length > 0) {
            console.warn('Missing Math functions:', missingFunctions);
            // Load polyfill script if needed
            await this.loadScript('assets/js/math-polyfill.js');
        }
    }

    async loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async loadModels() {
        try {
            console.log('Loading face detection models...');
            
            // Use a more compatible model loading approach
            const modelLoadPromises = [
                faceapi.nets.tinyFaceDetector.loadFromUri(this.modelPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(this.modelPath),
                faceapi.nets.faceRecognitionNet.loadFromUri(this.modelPath)
            ];

            await Promise.all(modelLoadPromises);
            
            this.isModelLoaded = true;
            console.log('All models loaded successfully');
            
        } catch (error) {
            console.error('Model loading failed:', error);
            throw new Error('Failed to load face recognition models: ' + error.message);
        }
    }

    async setupVideo() {
        this.video = document.getElementById('video');
        
        if (!this.video) {
            throw new Error('Video element not found');
        }

        try {
            const constraints = {
                video: {
                    width: { ideal: 640, max: 1280 },
                    height: { ideal: 480, max: 720 },
                    facingMode: 'user'
                }
            };

            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.video.srcObject = stream;
            
            return new Promise((resolve, reject) => {
                this.video.onloadedmetadata = () => {
                    console.log('Video loaded successfully');
                    resolve();
                };
                
                this.video.onerror = (error) => {
                    console.error('Video error:', error);
                    reject(error);
                };
                
                setTimeout(() => {
                    reject(new Error('Video loading timeout'));
                }, 15000);
            });
            
        } catch (error) {
            throw new Error('Camera access failed: ' + error.message);
        }
    }

    async detectFaces() {
        if (!this.isModelLoaded || !this.video) {
            throw new Error('System not properly initialized');
        }

        try {
            // Use more stable detection options
            const options = new faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.5
            });

            const detections = await faceapi
                .detectAllFaces(this.video, options)
                .withFaceLandmarks()
                .withFaceDescriptors();

            return detections;
            
        } catch (error) {
            console.error('Face detection error:', error);
            throw new Error('Face detection failed: ' + error.message);
        }
    }

    async recognizeFace() {
        if (this.isRecognizing) {
            return null;
        }

        this.isRecognizing = true;

        try {
            const detections = await this.detectFaces();
            
            if (detections.length === 0) {
                throw new Error('No face detected. Please position your face clearly in the camera.');
            }

            if (detections.length > 1) {
                throw new Error('Multiple faces detected. Please ensure only one person is visible.');
            }

            const detection = detections[0];
            
            // Validate face descriptor
            if (!detection.descriptor || detection.descriptor.length === 0) {
                throw new Error('Could not generate face descriptor. Please try again.');
            }

            return {
                descriptor: Array.from(detection.descriptor),
                landmarks: detection.landmarks,
                box: detection.detection.box,
                confidence: detection.detection.score
            };

        } catch (error) {
            console.error('Face recognition error:', error);
            throw error;
        } finally {
            this.isRecognizing = false;
        }
    }

    handleError(error) {
        let errorMessage = 'Face recognition error: ';
        
        if (error.message.includes('Math')) {
            errorMessage += 'Browser compatibility issue. Please update your browser or try a different one.';
        } else if (error.message.includes('model')) {
            errorMessage += 'Failed to load face recognition models. Please check your internet connection.';
        } else if (error.message.includes('camera') || error.message.includes('getUserMedia')) {
            errorMessage += 'Camera access failed. Please allow camera permissions and try again.';
        } else {
            errorMessage += error.message;
        }

        this.showError(errorMessage);
    }

    showError(message) {
        const errorDiv = document.getElementById('errorMessage');
        if (errorDiv) {
            errorDiv.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Error:</strong> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        console.error(message);
    }

    stopVideo() {
        if (this.video && this.video.srcObject) {
            const tracks = this.video.srcObject.getTracks();
            tracks.forEach(track => track.stop());
            this.video.srcObject = null;
        }
    }
}

// Global instance
window.faceRecognition = new FaceRecognitionFixed();