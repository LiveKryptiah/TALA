importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-messaging-compat.js');

// Initialize the Firebase app in the service worker
firebase.initializeApp({
  apiKey: "AIzaSyCEShhy_n6E6QMbw-1fM3NBVXUo4BbW9YI",
    authDomain: "talanotification.firebaseapp.com",
    projectId: "talanotification",
    storageBucket: "talanotification.firebasestorage.app",
    messagingSenderId: "535190405539",
    appId: "1:535190405539:web:eeb3b90131e8ddbfcd5a16",
    measurementId: "G-VY11VS0KR3"
});

// Retrieve an instance of Firebase Messaging
const messaging = firebase.messaging();

// Listen for background messages (when the browser is closed)
messaging.onBackgroundMessage((payload) => {
    console.log('[firebase-messaging-sw.js] Received background message: ', payload);
    return self.registration.showNotification(notificationTitle, notificationOptions);
    // Notice there is NO self.registration.showNotification() here.
    // Firebase automatically creates the popup based on the PHP payload!
});