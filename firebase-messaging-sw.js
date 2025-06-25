importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyB5wfuCZGZRxMYW6vxzblotSZK5-9bEwM4",
    authDomain: "testing-398711.firebaseapp.com",
    projectId: "testing-398711",
    messagingSenderId: "1041698225513",
    appId: "1:1041698225513:web:0c1d2f82a76f0d379b11f8"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    console.log('[firebase-messaging-sw.js] Received background message ', payload);
    const { title, body, icon } = payload.notification;

    self.registration.showNotification(title, {
        body: body,
        icon: icon || '/favicon.ico'
    });
});
