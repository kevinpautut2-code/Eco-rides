<?php
/**
 * EcoRide - RatingsController
 * Gère les requêtes API pour la soumission d'avis et de notation (US 12).
 */

require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/models/Rating.php';
// require_once 'sendResponse.php'; // Si vous centralisez la fonction

// NOTE: Assurez-vous que cette fonction est accessible (via un require ou en la collant ici)
function sendResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// ====================================================================
// INITIALISATION & AUTHENTIFICATION (Requise pour la soumission d'avis)
// ====================================================================

$method = $_SERVER['REQUEST_METHOD'];
$userData = null;
$authorUserId = null; // L'utilisateur qui soumet l'avis (le passager)

try {
    // Seul un utilisateur authentifié peut soumettre un avis
    $userData = AuthMiddleware::verifyToken(); 
    $authorUserId = $userData->id;
} catch (Exception $e) {
    sendResponse(false, "Authentification requise pour soumettre un avis.", [], 401);
}

$ratingModel = new Rating();

// ====================================================================
// GESTION DES REQUÊTES
// ====================================================================

switch ($method) {
    
    case 'POST':
        // --- POST /ratings : Soumettre un nouvel avis ---
        $data = json_decode(file_get_contents("php://input"));

        // Validation des champs reçus
        if (empty($data->ride_id) || empty($data->target_user_id) || !isset($data->rating) || $data->rating === null) {
            sendResponse(false, "ID du trajet, ID du chauffeur et note (rating) sont requis.", [], 400);
        }
        
        $rideId = (int)$data->ride_id;
        $targetUserId = (int)$data->target_user_id; // Le chauffeur
        $rating = (int)$data->rating;
        $comment = $data->comment ?? null;
        
        // Sécurité: le passager ne peut pas se noter lui-même
        if ($authorUserId === $targetUserId) {
            sendResponse(false, "Vous ne pouvez pas vous noter vous-même.", [], 403);
        }

        try {
            // Le modèle gère toutes les vérifications business (trajet terminé, passager sur le trajet, non noté, etc.)
            if ($ratingModel->create($rideId, $authorUserId, $targetUserId, $rating, $comment)) {
                sendResponse(true, "Avis soumis avec succès.");
            } else {
                sendResponse(false, "Erreur lors de l'enregistrement de l'avis.", [], 500);
            }
        } catch (Exception $e) {
             // Remonte les erreurs de validation du modèle (déjà noté, pas de passager, etc.)
             sendResponse(false, $e->getMessage(), [], 400); 
        }

        break;
        
    case 'GET':
        // NOTE: La lecture des notes est déjà gérée par RideController/Ride.php
        sendResponse(false, "Cette route ne supporte que la méthode POST pour la soumission d'avis.", [], 405);
        break;

    default:
        sendResponse(false, "Méthode non autorisée.", [], 405);
        break;
}