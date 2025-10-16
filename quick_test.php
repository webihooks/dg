<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Push Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        button { padding: 15px 25px; margin: 10px; font-size: 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .test-btn { background: #007bff; color: white; }
        .test-btn:hover { background: #0056b3; }
        .ring-btn { background: #28a745; color: white; }
        .ring-btn:hover { background: #1e7e34; }
        .stop-btn { background: #dc3545; color: white; }
        .stop-btn:hover { background: #c82333; }
        .log { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 8px; border: 1px solid #e9ecef; height: 200px; overflow-y: auto; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .status { padding: 15px; margin: 15px 0; border-radius: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Quick Push & Ring Test</h1>
        <p>Test push notifications with continuous ring sound even when app is closed.</p>
        
        <div class="status" id="status">Ready to test...</div>
        
        <div>
            <button class="test-btn" onclick="testPush()">Test Push Notification</button>
            <button class="ring-btn" onclick="startRing()">Start Continuous Ring</button>
            <button class="stop-btn" onclick="stopRing()">Stop Ring</button>
            <button onclick="testBoth()">Test Both (Push + Ring)</button>
        </div>
        
        <h3>Test Log:</h3>
        <div id="log" class="log"></div>
        
        <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 8px;">
            <h4>📱 Testing Instructions:</h4>
            <ol>
                <li>Click "Start Continuous Ring" to test ring sound</li>
                <li>Click "Test Push Notification" to send push notification</li>
                <li>Minimize browser or lock phone to test background behavior</li>
                <li>Ring should continue playing even when app is in background</li>
            </ol>
        </div>
        
        <!-- Audio element for continuous ring -->
        <audio id="ringAudio" loop>
            <source src="/assets/sounds/new_order.mp3" type="audio/mpeg">
        </audio>
    </div>

    <script>
    const log = document.getElementById('log');
    const status = document.getElementById('status');
    const ringAudio = document.getElementById('ringAudio');
    let isRingPlaying = false;

    // Set audio volume
    ringAudio.volume = 0.8;

    function addLog(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const messageDiv = document.createElement('div');
        messageDiv.className = type;
        messageDiv.innerHTML = `[${timestamp}] ${message}`;
        log.appendChild(messageDiv);
        log.scrollTop = log.scrollHeight;
        console.log(`[${type.toUpperCase()}] ${message}`);
    }

    function updateStatus(message, type = 'info') {
        status.textContent = message;
        status.className = `status ${type}`;
        status.style.backgroundColor = type === 'success' ? '#d4edda' : 
                                     type === 'error' ? '#f8d7da' : 
                                     type === 'warning' ? '#fff3cd' : '#d1ecf1';
        status.style.color = type === 'success' ? '#155724' : 
                           type === 'error' ? '#721c24' : 
                           type === 'warning' ? '#856404' : '#0c5460';
    }

    async function testPush() {
        addLog('🚀 Testing push notification...', 'info');
        updateStatus('Sending push notification...', 'info');
        
        try {
            const response = await fetch('test_web_push_clean.php');
            const result = await response.json();
            
            if (result.success) {
                addLog('✅ Push notification sent successfully!', 'success');
                addLog('📱 Check your device for the notification', 'success');
                updateStatus('Push notification sent! Check your device.', 'success');
            } else {
                addLog('❌ Push test failed: ' + result.message, 'error');
                updateStatus('Push test failed: ' + result.message, 'error');
            }
        } catch (error) {
            addLog('❌ Push test error: ' + error.message, 'error');
            updateStatus('Push test error: ' + error.message, 'error');
        }
    }

    async function startRing() {
        if (isRingPlaying) {
            addLog('🔔 Ring is already playing', 'info');
            return;
        }

        addLog('🔔 Starting continuous ring sound...', 'info');
        updateStatus('Playing continuous ring...', 'info');
        
        try {
            // Set up audio for continuous playback
            ringAudio.currentTime = 0;
            ringAudio.loop = true;
            
            const playPromise = ringAudio.play();
            
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    isRingPlaying = true;
                    addLog('✅ Continuous ring started successfully', 'success');
                    updateStatus('Ring playing continuously...', 'success');
                    
                    // Set up periodic checks to keep audio playing
                    setInterval(() => {
                        if (isRingPlaying && ringAudio.paused) {
                            addLog('🔄 Audio paused, resuming...', 'info');
                            ringAudio.play().catch(e => {
                                addLog('❌ Failed to resume audio: ' + e.message, 'error');
                            });
                        }
                    }, 1000);
                    
                }).catch(error => {
                    addLog('❌ Failed to play ring: ' + error.message, 'error');
                    updateStatus('Ring play failed: ' + error.message, 'error');
                });
            }
        } catch (error) {
            addLog('❌ Ring start error: ' + error.message, 'error');
            updateStatus('Ring start error: ' + error.message, 'error');
        }
    }

    function stopRing() {
        if (!isRingPlaying) {
            addLog('🔇 Ring is not playing', 'info');
            return;
        }

        ringAudio.pause();
        ringAudio.currentTime = 0;
        isRingPlaying = false;
        
        addLog('🔇 Ring stopped', 'success');
        updateStatus('Ring stopped', 'info');
    }

    async function testBoth() {
        addLog('🎯 Testing both push notification and ring...', 'info');
        updateStatus('Testing push + ring...', 'info');
        
        // Start ring first
        await startRing();
        
        // Wait a bit then send push
        setTimeout(async () => {
            await testPush();
        }, 1000);
    }

    // Handle page visibility changes
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && isRingPlaying && ringAudio.paused) {
            addLog('🔄 Page became visible, resuming ring...', 'info');
            ringAudio.play().catch(e => {
                addLog('❌ Failed to resume ring: ' + e.message, 'error');
            });
        }
    });

    // Handle user interactions to resume audio if needed
    document.addEventListener('click', () => {
        if (isRingPlaying && ringAudio.paused) {
            ringAudio.play().catch(e => {
                console.log('Audio resume attempt failed:', e);
            });
        }
    });

    // Auto-start ring when page loads (optional)
    // setTimeout(startRing, 2000);

    addLog('Quick test page loaded successfully.', 'info');
    addLog('Click buttons to test push notifications and ring sound.', 'info');
    addLog('Ring will continue playing even when app is in background.', 'info');
    
    
    
    
    // Enhanced ring player for background
class RingPlayer {
    constructor() {
        this.audio = new Audio('/assets/sounds/new_order.mp3');
        this.audio.loop = true;
        this.audio.volume = 0.9;
        this.isPlaying = false;
        this.retryCount = 0;
        this.maxRetries = 10;
        
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // Resume on user interaction
        const resumeEvents = ['click', 'touchstart', 'keydown', 'mousedown'];
        resumeEvents.forEach(event => {
            document.addEventListener(event, () => {
                if (this.isPlaying && this.audio.paused) {
                    this.play();
                }
            }, { passive: true });
        });
        
        // Handle page visibility
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isPlaying && this.audio.paused) {
                setTimeout(() => this.play(), 500);
            }
        });
        
        // Handle audio errors
        this.audio.addEventListener('error', (e) => {
            console.error('Audio error:', e);
            this.retryPlay();
        });
        
        this.audio.addEventListener('ended', () => {
            if (this.isPlaying) {
                this.play();
            }
        });
    }
    
    async play() {
        if (this.isPlaying && !this.audio.paused) return;
        
        this.isPlaying = true;
        this.audio.currentTime = 0;
        
        try {
            const playPromise = this.audio.play();
            
            if (playPromise !== undefined) {
                await playPromise;
                console.log('Ring started successfully');
                this.retryCount = 0;
            }
        } catch (error) {
            console.log('Ring play failed, will retry:', error);
            this.retryPlay();
        }
    }
    
    retryPlay() {
        if (this.retryCount >= this.maxRetries) {
            console.error('Max retries reached for ring playback');
            return;
        }
        
        this.retryCount++;
        console.log(`Retrying ring playback (attempt ${this.retryCount})`);
        
        setTimeout(() => {
            if (this.isPlaying) {
                this.play();
            }
        }, 1000 * this.retryCount);
    }
    
    stop() {
        this.isPlaying = false;
        this.audio.pause();
        this.audio.currentTime = 0;
        this.retryCount = 0;
        console.log('Ring stopped');
    }
    
    // Method to play ring in background via service worker
    playInBackground() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'PLAY_RING'
            });
        }
        this.play();
    }
}

// Initialize ring player
window.ringPlayer = new RingPlayer();
    </script>
</body>
</html>