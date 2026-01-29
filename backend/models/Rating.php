<?php
/**
 * EcoRide - Modèle Rating
 * Gère la création des notations et des avis (US 12).
 */

require_once __DIR__ . '/../config/Database.php';

class Rating {
    private $conn;
    private $table = 'reviews_pending';

    public function __construct() {
        $this->conn = Database::getConnection(); 
    }

    // ====================================================================
    // US 12 : CRÉATION D'UN AVIS/NOTE
    // ====================================================================

    /**
     * Crée un nouvel avis pour un utilisateur (chauffeur) après un trajet terminé.
     * * @param int $rideId
     * @param int $authorUserId (Le passager qui note)
     * @param int $targetUserId (Le chauffeur noté)
     * @param int $rating (La note de 1 à 5)
     * @param string $comment (Le commentaire)
     * @return bool
     * @throws Exception
     */
    public function create($rideId, $authorUserId, $targetUserId, $rating, $comment) {
        // Validation des données de base
        if ($rating < 1 || $rating > 5) {
            throw new Exception("La note doit être comprise entre 1 et 5.");
        }
        if (empty($rideId) || empty($authorUserId) || empty($targetUserId)) {
            throw new Exception("Informations de trajet, auteur ou cible manquantes.");
        }

        // 1. VÉRIFICATION DU RÔLE et STATUT du TRAJET
        // L'auteur doit avoir été passager du trajet et le trajet doit être terminé.
        $verificationQuery = "
            SELECT 
                r.driver_id, r.departure_datetime 
            FROM rides r
            INNER JOIN bookings b ON b.ride_id = r.id
            WHERE 
                r.id = :ride_id AND 
                b.passenger_id = :author_id AND 
                r.driver_id = :target_id AND 
                b.booking_status = 'confirmed'
            LIMIT 1
        ";
        
        $stmtVerif = $this->conn->prepare($verificationQuery);
        $stmtVerif->bindParam(':ride_id', $rideId);
        $stmtVerif->bindParam(':author_id', $authorUserId);
        $stmtVerif->bindParam(':target_id', $targetUserId);
        $stmtVerif->execute();
        $rideData = $stmtVerif->fetch(PDO::FETCH_ASSOC);

        if (!$rideData) {
            throw new Exception("Impossible de noter. Vérifiez que vous étiez passager de ce trajet et que la réservation est confirmée.");
        }
        
        // Vérification si le trajet est terminé (Sécurité)
        if (new DateTime($rideData['departure_datetime']) > new DateTime()) {
            throw new Exception("Impossible de noter un trajet qui n'a pas encore commencé.");
        }
        
        // 2. VÉRIFICATION : L'avis a-t-il déjà été soumis pour ce trajet ?
        $checkExistingQuery = "
            SELECT id FROM " . $this->table . " 
            WHERE 
                ride_id = :ride_id AND 
                author_user_id = :author_id
            LIMIT 1
        ";
        
        $stmtCheck = $this->conn->prepare($checkExistingQuery);
        $stmtCheck->bindParam(':ride_id', $rideId);
        $stmtCheck->bindParam(':author_id', $authorUserId);
        $stmtCheck->execute();

        if ($stmtCheck->rowCount() > 0) {
            throw new Exception("Vous avez déjà soumis un avis pour ce trajet.");
        }

        // 3. INSÉRER LE NOUVEL AVIS
        $query = "INSERT INTO " . $this->table . "
                  SET
                    ride_id = :ride_id,
                    author_user_id = :author_user_id,
                    target_user_id = :target_user_id,
                    rating = :rating,
                    comment = :comment";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':ride_id', $rideId);
        $stmt->bindParam(':author_user_id', $authorUserId);
        $stmt->bindParam(':target_user_id', $targetUserId);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':comment', $comment);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // NOTE: La lecture (read) des avis est déjà implémentée dans Ride.php::findById()
    // pour afficher les avis du chauffeur sur la page de détail du trajet (US 5).
}