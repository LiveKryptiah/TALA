import { getMessaging, getToken } from "firebase/messaging";

// You must point to the path where the file exists
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/firebase-messaging-sw.js')
    .then((registration) => {
      console.log('Service Worker registered with scope:', registration.scope);
      
      // Now initialize messaging
      const messaging = getMessaging();
      // ... your getToken code here
    })
    .catch((err) => {
      console.error('Service worker registration failed:', err);
    });
}