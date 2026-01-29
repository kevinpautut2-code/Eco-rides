<?php
// Utiliser le chemin relatif correct
require_once 'config/Database.php';

// -----------------------------------------------------
// TEST CRITIQUE DE CONNEXION MYSQL
// -----------------------------------------------------

// Appel statique à la méthode getConnection()
try {
    // La méthode est statique et est appelée directement via la classe
    $conn = Database::getConnection(); 

    echo "Connexion à la BDD MySQL réussie !";
    
    // Tester une requête simple
    $stmt = $conn->query("SELECT COUNT(*) FROM users;");
    $count = $stmt->fetchColumn();
    echo "<br>Nombre d'utilisateurs trouvés : " . $count;

} catch (\PDOException $e) {
    echo "ÉCHEC CRITIQUE DE CONNEXION MySQL. Vérifiez vos identifiants dans le fichier .env.";
    echo "<br>Erreur: " . $e->getMessage();
}

?>