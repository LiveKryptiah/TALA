<?php
// Vercel Serverless PHP Dispatcher / Front Controller for TALA OS

// Enable detailed error output to diagnose issues
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure sessions use /tmp which is the only writable directory on Vercel AWS Lambda
if (is_dir('/tmp') && is_writable('/tmp')) {
    session_save_path('/tmp');
}

// 1. Identify base directory (project root)
$baseDir = dirname(__DIR__);
chdir($baseDir);

// 2. Parse requested path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$path = trim($parsedPath, '/');

// 3. Resolve target file
$target = 'index.php';

if (!empty($path) && $path !== 'index.php') {
    // Prevent path traversal
    $path = str_replace(['..', "\0"], '', $path);
    
    if (file_exists($baseDir . '/' . $path) && is_file($baseDir . '/' . $path)) {
        // If it's a non-PHP file that somehow reached here, serve it directly
        if (!str_ends_with($path, '.php')) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mimes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'ico' => 'image/x-icon',
                'svg' => 'image/svg+xml',
                'mp4' => 'video/mp4',
                'mp3' => 'audio/mpeg',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
            ];
            if (isset($mimes[$ext])) {
                header('Content-Type: ' . $mimes[$ext]);
            }
            readfile($baseDir . '/' . $path);
            exit;
        }
        $target = $path;
    } elseif (file_exists($baseDir . '/' . $path . '.php') && is_file($baseDir . '/' . $path . '.php')) {
        $target = $path . '.php';
    }
}

// 4. Set server variables so scripts and navigation know the active page
$_SERVER['SCRIPT_NAME'] = '/' . $target;
$_SERVER['PHP_SELF'] = '/' . $target;
$_SERVER['SCRIPT_FILENAME'] = $baseDir . '/' . $target;

// 5. Execute target file with error handling
$fullPath = $baseDir . '/' . $target;

try {
    if (file_exists($fullPath) && is_file($fullPath)) {
        require $fullPath;
    } else {
        http_response_code(404);
        echo "<!DOCTYPE html><html><head><title>404 Not Found</title><style>body{font-family:sans-serif;text-align:center;padding:50px;}</style></head><body><h1>404 Not Found</h1><p>The requested page <code>" . htmlspecialchars($target) . "</code> could not be found.</p><a href='/'>Return to Home</a></body></html>";
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<!DOCTYPE html><html><head><title>TALA - Application Error</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;padding:40px;color:#1e293b;} .card{max-width:700px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.05);} h2{color:#e11d48;margin-top:0;} pre{background:#f1f5f9;padding:16px;border-radius:8px;font-size:12px;overflow-x:auto;}</style></head><body>";
    echo "<div class='card'>";
    echo "<h2>Application Notice</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Location:</strong> " . htmlspecialchars($e->getFile()) . " on line " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div></body></html>";
}
