<?php
/**
 * EcoRide - Modèle Message
 * Gère les messages échangés entre utilisateurs concernant un trajet (US 7).
 */

require_once __DIR__ . '/../config/Database.php';

class Message {
    private $conn;
    private $table = 'messages';

    public $id;
    public $ride_id;
    public $sender_id;
    public $receiver_id;
    public $content;
    public $sent_at;

    public function __construct() {
        $this->conn = Database::getConnection(); 
    }

    // ====================================================================
    // US 7 : ENVOYER UN MESSAGE
    // ====================================================================

    /**
     * Enregistre un nouveau message dans la base de données.
     * La logique de vérification (si les deux utilisateurs sont liés au trajet)
     * sera principalement gérée dans le Contrôleur.
     * * @param int $rideId
     * @param int $senderId
     * @param int $receiverId
     * @param string $content
     * @return bool
     */
    public function create($rideId, $senderId, $receiverId, $content) {
        if (empty($rideId) || empty($senderId) || empty($receiverId) || empty($content)) {
            return false;
        }

        $query = "INSERT INTO " . $this->table . "
                  SET
                    ride_id = :ride_id,
                    sender_id = :sender_id,
                    receiver_id = :receiver_id,
                    content = :content";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':ride_id', $rideId);
        $stmt->bindParam(':sender_id', $senderId);
        $stmt->bindParam(':receiver_id', $receiverId);
        $stmt->bindParam(':content', $content);

        return $stmt->execute();
    }

    // ====================================================================
    // US 7 : RÉCUPÉRER UNE CONVERSATION
    // ====================================================================

    /**
     * Récupère tous les messages associés à un trajet (rideId),
     * impliquant l'utilisateur qui fait la requête (userId).
     * * @param int $rideId
     * @param int $userId (L'utilisateur actuel, nécessaire pour filtrer)
     * @return array
     */
    public function getConversation($rideId, $userId) {
        // La conversation inclut tous les messages où l'utilisateur est soit l'expéditeur, soit le destinataire.
        $query = "
            SELECT 
                m.id, m.ride_id, m.content, m.sent_at,
                u_sender.pseudo AS sender_pseudo,
                u_receiver.pseudo AS receiver_pseudo
            FROM " . $this->table . " m
            INNER JOIN users u_sender ON u_sender.id = m.sender_id
            INNER JOIN users u_receiver ON u_receiver.id = m.receiver_id
            WHERE 
                m.ride_id = :ride_id AND 
                (m.sender_id = :user_id OR m.receiver_id = :user_id)
            ORDER BY m.sent_at ASC"; 

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ride_id', $rideId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ====================================================================
    // OPTIONNEL : RÉCUPÉRER LA LISTE DES CONVERSATIONS ACTIVES
    // (Utile pour un tableau de bord de messagerie)
    // ====================================================================
    
    /**
     * Récupère la liste des derniers messages par trajet/interlocuteur pour un utilisateur.
     * @param int $userId
     * @return array
     */
    public function getConversationList($userId) {
        // Cette requête est complexe et varie, mais l'idée est de trouver le dernier message
        // pour chaque paire (ride_id, interlocuteur).
        
        $query = "
            SELECT
                m.*,
                r.departure_city, r.arrival_city, r.departure_datetime,
                CASE WHEN m.sender_id = :user_id THEN u_receiver.pseudo ELSE u_sender.pseudo END AS interlocutor_pseudo
            FROM messages m
            INNER JOIN (
                SELECT ride_id, MAX(sent_at) AS last_message_time
                FROM messages
                WHERE sender_id = :user_id OR receiver_id = :user_id
                GROUP BY ride_id
            ) AS latest_msgs ON m.ride_id = latest_msgs.ride_id AND m.sent_at = latest_msgs.last_message_time
            INNER JOIN rides r ON r.id = m.ride_id
            INNER JOIN users u_sender ON u_sender.id = m.sender_id
            INNER JOIN users u_receiver ON u_receiver.id = m.receiver_id
            WHERE m.sender_id = :user_id OR m.receiver_id = :user_id
            ORDER BY m.sent_at DESC";
            
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}