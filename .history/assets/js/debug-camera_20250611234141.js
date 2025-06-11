// Debug helper functions
function debugCamera() {
    console.log('=== Camera Debug Info ===');
    console.log('Browser:', navigator.userAgent);
    console.log('Protocol:', location.protocol);
    console.log('Host:', location.hostname);
    console.log('MediaDevices support:', !!navigator.mediaDevices);
    console.log('getUserMedia support:', !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia));
    
    // Check elements
    const video = document.getElementById('video');
    const startBtn = document.getElementById('startCamera');
    
    console.log('Video element found:', !!video);
    console.log('Start button found:', !!startBtn);
    
    if (video) {
        console.log('Video element details:', {
            width: video.width,
            height: video.height,
            autoplay: video.autoplay,
            muted: video.muted,
            playsInline: video.playsInline
        });
    }
    
    // Test basic camera access
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                console.log('✅ Basic camera test successful');
                console.log('Stream details:', stream);
                console.log('Video tracks:', stream.getVideoTracks());
                
                // Stop test stream
                stream.getTracks().forEach(track => track.stop());
            })
            .catch(error => {
                console.error('❌ Basic camera test failed:', error);
            });
    }
}

// Run debug on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(debugCamera, 1000);
});