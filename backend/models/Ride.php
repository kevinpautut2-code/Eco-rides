<?php
/**
 * EcoRide - Modèle Ride
 * Gère les trajets proposés (Rides), la création, la recherche, l'annulation et le détail.
 */

require_once __DIR__ . '/../config/Database.php';

class Ride {
    private $conn;
    private $table = 'rides';

    // Propriétés de la table rides
    public $id;
    public $driver_id;
    public $vehicle_id;
    public $departure_city;
    public $departure_address;
    public $departure_datetime;
    public $arrival_city;
    public $arrival_address;
    public $arrival_datetime;
    public $price_credits;
    public $seats_available;
    public $status = 'published'; 
    public $preferences; 

    public function __construct() {
        $this->conn = Database::getConnection(); 
    }

    // ====================================================================
    // US 9 : CRÉATION DE TRAJET
    // ====================================================================

    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  SET
                    driver_id = :driver_id,
                    vehicle_id = :vehicle_id,
                    departure_city = :departure_city,
                    departure_address = :departure_address,
                    departure_datetime = :departure_datetime,
                    arrival_city = :arrival_city,
                    arrival_address = :arrival_address,
                    arrival_datetime = :arrival_datetime,
                    price_credits = :price_credits,
                    seats_available = :seats_available,
                    status = :status,
                    preferences = :preferences";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':driver_id', $this->driver_id);
        $stmt->bindParam(':vehicle_id', $this->vehicle_id);
        $stmt->bindParam(':departure_city', $this->departure_city);
        $stmt->bindParam(':departure_address', $this->departure_address);
        $stmt->bindParam(':departure_datetime', $this->departure_datetime);
        $stmt->bindParam(':arrival_city', $this->arrival_city);
        $stmt->bindParam(':arrival_address', $this->arrival_address);
        $stmt->bindParam(':arrival_datetime', $this->arrival_datetime);
        $stmt->bindParam(':price_credits', $this->price_credits);
        $stmt->bindParam(':seats_available', $this->seats_available);
        $stmt->bindParam(':status', $this->status);
        $preferencesJson = is_array($this->preferences) ? json_encode($this->preferences) : $this->preferences;
        $stmt->bindParam(':preferences', $preferencesJson);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }
    
    // ====================================================================
    // US 8/10 : HISTORIQUE CHAUFFEUR
    // ====================================================================
    
    public function findByDriverId($driverId) {
        $query = "SELECT
                    r.id,
                    r.departure_city,
                    r.arrival_city,
                    r.departure_datetime,
                    r.arrival_datetime,
                    r.seats_available,
                    r.price_credits,
                    r.status,
                    v.brand,
                    v.model,
                    (SELECT COUNT(*) FROM bookings b WHERE b.ride_id = r.id AND b.status = 'confirmed') as seats_booked_count
                FROM " . $this->table . " r
                INNER JOIN vehicles v ON v.id = r.vehicle_id
                WHERE r.driver_id = :driver_id
                ORDER BY r.departure_datetime DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':driver_id', $driverId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ====================================================================
    // US 3/4 : RECHERCHE DE TRAJETS (NOUVELLE MÉTHODE)
    // ====================================================================

    /**
     * Recherche des trajets disponibles selon les critères (US3, US4).
     * @param array $criteria
     * @return array
     */
    public function search($criteria) {
        $query = "SELECT
                    r.id,
                    r.departure_city,
                    r.arrival_city,
                    r.departure_datetime,
                    r.price_credits,
                    r.seats_available,
                    u.pseudo AS driver_pseudo,
                    u.avatar_url AS driver_avatar,
                    (SELECT AVG(rating) FROM ratings WHERE target_user_id = r.driver_id) AS average_rating,
                    v.type AS vehicle_type
                FROM " . $this->table . " r
                INNER JOIN users u ON u.id = r.driver_id
                INNER JOIN vehicles v ON v.id = r.vehicle_id
                WHERE r.status = 'published'
                AND r.seats_available > 0
                AND r.departure_datetime >= CURRENT_TIMESTAMP()"; // Ne montre que les trajets futurs
        
        $bindParams = [];

        // Filtre principal (US3)
        if (!empty($criteria['departure_city'])) {
            $query .= " AND r.departure_city LIKE :departure_city";
            $bindParams[':departure_city'] = '%' . $criteria['departure_city'] . '%';
        }
        if (!empty($criteria['arrival_city'])) {
            $query .= " AND r.arrival_city LIKE :arrival_city";
            $bindParams[':arrival_city'] = '%' . $criteria['arrival_city'] . '%';
        }
        if (!empty($criteria['date'])) {
             // Chercher les trajets à n'importe quelle heure de cette journée
            $query .= " AND DATE(r.departure_datetime) = :date";
            $bindParams[':date'] = $criteria['date'];
        }

        // Filtres avancés (US4)
        if (!empty($criteria['max_price'])) {
            $query .= " AND r.price_credits <= :max_price";
            $bindParams[':max_price'] = $criteria['max_price'];
        }
        if (!empty($criteria['min_rating'])) {
            // Utiliser HAVING pour filtrer sur la moyenne d'évaluation
            // Pour la simplicité, on utilise un filtre WHERE approximatif ici, mais HAVING serait plus précis
            // Nous allons ajouter le filtre à la fin de la requête pour inclure le calcul de la moyenne.
        }
        
        $query .= " ORDER BY r.departure_datetime ASC";

        $stmt = $this->conn->prepare($query);

        // Binding des paramètres
        foreach ($bindParams as $key => &$value) {
            $stmt->bindParam($key, $value);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Post-filtre (si nécessaire pour la moyenne)
        if (!empty($criteria['min_rating']) && $criteria['min_rating'] > 0) {
            $minRating = $criteria['min_rating'];
            $results = array_filter($results, function($ride) use ($minRating) {
                // Ne garder que les trajets dont le chauffeur a une note suffisante, ou pas de note
                return $ride['average_rating'] === null || $ride['average_rating'] >= $minRating;
            });
        }
        
        return array_values($results); // Réindexer le tableau après filtrage
    }


    // ====================================================================
    // US 5 : DÉTAIL DU TRAJET (NOUVELLE MÉTHODE)
    // ====================================================================

    /**
     * Récupère les détails complets d'un trajet, y compris les informations chauffeur/véhicule et avis.
     * @param int $rideId
     * @return array|null
     */
    public function findById($rideId) {
        $query = "SELECT
                    r.*,
                    u.pseudo AS driver_pseudo,
                    u.first_name AS driver_first_name,
                    u.avatar_url AS driver_avatar,
                    u.bio AS driver_bio,
                    (SELECT AVG(rating) FROM ratings WHERE target_user_id = r.driver_id) AS average_rating,
                    v.brand AS vehicle_brand,
                    v.model AS vehicle_model,
                    v.license_plate AS vehicle_license,
                    v.type AS vehicle_type,
                    v.color AS vehicle_color,
                    v.capacity AS vehicle_capacity
                FROM " . $this->table . " r
                INNER JOIN users u ON u.id = r.driver_id
                INNER JOIN vehicles v ON v.id = r.vehicle_id
                WHERE r.id = :ride_id AND r.status = 'published'
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ride_id', $rideId);
        $stmt->execute();

        $rideData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rideData) {
            return null;
        }

        // Récupérer les avis du conducteur (US5)
        $ratingsQuery = "SELECT 
                            rt.rating, 
                            rt.comment, 
                            rt.created_at,
                            u.pseudo AS author_pseudo
                         FROM ratings rt
                         INNER JOIN users u ON u.id = rt.author_user_id
                         WHERE rt.target_user_id = :driver_id
                         ORDER BY rt.created_at DESC
                         LIMIT 5"; // Limite les 5 derniers avis

        $stmtRatings = $this->conn->prepare($ratingsQuery);
        $stmtRatings->bindParam(':driver_id', $rideData['driver_id']);
        $stmtRatings->execute();
        $ratings = $stmtRatings->fetchAll(PDO::FETCH_ASSOC);

        $rideData['driver_ratings'] = $ratings;
        $rideData['preferences'] = json_decode($rideData['preferences'], true); // Décodage JSON
        
        return $rideData;
    }


    // ====================================================================
    // US 10 : ANNULATION CHAUFFEUR (TRANSACTIONNELLE)
    // ====================================================================

    public function cancel($rideId, $driverId) {
        $this->conn->beginTransaction();

        try {
            // 1. Vérification
            $checkQuery = "SELECT id FROM rides 
                           WHERE id = :ride_id AND driver_id = :driver_id AND status = 'published' 
                           LIMIT 1";
            $stmtCheck = $this->conn->prepare($checkQuery);
            $stmtCheck->bindParam(':ride_id', $rideId);
            $stmtCheck->bindParam(':driver_id', $driverId);
            $stmtCheck->execute();

            if ($stmtCheck->rowCount() === 0) {
                throw new Exception("Trajet non trouvé, déjà annulé, ou vous n'êtes pas le chauffeur.");
            }

            // 2. Récupérer les réservations confirmées
            $bookingsQuery = "SELECT id, passenger_id, price_paid 
                              FROM bookings 
                              WHERE ride_id = :ride_id AND status = 'confirmed'";
            $stmtBookings = $this->conn->prepare($bookingsQuery);
            $stmtBookings->bindParam(':ride_id', $rideId);
            $stmtBookings->execute();
            $confirmedBookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

            // 3. Boucle de remboursement
            foreach ($confirmedBookings as $booking) {
                // a. Rembourser les crédits
                $refundQuery = "UPDATE users SET credits = credits + :amount WHERE id = :passenger_id";
                $stmtRefund = $this->conn->prepare($refundQuery);
                $stmtRefund->bindParam(':amount', $booking['price_paid']);
                $stmtRefund->bindParam(':passenger_id', $booking['passenger_id']);
                
                if (!$stmtRefund->execute()) {
                    throw new Exception("Erreur lors du remboursement du passager ID: " . $booking['passenger_id']);
                }

                // b. Enregistrer la transaction de remboursement
                $transactionQuery = "INSERT INTO credit_transactions
                                     (user_id, amount, type, reference_type, reference_id, balance_after, description)
                                     SELECT :user_id, :amount, 'refund', 'booking', :booking_id,
                                            (SELECT credits FROM users WHERE id = :user_id),
                                            'Remboursement suite annulation trajet ID: ' || :ride_id";

                $stmtTransaction = $this->conn->prepare($transactionQuery);
                $stmtTransaction->bindParam(':user_id', $booking['passenger_id']);
                $stmtTransaction->bindParam(':amount', $booking['price_paid']);
                $stmtTransaction->bindParam(':booking_id', $booking['id']);
                $stmtTransaction->bindParam(':ride_id', $rideId);
                $stmtTransaction->execute();

                // c. Mettre à jour le statut de la réservation
                $updateBookingQuery = "UPDATE bookings SET status = 'cancelled' WHERE id = :booking_id";
                $stmtUpdateBooking = $this->conn->prepare($updateBookingQuery);
                $stmtUpdateBooking->bindParam(':booking_id', $booking['id']);
                $stmtUpdateBooking->execute();
            }

            // 4. Mettre à jour le statut du trajet
            $rideUpdateQuery = "UPDATE rides SET status = 'cancelled' WHERE id = :ride_id";
            $stmtRideUpdate = $this->conn->prepare($rideUpdateQuery);
            $stmtRideUpdate->bindParam(':ride_id', $rideId);
            
            if (!$stmtRideUpdate->execute()) {
                 throw new Exception("Erreur lors de la mise à jour du statut du trajet.");
            }

            // 5. Commit la transaction
            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            // 6. Rollback en cas d'erreur
            $this->conn->rollBack();
            error_log("Ride cancellation error (ID: $rideId): " . $e->getMessage());
            throw new Exception("Annulation échouée: " . $e->getMessage());
        }
    }
}