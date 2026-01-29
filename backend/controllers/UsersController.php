<?php
/**
 * EcoRide - UsersController
 * Gère les requêtes API pour la gestion du profil utilisateur (US 13).
 */

require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/models/User.php';

// ====================================================================
// Fonction d'aide pour les réponses JSON (Réutilisée)
// ====================================================================

function sendResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// ====================================================================
// INITIALISATION & AUTHENTIFICATION (Requise pour toutes les actions de profil)
// ====================================================================

$method = $_SERVER['REQUEST_METHOD'];
$userData = null;
$userId = null;

try {
    // Authentification requise pour TOUTES les actions
    $userData = AuthMiddleware::verifyToken(); 
    $userId = $userData->id;
} catch (Exception $e) {
    sendResponse(false, "Authentification requise pour accéder au profil.", [], 401);
}

$userModel = new User();

// ====================================================================
// EXTRACTION DE L'ID DE L'UTILISATEUR CIBLE (Pour la flexibilité, mais l'ID du token est prioritaire)
// ====================================================================
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pathParts = explode('/', $path);

$targetUserId = null;
$lastSegment = end($pathParts);

if (is_numeric($lastSegment) && $lastSegment > 0) {
    $targetUserId = (int)$lastSegment;
}

// Pour des raisons de sécurité, l'utilisateur ne peut modifier que son propre profil
if ($targetUserId && $targetUserId !== $userId) {
     // Permettre la lecture du profil d'un autre utilisateur (GET), mais bloquer la modification (PUT)
     if ($method === 'PUT' || $method === 'PATCH') {
         sendResponse(false, "Vous n'êtes pas autorisé à modifier ce profil.", [], 403);
     }
} else {
    // Si pas d'ID spécifié ou l'ID spécifié est le sien, on utilise l'ID du token.
    $targetUserId = $userId; 
}


// ====================================================================
// GESTION DES REQUÊTES
// ====================================================================

switch ($method) {
    
    case 'GET':
        // --- US 13: Afficher le profil de l'utilisateur (GET /users/{id}) ---
        try {
            $user = $userModel->findById($targetUserId);
            
            if ($user) {
                sendResponse(true, "Profil utilisateur récupéré.", ['user' => $user]);
            } else {
                sendResponse(false, "Profil utilisateur non trouvé.", [], 404);
            }
        } catch (Exception $e) {
            sendResponse(false, "Erreur lors de la récupération du profil: " . $e->getMessage(), [], 500);
        }
        break;

    case 'PUT':
    case 'PATCH':
        // --- US 13: Modifier le profil de l'utilisateur (PUT/PATCH /users/{id}) ---
        $data = json_decode(file_get_contents("php://input"));
        
        // L'authentification a déjà vérifié que $targetUserId est égal à $userId
        
        try {
            if ($userModel->updateProfile($userId, $data)) {
                sendResponse(true, "Profil mis à jour avec succès.");
            } else {
                sendResponse(false, "Échec de la mise à jour. Aucune donnée valide fournie ou erreur BDD.", [], 400);
            }
        } catch (Exception $e) {
             sendResponse(false, "Erreur imprévue: " . $e->getMessage(), [], 500);
        }

        break;
        
    case 'DELETE':
        // NOTE: La suppression de compte n'est pas une US explicite, mais l'endpoint est souvent là.
        // Si vous devez l'implémenter:
        // try { $userModel->delete($userId); sendResponse(true, "Compte supprimé."); } catch (...)

    default:
        sendResponse(false, "Méthode non autorisée.", [], 405);
        break;
}