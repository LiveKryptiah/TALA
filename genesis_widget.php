<?php
// TALA OS - Genesis AI Widget
// Checks if the widget has already been loaded or is present via chat_widget / kryptiah_widget
if (!defined('TALA_AI_WIDGET_LOADED')) {
    define('TALA_AI_WIDGET_LOADED', true);
    if (file_exists(__DIR__ . '/kryptiah_widget.php')) {
        include_once __DIR__ . '/kryptiah_widget.php';
    } elseif (file_exists(__DIR__ . '/chat_widget.php')) {
        include_once __DIR__ . '/chat_widget.php';
    }
}
?>
