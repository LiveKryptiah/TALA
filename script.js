<!-- THE SLIM SUBSCRIPTION FOOTER -->
<div id="tala-footer" class="fixed bottom-0 left-0 right-0 z-[99999] bg-white border-t border-gray-100 shadow-[0_-5px_10px_rgba(0,0,0,0.05)] p-2">
    <div class="max-w-4xl mx-auto flex items-center justify-center gap-3">
        
        <span class="text-xs font-bold text-gray-700 hidden sm:block">Stay Updated:</span>
        
        <input type="text" id="nameInput" placeholder="Enter your name..." 
               class="w-32 sm:w-48 px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
        
        <button id="subBtn" class="px-5 py-1.5 text-sm font-bold text-white rounded-lg transition-all transform hover:scale-105 bg-gradient-to-r from-green-500 to-yellow-400 shadow-md">
            Subscribe
        </button>
    </div>
    <p id="statusMsg" class="text-[10px] text-center mt-1 font-medium text-red-500"></p>
</div>

<!-- FIREBASE SCRIPTS -->
<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js";
    import { getMessaging, getToken } from "https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging.js";

    // --- FILL THESE IN ---
    const firebaseConfig = {
          apiKey: "AIzaSyCEShhy_n6E6QMbw-1fM3NBVXUo4BbW9YI",
    authDomain: "talanotification.firebaseapp.com",
    projectId: "talanotification",
    storageBucket: "talanotification.firebasestorage.app",
    messagingSenderId: "535190405539",
    appId: "1:535190405539:web:eeb3b90131e8ddbfcd5a16",
    measurementId: "G-VY11VS0KR3"
    };

    const app = initializeApp(firebaseConfig);
    const messaging = getMessaging(app);

    document.getElementById('subBtn').addEventListener('click', async () => {
        const name = document.getElementById('nameInput').value.trim();
        const msg = document.getElementById('statusMsg');

        if (!name) { msg.innerText = "Please enter a name!"; return; }

        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error("Permission Denied");

            // --- FILL VAPID KEY HERE ---
            const token = await getToken(messaging, { vapidKey: 'BOcQ8HQBG9s0segaA0pXt6w6uAavMAKexQAEA0qzTIHyZMEFHhViIPBpYhS5NEHRQUt3IQa3CDjI7biZ3sPlGmw' });
            
            const response = await fetch('save_token.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name, token: token })
            });

            const result = await response.json();
            if (result.success) {
                document.getElementById('tala-footer').style.display = 'none';
                alert("Successfully subscribed!");
            } else {
                msg.innerText = "Error: " + result.message;
            }
        } catch (e) {
            msg.innerText = "Error: " + e.message;
        }
    });
</script>