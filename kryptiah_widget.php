<div id="kryptiah-widget-container" class="fixed bottom-6 right-6 z-[99999] font-sans flex flex-col items-end pointer-events-none" style="position: fixed; bottom: 1.5rem; right: 1.5rem;">
    
    <div id="kryptiah-chat-window" class="hidden flex-col w-[380px] sm:w-[480px] h-[480px] bg-white/95 backdrop-blur-3xl border border-slate-200/80 rounded-[2rem] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.15)] mb-6 overflow-hidden origin-bottom-right transition-all pointer-events-auto">
        
        <div class="px-6 py-5 bg-white border-b border-slate-100 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 flex items-center justify-center shrink-0 bg-transparent">
                    <img src="kryptiah_logo.png" alt="Kryptiah" class="w-full h-full object-contain">
                </div>
                <div>
                    <h3 class="text-slate-900 font-extrabold text-sm tracking-wide">Kryptiah AI</h3>
                    <div class="mt-0.5">
                        <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Active System Core</span>
                    </div>
                </div>
            </div>
            <button id="kryptiah-close-btn" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors shrink-0">
                <i class="ph-bold ph-x text-sm"></i>
            </button>
        </div>

        <div id="kryptiah-chat-box" class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/50 custom-scrollbar">
            <div class="flex items-start">
                <div class="bg-white border border-slate-200 p-4 rounded-2xl rounded-tl-sm shadow-sm max-w-[85%]">
                    <p class="text-[13px] font-medium text-slate-700 leading-relaxed">
                        Hello! I am Kryptiah. I am currently monitoring the TALA OS database. How can I assist you today?
                    </p>
                </div>
            </div>
        </div>

        <div class="p-4 bg-white border-t border-slate-100 shrink-0">
            <form id="kryptiah-chat-form" class="relative flex items-center">
                <input type="text" id="kryptiah-user-input" placeholder="Message Kryptiah..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-2xl pl-5 pr-14 py-4 text-[13px] font-semibold focus:outline-none focus:ring-2 focus:ring-slate-900/10 focus:border-slate-400 transition-all shadow-sm" autocomplete="off" required>
                <button type="submit" id="kryptiah-send-btn" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center transition-all hover:bg-slate-800 shadow-sm hover:scale-105 active:scale-95">
                    <i class="ph-bold ph-arrow-up text-[15px]"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- FIXED BUTTON: Removed the <a> tag inside so it no longer redirects -->
    <button id="kryptiah-toggle-btn" class="w-16 h-16 flex items-center justify-center hover:scale-110 transition-all duration-300 relative pointer-events-auto group outline-none focus:outline-none bg-transparent border-none cursor-pointer">
        <img src="kryptiah_logo.png" alt="Kryptiah AI" class="w-full h-full object-contain pointer-events-none">
    </button>
</div>

<style>
    /* Typing Animation */
    .typing-indicator span { animation: blink 1.4s infinite both; height: 5px; width: 5px; background: #94a3b8; border-radius: 50%; display: inline-block; margin: 0 2px; }
    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes blink { 0% { opacity: .2; } 20% { opacity: 1; } 100% { opacity: .2; } }
    
    /* Clean Scrollbar */
    #kryptiah-chat-box::-webkit-scrollbar { width: 5px; }
    #kryptiah-chat-box::-webkit-scrollbar-track { background: transparent; }
    #kryptiah-chat-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    #kryptiah-chat-box::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('kryptiah-toggle-btn');
    const closeBtn = document.getElementById('kryptiah-close-btn');
    const chatWindow = document.getElementById('kryptiah-chat-window');
    const chatForm = document.getElementById('kryptiah-chat-form');
    const userInput = document.getElementById('kryptiah-user-input');
    const chatBox = document.getElementById('kryptiah-chat-box');

    // Toggle Chat Window
    const toggleChat = (e) => {
        if(e) e.preventDefault();
        chatWindow.classList.toggle('hidden');
        chatWindow.classList.toggle('flex');
        if (!chatWindow.classList.contains('hidden')) {
            userInput.focus();
        }
    };

    toggleBtn.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    // Auto-Scroll to bottom
    const scrollToBottom = () => {
        chatBox.scrollTop = chatBox.scrollHeight;
    };

    // Append User Message
    const appendUserMessage = (msg) => {
        const html = `
            <div class="flex items-start flex-row-reverse">
                <div class="bg-slate-900 text-white p-4 rounded-2xl rounded-tr-sm shadow-sm max-w-[85%]">
                    <p class="text-[13px] font-medium leading-relaxed">${msg}</p>
                </div>
            </div>
        `;
        chatBox.insertAdjacentHTML('beforeend', html);
        scrollToBottom();
    };

    // Append Kryptiah Message
    const appendKryptiahMessage = (msg) => {
        const html = `
            <div class="flex items-start">
                <div class="bg-white border border-slate-200 p-4 rounded-2xl rounded-tl-sm shadow-sm max-w-[85%]">
                    <p class="text-[13px] font-medium text-slate-700 leading-relaxed">${msg}</p>
                </div>
            </div>
        `;
        chatBox.insertAdjacentHTML('beforeend', html);
        scrollToBottom();
    };

    // Show Typing Indicator
    const showTyping = () => {
        const html = `
            <div class="flex items-start" id="kryptiah-typing">
                <div class="bg-white border border-slate-200 py-4 px-5 rounded-2xl rounded-tl-sm shadow-sm flex items-center typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `;
        chatBox.insertAdjacentHTML('beforeend', html);
        scrollToBottom();
    };

    // Remove Typing Indicator
    const removeTyping = () => {
        const typingEl = document.getElementById('kryptiah-typing');
        if (typingEl) typingEl.remove();
    };

    // Handle Form Submission
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = userInput.value.trim();
        if (!msg) return;

        appendUserMessage(msg);
        userInput.value = '';
        showTyping();

        try {
            const formData = new FormData();
            formData.append('message', msg);

            const response = await fetch('kryptiah_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            removeTyping();

            if (data.status === 'success') {
                appendKryptiahMessage(data.reply);
            } else {
                appendKryptiahMessage("System Error: " + data.message);
            }
        } catch (error) {
            removeTyping();
            appendKryptiahMessage("Network Error. Kryptiah cannot reach the core database.");
        }
    });
});
</script>