/**
 * EcoRide - Quick Login for Demo
 * Remplissage rapide des champs de connexion
 */

function quickLogin(email, password) {
    // Remplir les champs
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
    
    // Mettre le focus sur le bouton pour que l'utilisateur sache quoi faire
    setTimeout(() => {
        document.querySelector('button[type="submit"]').focus();
    }, 100);
    
    // Optionnel: soumettre automatiquement le formulaire
    // setTimeout(() => {
    //     document.getElementById('loginForm').submit();
    // }, 500);
}

// Initialiser le client API au chargement
let apiClient;

document.addEventListener('DOMContentLoaded', () => {
    // Créer une instance du client API
    if (typeof APIClient !== 'undefined') {
        window.apiClient = new APIClient();
    }
});
