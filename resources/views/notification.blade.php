<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Firebase Push Notification Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        button { padding: 10px 20px; margin: 5px; background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        .token-display { background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; margin: 10px 0; word-break: break-word; }
        .log { background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; margin: 10px 0; height: 200px; overflow-y: scroll; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
<div class="container">
    <h1>Firebase Push Notification Test</h1>

    <div class="section">
        <h3>Step 1: Initialize Firebase & Get Permission</h3>
        <button onclick="initializeFirebase()">Initialize Firebase</button>
        <button onclick="requestPermission()">Request Permission</button>
        <div id="permission-status"></div>
    </div>

    <div class="section">
        <h3>Step 2: Device Token</h3>
        <button onclick="getDeviceToken()">Get Device Token</button>
        <button onclick="saveTokenToLaravel()">Save Token to Laravel</button>
        <div class="token-display" id="token-display">Token will appear here...</div>
    </div>

    <div class="section">
        <h3>Step 3: Test Notifications</h3>
        <button onclick="sendTestNotification()">Send Test Notification (via Laravel)</button>
        <button onclick="sendDirectNotification()">Send Direct Notification (via Firebase)</button>
    </div>

    <div class="section">
        <h3>Logs</h3>
        <button onclick="clearLogs()">Clear Logs</button>
        <div class="log" id="log"></div>
    </div>
</div>

<!-- Firebase SDK -->
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/9.0.0/firebase-app.js';
    import { getMessaging, getToken, onMessage } from 'https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging.js';

    const firebaseConfig = {
        apiKey: "AIzaSyB5wfuCZGZRxMYW6vxzblotSZK5-9bEwM4",
        authDomain: "testing-398711.firebaseapp.com",
        databaseURL: "https://testing-398711-default-rtdb.firebaseio.com",
        projectId: "testing-398711",
        storageBucket: "testing-398711.appspot.com",
        messagingSenderId: "1041698225513",
        appId: "1:1041698225513:web:0c1d2f82a76f0d379b11f8",
        measurementId: "G-789RMW0Z5Y"


    };

    const vapidKey = "BA6gMDiVB7tMvcCf_C0BxhGDZRXX9lQfO1gbr97kpQtcp5V4FtzrQ5OEw0Az4Hv6i9sAkX-GczO7DT1hIA3zxPo";
    const serverKey = "BA6gMDiVB7tMvcCf_C0BxhGDZRXX9lQfO1gbr97kpQtcp5V4FtzrQ5OEw0Az4Hv6i9sAkX-GczO7DT1hIA3zxPo"; // Replace with your actual server key

    let app;
    let messaging;
    let currentToken = null;
    let savedUserId = null;

    window.initializeFirebase = initializeFirebase;
    window.requestPermission = requestPermission;
    window.getDeviceToken = getDeviceToken;
    window.saveTokenToLaravel = saveTokenToLaravel;
    window.sendTestNotification = sendTestNotification;
    window.sendDirectNotification = sendDirectNotification;
    window.clearLogs = clearLogs;

    function log(message, type = 'info') {
        const logDiv = document.getElementById('log');
        const timestamp = new Date().toLocaleTimeString();
        const className = type === 'error' ? 'error' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : ''));
        logDiv.innerHTML += `<div class="${className}">[${timestamp}] ${message}</div>`;
        logDiv.scrollTop = logDiv.scrollHeight;
        console.log(message);
    }

    function clearLogs() {
        document.getElementById('log').innerHTML = '';
    }

    async function initializeFirebase() {
        try {
            app = initializeApp(firebaseConfig);
            messaging = getMessaging(app);

            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            log('✅ Firebase initialized and service worker registered', 'success');

            onMessage(messaging, (payload) => {
                log('📨 Foreground message received: ' + JSON.stringify(payload), 'success');
                if (payload.notification) {
                    new Notification(payload.notification.title, {
                        body: payload.notification.body,
                        icon: payload.notification.icon || '/favicon.ico'
                    });
                }
            });

        } catch (error) {
            log('❌ Firebase initialization failed: ' + error.message, 'error');
        }
    }

    async function requestPermission() {
        try {
            const permission = await Notification.requestPermission();
            document.getElementById('permission-status').innerHTML =
                `<strong>Permission Status:</strong> ${permission}`;

            if (permission === 'granted') {
                log('✅ Notification permission granted', 'success');
            } else {
                log('❌ Notification permission denied', 'error');
            }
        } catch (error) {
            log('❌ Error requesting permission: ' + error.message, 'error');
        }
    }

    async function getDeviceToken() {
        if (!messaging) {
            log('❌ Firebase not initialized. Click "Initialize Firebase" first.', 'error');
            return;
        }

        await debugFirebaseSetup()

        try {
            const registration = await navigator.serviceWorker.getRegistration('./firebase-messaging-sw.js');
            if (!registration) {
                log('❌ Service worker not found.', 'error');
                return;
            }

            console.log('Service worker registration:', registration);
            console.log('VAPID key:', vapidKey);

            const token = await getToken(messaging, {
                vapidKey: vapidKey,
                serviceWorkerRegistration: registration,
            });


            if (token) {
                currentToken = token;
                document.getElementById('token-display').innerHTML =
                    `<strong>Device Token:</strong><br>${token}`;
                log('✅ Device token retrieved successfully', 'success');
            } else {
                log('❌ No registration token available. Request permission first.', 'error');
            }

        } catch (error) {
            log('❌ Error getting device token: ' + error.message, 'error');
        }
    }

    async function saveTokenToLaravel() {
        if (!currentToken) {
            log('❌ No device token available. Get token first.', 'error');
            return;
        }

        try {
            const response = await fetch('/device-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    device_token: currentToken,
                    device_type: 'web'
                })
            });

            const result = await response.json();

            if (response.ok) {
                savedUserId = result.user_id;
                log('✅ Token saved to Laravel successfully: ' + result.message, 'success');
            } else {
                log('❌ Error saving token: ' + (result.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            log('❌ Network error saving token: ' + error.message, 'error');
        }
    }

    async function sendTestNotification() {
        if (!currentToken) {
            log('❌ No device token available. Get token first.', 'error');
            return;
        }

        try {
            const response = await fetch('/send-notification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    title: 'Laravel Test Notification',
                    body: 'This notification was sent via Laravel backend!',
                    device_token: currentToken,
                    user_id:savedUserId,
                    data: {
                        test: true,
                        timestamp: new Date().toISOString()
                    }
                })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                log('✅ Notification sent via Laravel successfully', 'success');
            } else {
                log('❌ Error sending notification: ' + (result.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            log('❌ Network error sending notification: ' + error.message, 'error');
        }
    }

    async function sendDirectNotification() {
        if (!currentToken) {
            log('❌ No device token available. Get token first.', 'error');
            return;
        }

        try {
            // Call your Laravel backend instead of FCM directly
            const response = await fetch('/send-topic-notification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    // Add CSRF token if using Laravel's web routes
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    // Or if using Sanctum/API tokens
                    // 'Authorization': `Bearer ${yourApiToken}`
                },
                body: JSON.stringify({
                    token: currentToken,
                    title: 'Direct Firebase Test',
                    topic: 'Laravel Implementation',
                    body: 'This notification was sent via Laravel backend!',
                    icon: '/favicon.ico',
                    data: {
                        direct_test: true,
                        timestamp: new Date().toISOString()
                    }
                })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                log('✅ Direct notification sent successfully via backend', 'success');
                if (result.message) {
                    log(`📝 Backend response: ${result.message}`, 'info');
                }
            } else {
                log(`❌ Backend error: ${result.message || 'Unknown error'}`, 'error');
                if (result.error) {
                    log(`🔍 Error details: ${JSON.stringify(result.error)}`, 'error');
                }
            }
        } catch (error) {
            log('❌ Network error calling backend: ' + error.message, 'error');
        }
    }


    // Add these debug functions to your code
    async function debugFirebaseSetup() {
        log('🔍 Starting Firebase setup debug...', 'info');

        // Check HTTPS
        log(`🔒 Protocol: ${location.protocol}`, location.protocol === 'https:' ? 'success' : 'warning');

        // Check notification permission
        log(`🔔 Notification permission: ${Notification.permission}`,
            Notification.permission === 'granted' ? 'success' : 'error');

        // Check service worker support
        if ('serviceWorker' in navigator) {
            log('✅ Service Worker supported', 'success');

            try {
                const registrations = await navigator.serviceWorker.getRegistrations();
                log(`📱 Active service workers: ${registrations.length}`, 'info');

                registrations.forEach((reg, index) => {
                    log(`  SW ${index + 1}: ${reg.scope}`, 'info');
                });

                // Check specific FCM service worker
                const fcmReg = await navigator.serviceWorker.getRegistration('/firebase-messaging-sw.js');
                if (fcmReg) {
                    log('✅ FCM Service Worker found', 'success');
                    log(`  State: ${fcmReg.active ? fcmReg.active.state : 'No active worker'}`, 'info');
                } else {
                    log('❌ FCM Service Worker not found', 'error');
                }
            } catch (error) {
                log('❌ Error checking service workers: ' + error.message, 'error');
            }
        } else {
            log('❌ Service Worker not supported', 'error');
        }

        // Check push messaging support
        if ('PushManager' in window) {
            log('✅ Push messaging supported', 'success');
        } else {
            log('❌ Push messaging not supported', 'error');
        }

        // Test service worker file accessibility
        try {
            const response = await fetch('/firebase-messaging-sw.js');
            if (response.ok) {
                log('✅ Service worker file accessible', 'success');
            } else {
                log(`❌ Service worker file not accessible (${response.status})`, 'error');
            }
        } catch (error) {
            log('❌ Error accessing service worker file: ' + error.message, 'error');
        }
    }

    // Add a button to call this function
    // <button onclick="debugFirebaseSetup()">Debug Firebase Setup</button>

    window.addEventListener('load', () => {
        log('🚀 Page loaded. Click "Initialize Firebase" to start.');
    });
</script>
</body>
</html>
