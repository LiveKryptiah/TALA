<?php
// TALA OS - AI Core Configuration
// Secrets are loaded from local config_secrets.php (git-ignored) or environment variables

if (file_exists(__DIR__ . '/config_secrets.php')) {
    require_once __DIR__ . '/config_secrets.php';
}

if (!isset($GEMINI_API_KEY)) {
    $GEMINI_API_KEY = getenv('GEMINI_API_KEY') ?: "YOUR_GEMINI_API_KEY_HERE";
}
?>
