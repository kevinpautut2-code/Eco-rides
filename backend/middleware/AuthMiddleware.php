<?php
/**
 * EcoRide - Middleware d'Authentification
 * Vérifie la validité du jeton JWT pour les routes sécurisées.
 */

// 1. INCLUSION DE L'AUTOLOAD DE COMPOSER
// Ce chemin doit pointer vers le dossier 'vendor' depuis 'middleware'
require_once dirname(__DIR__) . '/vendor/autoload.php'; 

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
// NOTE: Assurez-vous d'avoir installé 'firebase/php-jwt' via Composer.

class AuthMiddleware {

    // Clé secrète (Doit être la même que celle utilisée pour générer le token)
    // Idéalement, cette clé est chargée depuis un fichier .env (pour la simplicité, on la met ici)
    private static $secret_key = "VOTRE_CLE_SECRETE_FORTE_ICI"; 
    private static $algorithm = 'HS256';

    /**
     * Vérifie le jeton JWT dans l'en-tête Authorization.
     *
     * @return object Les données de l'utilisateur décodées (payload)
     * @throws Exception Si le token est manquant, invalide, ou expiré.
     */
    public static function verifyToken() {
        // 1. Récupérer l'en-tête
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        // 2. Vérifier le format du token (Bearer ...)
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            self::sendError("Accès refusé. Jeton d'authentification manquant ou mal formaté.", 401);
        }

        $jwt = $matches[1];

        // 3. Tenter le décodage et la vérification
        try {
            // Décodage du token avec la clé et l'algorithme
            $decoded = JWT::decode($jwt, new Key(self::$secret_key, self::$algorithm));
            
            // Si le décodage réussit, retourner le payload (les données utilisateur)
            return $decoded->data;

        } catch (\Firebase\JWT\ExpiredException $e) {
            self::sendError("Jeton expiré. Veuillez vous reconnecter.", 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
             self::sendError("Signature du jeton invalide.", 401);
        } catch (\Exception $e) {
            // Gérer toutes les autres erreurs de décodage/vérification
            self::sendError("Jeton invalide: " . $e->getMessage(), 401);
        }
    }

    /**
     * Envoie une réponse d'erreur et arrête l'exécution.
     *
     * @param string $message
     * @param int $statusCode
     */
    private static function sendError($message, $statusCode) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => $statusCode
        ]);
        exit();
    }
}