<?php
/**
 * Routeur API pour les endpoints
 * Gère les requêtes vers /backend/api/*
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = trim($path, '/');

// Le chemin devrait être something like "backend/api/bookings"
// On cherche le dernier segment qui est le nom de l'endpoint

$segments = explode('/', $path);
$endpoint = end($segments); // Prend le dernier segment

// Charger l'endpoint PHP correspondant du répertoire parent
$file = __DIR__ . '/' . $endpoint . '.php';

if ($endpoint && file_exists($file)) {
    require $file;
    exit();
}

// Fallback: endpoint non trouvé
http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => 'Endpoint not found',
    'requested' => $path,
    'endpoint' => $endpoint,
    'file_checked' => $file
]);
?>
