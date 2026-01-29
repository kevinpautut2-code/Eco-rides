<?php
/**
 * EcoRide - MessagesController
 * Gère les requêtes API pour la messagerie entre utilisateurs liés à un trajet (US 7).
 */

require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/models/Message.php';
require_once dirname(__DIR__) . '/models/Ride.php'; // Nécessaire pour vérifier le lien au trajet
require_once dirname(__DIR__) . '/config/Database.php'; // Nécessaire pour les vérifications de lien

// NOTE: Assurez-vous que cette fonction est accessible (via un require ou en la collant ici)
function sendResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// ====================================================================
// VÉRIFICATION DE LIEN AU TRAJET (LOGIQUE MÉTIER)
// ====================================================================

/**
 * Vérifie si deux utilisateurs (sender et receiver) sont liés par ce trajet (driver/passenger).
 * @param int $rideId
 * @param int $userId (sender)
 * @param int $targetId (receiver)
 * @return bool
 */
function isUserLinkedToRide($rideId, $userId, $targetId) {
    $conn = Database::getConnection();
    
    // Vérification de la relation Driver <-> Passenger
    $query = "
        SELECT 
            r.driver_id, b.passenger_id 
        FROM rides r
        LEFT JOIN bookings b ON b.ride_id = r.id AND b.booking_status = 'confirmed'
        WHERE 
            r.id = :ride_id AND
            (
                (r.driver_id = :user_id AND b.passenger_id = :target_id) OR
                (r.driver_id = :target_id AND b.passenger_id = :user_id)
            )
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':ride_id', $rideId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':target_id', $targetId);
    $stmt->execute();

    // Si on trouve une ligne où l'un est le chauffeur et l'autre un passager confirmé, c'est OK.
    if ($stmt->rowCount() > 0) {
        return true;
    }
    
    return false;
}

// ====================================================================
// INITIALISATION & AUTHENTIFICATION
// ====================================================================

$method = $_SERVER['REQUEST_METHOD'];
$userData = null;
$senderId = null; 

try {
    $userData = AuthMiddleware::verifyToken(); 
    $senderId = $userData->id;
} catch (Exception $e) {
    sendResponse(false, "Authentification requise pour la messagerie.", [], 401);
}

$messageModel = new Message();

// ====================================================================
// GESTION DES REQUÊTES
// ====================================================================

switch ($method) {
    
    case 'POST':
        // --- POST /messages : Envoyer un message ---
        $data = json_decode(file_get_contents("php://input"));

        // Validation des champs reçus
        if (empty($data->ride_id) || empty($data->receiver_id) || empty($data->content)) {
            sendResponse(false, "Trajet ID, destinataire et contenu du message sont requis.", [], 400);
        }
        
        $rideId = (int)$data->ride_id;
        $receiverId = (int)$data->receiver_id;
        $content = trim($data->content);
        
        // 1. Sécurité: ne pas s'envoyer de message à soi-même
        if ($senderId === $receiverId) {
            sendResponse(false, "Vous ne pouvez pas vous envoyer un message à vous-même.", [], 400);
        }

        // 2. Vérification de la relation (Driver <-> Passenger)
        if (!isUserLinkedToRide($rideId, $senderId, $receiverId)) {
            sendResponse(false, "Vous ne pouvez envoyer un message qu'au chauffeur ou à un passager confirmé de ce trajet.", [], 403);
        }

        try {
            if ($messageModel->create($rideId, $senderId, $receiverId, $content)) {
                sendResponse(true, "Message envoyé avec succès.", [], 201);
            } else {
                sendResponse(false, "Erreur lors de l'enregistrement du message.", [], 500);
            }
        } catch (Exception $e) {
             sendResponse(false, "Erreur: " . $e->getMessage(), [], 500); 
        }

        break;
        
    case 'GET':
        // --- GET /messages?ride_id={id} : Récupérer la conversation ---
        $rideId = (int)$_GET['ride_id'] ?? null;

        if (!$rideId) {
            // Optionnel : GET /messages sans ID pourrait retourner la liste des conversations (voir Message.php)
            try {
                 $conversations = $messageModel->getConversationList($senderId);
                 sendResponse(true, "Liste des conversations récupérée.", ['conversations' => $conversations]);
            } catch (Exception $e) {
                 sendResponse(false, "Erreur lors de la récupération de la liste de conversations: " . $e->getMessage(), [], 500);
            }
            break;
        }
        
        try {
            // Récupérer la conversation (la vérification de lien est implicite dans le modèle)
            $conversation = $messageModel->getConversation($rideId, $senderId);

            if (empty($conversation)) {
                 // On peut retourner un succès même si la conversation est vide
                sendResponse(true, "Conversation vide. Vous êtes le premier à envoyer un message.", ['messages' => []]);
            } else {
                sendResponse(true, "Conversation récupérée.", ['messages' => $conversation]);
            }
        } catch (Exception $e) {
            sendResponse(false, "Erreur lors de la récupération de la conversation: " . $e->getMessage(), [], 500);
        }
        break;

    default:
        sendResponse(false, "Méthode non autorisée.", [], 405);
        break;
}