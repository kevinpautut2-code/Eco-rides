<?php
/**
 * EcoRide - Routeur Personnalisé
 * Gère les routes API et les fichiers statiques
 */

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Nettoyer le chemin
$path = trim($path, '/');

// ===== PASSARELLE API =====
// Toutes les requêtes vers /backend/api/* sont gérées par api.php
if (strpos($path, 'backend/api') === 0) {
    require __DIR__ . '/api.php';
    exit();
}

// ===== FICHIERS STATIQUES =====
$file = __DIR__ . '/' . $path;

// Sécurité: empêcher les traversées de répertoires
$realFile = realpath($file);
$realDir = realpath(__DIR__);
if ($realFile && strpos($realFile, $realDir) !== 0) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

// Si c'est un fichier existant (mais pas .php sauf api.php), le servir
if ($realFile && is_file($realFile)) {
    $ext = pathinfo($realFile, PATHINFO_EXTENSION);
    $filename = basename($realFile);
    
    // Les fichiers .php ne doivent jamais être servis comme fichiers statiques
    // SAUF api.php qui est notre passerelle API
    if ($ext === 'php' && $filename !== 'api.php') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'PHP files cannot be served directly']);
        exit();
    }
    
    $mimeTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];
    
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mimeType);
    readfile($realFile);
    exit();
}

// Si c'est un répertoire avec un index.html, le servir
if ($realFile && is_dir($realFile)) {
    $indexFile = $realFile . '/index.html';
    if (file_exists($indexFile)) {
        header('Content-Type: text/html');
        readfile($indexFile);
        exit();
    }
}

// Pour tous les autres cas (routes SPA), servir index.html
$indexFile = __DIR__ . '/index.html';
if (file_exists($indexFile)) {
    header('Content-Type: text/html');
    readfile($indexFile);
    exit();
}

// Fallback: Fichier non trouvé
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found', 'path' => $path]);
exit();
?>
