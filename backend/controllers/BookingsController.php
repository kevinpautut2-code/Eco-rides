<?php
/**
 * EcoRide - BookingsController
 * Gère les requêtes API pour les réservations (création, annulation, historique).
 */

require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/models/Booking.php';

// ====================================================================
// Fonction d'aide pour les réponses JSON (à centraliser si possible)
// ====================================================================

function sendResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// ====================================================================
// INITIALISATION & AUTHENTIFICATION
// ====================================================================

$method = $_SERVER['REQUEST_METHOD'];
$userData = null;
$passengerId = null;

// L'authentification est requise pour TOUTES les opérations sur les réservations.
try {
    $userData = AuthMiddleware::verifyToken(); 
    $passengerId = $userData->id;
} catch (Exception $e) {
    // Si l'authentification échoue, AuthMiddleware envoie déjà la réponse 401.
    sendResponse(false, "Authentification requise.", [], 401);
}

$bookingModel = new Booking();

// ====================================================================
// GESTION DES REQUÊTES
// ====================================================================

switch ($method) {
    
    case 'GET':
        // --- US 10: Afficher l'historique des réservations du passager (GET /bookings) ---
        try {
            $bookings = $bookingModel->findByPassengerId($passengerId);
            sendResponse(true, "Réservations récupérées avec succès.", ['bookings' => $bookings]);
        } catch (Exception $e) {
            sendResponse(false, "Erreur lors de la récupération des réservations: " . $e->getMessage(), [], 500);
        }
        break;

    case 'POST':
        // --- US 6: Créer une nouvelle réservation (POST /bookings) ---
        $data = json_decode(file_get_contents("php://input"));

        // Validation minimale
        if (empty($data->ride_id) || empty($data->seats_booked) || $data->seats_booked <= 0) {
            sendResponse(false, "Données de réservation incomplètes. Trajet ID et nombre de places requis.", [], 400);
        }

        $rideId = (int)$data->ride_id;
        $seatsBooked = (int)$data->seats_booked;
        
        try {
            if ($bookingModel->create($rideId, $passengerId, $seatsBooked)) {
                sendResponse(true, "Réservation effectuée avec succès. Vos crédits ont été débités.", [], 201);
            } else {
                sendResponse(false, "Échec inconnu de la réservation.", [], 500);
            }
        } catch (Exception $e) {
             // Afficher l'erreur transactionnelle (crédits insuffisants, places non dispo, etc.)
             sendResponse(false, $e->getMessage(), [], 400); 
        }

        break;
        
    case 'DELETE':
        // --- US 10: Annuler une réservation (DELETE /bookings?id=5) ---
        // On récupère l'ID via le paramètre de requête (query string)
        $bookingId = (int)$_GET['id'] ?? null;

        if (!$bookingId) {
            sendResponse(false, "ID de réservation manquant ou invalide pour l'annulation.", [], 400);
        }

        try {
            if ($bookingModel->cancel($bookingId, $passengerId)) {
                sendResponse(true, "Réservation annulée avec succès. Le remboursement a été effectué en crédits.");
            } else {
                sendResponse(false, "Annulation impossible (état inconnu).", [], 500);
            }
        } catch (Exception $e) {
            sendResponse(false, $e->getMessage(), [], 400);
        }
        break;

    default:
        sendResponse(false, "Méthode non autorisée.", [], 405);
        break;
}