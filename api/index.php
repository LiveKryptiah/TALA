<?php
// Vercel Serverless PHP Dispatcher / Front Controller for TALA OS

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
    
    if (file_exists($baseDir . '/' . $path) && is_file($baseDir . '/' . $path) && str_ends_with($path, '.php')) {
        $target = $path;
    } elseif (file_exists($baseDir . '/' . $path . '.php') && is_file($baseDir . '/' . $path . '.php')) {
        $target = $path . '.php';
    }
}

// 4. Set server variables so scripts and navigation know the active page
$_SERVER['SCRIPT_NAME'] = '/' . $target;
$_SERVER['PHP_SELF'] = '/' . $target;
$_SERVER['SCRIPT_FILENAME'] = $baseDir . '/' . $target;

// 5. Execute target file
$fullPath = $baseDir . '/' . $target;

if (file_exists($fullPath) && is_file($fullPath)) {
    require $fullPath;
} else {
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>404 Not Found</title><style>body{font-family:sans-serif;text-align:center;padding:50px;}</style></head><body><h1>404 Not Found</h1><p>The requested page could not be found.</p><a href='/'>Return to Home</a></body></html>";
}
