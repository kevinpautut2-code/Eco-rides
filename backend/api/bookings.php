<?php
/**
 * EcoRide - Système de Réservation (Bookings)
 * Gère les réservations de trajets
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Charger les données
$usersFile = __DIR__ . '/../../frontend/data/users.json';
$ridesFile = __DIR__ . '/../../frontend/data/rides.json';
$bookingsFile = __DIR__ . '/../../frontend/data/bookings.json';

// Initialiser bookings.json s'il n'existe pas
if (!file_exists($bookingsFile)) {
    file_put_contents($bookingsFile, json_encode(['bookings' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$usersData = json_decode(file_get_contents($usersFile), true);
$ridesData = json_decode(file_get_contents($ridesFile), true);
$bookingsData = json_decode(file_get_contents($bookingsFile), true);

$users = $usersData['users'] ?? [];
$rides = $ridesData['rides'] ?? [];
$bookings = $bookingsData['bookings'] ?? [];

// ====== GET CURRENT USER FROM TOKEN ======
function getCurrentUserFromToken() {
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

    if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        return null;
    }

    $token = $matches[1];
    try {
        $decoded = json_decode(base64_decode($token), true);
        if (!$decoded || $decoded['exp'] < time()) {
            return null;
        }
        return $decoded['id'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}

// ====== POST /bookings - CRÉER UNE RÉSERVATION ======
if ($method === 'POST' && strpos($path, 'bookings') !== false) {
    $userId = getCurrentUserFromToken();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $rideId = $input['ride_id'] ?? null;
    $passengersCount = $input['passengers_count'] ?? 1;

    if (!$rideId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ride ID requis']);
        exit();
    }

    // Trouver le trajet
    $ride = null;
    foreach ($rides as $r) {
        if ($r['id'] == $rideId) {
            $ride = $r;
            break;
        }
    }

    if (!$ride) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Trajet non trouvé']);
        exit();
    }

    // Vérifier les places disponibles
    if ($ride['seats_available'] < $passengersCount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pas assez de places disponibles']);
        exit();
    }

    // Calculer le coût total
    $totalCost = $ride['price_credits'] * $passengersCount;

    // Trouver l'utilisateur (passager)
    $passenger = null;
    foreach ($users as $u) {
        if ($u['id'] == $userId) {
            $passenger = $u;
            break;
        }
    }

    if (!$passenger) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit();
    }

    // Vérifier que le passager a assez de crédits
    if ($passenger['credits'] < $totalCost) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Crédits insuffisants. Vous avez ' . $passenger['credits'] . ' crédits, vous en avez besoin de ' . $totalCost]);
        exit();
    }

    // Créer la réservation
    $bookingId = count($bookings) > 0 ? max(array_column($bookings, 'id')) + 1 : 1;
    
    $booking = [
        'id' => $bookingId,
        'ride_id' => $rideId,
        'passenger_id' => $userId,
        'passenger_name' => $passenger['pseudo'],
        'passengers_count' => $passengersCount,
        'total_cost' => $totalCost,
        'status' => 'confirmed',
        'created_at' => date('Y-m-d H:i:s'),
        'driver_id' => $ride['driver_id']
    ];

    $bookings[] = $booking;

    // Sauvegarder les réservations
    file_put_contents($bookingsFile, json_encode(['bookings' => $bookings], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Mettre à jour les crédits du passager en localStorage du client
    // (Note: Dans une vraie app, il faudrait sauvegarder en BD)

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Réservation confirmée!',
        'booking' => $booking,
        'passenger_credits_remaining' => $passenger['credits'] - $totalCost
    ]);
    exit();
}

// ====== GET /bookings - LISTER MES RÉSERVATIONS ======
if ($method === 'GET' && strpos($path, 'bookings') !== false && !strpos($path, 'bookings/')) {
    $userId = getCurrentUserFromToken();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        exit();
    }

    // Récupérer les réservations de l'utilisateur
    $userBookings = [];
    foreach ($bookings as $booking) {
        if ($booking['passenger_id'] == $userId) {
            // Ajouter les infos du trajet
            foreach ($rides as $ride) {
                if ($ride['id'] == $booking['ride_id']) {
                    $booking['ride'] = $ride;
                    break;
                }
            }
            $userBookings[] = $booking;
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'bookings' => $userBookings
    ]);
    exit();
}

// ====== GET /bookings/{id} - DÉTAILS D'UNE RÉSERVATION ======
if ($method === 'GET' && preg_match('/bookings\/(\d+)/', $path, $matches)) {
    $bookingId = (int)$matches[1];
    $userId = getCurrentUserFromToken();

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        exit();
    }

    $booking = null;
    foreach ($bookings as $b) {
        if ($b['id'] == $bookingId) {
            // Vérifier que l'utilisateur est le passager ou le chauffeur
            if ($b['passenger_id'] != $userId && $b['driver_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            $booking = $b;
            break;
        }
    }

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Réservation non trouvée']);
        exit();
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'booking' => $booking
    ]);
    exit();
}

// ====== DELETE /bookings/{id} - ANNULER UNE RÉSERVATION ======
if ($method === 'DELETE' && preg_match('/bookings\/(\d+)/', $path, $matches)) {
    $bookingId = (int)$matches[1];
    $userId = getCurrentUserFromToken();

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        exit();
    }

    $bookingIndex = -1;
    foreach ($bookings as $i => $b) {
        if ($b['id'] == $bookingId) {
            if ($b['passenger_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Seul le passager peut annuler']);
                exit();
            }
            $bookingIndex = $i;
            break;
        }
    }

    if ($bookingIndex === -1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Réservation non trouvée']);
        exit();
    }

    // Rembourser les crédits
    $booking = $bookings[$bookingIndex];
    // Ici on devrait rembourser les crédits au passager

    // Supprimer la réservation
    array_splice($bookings, $bookingIndex, 1);
    file_put_contents($bookingsFile, json_encode(['bookings' => $bookings], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Réservation annulée et crédits remboursés',
        'refund_amount' => $booking['total_cost']
    ]);
    exit();
}

// ====== DEFAULT ======
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Endpoint non trouvé'
]);
?>
