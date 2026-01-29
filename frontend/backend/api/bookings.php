<?php
/**
 * ========================================
 * WRAPPER API POUR LES RÉSERVATIONS
 * ========================================
 * Fichier passerelle qui redirige vers l'API réelle des réservations
 * 
 * Cette couche permet :
 * - De servir l'API depuis le répertoire /frontend
 * - Tout en maintenant la logique métier dans /backend/api
 * - Évite les conflits CORS et de routing
 * 
 * Opérations gérées :
 * - POST : Créer une réservation
 * - GET  : Récupérer les réservations de l'utilisateur
 * - DELETE : Annuler une réservation
 */

// ÉTAPE 1 : Inclure le vrai fichier bookings.php du répertoire backend
// Le chemin remonte de 4 niveaux : /frontend/backend/api/bookings.php → /backend/api/bookings.php
require __DIR__ . '/../../../backend/api/bookings.php';
?>
