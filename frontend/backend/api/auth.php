<?php
/**
 * ========================================
 * WRAPPER API POUR AUTHENTIFICATION
 * ========================================
 * Fichier passerelle pour les requêtes d'authentification
 * 
 * Supporte les deux formats :
 * - POST /backend/api/auth?action=login
 * - POST /backend/api/auth avec JSON {"action":"login",...}
 * 
 * Gère : login, register, me (profil utilisateur)
 */

// ÉTAPE 1 : Lire les données JSON envoyées par le client
// On ne peut lire php://input qu'UNE SEULE FOIS, donc on la capture et la sauvegarde
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? null;

// ÉTAPE 2 : Sauvegarder le contenu du body pour que le vrai auth.php puisse le relire
// Cette technique contourne la limite de php://input (lecture unique)
$body = json_encode($input);
if (empty($GLOBALS['_input_cache'])) {
    $GLOBALS['_input_cache'] = $input;
}

// ÉTAPE 3 : Définir les chemins des répertoires
// L'API réelle se trouve dans /backend/api, pas dans /frontend/backend/api
define('API_ROOT_DIR', dirname(dirname(dirname(__DIR__))) . '/backend/api');
define('FRONTEND_DIR', dirname(dirname(dirname(__DIR__))) . '/frontend');

$method = $_SERVER['REQUEST_METHOD'];

// ÉTAPE 4 : Réécrire le REQUEST_URI pour que le vrai auth.php interprète correctement l'action
// Cela permet au routeur de savoir quelle action performer (login, register, me)
if ($action === 'login') {
    $_SERVER['REQUEST_URI'] = '/auth/login';
} elseif ($action === 'register') {
    $_SERVER['REQUEST_URI'] = '/auth/register';
} elseif ($action === 'me') {
    $_SERVER['REQUEST_URI'] = '/auth/me';
}

// ÉTAPE 5 : Remettre le body en place pour php://input
// On crée un stream mémoire avec le contenu JSON
$temp = fopen('php://memory', 'rw');
fwrite($temp, $body);
rewind($temp);

// NOTE : Injecter le stream dans php://input globalement n'est pas possible directement
// La solution est d'utiliser $GLOBALS['_input_cache'] dans le vrai auth.php
// OU d'inclure auth.php et intercepter sa lecture

// ÉTAPE 6 : Inclure le vrai fichier auth.php du répertoire backend
// C'est ici que les vraies opérations d'authentification se font
require API_ROOT_DIR . '/auth.php';
?>
