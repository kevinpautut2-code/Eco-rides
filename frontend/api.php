<?php
/**
 * ========================================
 * PASSERELLE API - ROUTEUR PRINCIPAL
 * ========================================
 * 
 * Ce fichier est le point d'entrée pour toutes les requêtes API
 * depuis le navigateur.
 * 
 * Il fait correspondre les URLs aux fichiers PHP corrects :
 * 
 * Requête navigateur:
 *   POST /backend/api/auth?action=login
 *   ↓
 * Ce fichier analyse l'URL
 *   ↓
 * Inclut : /backend/api/auth.php
 *   ↓
 * Répond avec JSON
 * 
 * Avantages :
 * - Point d'entrée unique pour l'API
 * - Centraliser les headers CORS
 * - Faciliter le routage
 * - Compatibilité avec le serveur PHP intégré
 */

// ========== CONFIGURATION CORS ==========
// Permettre les requêtes depuis n'importe quel domaine
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// ÉTAPE 1 : Gérer les requêtes OPTIONS (CORS preflight)
// Le navigateur envoie une requête OPTIONS avant les vraies requêtes cross-origin
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ÉTAPE 2 : Extraire le chemin de l'URL
// Exemple : /backend/api/auth/login → backend/api/auth/login
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($uri, '/');

// ÉTAPE 3 : Diviser le chemin en segments
// backend/api/auth/login → ['backend', 'api', 'auth', 'login']
$segments = array_filter(explode('/', $path));
$segments = array_values($segments); // Ré-indexer pour nettoyer les indices

// ÉTAPE 4 : Valider le format
// On s'attend à au moins 3 segments : backend / api / endpoint
if (count($segments) < 3 || $segments[0] !== 'backend' || $segments[1] !== 'api') {
    http_response_code(400);
    echo json_encode(['error' => 'Chemin API invalide']);
    exit();
}

// ÉTAPE 5 : Extraire l'endpoint et l'action
// endpoint = 'auth', 'bookings', 'users', etc.
// action = 'login', 'register', 'me', etc. (optionnel)
$endpoint = $segments[2];          // Toujours présent
$action = $segments[3] ?? null;   // Peut être absent

// ÉTAPE 6 : Construire le chemin du fichier PHP correspondant
// /backend/api/auth/login → /backend/api/auth.php
$apiFile = __DIR__ . '/../backend/api/' . $endpoint . '.php';

// ÉTAPE 7 : Vérifier que le fichier existe
if (!file_exists($apiFile)) {
    http_response_code(404);
    echo json_encode([
        'error' => 'Endpoint API non trouvé',
        'endpoint' => $endpoint,
        'path_tried' => $apiFile
    ]);
    exit();
}

// ÉTAPE 8 : Définir des constantes accessibles au fichier PHP incluré
// Permet au fichier auth.php ou bookings.php de savoir ce qu'il doit faire
define('API_ENDPOINT', $endpoint);   // 'auth', 'bookings', etc.
define('API_ACTION', $action);       // 'login', 'register', 'me', etc.
define('API_SEGMENTS', $segments);   // Tous les segments du chemin

// ÉTAPE 9 : Réécrire REQUEST_URI pour compatibilité
// Format attendu par les vrais fichiers API :
// /auth/login, /auth/register, /bookings, etc.
// (pas /backend/api/auth/login)
$_SERVER['REQUEST_URI'] = '/' . $endpoint . ($action ? '/' . $action : '');

require $apiFile;
?>
