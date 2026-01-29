<?php
// backend/API/router.php

/**
 * Routeur pour le serveur de développement PHP intégré (php -S)
 * * Il s'exécute à chaque requête.
 * Si l'URI correspond à un fichier statique (y compris ceux du dossier 'frontend'), 
 * il renvoie 'false' pour que le serveur PHP serve ce fichier directement.
 * Sinon, il redirige la requête vers 'index.php' pour le routage de l'API.
 */

$uri = $_SERVER["REQUEST_URI"];

// --- 1. GESTION DES FICHIERS STATIQUES ---
// Si l'URI contient une extension de fichier connue (JS, CSS, images, polices, HTML),
// le serveur PHP le sert directement depuis le répertoire de lancement.
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg|woff|woff2|ttf|eot|html)$/', $uri)) {
    return false;
}

// --- 2. GESTION DES REQUÊTES API ---
// Toute autre requête (ex: /api/rides, /auth/login, etc.) est dirigée vers index.php
// Note: Le chemin vers index.php est basé sur la position de ce fichier (router.php).
require_once __DIR__ . '/index.php';