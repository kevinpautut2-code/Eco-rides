# 📖 Documentation Détaillée - Fichiers PHP

## Fichier 1 : `/backend/api/auth.php` - Authentification

Ce fichier gère toutes les opérations d'authentification.

### 1. Structure Générale

```php
<?php
// ÉTAPE 1 : Configuration CORS
header('Access-Control-Allow-Origin: *');

// ÉTAPE 2 : Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit();

// ÉTAPE 3 : Charger les utilisateurs depuis users.json
$usersFile = __DIR__ . '/../../frontend/data/users.json';
$users = json_decode(file_get_contents($usersFile), true)['users'];

// ÉTAPE 4 : Déterminer l'action (login/register/me)
$action = strpos($path, 'login') !== false ? 'login' : ...;

// ÉTAPE 5 : Lire l'input JSON
$input = json_decode(file_get_contents('php://input'), true);

// ÉTAPE 6 : Exécuter l'action appropriée
if ($action === 'login') { ... }
elseif ($action === 'register') { ... }
elseif ($action === 'me') { ... }
?>
```

### 2. Opération LOGIN

**Requête :**
```
POST /backend/api/auth?action=login
Content-Type: application/json

{
    "email": "passager@ecoride.fr",
    "password": "Test@2025!"
}
```

**Processus :**
```php
// 1. Récupérer email et password de la requête
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

// 2. Valider qu'ils ne sont pas vides
if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

// 3. Chercher l'utilisateur avec cet email
$user = null;
foreach ($users as $u) {
    if ($u['email'] === $email && $u['password'] === $password) {
        $user = $u;
        break;
    }
}

// 4. Si trouvé, générer un token
if ($user) {
    $token = base64_encode(json_encode([
        'id' => $user['id'],
        'email' => $user['email'],
        'exp' => time() + (86400 * 7)  // Expire dans 7 jours
    ]));
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => $user
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
}
```

**Réponse :**
```json
{
    "success": true,
    "token": "eyJpZCI6MSwiZW1haWwiOiJwYXNzYWdlckBlY29yaWRlLmZyIiwiZXhwIjoxNzM4M...",
    "user": {
        "id": 1,
        "email": "passager@ecoride.fr",
        "first_name": "Passager",
        "credits": 100,
        "user_type": "passenger"
    }
}
```

### 3. Opération REGISTER

**Requête :**
```
POST /backend/api/auth?action=register
Content-Type: application/json

{
    "first_name": "Nouveau",
    "last_name": "Client",
    "email": "nouveau@test.com",
    "password": "SecurePass@123!",
    "user_type": "passenger"
}
```

**Processus :**
```php
// 1. Valider les données
if (!$first_name || !$last_name || !$email || !$password) {
    return error('Tous les champs requis');
}

// 2. Vérifier que l'email n'existe pas déjà
foreach ($users as $u) {
    if ($u['email'] === $email) {
        return error('Email déjà utilisé');
    }
}

// 3. Créer le nouvel utilisateur
$newUser = [
    'id' => count($users) + 1,
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'password' => $password,  // ⚠️ En production, utiliser password_hash()
    'credits' => 100,  // Crédits initiaux
    'user_type' => $user_type
];

// 4. Ajouter à la liste
$users[] = $newUser;

// 5. Sauvegarder dans users.json
$usersData['users'] = $users;
file_put_contents($usersFile, json_encode($usersData, JSON_PRETTY_PRINT));

// 6. Générer un token et retourner
$token = base64_encode(json_encode([...]));
echo json_encode(['success' => true, 'token' => $token, 'user' => $newUser]);
```

### 4. Opération ME (Récupérer l'utilisateur connecté)

**Requête :**
```
GET /backend/api/auth?action=me
Authorization: Bearer <token>
```

**Processus :**
```php
// 1. Récupérer le token du header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
// Format : "Bearer eyJpZC..."

// 2. Extraire le token (enlever "Bearer ")
preg_match('/Bearer\s+(\S+)/', $authHeader, $matches);
$token = $matches[1] ?? null;

// 3. Décoder le token
$decoded = json_decode(base64_decode($token), true);
$userId = $decoded['id'] ?? null;

// 4. Vérifier que le token n'a pas expiré
if ($decoded['exp'] < time()) {
    return error('Token expiré', 401);
}

// 5. Chercher l'utilisateur par ID
$user = array_filter($users, fn($u) => $u['id'] === $userId)[0] ?? null;

// 6. Retourner les infos
if ($user) {
    echo json_encode(['success' => true, 'user' => $user]);
} else {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
}
```

---

## Fichier 2 : `/backend/api/bookings.php` - Réservations

Ce fichier gère la création, la modification et l'annulation des réservations.

### 1. Opération CREATE (Créer une réservation)

**Requête :**
```
POST /backend/api/bookings
Authorization: Bearer <token>
Content-Type: application/json

{
    "ride_id": 5,
    "passengers_count": 2
}
```

**Processus :**
```php
// ÉTAPE 1 : Authentifier l'utilisateur
$token = extractTokenFromHeader();
$userId = validateToken($token);

if (!$userId) {
    return error('Non authentifié', 401);
}

// ÉTAPE 2 : Récupérer le trajet
$ride = getRideById($rideId);
if (!$ride) return error('Trajet non trouvé', 404);

// ÉTAPE 3 : Calculer le coût total
$totalCost = $ride['price_credits'] * $passengers_count;

// ÉTAPE 4 : Vérifier les crédits disponibles
$user = getUserById($userId);
if ($user['credits'] < $totalCost) {
    return error('Crédits insuffisants. Vous avez ' . $user['credits'] . ', vous en avez besoin de ' . $totalCost);
}

// ÉTAPE 5 : Vérifier la disponibilité des places
if ($ride['seats_available'] < $passengers_count) {
    return error('Pas assez de places disponibles');
}

// ÉTAPE 6 : Créer la réservation
$newBooking = [
    'id' => count($bookings) + 1,
    'ride_id' => $rideId,
    'passenger_id' => $userId,
    'passengers_count' => $passengers_count,
    'total_credits' => $totalCost,
    'status' => 'confirmed',
    'created_at' => date('Y-m-d H:i:s')
];
$bookings[] = $newBooking;

// ÉTAPE 7 : Déduire les crédits du passager
$user['credits'] -= $totalCost;
updateUser($user);

// ÉTAPE 8 : Réduire les places disponibles
$ride['seats_available'] -= $passengers_count;
updateRide($ride);

// ÉTAPE 9 : Ajouter des crédits au chauffeur
// (Le chauffeur reçoit un pourcentage des crédits)
$driverShare = ($totalCost * 0.8);  // 80% pour le chauffeur
$driver = getUserById($ride['driver_id']);
$driver['credits'] += $driverShare;
updateUser($driver);

// ÉTAPE 10 : Retourner la confirmation
echo json_encode([
    'success' => true,
    'booking' => $newBooking,
    'passenger_credits_remaining' => $user['credits'],
    'ride_seats_remaining' => $ride['seats_available']
]);
```

**Réponse :**
```json
{
    "success": true,
    "booking": {
        "id": 42,
        "ride_id": 5,
        "passenger_id": 1,
        "passengers_count": 2,
        "total_credits": 90,
        "status": "confirmed",
        "created_at": "2025-02-01 15:30:45"
    },
    "passenger_credits_remaining": 10,
    "ride_seats_remaining": 1
}
```

### 2. Opération GET (Récupérer mes réservations)

**Requête :**
```
GET /backend/api/bookings
Authorization: Bearer <token>
```

**Processus :**
```php
// 1. Authentifier
$userId = validateToken($token);

// 2. Récupérer toutes les réservations de cet utilisateur
$userBookings = array_filter($bookings, fn($b) => $b['passenger_id'] === $userId);

// 3. Ajouter les détails du trajet pour chaque réservation
foreach ($userBookings as &$booking) {
    $ride = getRideById($booking['ride_id']);
    $booking['ride'] = $ride;
}

// 4. Retourner
echo json_encode([
    'success' => true,
    'bookings' => array_values($userBookings),
    'count' => count($userBookings)
]);
```

### 3. Opération DELETE (Annuler une réservation)

**Requête :**
```
DELETE /backend/api/bookings/42
Authorization: Bearer <token>
```

**Processus :**
```php
// 1. Authentifier
$userId = validateToken($token);

// 2. Récupérer la réservation
$booking = getBookingById($bookingId);
if (!$booking) return error('Réservation non trouvée');

// 3. Vérifier qu'on annule sa propre réservation
if ($booking['passenger_id'] !== $userId) {
    return error('Vous ne pouvez annuler que vos propres réservations', 403);
}

// 4. Rembourser les crédits au passager
$user = getUserById($userId);
$user['credits'] += $booking['total_credits'];
updateUser($user);

// 5. Restituer les crédits au chauffeur (en partie)
$driver = getUserById($booking['driver_id']);
$driverRefund = ($booking['total_credits'] * 0.5);  // 50% remboursé
$driver['credits'] -= $driverRefund;
updateUser($driver);

// 6. Rendre les places disponibles
$ride = getRideById($booking['ride_id']);
$ride['seats_available'] += $booking['passengers_count'];
updateRide($ride);

// 7. Marquer comme annulée
$booking['status'] = 'cancelled';
updateBooking($booking);

echo json_encode([
    'success' => true,
    'message' => 'Réservation annulée',
    'refund' => $booking['total_credits']
]);
```

---

## Flux Complet : De la Réservation au Stockage

```
┌──────────────────┐
│ frontend/js/     │
│ booking-manager  │ ← Utilisateur clique "Réserver"
└────────┬─────────┘
         │ POST /backend/api/bookings
         │ {"ride_id": 5, "passengers_count": 2}
         ↓
┌──────────────────────────┐
│ /frontend/backend/api/   │
│ bookings.php (wrapper)   │ ← Reçoit la requête
└────────┬─────────────────┘
         │ require() ...
         ↓
┌──────────────────────────┐
│ /backend/api/            │
│ bookings.php (API réelle)│ ← Traite la requête
│                          │
│ 1. Valide token JWT      │
│ 2. Cherche le trajet     │
│ 3. Vérifie crédits       │
│ 4. Crée réservation      │
│ 5. Déduit crédits        │
│ 6. Sauvegarde JSON       │
│ 7. Envoie réponse        │
└────────┬─────────────────┘
         │ JSON {"success": true, ...}
         ↓
┌──────────────────┐
│ frontend/js/     │
│ booking-manager  │ ← Reçoit la réponse
│                  │
│ Met à jour :     │
│ - Affichage      │
│ - localStorage   │
│ - Barre user     │
└──────────────────┘
```

---

## Points de Données Modifiés

### Lors d'une réservation, ces fichiers JSON sont mises à jour :

**`bookings.json`**
```json
{
    "bookings": [
        // Nouvelle entrée créée
        {
            "id": 42,
            "ride_id": 5,
            "passenger_id": 1,
            "passengers_count": 2,
            "total_credits": 90,
            "status": "confirmed"
        }
    ]
}
```

**`users.json`** (Passager)
```json
{
    "id": 1,
    "email": "passager@ecoride.fr",
    "credits": 10  // ← Réduit de 100 à 10
}
```

**`users.json`** (Chauffeur)
```json
{
    "id": 2,
    "email": "driver@ecoride.fr",
    "credits": 122  // ← Augmenté de 72 crédits (80% de 90)
}
```

**`rides.json`** (Le trajet)
```json
{
    "id": 5,
    "seats_available": 1  // ← Réduit de 3 à 1
}
```

---

## ⚠️ Limitations Actuelles

1. **Mots de passe en clair** → À chiffrer en production
2. **Tokens sans signature cryptographique** → Utiliser une vraie librairie JWT
3. **Données en JSON** → Utiliser une vraie BDD (PostgreSQL/MySQL)
4. **Pas de validation côté serveur robuste** → Ajouter des validations strictes
5. **CORS permis pour tous** → Restreindre à votre domaine en production

---

## 🔧 Comment Déboguer

### Voir les logs PHP
```bash
php -S localhost:8000 -t frontend/ 2>&1 | tee server.log
```

### Voir les requêtes API (DevTools)
1. F12 → Network tab
2. Faire une action (login, réservation)
3. Regarder la requête/réponse

### Vérifier les données JSON
```bash
cat frontend/data/users.json | jq .
cat frontend/data/bookings.json | jq .
cat frontend/data/rides.json | jq .
```

---

Bonne compréhension ! 🚀
