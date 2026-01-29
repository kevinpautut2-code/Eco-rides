<?php
/**
 * EcoRide - Modèle Booking
 * Gère les réservations de trajets (création, affichage, annulation).
 */

require_once __DIR__ . '/../config/Database.php';

class Booking {
    private $conn;
    private $table = 'bookings';

    public function __construct() {
        $this->conn = Database::getConnection(); 
    }

    // ====================================================================
    // US 6 : CRÉATION DE RÉSERVATION (TRANSACTIONNELLE)
    // ====================================================================

    /**
     * Crée une nouvelle réservation en gérant la transaction : débit crédits, mise à jour places.
     * * @param int $rideId
     * @param int $passengerId
     * @param int $seatsBooked
     * @return bool
     * @throws Exception
     */
    public function create($rideId, $passengerId, $seatsBooked) {
        $this->conn->beginTransaction();

        try {
            // --- 1. VÉRIFICATIONS INITIALES ---
            
            // Récupérer les infos du trajet et verrouiller les lignes (FOR UPDATE)
            $rideQuery = "SELECT driver_id, price_credits, seats_available FROM rides WHERE id = :ride_id AND status = 'published' FOR UPDATE";
            $stmtRide = $this->conn->prepare($rideQuery);
            $stmtRide->bindParam(':ride_id', $rideId);
            $stmtRide->execute();
            $ride = $stmtRide->fetch(PDO::FETCH_ASSOC);

            if (!$ride) {
                throw new Exception("Trajet non trouvé ou non disponible.");
            }
            if ($ride['seats_available'] < $seatsBooked) {
                throw new Exception("Nombre de places insuffisant pour ce trajet. Disponible: " . $ride['seats_available']);
            }
            
            $totalCost = $ride['price_credits'] * $seatsBooked;

            // Récupérer les crédits de l'utilisateur et verrouiller
            $userQuery = "SELECT credits FROM users WHERE id = :passenger_id FOR UPDATE";
            $stmtUser = $this->conn->prepare($userQuery);
            $stmtUser->bindParam(':passenger_id', $passengerId);
            $stmtUser->execute();
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $user['credits'] < $totalCost) {
                throw new Exception("Crédits insuffisants. Coût: $totalCost, Solde: " . ($user['credits'] ?? 0));
            }
            
            // --- 2. DÉBIT ET MISE À JOUR ---
            
            // a. Débiter les crédits de l'utilisateur
            $debitQuery = "UPDATE users SET credits = credits - :amount WHERE id = :passenger_id";
            $stmtDebit = $this->conn->prepare($debitQuery);
            $stmtDebit->bindParam(':amount', $totalCost);
            $stmtDebit->bindParam(':passenger_id', $passengerId);
            $stmtDebit->execute();
            
            // b. Enregistrer la transaction de débit
            $transactionQuery = "INSERT INTO credit_transactions
                                 (user_id, amount, type, reference_type, reference_id, balance_after, description)
                                 VALUES (:user_id, :amount, 'debit', 'booking', NULL, 
                                 (SELECT credits FROM users WHERE id = :user_id_ref), 
                                 'Débit pour réservation trajet ID: ' || :ride_id)";

            $stmtTransaction = $this->conn->prepare($transactionQuery);
            $stmtTransaction->bindParam(':user_id', $passengerId);
            $stmtTransaction->bindParam(':amount', $totalCost);
            $stmtTransaction->bindParam(':user_id_ref', $passengerId);
            $stmtTransaction->bindParam(':ride_id', $rideId);
            $stmtTransaction->execute();
            $transactionId = $this->conn->lastInsertId();

            // c. Mettre à jour les places disponibles du trajet
            $updateRideQuery = "UPDATE rides SET seats_available = seats_available - :seats_booked WHERE id = :ride_id";
            $stmtUpdateRide = $this->conn->prepare($updateRideQuery);
            $stmtUpdateRide->bindParam(':seats_booked', $seatsBooked);
            $stmtUpdateRide->bindParam(':ride_id', $rideId);
            $stmtUpdateRide->execute();

            // --- 3. CRÉER LA RÉSERVATION ---
            
            $bookingInsertQuery = "INSERT INTO bookings 
                                   (ride_id, passenger_id, seats_booked, price_paid, booking_status, transaction_id) 
                                   VALUES (:ride_id, :passenger_id, :seats_booked, :price_paid, 'confirmed', :transaction_id)";
            
            $stmtBooking = $this->conn->prepare($bookingInsertQuery);
            $stmtBooking->bindParam(':ride_id', $rideId);
            $stmtBooking->bindParam(':passenger_id', $passengerId);
            $stmtBooking->bindParam(':seats_booked', $seatsBooked);
            $stmtBooking->bindParam(':price_paid', $totalCost);
            $stmtBooking->bindParam(':transaction_id', $transactionId);
            
            if (!$stmtBooking->execute()) {
                throw new Exception("Erreur lors de l'enregistrement de la réservation.");
            }
            
            $bookingId = $this->conn->lastInsertId();
            
            // Mettre à jour la transaction avec l'ID de la réservation
            $updateTransactionRef = "UPDATE credit_transactions SET reference_id = :booking_id WHERE id = :transaction_id";
            $stmtUpdateTrans = $this->conn->prepare($updateTransactionRef);
            $stmtUpdateTrans->bindParam(':booking_id', $bookingId);
            $stmtUpdateTrans->bindParam(':transaction_id', $transactionId);
            $stmtUpdateTrans->execute();

            // 4. Commit la transaction
            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            // Rollback en cas d'erreur
            $this->conn->rollBack();
            error_log("Booking creation error: " . $e->getMessage());
            throw new Exception("Échec de la réservation : " . $e->getMessage());
        }
    }

    // ====================================================================
    // US 10 : AFFICHAGE DASHBOARD PASSAGER
    // ====================================================================

    /**
     * Récupère toutes les réservations d'un passager.
     * @param int $passengerId
     * @return array
     */
    public function findByPassengerId($passengerId) {
        $query = "SELECT
                    b.id AS booking_id,
                    b.seats_booked,
                    b.price_paid,
                    b.booking_status,
                    r.id AS ride_id,
                    r.departure_city,
                    r.arrival_city,
                    r.departure_datetime,
                    r.price_credits AS ride_price_per_seat,
                    u.pseudo AS driver_pseudo,
                    u.avatar_url AS driver_avatar
                FROM " . $this->table . " b
                INNER JOIN rides r ON r.id = b.ride_id
                INNER JOIN users u ON u.id = r.driver_id
                WHERE b.passenger_id = :passenger_id
                ORDER BY r.departure_datetime DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':passenger_id', $passengerId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ====================================================================
    // US 10 : ANNULATION DE RÉSERVATION (PASSAGER)
    // ====================================================================
    
    /**
     * Annule une réservation par le passager et gère le remboursement transactionnel.
     * * @param int $bookingId
     * @param int $passengerId (Sécurité)
     * @return bool
     * @throws Exception
     */
    public function cancel($bookingId, $passengerId) {
        $this->conn->beginTransaction();

        try {
            // 1. Récupérer et verrouiller la réservation
            $bookingQuery = "SELECT 
                                b.id, b.ride_id, b.seats_booked, b.price_paid, b.booking_status, 
                                r.seats_available 
                             FROM bookings b 
                             INNER JOIN rides r ON r.id = b.ride_id
                             WHERE b.id = :booking_id AND b.passenger_id = :passenger_id AND b.booking_status = 'confirmed' 
                             FOR UPDATE";
            
            $stmtBooking = $this->conn->prepare($bookingQuery);
            $stmtBooking->bindParam(':booking_id', $bookingId);
            $stmtBooking->bindParam(':passenger_id', $passengerId);
            $stmtBooking->execute();
            $booking = $stmtBooking->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                throw new Exception("Réservation non trouvée ou déjà annulée.");
            }

            // --- 2. OPÉRATIONS DE REMBOURSEMENT ---
            
            $refundAmount = $booking['price_paid'];
            $seatsToRestore = $booking['seats_booked'];
            
            // a. Rembourser les crédits au passager
            $refundQuery = "UPDATE users SET credits = credits + :amount WHERE id = :passenger_id";
            $stmtRefund = $this->conn->prepare($refundQuery);
            $stmtRefund->bindParam(':amount', $refundAmount);
            $stmtRefund->bindParam(':passenger_id', $passengerId);
            $stmtRefund->execute();

            // b. Rétablir les places du trajet
            $updateRideQuery = "UPDATE rides SET seats_available = seats_available + :seats_booked WHERE id = :ride_id";
            $stmtUpdateRide = $this->conn->prepare($updateRideQuery);
            $stmtUpdateRide->bindParam(':seats_booked', $seatsToRestore);
            $stmtUpdateRide->bindParam(':ride_id', $booking['ride_id']);
            $stmtUpdateRide->execute();
            
            // c. Mettre à jour le statut de la réservation
            $updateBookingQuery = "UPDATE bookings SET booking_status = 'cancelled' WHERE id = :booking_id";
            $stmtUpdateBooking = $this->conn->prepare($updateBookingQuery);
            $stmtUpdateBooking->bindParam(':booking_id', $bookingId);
            $stmtUpdateBooking->execute();
            
            // d. Enregistrer la transaction de remboursement
            $transactionQuery = "INSERT INTO credit_transactions
                                 (user_id, amount, type, reference_type, reference_id, balance_after, description)
                                 SELECT :user_id, :amount, 'refund', 'booking', :booking_id,
                                        (SELECT credits FROM users WHERE id = :user_id),
                                        'Remboursement suite annulation réservation ID: ' || :booking_id";

            $stmtTransaction = $this->conn->prepare($transactionQuery);
            $stmtTransaction->bindParam(':user_id', $passengerId);
            $stmtTransaction->bindParam(':amount', $refundAmount);
            $stmtTransaction->bindParam(':booking_id', $bookingId);
            $stmtTransaction->execute();

            // 3. Commit la transaction
            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            // Rollback en cas d'erreur
            $this->conn->rollBack();
            error_log("Booking cancellation error: " . $e->getMessage());
            throw new Exception("Échec de l'annulation : " . $e->getMessage());
        }
    }
}