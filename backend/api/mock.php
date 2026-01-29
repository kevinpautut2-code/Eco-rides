<?php
/**
 * EcoRide - API Mock pour développement
 * Sert les données JSON en attendant la configuration complète de la base de données
 */

// Activer les headers CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pathParts = explode('/', $path);

// Récupérer le type de ressource (rides, users, etc)
$resource = $pathParts[count($pathParts) - 1] ?? '';

// Chemin vers les fichiers de données
$dataDir = __DIR__ . '/data/';

switch ($resource) {
    case 'rides':
        if (file_exists($dataDir . 'rides.json')) {
            $ridesData = json_decode(file_get_contents($dataDir . 'rides.json'), true);
            
            // Appliquer les filtres s'ils existent
            $filtered = $ridesData['rides'];
            
            if (isset($_GET['departure'])) {
                $departure = strtolower($_GET['departure']);
                $filtered = array_filter($filtered, function($ride) use ($departure) {
                    return stripos($ride['departure_city'], $departure) === 0;
                });
            }
            
            if (isset($_GET['arrival'])) {
                $arrival = strtolower($_GET['arrival']);
                $filtered = array_filter($filtered, function($ride) use ($arrival) {
                    return stripos($ride['arrival_city'], $arrival) === 0;
                });
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Trajets chargés avec succès',
                'rides' => array_values($filtered)
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Aucun trajet trouvé',
                'rides' => []
            ]);
        }
        break;
        
    case 'users':
        if (file_exists($dataDir . 'users.json')) {
            $usersData = json_decode(file_get_contents($dataDir . 'users.json'), true);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Utilisateurs chargés',
                'users' => $usersData['users']
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Aucun utilisateur trouvé'
            ]);
        }
        break;
        
    case 'auth':
        // Endpoint pour tester la connexion avec les données mock
        $email = $_GET['email'] ?? null;
        $usersData = json_decode(file_get_contents($dataDir . 'users.json'), true);
        
        $user = null;
        foreach ($usersData['users'] as $u) {
            if ($u['email'] === $email) {
                $user = $u;
                break;
            }
        }
        
        if ($user) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Utilisateur trouvé',
                'user' => [
                    'id' => $user['id'],
                    'pseudo' => $user['pseudo'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'credits' => $user['credits'],
                    'photo_url' => $user['photo_url']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint non valide'
        ]);
}
?>
