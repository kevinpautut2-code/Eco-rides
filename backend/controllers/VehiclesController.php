<?php
/**
 * EcoRide - VehiclesController
 * Gère les requêtes API pour la gestion des véhicules par le chauffeur (US 11).
 */

// On inclut le middleware d'authentification
require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';
// On inclut le modèle qui définit la classe Vehicle
require_once dirname(__DIR__) . '/models/Vehicle.php';
// require_once 'sendResponse.php'; // Si vous centralisez la fonction
// backend/controllers/VehiclesController.php


// NOTE: La fonction sendResponse doit être définie ou incluse ici
// (Nous avons fait l'hypothèse qu'elle est soit dans un utilitaire, soit copiée en tête des contrôleurs).
function sendResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}


$method = $_SERVER['REQUEST_METHOD'];
$userData = null;
$driverId = null;

try {
    $userData = AuthMiddleware::verifyToken(); 
    $driverId = $userData->id;
} catch (Exception $e) {
    sendResponse(false, "Authentification requise pour gérer les véhicules.", [], 401);
}

// L'instanciation de la classe Vehicle est correcte
$vehicleModel = new Vehicle(); 

// Extraction de l'ID du véhicule (GET, PUT, DELETE)
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pathParts = explode('/', $path);
$vehicleId = null;
$lastSegment = end($pathParts);

if (is_numeric($lastSegment) && $lastSegment > 0) {
    $vehicleId = (int)$lastSegment;
}

switch ($method) {
    
    case 'GET':
        // GET /vehicles/{id} : Récupérer un véhicule spécifique
        if ($vehicleId) {
            $vehicle = $vehicleModel->readSingle($vehicleId, $driverId);
            if ($vehicle) {
                sendResponse(true, "Véhicule récupéré.", ['vehicle' => $vehicle]);
            } else {
                sendResponse(false, "Véhicule non trouvé ou n'appartient pas à cet utilisateur.", [], 404);
            }
            break;
        }
        
        // GET /vehicles : Lister tous les véhicules du chauffeur
        $vehicles = $vehicleModel->readAllByDriver($driverId);
        sendResponse(true, "Liste des véhicules récupérée.", ['vehicles' => $vehicles]);
        break;

    case 'POST':
        // POST /vehicles : Ajouter un nouveau véhicule
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->brand) || empty($data->model) || empty($data->capacity) || empty($data->license_plate)) {
            sendResponse(false, "Marque, modèle, capacité et plaque d'immatriculation sont requis.", [], 400);
        }

        $vehicleModel->driver_id = $driverId; 
        $vehicleModel->brand = $data->brand;
        $vehicleModel->model = $data->model;
        $vehicleModel->license_plate = $data->license_plate;
        $vehicleModel->type = $data->type ?? 'Inconnu'; 
        $vehicleModel->color = $data->color ?? 'Blanc';
        $vehicleModel->capacity = (int)$data->capacity;
        
        try {
            if ($vehicleModel->create()) {
                sendResponse(true, "Véhicule ajouté avec succès.", ['vehicle_id' => $vehicleModel->id], 201);
            } else {
                sendResponse(false, "Erreur lors de l'ajout du véhicule.", [], 500);
            }
        } catch (Exception $e) {
             sendResponse(false, "Erreur imprévue: " . $e->getMessage(), [], 500);
        }
        break;
        
    case 'PUT':
    case 'PATCH':
        // PUT/PATCH /vehicles/{id} : Modifier un véhicule
        if (!$vehicleId) { sendResponse(false, "ID de véhicule manquant.", [], 400); }
        
        $data = json_decode(file_get_contents("php://input"));

        $vehicleModel->id = $vehicleId;
        $vehicleModel->driver_id = $driverId; 
        
        $vehicleModel->brand = $data->brand ?? null; 
        $vehicleModel->model = $data->model ?? null;
        $vehicleModel->license_plate = $data->license_plate ?? null;
        $vehicleModel->type = $data->type ?? null;
        $vehicleModel->color = $data->color ?? null;
        $vehicleModel->capacity = (int)($data->capacity ?? 0); 
        
        try {
            if ($vehicleModel->update()) {
                sendResponse(true, "Véhicule mis à jour avec succès.");
            } else {
                sendResponse(false, "La mise à jour du véhicule a échoué (ID incorrect ou pas le propriétaire).", [], 400);
            }
        } catch (Exception $e) {
             sendResponse(false, "Erreur: " . $e->getMessage(), [], 500);
        }
        break;

    case 'DELETE':
        // DELETE /vehicles/{id} : Supprimer un véhicule
        if (!$vehicleId) { sendResponse(false, "ID de véhicule manquant.", [], 400); }

        try {
            if ($vehicleModel->delete($vehicleId, $driverId)) {
                sendResponse(true, "Véhicule supprimé avec succès.");
            } else {
                sendResponse(false, "Suppression échouée. Vérifiez que l'ID existe et vous appartient.", [], 404);
            }
        } catch (Exception $e) {
            sendResponse(false, "Erreur lors de la suppression: " . $e->getMessage(), [], 500);
        }
        break;

    default:
        sendResponse(false, "Méthode non autorisée.", [], 405);
        break;
}