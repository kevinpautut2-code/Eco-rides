<?php
/**
 * EcoRide - Modèle User
 * Gère les utilisateurs (authentification, inscription, et gestion du profil US 13).
 */

require_once __DIR__ . '/../config/Database.php';

class User {
    private $conn;
    private $table = 'users';

    // Propriétés de la table users
    public $id;
    public $pseudo;
    public $email;
    public $password; 
    public $first_name;
    public $last_name;
    public $bio;
    public $avatar_url;
    public $credits = 0; 
    public $role = 'passenger'; 
    public $created_at;

    public function __construct() {
        $this->conn = Database::getConnection(); 
    }

    // ====================================================================
    // PLACEHOLDERS POUR AUTHENTIFICATION (Login/Register)
    // ====================================================================
    public function login() {
        // Logique de connexion et création de token JWT ici.
        // ...
    }
    
    public function register() {
        // Logique d'inscription ici.
        // ...
    }
    
    // ====================================================================
    // US 13 : RÉCUPÉRER LE PROFIL PAR ID (GET)
    // ====================================================================

    public function findById($userId) {
        $query = "SELECT 
                    id, pseudo, email, first_name, last_name, bio, avatar_url, credits, role, created_at
                  FROM " . $this->table . " 
                  WHERE id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ====================================================================
    // US 13 : MISE À JOUR DU PROFIL (PUT/PATCH)
    // ====================================================================

    public function updateProfile($userId, $data) {
        $fields = [];
        $bindParams = ['id' => $userId];
        
        if (isset($data->pseudo)) {
            $fields[] = "pseudo = :pseudo";
            $bindParams['pseudo'] = $data->pseudo;
        }
        if (isset($data->first_name)) {
            $fields[] = "first_name = :first_name";
            $bindParams['first_name'] = $data->first_name;
        }
        if (isset($data->last_name)) {
            $fields[] = "last_name = :last_name";
            $bindParams['last_name'] = $data->last_name;
        }
        if (isset($data->bio)) {
            $fields[] = "bio = :bio";
            $bindParams['bio'] = $data->bio;
        }
        if (isset($data->avatar_url)) {
            $fields[] = "avatar_url = :avatar_url";
            $bindParams['avatar_url'] = $data->avatar_url;
        }

        if (empty($fields)) {
            return true;
        }

        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);

        foreach ($bindParams as $key => &$value) {
            $stmt->bindParam(":$key", $value);
        }

        return $stmt->execute();
    }
}