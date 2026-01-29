<?php
/**
 * ========================================
 * API AUTHENTIFICATION - EcoRide
 * ========================================
 * Gère tous les opérations d'authentification :
 * - POST /auth/login  → Connecter un utilisateur
 * - POST /auth/register → Créer un nouveau compte
 * - GET  /auth/me  → Récupérer les infos de l'utilisateur connecté
 * 
 * Les données utilisateur sont stockées en JSON (frontend/data/users.json)
 * Les tokens sont des JWT simples encodés en base64
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

$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// ÉTAPE 2 : Charger les utilisateurs depuis le fichier JSON
// Le fichier JSON sert de "base de données" pour cette démo
$usersFile = __DIR__ . '/../../frontend/data/users.json';

if (!file_exists($usersFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fichier utilisateurs non trouvé'
    ]);
    exit();
}

$usersData = json_decode(file_get_contents($usersFile), true);
$users = $usersData['users'] ?? [];

// ÉTAPE 3 : Déterminer l'action à performer
// L'action peut venir soit d'une constante (API_ACTION) soit du chemin de l'URL
$action = (defined('API_ACTION') && API_ACTION) ? API_ACTION : (
    strpos($path, 'login') !== false ? 'login' :
    (strpos($path, 'register') !== false ? 'register' :
    (strpos($path, 'me') !== false ? 'me' : null))
);

// ÉTAPE 4 : Lire l'input JSON une seule fois
// Important : php://input ne peut être lu qu'une fois, d'où la nécessité de la cache
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ========== ACTION LOGIN ==========
/**
 * Connexion utilisateur
 * Vérifie les identifiants et génère un token JWT
 */
if ($method === 'POST' && $action === 'login') {
    // Récupérer les données depuis JSON ou POST
    $email = $input['email'] ?? $_POST['email'] ?? null;
    $password = $input['password'] ?? $_POST['password'] ?? null;

    // Vérifier que les données requises sont présentes
    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email et mot de passe requis'
        ]);
        exit();
    }

    // ÉTAPE 1 : Chercher l'utilisateur avec cet email
    $user = null;
    foreach ($users as $u) {
        if ($u['email'] === $email) {
            // ÉTAPE 2 : Vérifier que le mot de passe correspond
            if ($u['password'] === $password) {
                $user = $u;
            }
            break;
        }
    }

    // ÉTAPE 3 : Si l'utilisateur existe et le mot de passe est correct
    if ($user) {
        // ÉTAPE 4 : Générer un token JWT (simplifié)
        // Un vrai JWT aurait une signature cryptographique
        $token = base64_encode(json_encode([
            'id' => $user['id'],
            'email' => $user['email'],
            'exp' => time() + (86400 * 7) // Le token expire dans 7 jours
        ]));

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'pseudo' => $user['pseudo'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'user',
                'user_type' => $user['user_type'] ?? 'both',
                'credits' => $user['credits'] ?? 0,
                'photo_url' => $user['photo_url'] ?? null
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Email ou mot de passe incorrect'
        ]);
    }
    exit();
}

// ====== REGISTER ======
if ($method === 'POST' && $action === 'register') {
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    $pseudo = $input['pseudo'] ?? null;

    if (!$email || !$password || !$pseudo) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email, pseudo et mot de passe requis'
        ]);
        exit();
    }

    // Vérifier que l'email/pseudo n'existe pas
    foreach ($users as $u) {
        if ($u['email'] === $email || $u['pseudo'] === $pseudo) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Email ou pseudo déjà utilisé'
            ]);
            exit();
        }
    }

    // Créer nouvel utilisateur
    $newUser = [
        'id' => max(array_column($users, 'id')) + 1,
        'pseudo' => $pseudo,
        'email' => $email,
        'password' => $password,
        'role' => 'user',
        'user_type' => $input['user_type'] ?? 'both',
        'credits' => 20,
        'photo_url' => 'https://i.pravatar.cc/150?img=' . rand(1, 70)
    ];

    // Ajouter à la liste
    $users[] = $newUser;

    // Sauvegarder le fichier JSON (optionnel - si on veut persister)
    // file_put_contents($usersFile, json_encode(['users' => $users], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Générer token
    $token = base64_encode(json_encode([
        'id' => $newUser['id'],
        'email' => $newUser['email'],
        'exp' => time() + (86400 * 7)
    ]));

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Inscription réussie',
        'token' => $token,
        'user' => [
            'id' => $newUser['id'],
            'pseudo' => $newUser['pseudo'],
            'email' => $newUser['email'],
            'role' => $newUser['role'],
            'user_type' => $newUser['user_type'],
            'credits' => $newUser['credits'],
            'photo_url' => $newUser['photo_url']
        ]
    ]);
    exit();
}

// ====== GET CURRENT USER ======
if ($method === 'GET' && strpos($path, 'auth/me') !== false) {
    // Obtenir le header Authorization de manière compatible
    $authHeader = '';
    
    if (!function_exists('getallheaders')) {
        // Utiliser $_SERVER si getallheaders() n'existe pas
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
    } else {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
    }

    if (!$authHeader || !preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token manquant'
        ]);
        exit();
    }

    $token = $matches[1];

    try {
        $decoded = json_decode(base64_decode($token), true);

        if (!$decoded || $decoded['exp'] < time()) {
            throw new Exception('Token expiré');
        }

        // Trouver l'utilisateur
        $user = null;
        foreach ($users as $u) {
            if ($u['id'] === $decoded['id']) {
                $user = $u;
                break;
            }
        }

        if (!$user) {
            throw new Exception('Utilisateur non trouvé');
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'pseudo' => $user['pseudo'],
                'email' => $user['email'],
                'role' => $user['role'],
                'user_type' => $user['user_type'],
                'credits' => $user['credits'],
                'photo_url' => $user['photo_url']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// ====== DEFAULT ======
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Endpoint non trouvé'
]);
?>
