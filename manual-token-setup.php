<!DOCTYPE html>
<html>
<head>
    <title>Manual FCM Token Setup</title>
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; margin: 5px; cursor: pointer; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; }
        #tokenDisplay { background: #e9ecef; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; word-break: break-all; }
    </style>
</head>
<body>
    <h2>Manual FCM Token Setup</h2>
    
    <div class="step">
        <h3>Step 1: Initialize Firebase</h3>
        <button onclick="initializeFirebase()">Initialize Firebase</button>
        <div id="firebaseStatus"></div>
    </div>
    
    <div class="step">
        <h3>Step 2: Request Notification Permission</h3>
        <button onclick="requestPermission()">Request Permission</button>
        <div id="permissionStatus"></div>
    </div>
    
    <div class="step">
        <h3>Step 3: Get FCM Token</h3>
        <button onclick="getFCMToken()">Get FCM Token</button>
        <div id="tokenStatus"></div>
        <div id="tokenDisplay"></div>
    </div>
    
    <div class="step">
        <h3>Step 4: Save Token to Database</h3>
        <button onclick="saveTokenToDB()">Save to Database</button>
        <div id="saveStatus"></div>
    </div>

    <script>
        const firebaseConfig = {
            apiKey: "AIzaSyAWMbksJRig5XKQnDEGPO-BW3VzX7c6bug",
            authDomain: "deegeecard---web-14-oct-25.firebaseapp.com",
            projectId: "deegeecard---web-14-oct-25",
            storageBucket: "deegeecard---web-14-oct-25.firebasestorage.app",
            messagingSenderId: "252167545803",
            appId: "1:252167545803:web:22ae4c128a5cd4b8544c4e",
            measurementId: "G-E57TF8E47D"
        };
        
        let app;
        let messaging;
        let fcmToken = '';
        
        function initializeFirebase() {
            try {
                app = firebase.initializeApp(firebaseConfig);
                messaging = firebase.messaging();
                document.getElementById('firebaseStatus').innerHTML = '<div class="success">✅ Firebase initialized successfully</div>';
            } catch (error) {
                document.getElementById('firebaseStatus').innerHTML = '<div class="error">❌ Firebase initialization failed: ' + error.message + '</div>';
            }
        }
        
        async function requestPermission() {
            try {
                const permission = await Notification.requestPermission();
                document.getElementById('permissionStatus').innerHTML = '<div class="success">✅ Notification permission: ' + permission + '</div>';
                return permission === 'granted';
            } catch (error) {
                document.getElementById('permissionStatus').innerHTML = '<div class="error">❌ Permission request failed: ' + error.message + '</div>';
                return false;
            }
        }
        
        async function getFCMToken() {
            try {
                if (!messaging) {
                    alert('Please initialize Firebase first');
                    return;
                }
                
                fcmToken = await messaging.getToken();
                document.getElementById('tokenStatus').innerHTML = '<div class="success">✅ FCM Token obtained successfully</div>';
                document.getElementById('tokenDisplay').innerHTML = '<strong>FCM Token:</strong><br>' + fcmToken;
                
            } catch (error) {
                document.getElementById('tokenStatus').innerHTML = '<div class="error">❌ Failed to get FCM token: ' + error.message + '</div>';
            }
        }
        
        async function saveTokenToDB() {
            if (!fcmToken) {
                alert('Please get FCM token first');
                return;
            }
            
            try {
                const response = await fetch('fcm-token-handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: fcmToken,
                        user_id: 1, // Change to your actual user ID
                        device_type: 'web',
                        manual_setup: true
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('saveStatus').innerHTML = '<div class="success">✅ Token saved to database successfully!</div>';
                    setTimeout(() => {
                        window.location.href = 'fcm-tokens.php';
                    }, 2000);
                } else {
                    document.getElementById('saveStatus').innerHTML = '<div class="error">❌ Failed to save token: ' + result.message + '</div>';
                }
                
            } catch (error) {
                document.getElementById('saveStatus').innerHTML = '<div class="error">❌ Save request failed: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>
