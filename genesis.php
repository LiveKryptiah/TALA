<?php
session_start();
require 'db_connect.php';

// Strict Admin Access Control
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$displayName = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin';
$displayInitial = strtoupper(substr($displayName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Genesis AI | TALA OS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, colors: { brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } }, boxShadow: { 'soft': '0 20px 40px -15px rgba(0,0,0,0.05)' } } } }
    </script>
    
    <style>
        @keyframes blob { 0% { transform: translate(0px, 0px) scale(1); } 33% { transform: translate(30px, -50px) scale(1.1); } 66% { transform: translate(-20px, 20px) scale(0.9); } 100% { transform: translate(0px, 0px) scale(1); } }
        .ambient-bg { position: fixed; inset: 0; z-index: -1; background: #f8fafc; overflow: hidden; pointer-events: none; }
        .blob { position: absolute; filter: blur(100px); opacity: 0.6; border-radius: 50%; mix-blend-multiply: true; animation: blob 10s infinite alternate ease-in-out; }
        .animation-delay-2000 { animation-delay: 2s; }
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .typing-indicator span { animation: blink 1.4s infinite both; height: 6px; width: 6px; background: #94a3b8; border-radius: 50%; display: inline-block; margin: 0 1px; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes blink { 0% { opacity: .2; } 20% { opacity: 1; } 100% { opacity: .2; } }
    </style>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden selection:bg-brand-500 selection:text-white">

    <div class="ambient-bg">
        <div class="blob bg-brand-200 w-[500px] h-[500px] -top-[10%] -left-[10%]"></div>
        <div class="blob bg-yellow-200 w-[500px] h-[500px] bottom-[10%] -right-[10%] animation-delay-2000"></div>
    </div>

    <?php include 'sidebar_admin.php'; ?>

    <main class="flex-1 flex flex-col p-8 ml-64 relative z-10 h-screen">
        <div class="max-w-5xl mx-auto w-full h-full flex flex-col space-y-6">
            
            <div class="flex justify-between items-end pb-4 border-b border-slate-200 shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center overflow-hidden">
                        <img src="genesis_logo.png" alt="Genesis Logo" class="w-full h-full object-cover" onerror="this.outerHTML='<i class=\'ph-fill ph-robot text-3xl text-brand-600\'></i>'">
                    </div>
                    <div>
                        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Genesis AI</h1>
                        <p class="text-sm font-semibold text-slate-500 mt-1 uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span> TALA OS Core Intelligence
                        </p>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-[2rem] shadow-soft flex flex-col flex-1 overflow-hidden border border-slate-200">
                
                <div id="chat-box" class="flex-1 overflow-y-auto p-6 md:p-8 space-y-6 custom-scrollbar">
                    
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-yellow-500 text-white flex items-center justify-center text-xl shrink-0 shadow-md">
                            <i class="ph-fill ph-robot"></i>
                        </div>
                        <div class="bg-white border border-slate-100 p-5 rounded-2xl rounded-tl-none shadow-sm max-w-[80%]">
                            <p class="text-sm font-medium text-slate-700 leading-relaxed">
                                Hello <?= htmlspecialchars($displayName) ?>. I am Genesis, the Core Intelligence of TALA OS. I monitor the municipal youth profiles, active programs, and financial ledgers. How can I assist you with the system today?
                            </p>
                        </div>
                    </div>

                </div>

                <div class="p-4 bg-white/50 border-t border-slate-100 shrink-0">
                    <form id="chat-form" class="relative flex items-center">
                        <input type="text" id="user-input" placeholder="Ask Genesis about system stats, budgets, or youth profiles..." class="w-full bg-white border border-slate-200 text-slate-800 rounded-2xl pl-6 pr-16 py-4 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm" autocomplete="off" required>
                        <button type="submit" id="send-btn" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-slate-900 hover:bg-brand-600 text-white rounded-xl flex items-center justify-center transition-colors shadow-sm">
                            <i class="ph-bold ph-paper-plane-right text-lg"></i>
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            const chatBox = $('#chat-box');
            const chatForm = $('#chat-form');
            const userInput = $('#user-input');

            function appendUserMessage(message) {
                const initial = "<?= $displayInitial ?>";
                const html = `
                    <div class="flex items-start gap-4 flex-row-reverse">
                        <div class="w-10 h-10 rounded-xl bg-slate-800 text-white flex items-center justify-center font-bold shrink-0 shadow-sm">${initial}</div>
                        <div class="bg-slate-800 text-white p-5 rounded-2xl rounded-tr-none shadow-sm max-w-[80%]">
                            <p class="text-sm font-medium leading-relaxed">${message}</p>
                        </div>
                    </div>
                `;
                chatBox.append(html);
                scrollToBottom();
            }

            function appendGenesisMessage(message) {
                const html = `
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-yellow-500 text-white flex items-center justify-center text-xl shrink-0 shadow-md">
                            <i class="ph-fill ph-robot"></i>
                        </div>
                        <div class="bg-white border border-slate-100 p-5 rounded-2xl rounded-tl-none shadow-sm max-w-[80%]">
                            <p class="text-sm font-medium text-slate-700 leading-relaxed genesis-text">${message}</p>
                        </div>
                    </div>
                `;
                chatBox.append(html);
                scrollToBottom();
            }

            function showTypingIndicator() {
                const html = `
                    <div class="flex items-start gap-4 typing-box">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-yellow-500 text-white flex items-center justify-center text-xl shrink-0 shadow-md">
                            <i class="ph-fill ph-robot"></i>
                        </div>
                        <div class="bg-white border border-slate-100 p-5 rounded-2xl rounded-tl-none shadow-sm flex items-center justify-center typing-indicator">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                `;
                chatBox.append(html);
                scrollToBottom();
            }

            function removeTypingIndicator() {
                $('.typing-box').remove();
            }

            function scrollToBottom() {
                chatBox.scrollTop(chatBox[0].scrollHeight);
            }

            chatForm.on('submit', function(e) {
                e.preventDefault();
                const msg = userInput.val().trim();
                if (!msg) return;

                appendUserMessage(msg);
                userInput.val('');
                showTypingIndicator();

                // Send to Genesis Brain
                $.ajax({
                    url: 'genesis_api.php',
                    method: 'POST',
                    data: { message: msg },
                    dataType: 'json',
                    success: function(response) {
                        removeTypingIndicator();
                        if(response.status === 'success') {
                            appendGenesisMessage(response.reply);
                        } else {
                            appendGenesisMessage("System Error: " + response.message);
                        }
                    },
                    error: function() {
                        removeTypingIndicator();
                        appendGenesisMessage("Network Error. Genesis cannot reach the core database.");
                    }
                });
            });
        });
    </script>
</body>
</html>