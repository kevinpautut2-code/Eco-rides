<?php
/**
 * EcoRide - RidesController
 * Gère les requêtes API pour les trajets (création, recherche, annulation, détail)
 */

// Assurez-vous que le chemin est correct pour votre architecture
require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/models/Ride.php';
require_once dirname(__DIR__) . '/config/Database.php'; // Ajouté pour s'assurer que la connexion est chargée

// ====================================================================
// Fonction d'aide pour les réponses JSON
// ====================================================================

/**
 * Envoie une réponse JSON et termine l'exécution.
 */
function sendResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    // Le JS attend un objet de réponse qui contient une clé 'rides' pour l'affichage
    echo json_encode(['success' => $success, 'message' => $message, 'rides' => $data]);
    exit();
}

// ====================================================================
// INITIALISATION
// ====================================================================

$method = $_SERVER['REQUEST_METHOD'];
$userData = null;
$driverId = null;

// Middleware d'authentification pour POST et DELETE
if ($method !== 'GET') {
    try {
        $userData = AuthMiddleware::verifyToken(); 
        $driverId = $userData->id;
    } catch (Exception $e) {
        sendResponse(false, "Authentification requise pour cette action.", [], 401);
    }
}

$rideModel = new Ride();

// ====================================================================
// EXTRACTION DE L'ID (Pour les requêtes RESTful: GET /rides/5 ou DELETE /rides/5)
// ====================================================================
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pathParts = explode('/', $path);

// Trouver l'ID du trajet qui est généralement le dernier segment de l'URL
$rideId = null;
$lastSegment = end($pathParts);

// Vérifie si la dernière partie du chemin est un ID numérique valide
if (is_numeric($lastSegment) && $lastSegment > 0) {
    $rideId = (int)$lastSegment;
}

// ====================================================================
// GESTION DES REQUÊTES
// ====================================================================

switch ($method) {
    
    case 'GET':
        // --- 0. US 3/4: Cas de recherche (GET /rides?departure=...&arrival=... OU GET /rides) ---
        // Ce bloc gère la recherche avec critères et le chargement initial.
        // On vérifie si on a des critères de recherche OU si on est dans le cas général sans ID.
        if (isset($_GET['departure']) || isset($_GET['arrival']) || isset($_GET['date']) || !($rideId)) {
            
            $criteria = [
                'departure_city' => $_GET['departure'] ?? null,
                'arrival_city' => $_GET['arrival'] ?? null,
                'date' => $_GET['date'] ?? null,
                // Filtres US4
                'max_price' => $_GET['max_price'] ?? null,
                'min_rating' => $_GET['min_rating'] ?? null,
                'vehicle_type' => $_GET['vehicle_type'] ?? null,
            ];
            
            // Le script rides.js appelle l'API sans paramètres pour le chargement initial.
            // On peut s'assurer que la méthode search() renvoie tous les trajets si les critères sont nuls.
            try {
                // Nécessite une méthode search dans Ride.php qui renvoie tous les trajets si les critères sont vides/nuls.
                $results = $rideModel->search($criteria); 
                
                // Note : On utilise 'rides' comme clé de donnée pour correspondre au JS (response.rides)
                sendResponse(true, "Résultats de recherche récupérés.", $results); 
                
            } catch (Exception $e) {
                sendResponse(false, "Erreur lors de la recherche: " . $e->getMessage(), [], 500);
            }
        }

        // --- 1. US 5: Détails du trajet (GET /rides/{id}) ---
        if ($rideId) {
            try {
                // Nécessite une méthode findById dans Ride.php
                $ride = $rideModel->findById($rideId); 
                if ($ride) {
                    sendResponse(true, "Détails du trajet récupérés.", $ride);
                } else {
                    sendResponse(false, "Trajet non trouvé.", [], 404);
                }
            } catch (Exception $e) {
                sendResponse(false, "Erreur lors de la récupération des détails: " . $e->getMessage(), [], 500);
            }
        }

        // --- 2. US 8/10: Historique Chauffeur (GET /rides?action=driverRides) ---
        if (isset($_GET['action']) && $_GET['action'] === 'driverRides') {
            try {
                // Vérification du token si ce n'est pas déjà fait (sécurité)
                if (!$userData) {
                    $userData = AuthMiddleware::verifyToken(); 
                    $driverId = $userData->id;
                }
                
                $rides = $rideModel->findByDriverId($driverId); 
                sendResponse(true, "Trajets proposés récupérés.", $rides);
            } catch (Exception $e) {
                // Ceci gère les erreurs de findByDriverId
                sendResponse(false, "Erreur lors de la récupération des trajets: " . $e->getMessage(), [], 500);
            }
        }
        
        // Si la requête GET ne correspond à aucun format connu
        sendResponse(false, "Requête GET incomplète ou incorrecte.", [], 400);
        break;

    case 'POST':
        // --- US 9: Création de trajet ---
        $data = json_decode(file_get_contents("php://input"));
        
        // ... (Le reste du code POST reste inchangé) ...

        // Validation minimale
        if (empty($data->departure_city) || empty($data->arrival_city) || empty($data->departure_datetime) || empty($data->price_credits) || empty($data->seats_available) || empty($data->vehicle_id)) {
            sendResponse(false, "Données de trajet incomplètes. Champs requis : villes, date/heure, prix, places, ID véhicule.", [], 400);
        }

        // Hydratation du modèle
        $rideModel->driver_id = $driverId; // Assuré par le token
        $rideModel->vehicle_id = $data->vehicle_id;
        $rideModel->departure_city = $data->departure_city;
        $rideModel->departure_address = $data->departure_address ?? null;
        $rideModel->departure_datetime = $data->departure_datetime;
        $rideModel->arrival_city = $data->arrival_city;
        $rideModel->arrival_address = $data->arrival_address ?? null;
        $rideModel->arrival_datetime = $data->arrival_datetime ?? null;
        $rideModel->price_credits = $data->price_credits;
        $rideModel->seats_available = $data->seats_available;
        $rideModel->preferences = $data->preferences ?? null;

        try {
            if ($rideModel->create()) {
                sendResponse(true, "Trajet créé avec succès.", ['ride_id' => $rideModel->id], 201);
            } else {
                sendResponse(false, "Erreur lors de la création du trajet. Échec de la base de données.", [], 500);
            }
        } catch (Exception $e) {
             sendResponse(false, "Erreur imprévue: " . $e->getMessage(), [], 500);
        }

        break;
        
    case 'DELETE':
        // --- US 10: Annuler un trajet (DELETE /rides/{ride_id}) ---
        // ... (Le reste du code DELETE reste inchangé) ...
        if (!$rideId) {
            sendResponse(false, "ID de trajet manquant ou invalide pour l'annulation.", [], 400);
        }

        try {
            // Appel de la méthode d'annulation transactionnelle (implémentée dans Ride.php)
            if ($rideModel->cancel($rideId, $driverId)) {
                sendResponse(true, "Trajet annulé et tous les passagers ont été remboursés.");
            } else {
                sendResponse(false, "Annulation du trajet impossible (état inconnu).", [], 500);
            }
        } catch (Exception $e) {
            // Gérer les erreurs (trajet non trouvé, pas le chauffeur, etc.)
            $httpCode = str_contains($e->getMessage(), 'chauffeur') ? 403 : 404;
            sendResponse(false, $e->getMessage(), [], $httpCode);
        }
        break;

    default:
        sendResponse(false, "Méthode non autorisée.", [], 405);
        break;
}
?>